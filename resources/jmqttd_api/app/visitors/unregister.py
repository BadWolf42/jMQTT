from __future__ import annotations
from logging import getLogger
from typing import List, Union

from visitors.abstractvisitor import LogicVisitor
from logics.broker import BrkLogic
from logics.cmd import CmdLogic
from logics.eq import EqLogic


# -----------------------------------------------------------------------------
class UnregisteringLogicVisitor(LogicVisitor):
    def __init__(self):
        self.logger = getLogger('jmqtt.visitor.unreg')
        self.result = []

    async def visit_brk(self, e: BrkLogic) -> None:
        self.logger.trace('id=%s, unregistering brk', e.model.id)
        # Let's stop first MQTT Client (and Real Time)
        await e.stop()
        # Then append the BrkLogic first to the result
        self.result.append(e)
        # Collect all directly linked CmdLogic, then EqLogic
        linked_cmd_eq = [v for v in e.cmd_i.values()]
        linked_cmd_eq += [v for v in e.cmd_a.values()]
        linked_cmd_eq += [v for v in e.eqpts.values()]
        for eq in linked_cmd_eq:
            await eq.accept(self)
        # Cleanup BrkLogic just in case
        e.cmd_i.clear()
        e.cmd_a.clear()
        e.eqpts.clear()
        # Delete the BrkLogic from the registery
        del BrkLogic.all[e.model.id]
        self.logger.debug('id=%s, brk unregistered', e.model.id)

    async def visit_eq(self, e: EqLogic) -> None:
        self.logger.trace('id=%s, unregistering eq', e.model.id)
        # Append this EqLogic to the result
        self.result.append(e)
        # Call the visitor on each CmdLogic info linked directly to the Broker
        for cmd in [v for v in e.cmd_i.values()]:
            await cmd.accept(self)
        # Call the visitor on each CmdLogic action linked directly to the Broker
        for cmd in [v for v in e.cmd_a.values()]:
            await cmd.accept(self)
        # Cleanup EqLogic just in case
        e.cmd_i.clear()
        e.cmd_a.clear()
        # Remove this EqLogic from the BrkLogic
        del e.weakBrk().eqpts[e.model.id]
        # Remove BrkLogic weakref
        e.weakBrk = None
        # Delete the EqLogic from the registery
        del EqLogic.all[e.model.id]
        self.logger.debug('id=%s, eq unregistered', e.model.id)

    async def visit_cmd(self, e: CmdLogic) -> None:
        self.logger.trace('id=%s, unregistering cmd', e.model.id)
        # Append this CmdLogic to the result
        self.result.append(e)
        # Handle removal from BrkLogic
        brk = e.weakBrk()
        topic = e.model.configuration.topic
        # Remove topic from Broker
        if topic in brk.topics:
            # Check if CmdLogic is in topics
            if e.model.id in brk.topics[topic]:
                del brk.topics[topic][e.model.id]
            # Check if unsubscription is needed
            if len(brk.topics[topic]) == 0:
                await brk.unsubscribe(topic)
                del brk.topics[topic]
        # Handle removal from EqLogic
        eq = e.weakEq()
        # Remove CmdLogic ref in EqLogic/BrkLogic
        if e.model.type == 'info':
            if e.model.id in eq.cmd_i:
                del eq.cmd_i[e.model.id]
        else:
            if e.model.id in eq.cmd_a:
                del eq.cmd_a[e.model.id]
        # Remove BrkLogic/EqLogic weakref
        e.weakBrk = None
        e.weakEq = None
        # Delete the CmdLogic from the registery
        del CmdLogic.all[e.model.id]
        self.logger.debug('id=%s, cmd unregistered', e.model.id)

    @classmethod
    async def unregister(
        cls, e: List[Union[BrkLogic, EqLogic, CmdLogic]]
    ) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        self = cls()
        await e.accept(self)
        return self.result
