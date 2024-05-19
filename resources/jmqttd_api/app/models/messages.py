from enum import Enum
from typing import Set
from pydantic import BaseModel


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
    qos: int = 0
    retain: bool = False


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
    # subscribe: Set[MqttSubscTopic] = set()
    subscribe: Set[str] = set()
    # exclude: Set[MqttTopic] = set()
    exclude: Set[str] = set()
    retained: bool = True
    duration: int = 180


# -----------------------------------------------------------------------------
class RealTimeStatusModel(RealTimeModel):
    enabled: bool
    timeleft: int
    count: int
