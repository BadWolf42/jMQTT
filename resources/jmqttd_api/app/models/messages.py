from enum import Enum
from typing import Optional, Set
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
    none = 'none'
    notset = 'notset'


# -----------------------------------------------------------------------------
class MqttMessageModel(BaseModel):
    topic: str
    payload: str
    qos: Optional[int] = 1
    retain: Optional[bool] = False


# -----------------------------------------------------------------------------
# class MqttTopic(str):
#     pass


# -----------------------------------------------------------------------------
# class MqttSubscTopic(MqttTopic):
#     pass


# -----------------------------------------------------------------------------
class RealTimeModel(BaseModel):
    eqLogic: int
    file: str
    # subscribe: Optional[Set[MqttSubscTopic]] = set()
    subscribe: Optional[Set[str]] = set()
    # exclude: Optional[Set[MqttTopic]] = set()
    exclude: Optional[Set[str]] = set()
    retained: Optional[bool] = True
    duration: Optional[int] = 180

    _val_eqLogic: classmethod = validator(
        "eqLogic", allow_reuse=True, pre=True
    )(strToInt)


# -----------------------------------------------------------------------------
class RealTimeStatusModel(RealTimeModel):
    enabled: bool
