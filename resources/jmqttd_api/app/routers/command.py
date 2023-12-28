from logging import getLogger
from fastapi import APIRouter, HTTPException, status
from typing import List

from models import CmdModel
from logics import Logic, CmdLogic


logger = getLogger('jmqtt.rest')
command = APIRouter(
    prefix="/command",
    tags=["Command"],
)


# -----------------------------------------------------------------------------
# POST /command => Create command
@command.post("", status_code=204)
def command_post(cmd: CmdModel):
    if id in CmdLogic.all:
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="Cmd exists"
        )
    Logic.registerCmdModel(cmd)


# -----------------------------------------------------------------------------
# PUT /command => modify command
@command.put("", status_code=204)
def command_put(cmd: CmdModel):
    if id not in CmdLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Cmd not found"
        )
    Logic.registerCmdModel(cmd)


# -----------------------------------------------------------------------------
# GET /command => list command
@command.get("", response_model_exclude_defaults=True)
def command_get() -> List[CmdModel]:
    return [cmd.model for cmd in CmdLogic.all.values()]

# -----------------------------------------------------------------------------
# GET /command/{Id} => Get command properties
@command.get("/{id}", response_model_exclude_defaults=True)
def command_get_id(id: int) -> CmdModel:
    if id not in CmdLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Cmd not found"
        )
    return CmdLogic.all[id].model


# -----------------------------------------------------------------------------
# DELETE /command/{Id} => Remove command
@command.delete("/{id}", status_code=204)
def command_delete_id(id: int):
    if id not in CmdLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Cmd not found"
        )
    Logic.unregisterCmdId(id)


# -----------------------------------------------------------------------------
# POST /callback => Send an event to Jeedom
# @command.post(
#     "/callback",
#     summary="Send an MQTT message to an existing Broker",
#     tags=['Callback']
# )
# def callback_event_to_jeedom(event: JmqttdEvent):
    # return {"result": "success"}
