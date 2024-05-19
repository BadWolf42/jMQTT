from aiomqtt import ProtocolVersion
from enum import Enum
from typing_extensions import Literal
from typing import Union
from pydantic import BaseModel, validator

from .bases import EqBaseModel
from .utils import strToBool, strToInt


# -----------------------------------------------------------------------------
# --- Broker model
class MqttProtoEnum(str, Enum):
    mqtt = 'mqtt'
    mqtts = 'mqtts'
    ws = 'ws'
    wss = 'wss'


class MqttVersionEnum(int, Enum):
    # V31 = ProtocolVersion.V31
    V311 = ProtocolVersion.V311
    V5 = ProtocolVersion.V5


class TlsCheckEnum(str, Enum):
    disabled = 'disabled'
    private = 'private'
    public = 'public'


class BrkConfigModel(BaseModel):
    type: Literal['broker']
    # auto_add_cmd: bool = False
    # auto_add_topic: str = '#'
    mqttProto: MqttProtoEnum = MqttProtoEnum.mqtt
    mqttVersion: MqttVersionEnum = MqttVersionEnum.V311
    mqttAddress: str = 'localhost'
    mqttWsHeader: Union[dict[str, str], None] = None
    mqttWsUrl: str = '/mqtt'
    mqttPort: int = 1883
    mqttUser: Union[str, None] = None
    mqttPass: Union[str, None] = None
    mqttId: bool = False
    mqttIdValue: str = 'jeedom'
    mqttLwt: bool = False
    mqttLwtTopic: str = 'jeedom/status'
    mqttLwtOnline: str = 'online'
    mqttLwtOffline: str = 'offline'
    mqttLwtQos: int = 0
    mqttLwtRetain: bool = True
    mqttInt: bool = False
    mqttIntTopic: str = 'jeedom/interact'
    mqttApi: bool = False
    mqttApiTopic: str = 'jeedom/api'
    mqttTlsCheck: TlsCheckEnum = TlsCheckEnum.public
    mqttTlsCa: str = ''
    mqttTlsClient: bool = False
    mqttTlsClientCert: str = ''
    mqttTlsClientKey: str = ''

    _val_bools: classmethod = validator(
        # "auto_add_cmd",
        "mqttId",
        "mqttLwt",
        "mqttInt",
        "mqttApi",
        "mqttTlsClient",
        allow_reuse=True,
        pre=True,
    )(
        strToBool
    )
    _val_ints: classmethod = validator(
        "mqttPort", "mqttLwtQos", allow_reuse=True, pre=True
    )(
        strToInt
    )
    _val_mqttProto: classmethod = validator(
        "mqttProto", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'mqtt')
    _val_mqttAddress: classmethod = validator(
        "mqttAddress", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'localhost')
    _val_mqttWsUrl: classmethod = validator("mqttWsUrl", allow_reuse=True, pre=True)(
        lambda v: ('/' + v.lstrip(' /')) if v != '' else '/mqtt'
    )
    _val_mqttUser: classmethod = validator("mqttUser", allow_reuse=True, pre=True)(
        lambda v: v if v is not None and v != '' else None
    )
    _val_mqttPass: classmethod = validator("mqttPass", allow_reuse=True, pre=True)(
        lambda v: v if v is not None and v != '' else None
    )
    _val_mqttIdValue: classmethod = validator(
        "mqttIdValue", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'jeedom')
    _val_mqttLwtTopic: classmethod = validator(
        "mqttLwtTopic", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'jeedom/status')
    _val_mqttLwtOnline: classmethod = validator(
        "mqttLwtOnline", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'online')
    _val_mqttLwtOffline: classmethod = validator(
        "mqttLwtOffline", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'offline')
    _val_mqttIntTopic: classmethod = validator(
        "mqttIntTopic", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'jeedom/interact')
    _val_mqttApiTopic: classmethod = validator(
        "mqttApiTopic", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'jeedom/api')
    _val_mqttTlsCheck: classmethod = validator(
        "mqttTlsCheck", allow_reuse=True, pre=True
    )(lambda v: v if v != '' else 'public')
    _val_mqttTlsClient: classmethod = validator(
        "mqttTlsClient", allow_reuse=True, pre=True
    )(strToBool)


class BrkModel(EqBaseModel):
    configuration: BrkConfigModel
