from fastapi import APIRouter, HTTPException, status
from logging import getLogger
from typing import List

from logics.logic import Logic, EqLogic
from models.eq import EqModel
from visitors.print import PrintVisitor


# -----------------------------------------------------------------------------
logger = getLogger('jmqtt.rest')
equipment = APIRouter(
    prefix="/equipment",
    tags=["eqLogic"],
)


# -----------------------------------------------------------------------------
@equipment.post("", status_code=204, summary="Create or update an Equipment in Daemon")
async def equipment_post(eq: EqModel):
    await Logic.registerEqModel(eq)


# -----------------------------------------------------------------------------
@equipment.get("", response_model_exclude_defaults=True)
async def equipment_get() -> List[EqModel]:
    return [eq.model for eq in EqLogic.all.values()]


# -----------------------------------------------------------------------------
@equipment.get("/{id}", response_model_exclude_defaults=True)
async def equipment_get_id(id: int) -> EqModel:
    if id not in EqLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Equipment not found"
        )
    return EqLogic.all[id].model


# -----------------------------------------------------------------------------
@equipment.delete("/{id}", status_code=204)
async def equipment_delete_id(id: int):
    if id not in EqLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Equipment not found"
        )
    await Logic.unregisterEqId(id)

# -----------------------------------------------------------------------------
@equipment.get("/{id}/debug/tree", status_code=204, summary="Log this eq/cmd tree")
async def equipment_get_debug_tree(id: int):
    if id not in EqLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Equipment not found"
        )
    await PrintVisitor.do(EqLogic.all[id])
