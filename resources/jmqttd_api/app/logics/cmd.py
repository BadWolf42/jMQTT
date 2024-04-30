from __future__ import annotations
from aiomqtt import Message
from logging import getLogger
from json import dumps, JSONDecodeError, loads
from typing import Dict, Union
from weakref import ref
from zlib import decompress as zlib_decompress

from callbacks import Callbacks
from converters.jsonpath import BadJsonPath, compiledJsonPath, JsonPathDidNotMatch
from visitors.abstractvisitor import VisitableLogic, LogicVisitor
from models.cmd import CmdInfoDecoderModel, CmdInfoHandlerModel
from models.unions import CmdModel


logger = getLogger('jmqtt.cmd')


class CmdLogic(VisitableLogic):
    all: Dict[int, CmdLogic] = {}

    def __init__(self, model: CmdModel):
        self.model: CmdModel = model
        self.weakEq: ref = None
        self.weakBrk: ref = None

    async def accept(self, visitor: LogicVisitor) -> None:
        await visitor.visit_cmd(self)

    def isWildcard(self):
        return (
            '+' in self.model.configuration.topic
            or '#' in self.model.configuration.topic
        )

    def _decompress(self, pl):
        # jMQTT will try to decompress the payload (requested in issue #135)
        try:
            pl = zlib_decompress(pl, wbits=-15)
            logger.trace(
                'id=%i: decompressed payload: "0x%s"',
                self.model.id,
                (bytes(pl, 'utf-8') if isinstance(pl, str) else pl).hex(),
            )
        except Exception:  # If payload cannot be decompressed
            logger.debug(
                'id=%i: could NOT decompress payload: "0x%s"',
                self.model.id,
                (bytes(pl, 'utf-8') if isinstance(pl, str) else pl).hex(),
            )
        return pl

    def _decode(self, pl, decoder) -> str:
        try:
            pl = pl.decode('utf-8', decoder)
            logger.trace(
                'id=%i: decoded (%s) payload: "%s"', self.model.id, decoder.name, pl
            )
        except Exception:
            logger.info(
                'id=%i: could NOT decode (%s) payload: "0x%s"',
                self.model.id,
                decoder.name,
                (bytes(pl, 'utf-8') if isinstance(pl, str) else pl).hex(),
            )
        return pl

    def _handleJsonPath(self, payload, ts: float) -> str:
        expr = compiledJsonPath(self.model.configuration.jsonPath)
        json = loads(payload)  # TODO Handle JSONDecodeError?
        found = expr.find(json)
        if len(found) == 0:
            raise JsonPathDidNotMatch()
        if len(found) == 1:
            return found[0].value
        return [match.value for match in found]

    def _handleJinja(self, payload, ts: float) -> str:
        jinja = self.model.configuration.jinja
        if jinja == '' or '{' not in jinja:
            return payload
        # TODO Handle Jinja template
        return payload

    def _writeToFile(self, payload, ts: float) -> str:
        filename = f'file_{self.model.id}'
        # TODO Callback to set file content = payload
        logger.debug(
            'id=%i: wrote to file "%s" payload: "0x%s"',
            self.model.id,
            filename,
            (bytes(payload, 'utf-8') if isinstance(payload, str) else payload).hex(),
        )
        return f'{filename}?{ts}'

    async def mqttMsg(self, message: Message, ts: float):
        try:
            payload = message.payload
            cfg = self.model.configuration
            if cfg.tryUnzip:
                payload = self._decompress(payload)
            if cfg.decoder != CmdInfoDecoderModel.none:
                payload = self._decode(payload, cfg.decoder)
            if cfg.handler == CmdInfoHandlerModel.jsonPath:
                payload = self._handleJsonPath(payload, ts)
            elif cfg.handler == CmdInfoHandlerModel.jinja:
                payload = self._handleJinja(payload, ts)
            if type(payload) not in [bool, int, float, str]:
                payload = dumps(payload)
            if cfg.toFile:
                payload = self._writeToFile(payload, ts)
            logger.info(
                'id=%i: payload="%s", QoS=%s, retain=%s, ts=%i',
                self.model.id,
                payload,
                message.qos,
                bool(message.retain),
                ts,
            )
            await Callbacks.change(self.model.id, payload, ts)
        except BadJsonPath as e:
            logger.warning(
                'id=%i: %s', self.model.id, e)
        except JsonPathDidNotMatch:
            logger.debug(
                'id=%i: jsonPath "%s" did NOT match',
                self.model.id,
                self.model.configuration.jsonPath,
            )
        except Exception as e:
            logger.exception('id=%i: MQTT message raised an exception:', self.model.id)
