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

    async def addCmd(self, cmd: CmdLogic) -> None:
        # Add the reference to EqLogic
        cmd.weakEq = ref(self)
        # Add CmdLogic ref in EqLogic
        if cmd.model.type == 'info':
            self.cmd_i[cmd.model.id] = cmd
        else:
            self.cmd_a[cmd.model.id] = cmd
            # logger.debug('id=%s, cmd disregarded: not an info', cmd.model.id)

    async def delCmd(self, cmd: CmdLogic) -> None:
        # Remove CmdLogic ref in EqLogic/BrkLogic
        if cmd.model.type == 'info':
            if cmd.model.id in self.cmd_i:
                del self.cmd_i[cmd.model.id]
        else:
            if cmd.model.id in self.cmd_a:
                del self.cmd_a[cmd.model.id]
        # Remove EqLogic weakref
        cmd.weakEq = None
