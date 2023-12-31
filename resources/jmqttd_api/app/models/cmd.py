from enum import Enum
from typing_extensions import Literal
from typing import Optional
from pydantic import BaseModel, validator

from .bases import CmdBaseModel
from .utils import strToBool, strToInt


# -----------------------------------------------------------------------------
# --- Cmd Info model
class CmdInfoConfigModel(BaseModel):
    topic: Optional[str] = ''
    jsonPath: Optional[str] = ''


# -----------------------------------------------------------------------------
class CmdInfoSubTypeModel(str, Enum):
    binary = 'binary'
    numeric = 'numeric'
    string = 'string'


# -----------------------------------------------------------------------------
class CmdInfoModel(CmdBaseModel):
    type: Literal['info']
    subType: CmdInfoSubTypeModel
    configuration: CmdInfoConfigModel


# -----------------------------------------------------------------------------
# --- Cmd Action model
class CmdActionConfigModel(BaseModel):
    topic: Optional[str] = ''
    request: Optional[str] = ''
    Qos: Optional[int] = 0
    retain: Optional[bool] = False
    autoPub: Optional[bool] = False

    _val_Qos: classmethod = validator("Qos", allow_reuse=True, pre=True)(strToInt)
    _val_retain: classmethod = validator(
        "retain", allow_reuse=True, pre=True
    )(strToBool)
    _val_autoPub: classmethod = validator(
        "autoPub", allow_reuse=True, pre=True
    )(strToBool)


# -----------------------------------------------------------------------------
class CmdActionSubTypeModel(str, Enum):
    color = 'color'
    message = 'message'
    select = 'select'
    slider = 'slider'
    other = 'other'


# -----------------------------------------------------------------------------
class CmdActionModel(CmdBaseModel):
    type: Literal['action']
    subType: CmdActionSubTypeModel
    configuration: CmdActionConfigModel
