from enum import Enum
from typing import Set, Union
from pydantic import BaseModel, validator

from .utils import strToInt


###############################################################################
# --- Messages FROM Jeedom model-
class LogLevelModel(str, Enum):
    trace = 'trace'
    debug = 'debug'
    info = 'info'
    notice = 'notice'
    warning = 'warning'
    error = 'error'
    critical = 'critical'
    alert = 'alert'
    emergency = 'emergency'
    none = 'none'
    notset = 'notset'


# -----------------------------------------------------------------------------
class MqttMessageModel(BaseModel):
    topic: str
    payload: str
    qos: Union[int, None] = 1
    retain: Union[bool, None] = False


# See: https://docs.pydantic.dev/2.5/concepts/models/#rootmodel-and-custom-root-types

# And Wildcard/Topic
# At: https://github.com/sbtinstruments/aiomqtt/blob/731f583e7b5d622d56dfeebf8fe96b3dba7cbbed/aiomqtt/client.py#L131

# -----------------------------------------------------------------------------
# class MqttTopic(str):
#     pass


# -----------------------------------------------------------------------------
# class MqttSubscTopic(MqttTopic):
#     pass


# -----------------------------------------------------------------------------
class RealTimeModel(BaseModel):
    eqLogic: int
    # subscribe: Union[Set[MqttSubscTopic], None] = set()
    subscribe: Union[Set[str], None] = set()
    # exclude: Union[Set[MqttTopic], None] = set()
    exclude: Union[Set[str], None] = set()
    retained: Union[bool, None] = True
    duration: Union[int, None] = 180

    _val_eqLogic: classmethod = validator("eqLogic", allow_reuse=True, pre=True)(
        strToInt
    )


# -----------------------------------------------------------------------------
class RealTimeStatusModel(RealTimeModel):
    enabled: bool
    timeleft: int
    count: int
