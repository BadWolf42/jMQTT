from __future__ import annotations
from aiomqtt import Message
from logging import getLogger
from typing import Dict
from weakref import ref
from zlib import decompress as zlib_decompress

from callbacks import Callbacks
from visitors.abstractvisitor import VisitableLogic, LogicVisitor
from models.cmd import CmdInfoDecoderModel
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

    async def _decompress(self, payload) -> str:
        # jMQTT will try to decompress the payload (requested in issue #135)
        try:
            payload = zlib_decompress(payload, wbits=-15)
            logger.debug(
                'id=%i: decompressed payload: "0x%s"',
                self.model.id,
                (
                    bytes(payload, 'utf-8') if isinstance(payload, str) else payload
                ).hex()
            )
        except Exception:  # If payload cannot be decompressed
            logger.debug(
                'id=%i: could NOT decompress payload: "0x%s"',
                self.model.id,
                (
                    bytes(payload, 'utf-8') if isinstance(payload, str) else payload
                ).hex()
            )
        return payload

    async def _decode(self, payload, decoder) -> str:
        try:
            payload = payload.decode('utf-8', decoder)
            logger.debug(
                'id=%i: decoded (%s) payload: "%s"',
                self.model.id,
                decoder.name,
                payload,
            )
        except Exception:
            logger.info(
                'id=%i: could NOT decode (%s) payload: "0x%s"',
                self.model.id,
                decoder.name,
                (
                    bytes(payload, 'utf-8') if isinstance(payload, str) else payload
                ).hex()
            )
        return payload

    async def _writeToFile(self, payload, ts: float) -> str:
        filename = f'file_{self.model.id}'
        # TODO Callback to set file content = payload
        logger.debug(
            'id=%i: wrote to file "%s" payload: "0x%s"',
            self.model.id,
            filename,
            (bytes(payload, 'utf-8') if isinstance(payload, str) else payload).hex(),
        )
        return f'{filename}?{ts}'

    async def handle(self, message: Message, ts: float):
        payload = message.payload
        cfg = self.model.configuration
        if cfg.tryUnzip:
            payload = await self._decompress(payload)
        if cfg.decoder != CmdInfoDecoderModel.none:
            payload = await self._decode(payload, cfg.decoder)
        if cfg.toFile:
            payload = await self._writeToFile(payload, ts)

        logger.info(
            'id=%i: payload="%s", QoS=%s, retain=%s, ts=%i',
            self.model.id,
            payload,
            message.qos,
            bool(message.retain),
            ts,
        )
        await Callbacks.message(
            self.weakBrk().model.id,
            str(message.topic),
            payload,
            message.qos,
            message.retain,
        )

    # def getEqLogic(self):
    #     return self.weakEq()

    # def getBrkLogic(self):
    #     return self.weakBrk()
