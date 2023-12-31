from typing import List, Union

from logics.broker import BrkLogic
from logics.cmd import CmdLogic
from logics.eq import EqLogic
from logics.visitor import (
    RegisteringLogicVisitor,
    UnregisteringLogicVisitor,
    PrintVisitor,
)
from models.broker import BrkModel
from models.eq import EqModel
from models.unions import CmdModel


class Logic():
    @classmethod
    def registerGenericModel(
        cls,
        model: Union[BrkModel, EqModel, CmdModel],
        logic: Union[BrkLogic, EqLogic, CmdLogic],
    ) -> None:
        # If Logic exists in register
        if model.id in logic.all:
            # Unregister it
            unreged = UnregisteringLogicVisitor.do(logic.all[model.id])
            # And replace it
            unreged[0] = logic(model)
        else:
            unreged = [logic(model)]
        # Register back each unregistered object
        for inst in unreged:
            # With the register class method of the object
            RegisteringLogicVisitor.do(inst)

    # -----------------------------------------------------------------------------
    @classmethod
    def unregisterGenericId(
        cls, id: int, logic: Union[BrkLogic, EqLogic, CmdLogic]
    ) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        if id not in logic.all:
            return []
        return UnregisteringLogicVisitor.do(logic.all[id])

    # -----------------------------------------------------------------------------
    @classmethod
    def registerBrkModel(cls, model: BrkModel) -> None:
        cls.registerGenericModel(model, BrkLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    def unregisterBrkId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        return cls.unregisterGenericId(id, BrkLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    def clear(cls) -> None:
        for inst in [v for v in BrkLogic.all.values()]:
            UnregisteringLogicVisitor.do(inst)

    # -----------------------------------------------------------------------------
    @classmethod
    def registerEqModel(cls, model: EqModel) -> None:
        cls.registerGenericModel(model, EqLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    def unregisterEqId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        return cls.unregisterGenericId(id, EqLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    def registerCmdModel(cls, model: CmdModel) -> None:
        cls.registerGenericModel(model, CmdLogic)
        # if model.id in cls.all:
        #     UnregisteringLogicVisitor.do(cls.all[model.id])
        # RegisteringLogicVisitor.do(CmdLogic(model))

    # -----------------------------------------------------------------------------
    @classmethod
    def unregisterCmdId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        return cls.unregisterGenericId(id, CmdLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    def printTree(cls) -> None:
        for b in BrkLogic.all.values():
            PrintVisitor.do(b)


    """
    # On BrkLogic
    @classmethod
    def registerBrkModel(cls, model: BrkModel) -> None:
        # If BrkLogic exists
        if model.id in cls.all:
        # Unregister it
            unreged = UnregisteringLogicVisitor.do(cls.all[model.id])
            # And replace it
            unreged[0] = BrkLogic(model)
        else:
            unreged = [BrkLogic(model)]
        # Register back each unregistered object
        for inst in unreged:
            # With the register class method of the object
            RegisteringLogicVisitor.do(inst)

    @classmethod
    def unregisterBrkId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        cls.unregisterGenericId(id, BrkLogic)

    @classmethod
    def clear(cls) -> None:
        for inst in cls.all.copy():
            UnregisteringLogicVisitor.do(inst)

    # -----------------------------------------------------------------------------
    # On EqLogic
    @classmethod
    def registerEqModel(cls, model: EqModel) -> None:
        # If EqLogic exists
        if model.id in cls.all:
        # Unregister it
            unreged = UnregisteringLogicVisitor.do(cls.all[model.id])
            # And replace it
            unreged[0] = EqLogic(model)
        else:
            unreged = [EqLogic(model)]
        # Register back each unregistered object
        for inst in unreged:
            # With the register class method of the object
            RegisteringLogicVisitor.do(inst)

    @classmethod
    def unregisterEqId(cls, id: int) -> VisitableLogic:
        # Not registered
        if id not in cls.all:
            return []
        return UnregisteringLogicVisitor.do(cls.all[id])

    # -----------------------------------------------------------------------------
    # On CmdLogic
    @classmethod
    def registerCmdModel(cls, model: CmdModel) -> None:
        if model.id in cls.all:
            UnregisteringLogicVisitor.do(cls.all[model.id])
        RegisteringLogicVisitor.do(CmdLogic(model))

    @classmethod
    def unregisterCmdId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        # Not registered
        if id not in cls.all:
            return []
        return UnregisteringLogicVisitor.do(cls.all[id])
    """
