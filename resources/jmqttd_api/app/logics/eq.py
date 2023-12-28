from __future__ import annotations
from logging import getLogger
from typing import Dict
from weakref import ref, WeakValueDictionary

from logics.abstractvisitor import VisitableLogic, LogicVisitor
# from models.cmd import CmdInfoModel, CmdActionModel
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

    def accept(self, visitor: LogicVisitor) -> None:
        visitor.visit_eqlogic(self)

    # def getBrokerId(self) -> int:
    #     return self.model.configuration.eqLogic

    # def isEnabled(self) -> bool:
    #     return self.model.isEnable

    # def addCmd(self, eq: CmdLogic) -> None:
    #     self.cmds.append(eq.model.id)

    # def delCmd(self, id: int) -> None:
    #     self.cmds.remove(id)
