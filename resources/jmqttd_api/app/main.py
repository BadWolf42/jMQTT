from asyncio import create_task
from contextlib import asynccontextmanager
from fastapi import Depends, FastAPI, HTTPException, Request, Security, status
from fastapi.responses import JSONResponse
from fastapi.security import HTTPBearer
from logging import getLogger
from pydantic import ValidationError

from sys import exit
from uvicorn import Config, Server

from callbacks import Callbacks
from heartbeat import Heartbeat
from routers import (
    broker,
    command,
    daemon,
    equipment,
)
from settings import settings
from utils import (
    getSocket,
    setup,
    shutdown,
    startup,
)


logger = getLogger('jmqtt')


# -----------------------------------------------------------------------------
def getToken(token: str = Security(HTTPBearer())):
    if token.credentials == settings.apikey:
        return token
    raise HTTPException(
        status_code=status.HTTP_403_FORBIDDEN,
        detail="Invalid API key"
    )


# -----------------------------------------------------------------------------
# Attach startup & shutdown handlers
@asynccontextmanager
async def lifespan(app: FastAPI):
    await startup()
    await Heartbeat.start()
    create_task(Callbacks.daemonUp())
    yield
    await shutdown()


# -----------------------------------------------------------------------------
if __name__ == "__main__":
    setup()

    # Create application
    if settings.localonly:
        app = FastAPI(
            docs_url=None,  # Disable docs (Swagger UI)
            redoc_url=None,  # Disable redoc
            lifespan=lifespan,
            dependencies=[Depends(getToken)],
        )
    else:
        app = FastAPI(
            docs_url='/docs',  # Enable docs (Swagger UI)
            redoc_url='/redoc',  # Enable redoc
            lifespan=lifespan,
            dependencies=[Depends(getToken)],
        )

    @app.exception_handler(ValidationError)
    def validation_exception_handler(req: Request, err: ValidationError):
        base_error_message = f"Failed to execute: {req.method}: {req.url}"
        return JSONResponse(
            status_code=400,
            content={"message": f"{base_error_message}. Details: {err.json()}"}
        )

    @app.exception_handler(Exception)
    def exception_handler(req: Request, err: Exception):
        base_error_message = f"Failed to execute: {req.method}: {req.url}"
        return JSONResponse(
            status_code=400,
            content={"message": f"{base_error_message}. Details: {err}"}
        )

    # Attach routers
    app.include_router(daemon)
    app.include_router(broker)
    app.include_router(equipment)
    app.include_router(command)

    # Prepare uvicon Server
    uv = Server(
        Config(
            app,
            log_config=None
        )
    )

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
