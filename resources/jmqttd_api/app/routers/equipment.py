from fastapi import APIRouter, HTTPException, status
from logging import getLogger
from typing import List

from logics import Logic, EqLogic
from models import EqModel


# -----------------------------------------------------------------------------
logger = getLogger('jmqtt.rest')
equipment = APIRouter(
    prefix="/equipment",
    tags=["eqLogic"],
)

# -----------------------------------------------------------------------------
@equipment.post("", status_code=204)
def equipment_post(eq: EqModel):
    if id in EqLogic.all:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="eqLogic exists"
        )
    Logic.registerEqModel(eq)

# -----------------------------------------------------------------------------
@equipment.put("", status_code=204, response_model_exclude_defaults=True)
def equipment_put(eq: EqModel):
    if id not in EqLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="eqLogic not found"
        )
    return Logic.registerEqModel(eq)

# -----------------------------------------------------------------------------
@equipment.get("", response_model_exclude_defaults=True)
def equipment_get() -> List[EqModel]:
    return [eq.model for eq in EqLogic.all.values()]

# -----------------------------------------------------------------------------
@equipment.get("/{id}", response_model_exclude_defaults=True)
def equipment_get_id(id: int) -> EqModel:
    if id not in EqLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="eqLogic not found"
        )
    return EqLogic.all[id].model

# -----------------------------------------------------------------------------
@equipment.delete("/{id}", status_code=204)
def equipment_delete_id(id: int):
    if id not in EqLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="eqLogic not found"
        )
    Logic.unregisterEqId(id)
