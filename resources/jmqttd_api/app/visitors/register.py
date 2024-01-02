from __future__ import annotations
from logging import getLogger
from weakref import ref, WeakValueDictionary

from visitors.abstractvisitor import LogicVisitor, VisitableLogic
from logics.broker import BrkLogic
from logics.cmd import CmdLogic
from logics.eq import EqLogic


# -----------------------------------------------------------------------------
class RegisteringLogicVisitor(LogicVisitor):
    def __init__(self):
        self.logger = getLogger('jmqtt.visitor.reg')

    async def visit_brk(self, e: BrkLogic) -> None:
        self.logger.trace('id=%s, registering brk', e.model.id)
        # Add BrkLogic in brkLogic table
        BrkLogic.all[e.model.id] = e
        await e.start()
        self.logger.debug('id=%s, brk registered', e.model.id)

    async def visit_eq(self, e: EqLogic) -> None:
        self.logger.trace('id=%s, registering eq', e.model.id)
        brkId = e.model.configuration.eqLogic
        # If BrkLogic is not found
        if brkId not in BrkLogic.all:
            self.logger.warning(
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
        self.logger.debug('id=%s, eq registered', e.model.id)

    async def visit_cmd(self, e: CmdLogic) -> None:
        self.logger.trace('id=%s, registering cmd', e.model.id)
        # Get parent eqLogic
        if e.model.eqLogic_id in EqLogic.all:
            # Parent is an EqLogic
            eq = EqLogic.all[e.model.eqLogic_id]
            # Add the reference to EqLogic and BrkLogic
            e.weakEq = ref(eq)
            e.weakBrk = ref(eq.weakBrk())
        elif e.model.eqLogic_id in BrkLogic.all:
            # Parent is a BrkLogic
            eq = BrkLogic.all[e.model.eqLogic_id]
            # Add the reference to EqLogic and BrkLogic
            e.weakEq = ref(eq)
            e.weakBrk = ref(eq)
        else:  # Could not find a parent
            self.logger.warning(
                'id=%s, cmd disregarded: EqId=%s not found',
                e.model.id,
                e.model.eqLogic_id,
            )
            return
        # Only add in CmdLogic if found a parent
        CmdLogic.all[e.model.id] = e
        # Add CmdLogic ref in EqLogic/BrkLogic
        if e.model.type == 'info':
            eq.cmd_i[e.model.id] = e
        else:
            eq.cmd_a[e.model.id] = e
            # self.logger.debug('id=%s, cmd disregarded: not an info', e.model.id)
        # Finish here if eq is not enabled
        if not eq.model.isEnable:
            self.logger.debug('id=%s, cmd registered, but is not enabled', e.model.id)
            return
        # Insert path in info topic tree
        if e.model.type != 'info':
            self.logger.debug('id=%s, cmd registered, but is an action', e.model.id)
            return
        topic = e.model.configuration.topic
        # TODO Check if topic is subscribable
        # if isBadTopicFilter(e.subscription):
        if topic == '':
            self.logger.notice(
                'id=%s, cmd registered, but topic "%s" is not subscribable',
                e.model.id,
                topic,
            )
            return
        brk = e.weakBrk()
        # Add topic to BrkLogic if missing
        sub_needed = topic not in brk.topics
        if sub_needed:
            brk.topics[topic] = WeakValueDictionary()
        # Add CmdLogic to topics in BrkLogic
        brk.topics[topic][e.model.id] = e
        if sub_needed and brk.model.isEnable:
            await brk.subscribe(topic, 1)  # TODO Get QoS when Qos location is in cmd
        self.logger.debug('id=%s, cmd registered', e.model.id)

    @classmethod
    async def do(cls, e: VisitableLogic) -> None:
        self = cls()
        await e.accept(self)
