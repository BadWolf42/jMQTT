from __future__ import annotations
from logging import getLogger
from typing import Dict
from weakref import ref

from visitors.abstractvisitor import VisitableLogic, LogicVisitor
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

    # def getEqLogic(self):
    #     return self.weakEq()

    # def getBrkLogic(self):
    #     return self.weakBrk()
