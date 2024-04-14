from enum import Enum
from typing_extensions import Literal
from typing import Optional
from pydantic import BaseModel, validator

from .bases import EqBaseModel
from .utils import strToBool, strToInt


# -----------------------------------------------------------------------------
# --- Broker model
class MqttProtoModel(str, Enum):
    mqtt = 'mqtt'
    mqtts = 'mqtts'
    ws = 'ws'
    wss = 'wss'


class TlsCheckModel(str, Enum):
    disabled = 'disabled'
    private = 'private'
    public = 'public'


class BrkConfigModel(BaseModel):
    type: Literal['broker']
    # brkId: int
    # qos: Optional[int]  = 1
    auto_add_cmd: Optional[bool] = False
    auto_add_topic: Optional[str] = '#'
    mqttProto: Optional[MqttProtoModel] = MqttProtoModel.mqtt
    mqttAddress: Optional[str] = 'localhost'
    mqttPort: Optional[int] = 0
    mqttUser: Optional[str] = ''
    mqttPass: Optional[str] = ''
    mqttId: Optional[bool] = False
    mqttIdValue: Optional[str] = 'jMQTT'
    mqttLwt: Optional[bool] = False
    mqttLwtTopic: Optional[str] = 'jeedom/status'
    mqttLwtOnline: Optional[str] = 'online'
    mqttLwtOffline: Optional[str] = 'offline'
    mqttInt: Optional[bool] = False
    mqttIntTopic: Optional[str] = ''
    mqttApi: Optional[bool] = False
    mqttApiTopic: Optional[str] = ''
    mqttTlsCheck: Optional[TlsCheckModel] = TlsCheckModel.disabled
    mqttTlsCa: Optional[str] = ''
    mqttTlsClient: Optional[bool] = False
    mqttTlsClientCert: Optional[str] = ''
    mqttTlsClientKey: Optional[str] = ''

    # _val_qos: classmethod = validator("qos", allow_reuse=True, pre=True)(strToInt)
    _val_auto_add_cmd: classmethod = validator(
        "auto_add_cmd", allow_reuse=True, pre=True
    )(strToBool)
    _val_mqttPort: classmethod = validator("mqttPort", allow_reuse=True, pre=True)(
        strToInt
    )
    _val_mqttId: classmethod = validator("mqttId", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_mqttLwt: classmethod = validator("mqttLwt", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_mqttInt: classmethod = validator("mqttInt", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_mqttApi: classmethod = validator("mqttApi", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_mqttTlsClient: classmethod = validator(
        "mqttTlsClient", allow_reuse=True, pre=True
    )(strToBool)


class BrkModel(EqBaseModel):
    configuration: BrkConfigModel
