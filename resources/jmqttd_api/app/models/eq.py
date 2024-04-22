from typing_extensions import Literal
from pydantic import BaseModel, validator

from .bases import EqBaseModel
from .utils import strToBool, strToInt


# -----------------------------------------------------------------------------
# --- Eq model
class EqConfigModel(BaseModel):
    type: Literal['eqpt']
    eqLogic: int = -1
    icone: str = ''
    auto_add_cmd: bool = False
    auto_add_topic: str = ''
    # rootTopic: str = ''
    Qos: int = 1
    availability_cmd: int = 0
    # availability_eq: int = 0
    battery_cmd: int = 0

    _val_eqLogic: classmethod = validator("eqLogic", allow_reuse=True, pre=True)(
        strToInt
    )
    _val_auto_add_cmd: classmethod = validator(
        "auto_add_cmd", allow_reuse=True, pre=True
    )(strToBool)
    _val_Qos: classmethod = validator("Qos", allow_reuse=True, pre=True)(strToInt)
    _val_availability_cmd: classmethod = validator(
        "availability_cmd", allow_reuse=True, pre=True
    )(strToInt)
    _val_battery_cmd: classmethod = validator(
        "battery_cmd", allow_reuse=True, pre=True
    )(strToInt)


class EqModel(EqBaseModel):
    configuration: EqConfigModel
