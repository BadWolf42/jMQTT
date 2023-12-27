from typing import Union, List

from .broker import (
    BrkModel,
)
from .eq import (
    EqModel,
)
from .cmd import (
    CmdInfoModel,
    CmdActionModel,
)

# -----------------------------------------------------------------------------
# --- Union model
EqLogicModel = Union[BrkModel, EqModel]
CmdModel = Union[CmdInfoModel, CmdActionModel]
# DataModel = List[Union[EqLogicModel, CmdModel]]
DataModel = List[Union[BrkModel, EqModel, CmdInfoModel, CmdActionModel]]
