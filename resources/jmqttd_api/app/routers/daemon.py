from logging import getLevelName, getLogger
from os import getpid, kill
from signal import SIGTERM

from fastapi import APIRouter

# from ..jmqttDaemon import JmqttDaemon
from heartbeat import Heartbeat
from models import (
    BrkModel,
    DataModel,
    EqModel,
    LogLevelModel,
)
from logics import (
    BrkLogic,
    EqLogic,
    CmdLogic,
)
from .broker import (
    broker_post,
    broker_put,
)
from .equipment import (
    equipment_post,
    equipment_put,
)
from .command import (
    command_post,
    command_put,
)
from settings import settings, rootloglevel
from utils import dumpLoggers, setLevel


logger = getLogger('jmqtt.rest')


# -----------------------------------------------------------------------------
daemon = APIRouter(
    prefix="/daemon",
    tags=["Daemon"],
)


# -----------------------------------------------------------------------------
@daemon.post(
    "",
    status_code=204,
    summary="Initialize Brokers, Equipments and Commands in the Daemon"
)
def daemon_post(data: DataModel):
    for item in data:
        if isinstance(item, BrkModel):
            if item.id in BrkLogic.all:
                broker_put(item)
            else:
                broker_post(item)
        elif isinstance(item, EqModel):
            if item.id in EqLogic.all:
                equipment_put(item)
            else:
                equipment_post(item)
        else:
            if item.id in CmdLogic.all:
                command_put(item)
            else:
                command_post(item)


# -----------------------------------------------------------------------------
@daemon.get(
    "",
    response_model_exclude_defaults=True,
    summary="Return all Brokers, Equipments and Commands in the Daemon"
)
def daemon_get():
    # JmqttDaemon.XXX() ### TODO SERIALIZE DAEMON STATE
    return []


# -----------------------------------------------------------------------------
@daemon.delete(
    "",
    status_code=204,
    summary="Clear all Brokers, Equipments and Commands in the Daemon"
)
def daemon_delete():
    pass


# -----------------------------------------------------------------------------
@daemon.put(
    "/hb",
    status_code=204,
    summary="Receive heatbeat from Jeedom"
)
def daemon_put_hb():
    Heartbeat.onReceive()


# -----------------------------------------------------------------------------
@daemon.put("/api", status_code=204, summary="Modify Daemon apikey")
def daemon_put_api(option: str):
    settings.apikey = option


# -----------------------------------------------------------------------------
@daemon.get(
    "/loglevel",
    response_model_exclude_defaults=True,
    summary="Get a loglevel"
)
def daemon_get_loglevel(name: str = '') -> str:
    return getLevelName(getLogger(name).getEffectiveLevel())


# -----------------------------------------------------------------------------
@daemon.put("/loglevel", status_code=204, summary="Set a loglevel")
def daemon_put_loglevel(level: LogLevelModel, name: str = ''):
    newlevel = setLevel(level, name)
    if name == '':
        rootloglevel = level
        logger.notice(
            'Log level of root logger set to: %s',
            getLevelName(newlevel)
        )
    elif name == 'jmqtt':
        settings.loglevel = level
        logger.notice(
            'Log level of logger jmqtt set to: %s',
            getLevelName(newlevel)
        )
    else:
        logger.notice(
            'Log level of logger %s set to: %s',
            name, getLevelName(newlevel)
        )


# -----------------------------------------------------------------------------
@daemon.get(
    "/loglevels",
    response_model_exclude_defaults=True,
    summary="Get all loglevel"
)
def daemon_get_loglevels() -> dict:
    return dumpLoggers()


# -----------------------------------------------------------------------------
@daemon.put("/stop", status_code=204, summary="Stop the daemon")
def daemon_put_stop():
    kill(getpid(), SIGTERM)
