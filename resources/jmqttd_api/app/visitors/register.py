from __future__ import annotations
from logging import getLogger
from weakref import ref, WeakValueDictionary

from visitors.abstractvisitor import LogicVisitor, VisitableLogic
from visitors.utils import addCmdInBrk, addCmdInEq
from logics.broker import BrkLogic
from logics.cmd import CmdLogic
from logics.eq import EqLogic


logger = getLogger('jmqtt.visitor.reg')


# -----------------------------------------------------------------------------
class RegisteringLogicVisitor(LogicVisitor):
    def __init__(self):
        pass

    async def visit_brk(self, e: BrkLogic) -> None:
        logger.trace('id=%s, registering brk', e.model.id)
        # Add BrkLogic in brkLogic table
        BrkLogic.all[e.model.id] = e
        await e.start()
        logger.debug('id=%s, brk registered', e.model.id)

    async def visit_eq(self, e: EqLogic) -> None:
        logger.trace('id=%s, registering eq', e.model.id)
        brkId = e.model.configuration.eqLogic
        # If BrkLogic is not found
        if brkId not in BrkLogic.all:
            logger.warning(
                'id=%s, eq disregarded: BrkId=%s not found', e.model.id, brkId
            )
            return
        # Cleanup EqLogic just in case
        e.cmd_i.clear()
        e.cmd_a.clear()
        # Add the reference to BrkLogic
        e.weakBrk = ref(BrkLogic.all[brkId])
        # Add in eqLogics
        EqLogic.all[e.model.id] = e
        # Add EqLogic in BrkLogic eqLogics list
        e.weakBrk().eqpts[e.model.id] = e
        logger.debug('id=%s, eq registered', e.model.id)

    async def visit_cmd(self, e: CmdLogic) -> None:
        logger.trace('id=%s, registering cmd', e.model.id)
        # Parent is a BrkLogic
        if e.model.eqLogic_id in BrkLogic.all:
            logger.warning(
                'id=%s, cmd disregarded: parent EqId=%s is a brk',
                e.model.id,
                e.model.eqLogic_id,
            )
            return
        # Could not find a parent
        if e.model.eqLogic_id not in EqLogic.all:
            logger.warning(
                'id=%s, cmd disregarded: parent EqId=%s not found',
                e.model.id,
                e.model.eqLogic_id,
            )
            return
        # Only add in CmdLogic if found a valid parent
        CmdLogic.all[e.model.id] = e
        # Get parent eqLogic
        eq = EqLogic.all[e.model.eqLogic_id]
        # Add CmdLogic in EqLogic
        await addCmdInEq(e, eq)
        # Add the reference to BrkLogic
        e.weakBrk = ref(eq.weakBrk())
        # Finish here if eq is not enabled
        if not eq.model.isEnable:
            logger.debug('id=%s, cmd registered, but is not enabled', e.model.id)
            return
        # Insert path in info topic tree
        if e.model.type != 'info':
            logger.debug('id=%s, cmd registered, but is an action', e.model.id)
            return
        # Add topic to Broker
        await addCmdInBrk(e, eq.weakBrk())
        logger.debug('id=%s, cmd registered', e.model.id)

    @classmethod
    async def register(cls, e: VisitableLogic) -> None:
        self = cls()
        await e.accept(self)
