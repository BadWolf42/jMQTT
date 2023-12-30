from typing_extensions import Literal
from typing import Optional
from pydantic import BaseModel, validator

from .utils import strToBool, strToInt


# -----------------------------------------------------------------------------
# --- eqLogic generic model
class EqBaseModel(BaseModel):
    id: int
    name: Optional[str] = None
    logicalId: Optional[str] = None
    # generic_type: Optional[str] = None
    eqType_name: Literal['jMQTT']
    # isVisible: Optional[bool] = False
    isEnable: bool

    _val_id: classmethod = validator("id", allow_reuse=True, pre=True)(strToInt)
    # _val_isVisible: classmethod = validator("isVisible", allow_reuse=True, pre=True)(strToBool)
    _val_isEnable: classmethod = validator("isEnable", allow_reuse=True, pre=True)(strToBool)


# -----------------------------------------------------------------------------
# --- Cmd generic model
class CmdBaseModel(BaseModel):
    id: int
    name: Optional[str] = None
    logicalId: Optional[str] = None
    # generic_type: Optional[str] = None
    eqType: Literal['jMQTT']
    eqLogic_id: Optional[int] = -1
    # isHistorized: Optional[bool] = False
    # isVisible: Optional[bool] = False

    _val_id: classmethod = validator("id", allow_reuse=True, pre=True)(strToInt)
    _val_eqLogic_id: classmethod = validator("eqLogic_id", allow_reuse=True, pre=True)(strToInt)
    # _val_isHistorized: classmethod = validator("isHistorized", allow_reuse=True, pre=True)(strToBool)
    # _val_isVisible: classmethod = validator("isVisible", allow_reuse=True, pre=True)(strToBool)
