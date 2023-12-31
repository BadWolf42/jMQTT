from __future__ import annotations
from asyncio import run, Task
from logging import getLogger  # , DEBUG
from typing import Dict
from weakref import WeakValueDictionary

from callbacks import Callbacks
from logics.abstractvisitor import VisitableLogic, LogicVisitor
from models.broker import BrkModel
# from models.cmd import (
#     CmdInfoModel,
#     CmdActionModel
# )
# from settings import settings


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

        self.client: Task = None
        self.realtime: Task = None

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
        run(Callbacks.brokerUp(self.model.id))

    def stop(self):
        self.log.debug('Stop requested')
        # TODO
        run(Callbacks.brokerDown(self.model.id))

    def restart(self):
        self.stop()
        self.start()

    def publish(self, topic: str, payload: str, qos: int, retain: bool):
        self.log.debug(
            f'TODO: {{"topic":"{topic}","payload":"{payload}","qos":{qos},"retain":{retain}}}'
        )
        # TODO

    def subscribe(self, topic: str, qos: int) -> None:
        self.log.debug(f'TODO: {{"topic":"{topic}","qos":{qos}}}')
        # TODO

    def unsubscribe(self, topic: str) -> None:
        self.log.debug(f'TODO: {{"topic":"{topic}"}}')
        # TODO
