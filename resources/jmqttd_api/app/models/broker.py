from enum import Enum
from typing_extensions import Literal
from typing import Union
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
    auto_add_cmd: Union[bool, None] = False
    auto_add_topic: Union[str, None] = '#'
    mqttProto: Union[MqttProtoModel, None] = MqttProtoModel.mqtt
    mqttVersion: Union[MqttVersionModel, None] = None
    mqttAddress: Union[str, None] = 'localhost'
    mqttWsHeader: Union[dict[str, str], None] = None
    mqttWsUrl: Union[str, None] = 'mqtt'
    mqttPort: Union[int, None] = 0
    mqttUser: Union[str, None] = None
    mqttPass: Union[str, None] = None
    mqttId: Union[bool, None] = False
    mqttIdValue: Union[str, None] = 'jMQTT'
    mqttLwt: Union[bool, None] = False
    mqttLwtTopic: Union[str, None] = 'jeedom/status'
    mqttLwtOnline: Union[str, None] = 'online'
    mqttLwtOffline: Union[str, None] = 'offline'
    mqttInt: Union[bool, None] = False
    mqttIntTopic: Union[str, None] = 'jeedom/interact'
    mqttApi: Union[bool, None] = False
    mqttApiTopic: Union[str, None] = 'jeedom/api'
    mqttTlsCheck: Union[TlsCheckModel, None] = TlsCheckModel.disabled
    mqttTlsCa: Union[str, None] = ''
    mqttTlsClient: Union[bool, None] = False
    mqttTlsClientCert: Union[str, None] = ''
    mqttTlsClientKey: Union[str, None] = ''
    mqttRecoInterval: Union[int, None] = 5

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
