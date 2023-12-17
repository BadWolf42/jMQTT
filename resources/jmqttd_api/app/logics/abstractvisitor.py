from __future__ import annotations
from abc import ABC, abstractmethod


# -----------------------------------------------------------------------------
class LogicVisitor(ABC):
    @abstractmethod
    def visit_brklogic(self, e: BrkLogic) -> None:
        pass
    @abstractmethod
    def visit_eqlogic(self, e: CmdLogic) -> None:
        pass
    @abstractmethod
    def visit_cmdlogic(self, e: EqLogic) -> None:
        pass

# -----------------------------------------------------------------------------
class VisitableLogic(ABC):
    @abstractmethod
    def accept(self, visitor: LogicVisitor) -> None:
        pass
