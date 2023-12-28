from __future__ import annotations
from logging import getLogger
from typing import Dict
from weakref import ref

from models import CmdModel
from . import VisitableLogic, LogicVisitor


logger = getLogger('jmqtt.cmd')


class CmdLogic(VisitableLogic):
    all: Dict[int, CmdLogic] = {}

    def __init__(self, model: CmdModel):
        self.model: CmdModel = model
        self.weakEq: ref = None
        self.weakBrk: ref = None

    def accept(self, visitor: LogicVisitor) -> None:
        visitor.visit_cmdlogic(self)

    # def getEqLogic(self):
    #     return self.weakEq()

    # def getBrkLogic(self):
    #     return self.weakBrk()
