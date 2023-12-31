from __future__ import annotations
from logging import getLogger
from typing import List, Union
from weakref import ref, WeakValueDictionary

from visitors.abstractvisitor import LogicVisitor, VisitableLogic
from logics.broker import BrkLogic
from logics.cmd import CmdLogic
from logics.eq import EqLogic


# -----------------------------------------------------------------------------
class PrintVisitor(LogicVisitor):
    def __init__(self):
        self.logger = getLogger('jmqtt.visitor.print')
        self.level = 0

    async def visit_brk(self, e: BrkLogic) -> None:
        self.logger.debug(
            '%s┌─►  BrkLogic id=%s, name=%s, enabled=%s',
            '│ ' * self.level,
            e.model.id,
            e.model.name,
            '1' if e.model.isEnable else '0',
        )

        for t in e.topics:
            self.logger.debug(
                '%s│      %s => %s',
                '│ ' * self.level,
                t,
                ' '.join([str(v.model.id) for v in e.topics[t].values()]),
            )
        self.level += 1
        linked_cmd_eq = [v for v in e.cmd_i.values()]
        linked_cmd_eq += [v for v in e.cmd_a.values()]
        linked_cmd_eq += [v for v in e.eqpts.values()]
        for eq in linked_cmd_eq:
            await eq.accept(self)
        self.level -= 1
        self.logger.debug('%s└%s', '│ ' * self.level, '─' * (50 - 2 * self.level - 1))

    async def visit_eq(self, e: EqLogic) -> None:
        self.logger.debug(
            '%s┌─►  EqLogic  id=%s, name=%s, enabled=%s',
            '│ ' * self.level,
            e.model.id,
            e.model.name,
            '1' if e.model.isEnable else '0'
        )
        self.level += 1
        for cmd in [v for v in e.cmd_i.values()]:
            await cmd.accept(self)
        for cmd in [v for v in e.cmd_a.values()]:
            await cmd.accept(self)
        self.level -= 1
        self.logger.debug('%s└%s', '│ ' * self.level, '─' * (50 - 2 * self.level - 1))

    async def visit_cmd(self, e: CmdLogic) -> None:
        if e.model.type == 'info':
            self.logger.debug(
                '%s - CmdLogic id=%s, name=%s, type=info, topic=%s, jsonPath=%s',
                '│ ' * self.level,
                e.model.id,
                e.model.name,
                e.model.configuration.topic,
                e.model.configuration.jsonPath,
            )
        else:
            self.logger.debug(
                '%s - CmdLogic id=%s, name=%s, type=%s, topic=%s',
                '│ ' * self.level,
                e.model.id,
                e.model.name,
                e.model.type,
                e.model.configuration.topic,
            )

    @classmethod
    async def do(cls, e: List[Union[BrkLogic, EqLogic, CmdLogic]]) -> None:
        self = cls()
        await e.accept(self)
