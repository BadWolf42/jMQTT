from __future__ import annotations
from logging import getLogger
from typing import Union

from visitors.abstractvisitor import LogicVisitor, VisitableLogic
from visitors.register import RegisteringLogicVisitor
from visitors.unregister import UnregisteringLogicVisitor
from logics.broker import BrkLogic
from logics.cmd import CmdLogic
from logics.eq import EqLogic
from models.broker import BrkModel
from models.eq import EqModel
from models.unions import CmdModel


# -----------------------------------------------------------------------------
class UpdatingLogicVisitor(LogicVisitor):
    def __init__(self):
        self.logger = getLogger('jmqtt.visitor.update')

    async def visit_brk(self, e: BrkLogic) -> None:
        pass

    async def visit_eq(self, e: EqLogic) -> None:
        pass

    async def visit_cmd(self, e: CmdLogic) -> None:
        pass

    @classmethod
    async def do(
        cls,
        existing: VisitableLogic,
        model: Union[BrkModel, EqModel, CmdModel],
    ) -> None:
        # TODO Use this visitor to update the correct logic
        # self = cls()
        # await e.accept(self)

        # Get class of existing logic
        logic = existing.__class__
        # Unregister existing logic
        unreged = await UnregisteringLogicVisitor.do(existing)
        # And replace existing logic by the created logic from model
        unreged[0] = logic(model)
        # Register back each unregistered logics
        for inst in unreged:
            # With the register class method of the logics
            await RegisteringLogicVisitor.do(inst)
