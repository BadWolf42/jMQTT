from __future__ import annotations
from abc import ABC, abstractmethod


# -----------------------------------------------------------------------------
class LogicVisitor(ABC):
    @abstractmethod
    async def visit_brk(self, e) -> None:
        pass

    @abstractmethod
    async def visit_eq(self, e) -> None:
        pass

    @abstractmethod
    async def visit_cmd(self, e) -> None:
        pass


# -----------------------------------------------------------------------------
class VisitableLogic(ABC):
    @abstractmethod
    async def accept(self, visitor: LogicVisitor) -> None:
        pass
