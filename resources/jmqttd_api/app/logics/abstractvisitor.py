from __future__ import annotations
from abc import ABC, abstractmethod


# -----------------------------------------------------------------------------
class LogicVisitor(ABC):
    @abstractmethod
    def visit_brklogic(self, l) -> None:
        pass
    @abstractmethod
    def visit_eqlogic(self, l) -> None:
        pass
    @abstractmethod
    def visit_cmdlogic(self, l) -> None:
        pass

# -----------------------------------------------------------------------------
class VisitableLogic(ABC):
    @abstractmethod
    def accept(self, visitor: LogicVisitor) -> None:
        pass
