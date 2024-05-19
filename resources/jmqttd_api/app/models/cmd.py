from enum import Enum
from typing import Union
from typing_extensions import Literal
from pydantic import BaseModel, model_validator, validator

from .bases import CmdBaseModel
from .utils import strToBool, strToInt


# -----------------------------------------------------------------------------
# --- Cmd Info model
class CmdInfoDecoderEnum(str, Enum):
    strict = 'strict'
    replace = 'replace'
    ignore = 'ignore'
    backslashreplace = 'backslashreplace'
    none = 'none'


# -----------------------------------------------------------------------------
class CmdInfoHandlerEnum(str, Enum):
    literal = 'literal'
    jsonPath = 'jsonPath'
    jinja = 'jinja'


# -----------------------------------------------------------------------------
class CmdInfoConfigModel(BaseModel):
    topic: str = ''
    tryUnzip: bool = False
    decoder: CmdInfoDecoderEnum = CmdInfoDecoderEnum.strict
    handler: CmdInfoHandlerEnum = CmdInfoHandlerEnum.jsonPath
    jsonPath: str = ''
    jinja: str = ''
    toFile: bool = False


# -----------------------------------------------------------------------------
class CmdInfoSubTypeEnum(str, Enum):
    binary = 'binary'
    numeric = 'numeric'
    string = 'string'


# -----------------------------------------------------------------------------
class CmdInfoModel(CmdBaseModel):
    type: Literal['info']
    subType: CmdInfoSubTypeEnum
    configuration: CmdInfoConfigModel


# -----------------------------------------------------------------------------
# --- Cmd Action model
class CmdActionConfigModel(BaseModel):
    topic: str = ''
    request: str = ''
    Qos: int = 0
    retain: bool = False
    autoPub: bool = False

    _val_Qos: classmethod = validator("Qos", allow_reuse=True, pre=True)(strToInt)
    _val_retain: classmethod = validator("retain", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_autoPub: classmethod = validator("autoPub", allow_reuse=True, pre=True)(
        strToBool
    )


# -----------------------------------------------------------------------------
class CmdActionSubTypeEnum(str, Enum):
    color = 'color'
    message = 'message'
    select = 'select'
    slider = 'slider'
    other = 'other'


# -----------------------------------------------------------------------------
class CmdActionModel(CmdBaseModel):
    type: Literal['action']
    subType: CmdActionSubTypeEnum
    configuration: CmdActionConfigModel


# -----------------------------------------------------------------------------
# --- Cmd Messages TO jeedom Models
class CmdValue(BaseModel):
    id: int
    value: Union[bool, int, float, str]


# -----------------------------------------------------------------------------
class CmdTimedValue(CmdValue):
    ts: float
