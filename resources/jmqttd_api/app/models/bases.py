from typing_extensions import Literal
from typing import Union
from pydantic import BaseModel, validator

from .utils import strToBool, strToInt


# -----------------------------------------------------------------------------
# --- eqLogic generic model
class EqBaseModel(BaseModel):
    id: int
    name: Union[str, None] = None
    logicalId: Union[str, None] = None
    # generic_type: Union[str, None] = None
    eqType_name: Literal['jMQTT']
    # isVisible: Union[bool, None] = False
    isEnable: bool

    _val_id: classmethod = validator("id", allow_reuse=True, pre=True)(strToInt)
    # _val_isVisible: classmethod = validator("isVisible", allow_reuse=True, pre=True)(strToBool)
    _val_isEnable: classmethod = validator("isEnable", allow_reuse=True, pre=True)(
        strToBool
    )


# -----------------------------------------------------------------------------
# --- Cmd generic model
class CmdBaseModel(BaseModel):
    id: int
    name: Union[str, None] = None
    logicalId: Union[str, None] = None
    # generic_type: Union[str, None] = None
    eqType: Literal['jMQTT']
    eqLogic_id: Union[int, None] = -1
    # isHistorized: Union[bool, None] = False
    # isVisible: Union[bool, None] = False

    _val_id: classmethod = validator("id", allow_reuse=True, pre=True)(strToInt)
    _val_eqLogic_id: classmethod = validator("eqLogic_id", allow_reuse=True, pre=True)(
        strToInt
    )
    # _val_isHistorized: classmethod = validator("isHistorized", allow_reuse=True, pre=True)(strToBool)
    # _val_isVisible: classmethod = validator("isVisible", allow_reuse=True, pre=True)(strToBool)
