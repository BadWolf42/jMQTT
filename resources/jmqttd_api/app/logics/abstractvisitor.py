from __future__ import annotations
from abc import ABC, abstractmethod


# -----------------------------------------------------------------------------
class LogicVisitor(ABC):
    @abstractmethod
    def visit_brklogic(self, e) -> None:
        pass

    @abstractmethod
    def visit_eqlogic(self, e) -> None:
        pass

    @abstractmethod
    def visit_cmdlogic(self, e) -> None:
        pass


# -----------------------------------------------------------------------------
class VisitableLogic(ABC):
    @abstractmethod
    def accept(self, visitor: LogicVisitor) -> None:
        pass
