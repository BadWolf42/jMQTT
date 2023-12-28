from typing import Union, List

from models.broker import BrkModel
from models.cmd import CmdInfoModel, CmdActionModel
from models.eq import EqModel


# -----------------------------------------------------------------------------
# --- Union model
# EqLogicModel = Union[BrkModel, EqModel]
CmdModel = Union[CmdInfoModel, CmdActionModel]
# DataModel = List[Union[EqLogicModel, CmdModel]]
DataModel = List[Union[BrkModel, EqModel, CmdInfoModel, CmdActionModel]]
