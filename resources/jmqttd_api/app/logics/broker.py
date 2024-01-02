from __future__ import annotations
from asyncio import Task
from logging import getLogger  # , DEBUG
from typing import Dict, List
from weakref import WeakValueDictionary

from callbacks import Callbacks
from healthcheck import Healthcheck
from visitors.abstractvisitor import VisitableLogic, LogicVisitor
from models.broker import BrkModel
from models.messages import (
    MqttMessageModel,
    RealTimeModel,
    RealTimeStatusModel,
)

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
        self.topics: Dict[str, WeakValueDictionary[int, VisitableLogic]] = {}

        self.client: Task = None
        self.realtime: Task = None

    async def accept(self, visitor: LogicVisitor) -> None:
        await visitor.visit_brk(self)

    # def getBrokerId(self) -> int:
    #     return self.model.id

    # def getBroker(self) -> BrkLogic:
    #     return self

    # def isEnabled(self) -> bool:
    #     return self.model.isEnable

    async def start(self):
        if not self.model.isEnable:
            self.log.debug('Not enabled, Broker not started')
            return
        self.log.debug('Start requested')
        # TODO
        await Callbacks.brokerUp(self.model.id)

    async def stop(self):
        self.log.debug('Stop requested')
        # TODO
        await Callbacks.brokerDown(self.model.id)

    async def restart(self):
        await self.stop()
        await self.start()

    async def publish(self, topic: str, payload: str, qos: int, retain: bool):
        self.log.debug(
            f'TODO: {{"topic":"{topic}","payload":"{payload}","qos":{qos},"retain":{retain}}}'
        )
        # TODO
        await Healthcheck.onReceive()

    async def subscribe(self, topic: str, qos: int) -> None:
        self.log.debug(f'TODO: {{"topic":"{topic}","qos":{qos}}}')
        # TODO

    async def unsubscribe(self, topic: str) -> None:
        self.log.debug(f'TODO: {{"topic":"{topic}"}}')
        # TODO

    async def realTimeStart(self, params: RealTimeModel) -> bool:
        self.log.debug('TODO')
        # TODO
        return self.model.isEnable
        # return self.client is not None

    async def realTimeStatus(self) -> RealTimeStatusModel:
        self.log.debug('TODO')
        # TODO
        return RealTimeStatusModel(
            eqLogic=self.model.id,
            retained=False,
            enabled=False,
            timeleft=0,
            count=0,
        )

    async def realTimeStop(self) -> None:
        self.log.debug('TODO')
        # TODO

    async def realTimeGet(self, since: int) -> List[MqttMessageModel]:
        self.log.debug('TODO')
        if self.realtime is None:
            return []
        # TODO
        return []

    async def realTimeClear(self) -> None:
        self.log.debug('TODO')
        # TODO
