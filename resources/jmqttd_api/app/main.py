from asyncio import create_task
from contextlib import asynccontextmanager
from fastapi import Depends, FastAPI, HTTPException, Request, Security, status
from fastapi.exception_handlers import (
    http_exception_handler,
    request_validation_exception_handler,
)
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from fastapi.security import HTTPBearer
from logging import getLogger
from pydantic import ValidationError
from starlette.exceptions import HTTPException as StarletteHTTPException
from sys import exit
from uvicorn import Config, Server

from comm.callbacks import Callbacks
from comm.healthcheck import Healthcheck
from routers.broker import broker
from routers.command import command
from routers.daemon import daemon
from routers.equipment import equipment
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
    raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Invalid API key")


# -----------------------------------------------------------------------------
# Attach startup & shutdown handlers
@asynccontextmanager
async def lifespan(app: FastAPI):
    await startup()
    await Healthcheck.start()
    task_up = create_task(Callbacks.daemonUp())
    yield
    if not task_up.done():
        logger.error('DaemonUp did not finish!')
    await shutdown()


# -----------------------------------------------------------------------------
def main():
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

    @app.exception_handler(StarletteHTTPException)
    async def http_except_h(req: Request, exc: StarletteHTTPException):
        base_error_message = f'{req.method} {req.url} raised: {repr(exc)}'
        getLogger('jmqtt.exception').exception(base_error_message, exc_info=False)
        return await http_exception_handler(req, exc)

    @app.exception_handler(RequestValidationError)
    async def req_val_except_h(req: Request, exc: RequestValidationError):
        base_error_message = f'{req.method} {req.url} raised: {repr(exc)}'
        getLogger('jmqtt.exception').exception(base_error_message, exc_info=False)
        return await request_validation_exception_handler(req, exc)

    @app.exception_handler(ValidationError)
    async def val_except_h(req: Request, exc: ValidationError):
        base_error_message = f'{req.method} {req.url} raised: {repr(exc)}'
        getLogger('jmqtt.exception').exception(base_error_message, exc_info=False)
        return JSONResponse(
            status_code=400,
            content={"message": f"{base_error_message}. Details: {exc}"},
        )

    @app.exception_handler(Exception)
    async def except_h(req: Request, exc: Exception):
        base_error_message = f'{req.method} {req.url} raised: {repr(exc)}'
        getLogger('jmqtt.exception').exception(base_error_message, exc_info=False)
        return JSONResponse(
            status_code=400,
            content={"message": f"{base_error_message}. Details: {exc}"},
        )

    # Attach routers
    app.include_router(daemon)
    app.include_router(broker)
    app.include_router(equipment)
    app.include_router(command)

    # Prepare uvicon Server
    uv = Server(Config(app, log_config=None))

    # Get socket listening in IPv4 and IPv6
    sock = getSocket()
    if sock is None:
        logger.error('Could not get a socket, exiting!')
        exit(2)

    # Put the used port in settings
    settings.socketport = sock.getsockname()[1]

    # Start uvicorn
    uv.run(
        [sock],
    )


if __name__ == "__main__":
    try:
        main()
    except Exception:
        # just in case
        logger.exception('Unhandled exception in main')
        exit(1)
