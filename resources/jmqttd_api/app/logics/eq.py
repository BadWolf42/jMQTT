from __future__ import annotations
from logging import getLogger
from typing import Dict
from weakref import ref, WeakValueDictionary

from visitors.abstractvisitor import VisitableLogic, LogicVisitor
from models.eq import EqModel


logger = getLogger('jmqtt.eq')


class EqLogic(VisitableLogic):
    all: Dict[int, EqLogic] = {}

    def __init__(self, model: EqModel):
        self.model = model
        # self.cmds: List[int] = []
        # self.cmds: WeakValueDictionary[int, VisitableLogic] = {}
        self.cmd_i: WeakValueDictionary[int, VisitableLogic] = {}
        self.cmd_a: WeakValueDictionary[int, VisitableLogic] = {}
        self.weakBrk: ref = None

    async def accept(self, visitor: LogicVisitor) -> None:
        await visitor.visit_eq(self)

    # def getBrokerId(self) -> int:
    #     return self.model.configuration.eqLogic

    # def isEnabled(self) -> bool:
    #     return self.model.isEnable

    # def addCmd(self, eq: CmdLogic) -> None:
    #     self.cmds.append(eq.model.id)

    # def delCmd(self, id: int) -> None:
    #     self.cmds.remove(id)
