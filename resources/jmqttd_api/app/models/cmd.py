from enum import Enum
from typing_extensions import Literal
from typing import Union
from pydantic import BaseModel, validator

from .bases import CmdBaseModel
from .utils import strToBool, strToInt


# -----------------------------------------------------------------------------
# --- Cmd Info model
class CmdInfoDecoderModel(str, Enum):
    strict = 'strict'
    replace = 'replace'
    ignore = 'ignore'
    backslashreplace = 'backslashreplace'
    none = 'none'


# -----------------------------------------------------------------------------
class CmdInfoHandlerModel(str, Enum):
    literal = 'literal'
    jsonPath = 'jsonPath'
    jinja = 'jinja'


# -----------------------------------------------------------------------------
class CmdInfoConfigModel(BaseModel):
    topic: Union[str, None] = ''
    tryUnzip: bool = False
    decoder: CmdInfoDecoderModel = CmdInfoDecoderModel.strict
    handler: CmdInfoHandlerModel = CmdInfoHandlerModel.jsonPath
    jsonPath: Union[str, None] = ''
    template: str = ''
    toFile: bool = False


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
    topic: Union[str, None] = ''
    request: Union[str, None] = ''
    Qos: Union[int, None] = 0
    retain: Union[bool, None] = False
    autoPub: Union[bool, None] = False

    _val_Qos: classmethod = validator("Qos", allow_reuse=True, pre=True)(strToInt)
    _val_retain: classmethod = validator("retain", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_autoPub: classmethod = validator("autoPub", allow_reuse=True, pre=True)(
        strToBool
    )


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
