from json import dumps, JSONDecodeError, loads
from logging import getLevelName, getLogger
from os import getpid, kill
from signal import SIGTERM
from time import time
from fastapi import APIRouter

from comm.callbacks import Callbacks
from comm.healthcheck import Healthcheck
from converters.jsonpath import compiledJsonPath
from logics.broker import BrkLogic
from models.broker import BrkModel
from models.daemon import TestRequest, TestResult
from models.eq import EqModel
from models.messages import LogLevelEnum
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
    # TODO daemon_get? To get daemon state and stats?
    pass


# -----------------------------------------------------------------------------
@daemon.delete(
    "",
    status_code=204,
    summary="Clear all Brokers, Equipments and Commands in Daemon",
    include_in_schema=False,
)
async def daemon_delete():
    # TODO daemon_delete?
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
async def daemon_put_loglevel(level: LogLevelEnum, name: str = ''):
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
@daemon.post(
    "/test/jsonpath",
    summary="Evaluate a payload against a jsonPath",
)
async def daemon_test_jsonpath(d: TestRequest) -> TestResult:
    logger.debug(f'Test jsonPath: {d.payload=} {d.filter=}')
    try:
        if d.filter.strip() == '':
            logger.info('payload="%s", jsonPath="%s" => NO path', d.payload, d.filter)
            return TestResult(success=True, value=d.payload)
        expr = compiledJsonPath(d.filter)
        try:
            json = loads(d.payload)
        except JSONDecodeError as e:
            res = f'invalid json payload="{d.payload}": {e}'
            return TestResult(value=res)
        found = expr.find(json)
        if len(found) == 0:
            logger.info('payload="%s", jsonPath="%s" => NO match', d.payload, d.filter)
            return TestResult(success=True, value='no match')
        res = found[0].value if len(found) == 1 else [match.value for match in found]
        res = dumps(res) if type(res) not in [bool, int, float, str] else str(res)
        logger.info('payload="%s", jsonPath="%s" => "%s"', d.payload, d.filter, res)
        return TestResult(success=True, match=True, value=res)
    except Exception as e:
        res = f'{e}'
        logger.info('payload="%s", jsonPath="%s" => %s', d.payload, d.filter, res)
        return TestResult(value=res)


# -----------------------------------------------------------------------------
@daemon.get("/debug/tree", status_code=204, summary="Log the global brk/eq/cmd tree")
async def daemon_get_debug_tree():
    for b in BrkLogic.all.values():
        await PrintVisitor(b).print()
