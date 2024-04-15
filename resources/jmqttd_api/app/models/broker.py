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


class MqttVersionModel(str, Enum):
    V31 = 'V31'
    V311 = 'V311'
    V5 = 'V5'


class TlsCheckModel(str, Enum):
    disabled = 'disabled'
    private = 'private'
    public = 'public'


class BrkConfigModel(BaseModel):
    type: Literal['broker']
    auto_add_cmd: Optional[bool] = False
    auto_add_topic: Optional[str] = '#'
    mqttProto: Optional[MqttProtoModel] = MqttProtoModel.mqtt
    mqttVersion: Optional[MqttVersionModel] = None
    mqttAddress: Optional[str] = 'localhost'
    mqttWsHeader: Optional[dict[str, str]] = None
    mqttWsUrl: Optional[str] = 'mqtt'
    mqttPort: Optional[int] = 0
    mqttUser: Optional[str] = None
    mqttPass: Optional[str] = None
    mqttId: Optional[bool] = False
    mqttIdValue: Optional[str] = 'jMQTT'
    mqttLwt: Optional[bool] = False
    mqttLwtTopic: Optional[str] = 'jeedom/status'
    mqttLwtOnline: Optional[str] = 'online'
    mqttLwtOffline: Optional[str] = 'offline'
    mqttInt: Optional[bool] = False
    mqttIntTopic: Optional[str] = 'jeedom/interact'
    mqttApi: Optional[bool] = False
    mqttApiTopic: Optional[str] = 'jeedom/api'
    mqttTlsCheck: Optional[TlsCheckModel] = TlsCheckModel.disabled
    mqttTlsCa: Optional[str] = ''
    mqttTlsClient: Optional[bool] = False
    mqttTlsClientCert: Optional[str] = ''
    mqttTlsClientKey: Optional[str] = ''
    mqttRecoInterval: Optional[int] = 5

    # _val_qos: classmethod = validator("qos", allow_reuse=True, pre=True)(strToInt)
    _val_auto_add_cmd: classmethod = validator(
        "auto_add_cmd", allow_reuse=True, pre=True
    )(strToBool)
    _val_mqttAddress: classmethod = validator(
        "mqttAddress", allow_reuse=True, pre=True
    )(
        lambda v: v if v is not None and v != '' else 'localhost'
    )
    _val_mqttWsUrl: classmethod = validator(
        "mqttWsUrl", allow_reuse=True, pre=True
    )(
        lambda v: v if v is not None and v != '' else 'mqtt'
    )
    _val_mqttPort: classmethod = validator("mqttPort", allow_reuse=True, pre=True)(
        strToInt
    )
    _val_mqttUser: classmethod = validator("mqttUser", allow_reuse=True, pre=True)(
        lambda v: v if v is not None and v != '' else None
    )
    _val_mqttPass: classmethod = validator("mqttPass", allow_reuse=True, pre=True)(
        lambda v: v if v is not None and v != '' else None
    )
    _val_mqttId: classmethod = validator("mqttId", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_mqttIdValue: classmethod = validator(
        "mqttIdValue", allow_reuse=True, pre=True)
    _val_mqttLwt: classmethod = validator("mqttLwt", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_mqttLwtTopic: classmethod = validator(
        "mqttLwtTopic", allow_reuse=True, pre=True
    )(lambda v: v if v is not None and v != '' else 'jeedom/status')
    _val_mqttLwtOnline: classmethod = validator(
        "mqttLwtOnline", allow_reuse=True, pre=True
    )(lambda v: v if v is not None and v != '' else 'online')
    _val_mqttLwtOffline: classmethod = validator(
        "mqttLwtOffline", allow_reuse=True, pre=True
    )(lambda v: v if v is not None and v != '' else 'offline')
    _val_mqttInt: classmethod = validator("mqttInt", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_mqttIntTopic: classmethod = validator(
        "mqttIntTopic", allow_reuse=True, pre=True
    )(lambda v: v if v is not None and v != '' else 'jeedom/interact')
    _val_mqttApi: classmethod = validator("mqttApi", allow_reuse=True, pre=True)(
        strToBool
    )
    _val_mqttApiTopic: classmethod = validator(
        "mqttApiTopic", allow_reuse=True, pre=True
    )(lambda v: v if v is not None and v != '' else 'jeedom/api')
    _val_mqttTlsClient: classmethod = validator(
        "mqttTlsClient", allow_reuse=True, pre=True
    )(strToBool)


class BrkModel(EqBaseModel):
    configuration: BrkConfigModel
