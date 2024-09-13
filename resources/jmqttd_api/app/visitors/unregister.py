from __future__ import annotations
from logging import getLogger
from typing import List, Union

from visitors.abstractvisitor import LogicVisitor
from logics.broker import BrkLogic
from logics.cmd import CmdLogic
from logics.eq import EqLogic


logger = getLogger('jmqtt.visitor.unreg')


# -----------------------------------------------------------------------------
class UnregisteringLogicVisitor(LogicVisitor):
    def __init__(self, e: List[Union[BrkLogic, EqLogic, CmdLogic]]):
        self.toDel = e
        self.result = []

    async def visit_brk(self, e: BrkLogic) -> None:
        logger.trace('id=%s, unregistering brk', e.model.id)
        # Let's stop first MQTT Client (and Real Time)
        await e.stop()
        # Then append the BrkLogic first to the result
        self.result.append(e)
        # Accept on all linked EqLogic
        for eq in [v for v in e.eqpts.values()]:
            await eq.accept(self)
        # Cleanup just in case
        e.eqpts.clear()
        # Delete the BrkLogic from the registery
        del BrkLogic.all[e.model.id]
        logger.trace('id=%s, brk unregistered', e.model.id)

    async def visit_eq(self, e: EqLogic) -> None:
        logger.trace('id=%s, unregistering eq', e.model.id)
        # Append this EqLogic to the result
        self.result.append(e)
        # Call the visitor on each CmdLogic info linked directly to the Broker
        for cmd in [v for v in e.cmd_i.values()]:
            await cmd.accept(self)
        # Call the visitor on each CmdLogic action linked directly to the Broker
        for cmd in [v for v in e.cmd_a.values()]:
            await cmd.accept(self)
        # Cleanup just in case
        e.cmd_i.clear()
        e.cmd_a.clear()
        # TODO Del eq to brk topics/wildcards if auto_add_cmd is enable
        # Remove this EqLogic from the BrkLogic
        del e.weakBrk().eqpts[e.model.id]
        # Remove BrkLogic weakref
        e.weakBrk = None
        # Delete the EqLogic from the registery
        del EqLogic.all[e.model.id]
        logger.trace('id=%s, eq unregistered', e.model.id)

    async def visit_cmd(self, e: CmdLogic) -> None:
        logger.trace('id=%s, unregistering cmd', e.model.id)
        # Append this CmdLogic to the result
        self.result.append(e)
        # Remove topic from Broker
        await e.weakBrk().delCmd(e)
        # Remove CmdLogic from EqLogic
        await e.weakEq().delCmd(e)
        # Remove BrkLogic weakref
        e.weakBrk = None
        # Delete the CmdLogic from the registery
        del CmdLogic.all[e.model.id]
        logger.trace('id=%s, cmd unregistered', e.model.id)

    async def unregister(self) -> None:
        await self.toDel.accept(self)
        return self.result
