from logging import getLevelName, getLogger
from os import getpid, kill
from signal import SIGTERM
from time import time
from fastapi import APIRouter

# from ..jmqttDaemon import JmqttDaemon
from callbacks import Callbacks
from healthcheck import Healthcheck
from logics.broker import BrkLogic
from models.broker import BrkModel
from models.eq import EqModel
from models.messages import LogLevelModel
from models.unions import DataModel
from routers.broker import broker_post
from routers.command import command_post
from routers.equipment import equipment_post
from settings import settings
from utils import dumpLoggers, setLevel
from visitors.print import PrintVisitor


logger = getLogger('jmqtt.rest')


# -----------------------------------------------------------------------------
daemon = APIRouter(
    prefix="/daemon",
    tags=["Daemon"],
)


# -----------------------------------------------------------------------------
@daemon.post(
    "", status_code=204, summary="Initialize Brokers, Equipments and Commands in Daemon"
)
async def daemon_post(data: DataModel):
    for item in data:
        if isinstance(item, BrkModel):
            await broker_post(item)
        elif isinstance(item, EqModel):
            await equipment_post(item)
        else:
            await command_post(item)


# -----------------------------------------------------------------------------
@daemon.get(
    "",
    response_model_exclude_defaults=True,
    include_in_schema=False,
    summary="Return all Brokers, Equipments and Commands in Daemon",
)
async def daemon_get():
    # JmqttDaemon.XXX() ### TODO SERIALIZE DAEMON STATE
    pass


# -----------------------------------------------------------------------------
@daemon.delete(
    "",
    status_code=204,
    summary="Clear all Brokers, Equipments and Commands in Daemon",
    include_in_schema=False,
)
async def daemon_delete():
    pass


# -----------------------------------------------------------------------------
@daemon.put("/hb", status_code=204, summary="Receive heatbeat from Jeedom")
async def daemon_put_hb():
    logger.debug(
        "Heartbeat FROM Jeedom (last msg from/to Jeedom %ds/%ds ago)",
        time() - Healthcheck._lastRcv,
        time() - Callbacks._lastSnd,
    )
    await Healthcheck.onReceive()


# -----------------------------------------------------------------------------
@daemon.put("/api", status_code=204, summary="Modify Daemon apikey")
async def daemon_put_api(newapikey: str):
    settings.apikey = newapikey


# -----------------------------------------------------------------------------
@daemon.get("/loglevel", response_model_exclude_defaults=True, summary="Get a loglevel")
async def daemon_get_loglevel(name: str = '') -> str:
    return getLevelName(getLogger(name).getEffectiveLevel())


# -----------------------------------------------------------------------------
@daemon.put("/loglevel", status_code=204, summary="Set a loglevel")
async def daemon_put_loglevel(level: LogLevelModel, name: str = ''):
    newlevel = setLevel(level, name)
    if name == '':
        settings.rootloglevel = level
        logger.notice('Log level of root logger set to: %s', getLevelName(newlevel))
    elif name == 'jmqtt':
        settings.loglevel = level
        logger.notice('Log level of logger jmqtt set to: %s', getLevelName(newlevel))
    else:
        logger.notice('Log level of logger %s set to: %s', name, getLevelName(newlevel))


# -----------------------------------------------------------------------------
@daemon.get(
    "/loglevels", response_model_exclude_defaults=True, summary="Get all loglevel"
)
async def daemon_get_loglevels() -> dict:
    return dumpLoggers()


# -----------------------------------------------------------------------------
@daemon.put("/stop", status_code=204, summary="Stop the daemon")
async def daemon_put_stop():
    kill(getpid(), SIGTERM)


# -----------------------------------------------------------------------------
@daemon.get("/debug/tree", status_code=204, summary="Log the global brk/eq/cmd tree")
async def daemon_get_debug_tree():
    for b in BrkLogic.all.values():
        await PrintVisitor(b).print()
