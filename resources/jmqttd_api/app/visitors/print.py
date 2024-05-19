from __future__ import annotations
from logging import getLogger
from typing import List, Union

from visitors.abstractvisitor import LogicVisitor
from logics.broker import BrkLogic
from logics.cmd import CmdLogic
from logics.eq import EqLogic
from logics.topicmap import TopicMap


logger = getLogger('jmqtt.visitor.print')


# -----------------------------------------------------------------------------
class PrintVisitor(LogicVisitor):
    def __init__(self, e: List[Union[BrkLogic, EqLogic, CmdLogic]]):
        self.level = 0
        self.toPrint = e

    async def visit_topicmap(self, e: TopicMap) -> None:
        for tab, char in [(e.topics, 'T'), (e.wildcards, 'W'), (e.defaults, 'D')]:
            for topic in tab:
                logger.debug(
                    f'{"│ " * self.level}│ {char}:   {topic} => {{%s}}',
                    ', '.join([str(v.model.id) for v in l[topic]]),
                )

    async def visit_brk(self, e: BrkLogic) -> None:
        logger.debug(
            '%s┌─►  BrkLogic id=%s, name=%s, enabled=%s',
            '│ ' * self.level,
            e.model.id,
            e.model.name,
            '1' if e.model.isEnable else '0',
        )
        await e.map.accept(self)
        self.level += 1
        for eq in [v for v in e.eqpts.values()]:
            await eq.accept(self)
        self.level -= 1
        logger.debug('%s└%s', '│ ' * self.level, '─' * (50 - 2 * self.level - 1))

    async def visit_eq(self, e: EqLogic) -> None:
        logger.debug(
            '%s┌─►  EqLogic  id=%s, name=%s, enabled=%s',
            '│ ' * self.level,
            e.model.id,
            e.model.name,
            '1' if e.model.isEnable else '0',
        )
        self.level += 1
        for cmd in [v for v in e.cmd_i.values()]:
            await cmd.accept(self)
        for cmd in [v for v in e.cmd_a.values()]:
            await cmd.accept(self)
        self.level -= 1
        logger.debug('%s└%s', '│ ' * self.level, '─' * (50 - 2 * self.level - 1))

    async def visit_cmd(self, e: CmdLogic) -> None:
        if e.model.type == 'info':
            logger.debug(
                '%s - CmdLogic id=%s, name=%s, type=info, topic=%s, jsonPath=%s',
                '│ ' * self.level,
                e.model.id,
                e.model.name,
                e.model.configuration.topic,
                e.model.configuration.jsonPath,
            )
        else:
            logger.debug(
                '%s - CmdLogic id=%s, name=%s, type=%s, topic=%s',
                '│ ' * self.level,
                e.model.id,
                e.model.name,
                e.model.type,
                e.model.configuration.topic,
            )

    async def print(self) -> None:
        await self.toPrint.accept(self)
