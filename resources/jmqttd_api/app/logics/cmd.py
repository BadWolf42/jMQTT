from __future__ import annotations
from aiomqtt import Message
from logging import getLogger
from json import dumps, JSONDecodeError, loads
from typing import Dict, TYPE_CHECKING, Union
from weakref import ref
from zlib import decompress as zlib_decompress

from comm.callbacks import Callbacks
from converters.jsonpath import BadJsonPath, compiledJsonPath, JsonPathDidNotMatch
from visitors.abstractvisitor import VisitableLogic, LogicVisitor

if TYPE_CHECKING:
    from logics.broker import BrkLogic
    from logics.eq import EqLogic
from logics.topicmap import Dispatcher
from models.cmd import CmdInfoDecoderEnum, CmdInfoHandlerEnum

if TYPE_CHECKING:
    from models.unions import CmdModel


logger = getLogger('jmqtt.cmd')


class BadJsonPayload(Exception):
    """Exception to signal a bad json payload"""

    pass


# -----------------------------------------------------------------------------
class CmdLogic(VisitableLogic, Dispatcher):
    all: Dict[int, CmdLogic] = {}

    # -----------------------------------------------------------------------------
    def __init__(self, model: CmdModel):
        self.model: CmdModel = model
        self.weakEq: ref[EqLogic] = None
        self.weakBrk: ref[BrkLogic] = None

    # -----------------------------------------------------------------------------
    async def accept(self, visitor: LogicVisitor) -> None:
        await visitor.visit_cmd(self)

    # -----------------------------------------------------------------------------
    def getDispatcherId(self) -> str:
        return f'cmd={self.model.id}'

    # -----------------------------------------------------------------------------
    def isWildcard(self):
        return (
            '+' in self.model.configuration.topic
            or '#' in self.model.configuration.topic
        )

    # -----------------------------------------------------------------------------
    def _decompress(self, pl):
        if not self.model.configuration.tryUnzip:
            return pl
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

    # -----------------------------------------------------------------------------
    def _decode(self, pl) -> str:
        decoder = self.model.configuration.decoder
        if decoder == CmdInfoDecoderEnum.none:
            return pl
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

    # -----------------------------------------------------------------------------
    def _handleLiteral(self, payload, ts: float) -> str:
        return payload

    # -----------------------------------------------------------------------------
    def _handleJsonPath(self, payload, ts: float) -> str:
        jsonPath = self.model.configuration.jsonPath
        if jsonPath.strip() == '':
            return payload
        expr = compiledJsonPath(jsonPath)
        try:
            json = loads(payload)
        except JSONDecodeError as e:
            raise BadJsonPayload(f'invalid json {payload=}: {e}')
        found = expr.find(json)
        if len(found) == 0:
            raise JsonPathDidNotMatch()
        if len(found) == 1:
            return found[0].value
        return [match.value for match in found]

    # -----------------------------------------------------------------------------
    def _handleJinja(self, payload, ts: float) -> str:
        jinja = self.model.configuration.jinja
        if jinja.strip() == '' or '{' not in jinja:
            return payload
        # TODO Handle Jinja template
        return payload

    # -----------------------------------------------------------------------------
    def _normalize(self, payload) -> str:
        if type(payload) not in [bool, int, float, str]:
            return dumps(payload)
        return payload

    # -----------------------------------------------------------------------------
    def _writeToFile(self, payload, ts: float) -> str:
        if not self.model.configuration.toFile:
            return payload
        filename = f'file_{self.model.id}'
        # TODO Callback to set file content = payload
        logger.debug(
            'id=%i: wrote to file "%s" payload: "0x%s"',
            self.model.id,
            filename,
            (bytes(payload, 'utf-8') if isinstance(payload, str) else payload).hex(),
        )
        return f'{filename}?{ts}'

    # -----------------------------------------------------------------------------
    async def dispatch(self, message: Message, ts: float) -> Union[int, None]:
        try:
            payload = message.payload
            payload = self._decompress(payload)
            payload = self._decode(payload)
            payload = {
                CmdInfoHandlerEnum.literal: self._handleLiteral,
                CmdInfoHandlerEnum.jsonPath: self._handleJsonPath,
                CmdInfoHandlerEnum.jinja: self._handleJinja,
            }.get(self.model.configuration.handler)(payload, ts)
            payload = self._normalize(payload)
            payload = self._writeToFile(payload, ts)
            logger.info(
                'id=%i: payload="%s", qos=%s, retain=%s, ts=%i',
                self.model.id,
                payload,
                message.qos,
                bool(message.retain),
                ts,
            )
            await Callbacks.change(self.model.id, payload, ts)
        except (BadJsonPath, BadJsonPayload) as e:
            logger.warning('id=%i: %s', self.model.id, e)
        except JsonPathDidNotMatch:
            logger.debug(
                'id=%i: jsonPath "%s" did NOT match',
                self.model.id,
                self.model.configuration.jsonPath,
            )
        except Exception:
            logger.exception('id=%i: MQTT message raised an exception:', self.model.id)
        return self.model.id
