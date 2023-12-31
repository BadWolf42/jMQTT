from logging import getLogger
from fastapi import APIRouter, HTTPException, status
from typing import List

from logics.cmd import CmdLogic
from logics.logic import Logic
from models.unions import CmdModel

logger = getLogger('jmqtt.rest')
command = APIRouter(
    prefix="/command",
    tags=["Command"],
)


# -----------------------------------------------------------------------------
# POST /command => Create command
@command.post("", status_code=204, summary="Create or update a Command in Daemon")
async def command_post(cmd: CmdModel):
    await Logic.registerCmdModel(cmd)


# -----------------------------------------------------------------------------
# GET /command => list command
@command.get("", response_model_exclude_defaults=True)
async def command_get() -> List[CmdModel]:
    return [cmd.model for cmd in CmdLogic.all.values()]


# -----------------------------------------------------------------------------
# GET /command/{Id} => Get command properties
@command.get("/{id}", response_model_exclude_defaults=True)
async def command_get_id(id: int) -> CmdModel:
    if id not in CmdLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Cmd not found"
        )
    return CmdLogic.all[id].model


# -----------------------------------------------------------------------------
# DELETE /command/{Id} => Remove command
@command.delete("/{id}", status_code=204)
async def command_delete_id(id: int):
    if id not in CmdLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Cmd not found"
        )
    await Logic.unregisterCmdId(id)


# -----------------------------------------------------------------------------
# POST /callback => Send an event to Jeedom
# @command.post(
#     "/callback",
#     summary="Send an MQTT message to an existing Broker",
#     tags=['Callback']
# )
# async def callback_event_to_jeedom(event: JmqttdEvent):
#     return {"result": "success"}
