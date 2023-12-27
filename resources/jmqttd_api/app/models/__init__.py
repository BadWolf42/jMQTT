from typing import Union, List

from .messages import (
    LogLevelModel,
    MqttMessageModel,
    # MqttSubscTopic,
    # MqttTopic,
    RealTimeModel,
    RealTimeStatusModel,
)
from .bases import (
    EqBaseModel,
    CmdBaseModel,
)
from .broker import (
    MqttProtoModel,
    TlsCheckModel,
    BrkConfigModel,
    BrkModel,
)
from .eq import (
    EqConfigModel,
    EqModel,
)
from .cmd import (
    CmdInfoSubTypeModel,
    CmdInfoConfigModel,
    CmdInfoModel,
    CmdActionSubTypeModel,
    CmdActionConfigModel,
    CmdActionModel,
)

# -----------------------------------------------------------------------------
# --- Union model
EqLogicModel = Union[BrkModel, EqModel]
CmdModel = Union[CmdInfoModel, CmdActionModel]
# DataModel = List[Union[EqLogicModel, CmdModel]]
DataModel = List[Union[BrkModel, EqModel, CmdInfoModel, CmdActionModel]]
