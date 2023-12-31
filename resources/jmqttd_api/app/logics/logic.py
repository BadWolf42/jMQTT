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


class Logic:
    @classmethod
    async def registerGenericModel(
        cls,
        model: Union[BrkModel, EqModel, CmdModel],
        logic: Union[BrkLogic, EqLogic, CmdLogic],
    ) -> None:
        # If Logic exists in register
        if model.id in logic.all:
            # Unregister it
            unreged = await UnregisteringLogicVisitor.do(logic.all[model.id])
            # And replace it
            unreged[0] = logic(model)
        else:
            unreged = [logic(model)]
        # Register back each unregistered object
        for inst in unreged:
            # With the register class method of the object
            await RegisteringLogicVisitor.do(inst)

    # -----------------------------------------------------------------------------
    @classmethod
    async def unregisterGenericId(
        cls, id: int, logic: Union[BrkLogic, EqLogic, CmdLogic]
    ) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        if id not in logic.all:
            return []
        return await UnregisteringLogicVisitor.do(logic.all[id])

    # -----------------------------------------------------------------------------
    @classmethod
    async def registerBrkModel(cls, model: BrkModel) -> None:
        await cls.registerGenericModel(model, BrkLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    async def unregisterBrkId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        return await cls.unregisterGenericId(id, BrkLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    async def clear(cls) -> None:
        for inst in [v for v in BrkLogic.all.values()]:
            await UnregisteringLogicVisitor.do(inst)

    # -----------------------------------------------------------------------------
    @classmethod
    async def registerEqModel(cls, model: EqModel) -> None:
        await cls.registerGenericModel(model, EqLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    async def unregisterEqId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        return await cls.unregisterGenericId(id, EqLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    async def registerCmdModel(cls, model: CmdModel) -> None:
        await cls.registerGenericModel(model, CmdLogic)
        # if model.id in cls.all:
        #     await UnregisteringLogicVisitor.do(cls.all[model.id])
        # await RegisteringLogicVisitor.do(CmdLogic(model))

    # -----------------------------------------------------------------------------
    @classmethod
    async def unregisterCmdId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        return await cls.unregisterGenericId(id, CmdLogic)

    # -----------------------------------------------------------------------------
    @classmethod
    async def printTree(cls) -> None:
        for b in BrkLogic.all.values():
            await PrintVisitor.do(b)

    """
    # On BrkLogic
    @classmethod
    async def registerBrkModel(cls, model: BrkModel) -> None:
        # If BrkLogic exists
        if model.id in cls.all:
        # Unregister it
            unreged = await UnregisteringLogicVisitor.do(cls.all[model.id])
            # And replace it
            unreged[0] = BrkLogic(model)
        else:
            unreged = [BrkLogic(model)]
        # Register back each unregistered object
        for inst in unreged:
            # With the register class method of the object
            await RegisteringLogicVisitor.do(inst)

    @classmethod
    async def unregisterBrkId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        await cls.unregisterGenericId(id, BrkLogic)

    @classmethod
    async def clear(cls) -> None:
        for inst in cls.all.copy():
            await UnregisteringLogicVisitor.do(inst)

    # -----------------------------------------------------------------------------
    # On EqLogic
    @classmethod
    async def registerEqModel(cls, model: EqModel) -> None:
        # If EqLogic exists
        if model.id in cls.all:
        # Unregister it
            unreged = await UnregisteringLogicVisitor.do(cls.all[model.id])
            # And replace it
            unreged[0] = EqLogic(model)
        else:
            unreged = [EqLogic(model)]
        # Register back each unregistered object
        for inst in unreged:
            # With the register class method of the object
            await RegisteringLogicVisitor.do(inst)

    @classmethod
    async def unregisterEqId(cls, id: int) -> VisitableLogic:
        # Not registered
        if id not in cls.all:
            return []
        return await UnregisteringLogicVisitor.do(cls.all[id])

    # -----------------------------------------------------------------------------
    # On CmdLogic
    @classmethod
    async def registerCmdModel(cls, model: CmdModel) -> None:
        if model.id in cls.all:
            await UnregisteringLogicVisitor.do(cls.all[model.id])
        await RegisteringLogicVisitor.do(CmdLogic(model))

    @classmethod
    async def unregisterCmdId(cls, id: int) -> List[Union[BrkLogic, EqLogic, CmdLogic]]:
        # Not registered
        if id not in cls.all:
            return []
        return await UnregisteringLogicVisitor.do(cls.all[id])
    """
