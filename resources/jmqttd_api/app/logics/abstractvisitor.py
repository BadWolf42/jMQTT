from __future__ import annotations
from abc import ABC, abstractmethod


# -----------------------------------------------------------------------------
class LogicVisitor(ABC):
    @abstractmethod
    async def visit_brklogic(self, e) -> None:
        pass

    @abstractmethod
    async def visit_eqlogic(self, e) -> None:
        pass

    @abstractmethod
    async def visit_cmdlogic(self, e) -> None:
        pass


# -----------------------------------------------------------------------------
class VisitableLogic(ABC):
    @abstractmethod
    async def accept(self, visitor: LogicVisitor) -> None:
        pass
