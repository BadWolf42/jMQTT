from __future__ import annotations
import asyncio
# from json import load
from logging import getLogger  # , DEBUG
# from time import time
from typing import Dict
from weakref import WeakValueDictionary

# from ..callbacks import Callbacks
# from ..settings import settings

from models import (
    BrkModel,
    # CmdInfoModel,
    # CmdActionModel
)
from . import VisitableLogic, LogicVisitor


"""
https://github.com/sbtinstruments/aiomqtt/issues/237
https://sbtinstruments.github.io/aiomqtt/subscribing-to-a-topic.html

from aiomqtt import Client, MqttError

TLS?
https://github.com/sbtinstruments/aiomqtt/issues/15
https://github.com/encode/httpx/discussions/2037#discussioncomment-2006795

"""

logger = getLogger('jmqtt.brk')


class BrkLogic(VisitableLogic):
    all: Dict[int, BrkLogic] = {}

    def __init__(self, model: BrkModel):
        self.log = getLogger(f'jmqtt.brk.{model.id}')
        self.model = model

        self.eqpts: WeakValueDictionary[int, VisitableLogic] = {}
        # self.cmds: WeakValueDictionary[int, VisitableLogic] = {}
        self.cmd_i: WeakValueDictionary[int, VisitableLogic] = {}
        self.cmd_a: WeakValueDictionary[int, VisitableLogic] = {}
        # -> topics = {'subtopic': {'cmdid': cmd}}
        self.topics: Dict[str, WeakValueDictionary[int, VisitableLogic]] = {}

        self.client: asyncio.Task = None
        self.realtime: asyncio.Task = None

    def accept(self, visitor: LogicVisitor) -> None:
        visitor.visit_brklogic(self)

    # def getBrokerId(self) -> int:
    #     return self.model.id

    # def getBroker(self) -> BrkLogic:
    #     return self

    # def isEnabled(self) -> bool:
    #     return self.model.isEnable

    def start(self):
        if not self.model.isEnable:
            self.log.debug('Not enabled, Broker not started')
            return
        self.log.debug('Start requested')
        # TODO

    def stop(self):
        self.log.debug('Stop requested')
        # TODO

    def restart(self):
        self.stop()
        self.start()

    def subscribe(self, topic: str, qos: int) -> None:
        pass

    def unsubscribe(self, topic: str) -> None:
        pass
