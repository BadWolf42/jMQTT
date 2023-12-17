from enum import Enum
from typing_extensions import Literal, Annotated
from typing import Union, List, Optional, Set
from pydantic import BaseModel, ValidationError, validator, Field

from .bases import EqBaseModel
from .utils import strToBool, strToInt



# -----------------------------------------------------------------------------
# --- Eq model
class EqConfigModel(BaseModel):
    type: Literal['eqpt']
    eqLogic: int #= Field(gt=0, description="Broker id", alias='brkId')
    icone: Optional[str]  = ''
    auto_add_cmd: Optional[bool] = False
    auto_add_topic: Optional[str] = ''
    Qos: Optional[int] = 1
    availability_cmd: Optional[int] = 0
    # availability_eq: Optional[int] = 0
    battery_cmd: Optional[int] = 0

    _val_eqLogic: classmethod = validator("eqLogic", allow_reuse=True, pre=True)(strToInt)
    _val_auto_add_cmd: classmethod = validator("auto_add_cmd", allow_reuse=True, pre=True)(strToBool)
    _val_Qos: classmethod = validator("Qos", allow_reuse=True, pre=True)(strToInt)
    _val_availability_cmd: classmethod = validator("availability_cmd", allow_reuse=True, pre=True)(strToInt)
    _val_battery_cmd: classmethod = validator("battery_cmd", allow_reuse=True, pre=True)(strToInt)

class EqModel(EqBaseModel):
    configuration: EqConfigModel
