from asyncio import sleep
from contextlib import asynccontextmanager
from fastapi import Depends, FastAPI, HTTPException, Security, status
from fastapi.responses import JSONResponse
from fastapi.security import HTTPBearer
from json import load
from logging import getLogger, DEBUG
from os import kill, remove
from os.path import isfile
from platform import python_version, version, system
from sys import exit
from typing import List
from uvicorn import Config, Server

from callbacks import Callbacks
from heartbeat import Heartbeat
from routers import (
    broker,
    command,
    daemon,
    equipment,
)
from logics import BrkLogic, Logic
from settings import pid, settings
from utils import dumpLoggers, getSocket, getToken, setLevel, setupLoggers


# -----------------------------------------------------------------------------
def setup():
    # Add trace, notice and none loglevels
    setupLoggers()
    # Get loglevel from ENV
    setLevel(settings.loglevel, 'jmqtt')

    # Welcome message
    logger.debug(
        'Python v%s on %s %s',
        python_version(), system(), version()
    )
    with open('../../plugin_info/info.json') as json_file:
        logger.info(
            'Thank you for using jMQTT v%s',
            load(json_file)['pluginVersion']
        )

    # Display loggers informations if logging at least in DEBUG
    if logger.isEnabledFor(DEBUG):
        logger.debug('┌─► Loggers ◄────────────────────────────')
        for name, level in dumpLoggers().items():
            logger.debug('│ %-30s%s', name, level)
        logger.debug('└────────────────────────────────────────')

    # Check the PID file
    if isfile(settings.pidfile):
        logger.debug('PID File "%s" already exists.', settings.pidfile)
        with open(settings.pidfile, "r") as f:
            f.seek(0)
            fpid = int(f.readline())
        try:
            # Try to ping the pid
            kill(fpid, 0)
        except OSError: # PID does not run we can continue
            pass
        except Exception: # just in case
            logger.exception("Unexpected error when checking PID")
            exit(3)
        else: # PID is alive -> we die
            logger.error('This daemon already runs! Exit 0')
            exit(0)
    try:
        # Try to write PID to file
        logger.debug("Writing PID %s to %s", pid, settings.pidfile)
        with open(settings.pidfile, 'w') as f:
            f.write("%s\n" % pid)
    except Exception:
        logger.exception('Could not write PID file')
        exit(4)

# -----------------------------------------------------------------------------
async def startup():
    logger.info('jMQTTd is starting...')

    # Display daemon informations
    logger.info('┌─► Daemon ◄─────────────────────────────')
    if settings.localonly:
        logger.debug('│ Listening   : on localhost only (doc disabled)')
    else:
        logger.debug('│ Listening   : on all interfaces (doc enabled)')
    # if dynamic port, socketport is only available after setup
    logger.info('│ Socket port : %s', settings.socketport)
    logger.info('│ Log level   : %s', settings.loglevel)
    logger.info('│ Callback url: %s', settings.callback)
    logger.debug('│ PID file    : %s', settings.pidfile)
    logger.debug('│ Apikey      : %s', settings.apikey)
    logger.info('└────────────────────────────────────────')

    # Test communication channel TO Jeedom
    if await Callbacks.test():
        logger.info('Communication channel with Jeedom is available')
    else:
        logger.critical(
            'Failed to Open the communication channel '
            'to get instructions FROM Jeedom'
        )
        raise Exception('Could not communicate with Jeedom')

    await sleep(1)
    logger.info('jMQTTd is started')
    await Heartbeat.start()
    await Callbacks.daemonUp()

# -----------------------------------------------------------------------------
async def shutdown():
    logger.info('jMQTTd is stopping...')

    # TODO remove debug
    # Logic.printTree()

    # logger.debug('Running tasks:\n%s', asyncio.all_tasks())
    await Heartbeat.stop()

    # Stop all register BrkLogic
    for inst in BrkLogic.all.values():
        inst.stop()

    # Inform Jeedom that daemon is going offline
    await Callbacks.daemonDown()

    # Delete PID file
    if isfile(settings.pidfile):
        logger.debug("Removing PID file %s", settings.pidfile)
        remove(settings.pidfile)

    logger.info('jMQTTd is stopped')

# -----------------------------------------------------------------------------
# Attach startup & shutdown handlers
@asynccontextmanager
async def lifespan(app: FastAPI):
    await startup()
    yield
    await shutdown()

# -----------------------------------------------------------------------------
if __name__ == "__main__":
    logger = getLogger('jmqtt')

    setup()

    # Create application
    if settings.localonly:
        app = FastAPI(
            docs_url=None, # Disable docs (Swagger UI)
            redoc_url=None, # Disable redoc
            lifespan=lifespan,
            dependencies=[Depends(getToken)],
        )
    else:
        app = FastAPI(
            docs_url='/docs', # Enable docs (Swagger UI)
            redoc_url='/redoc', # Enable redoc
            lifespan=lifespan,
            dependencies=[Depends(getToken)],
    )

    @app.exception_handler(Exception)
    def validation_exception_handler(request, err):
        base_error_message = f"Failed to execute: {request.method}: {request.url}"
        return JSONResponse(status_code=400, content={"message": f"{base_error_message}. Detail: {err}"})


    # Attach routers
    app.include_router(daemon)
    app.include_router(broker)
    app.include_router(equipment)
    app.include_router(command)

    # Prepare uvicon Server
    uv = Server(Config(
        app,
        log_config=None
    ))

    # Get socket listening in IPv4 and IPv6
    sock = getSocket()
    if sock is None:
        logger.error('Could not get a socket, exiting!')
        exit(1)

    # Put the used port in settings
    settings.socketport = sock.getsockname()[1]

    # Start uvicorn
    uv.run(
        [sock],
    )
