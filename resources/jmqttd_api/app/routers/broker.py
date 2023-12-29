from logging import getLogger
from fastapi import APIRouter, HTTPException, status
from typing import List

from logics.broker import BrkLogic
from logics.logic import Logic
from models.broker import BrkModel
from models.messages import (
    MqttMessageModel,
    RealTimeModel,
    RealTimeStatusModel,
)


logger = getLogger('jmqtt.rest')


# -----------------------------------------------------------------------------
broker = APIRouter(
    prefix="/broker",
    tags=["eqBroker"],
)


# -----------------------------------------------------------------------------
@broker.get(
    "",
    response_model=List[BrkModel],
    response_model_exclude_defaults=True,
    summary="List all Brokers in Daemon"
)
def broker_get():
    return [id.model for id in BrkLogic.all.values()]


# -----------------------------------------------------------------------------
@broker.post("", status_code=204, summary="Create or update a Broker in Daemon")
def broker_post(broker: BrkModel):
    Logic.registerBrkModel(broker)


# -----------------------------------------------------------------------------
@broker.delete("", status_code=204, summary="Delete all Brokers in Daemon")
def broker_delete():
    for k in BrkLogic.all.copy():
        broker_delete_id(k)


# -----------------------------------------------------------------------------
# -----------------------------------------------------------------------------
@broker.get(
    "/{id}",
    response_model=BrkModel,
    response_model_exclude_defaults=True,
    summary="Get Broker properties in Daemon"
)
def broker_get_id(id: int):
    if id in BrkLogic.all:
        return BrkLogic.all[id].model  # TODO Check if should be something else
    raise HTTPException(
        status_code=status.HTTP_404_NOT_FOUND,
        detail="Broker not found"
    )


# -----------------------------------------------------------------------------
@broker.delete(
    "/{id}",
    status_code=204,
    summary="Remove Broker from Daemon"
)
def broker_delete_id(id: int):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Broker not found"
        )
    Logic.unregisterBrkId(id)


# -----------------------------------------------------------------------------
@broker.post(
    "/{id}/publish",
    status_code=204,
    summary="Send an MQTT message to an existing Broker"
)
def broker_post_id_sendmsg(id: int, data: MqttMessageModel):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Broker not found"
        )
    BrkLogic.all[id].publish(data.topic, data.payload, data.qos, data.retain)


# -----------------------------------------------------------------------------
@broker.get(
    "/{id}/restart",
    status_code=204,
    summary="Restart connection to the broker"
)
def broker_get_id_restart(id: int):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Broker not found"
        )
    BrkLogic.all[id].restart()


###################################################################
# Real Time
@broker.put(
    "/{id}/realtime/start",
    status_code=204,
    summary="Enable Broker real time mode",
    # tags=['eqBroker Real Time']
)
def broker_put_id_rt_start(id: int, option: RealTimeModel):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Broker not found"
        )
    # TODO


# -----------------------------------------------------------------------------
@broker.get(
    "/{id}/realtime/status",
    response_model_exclude_defaults=True,
    summary="Get Broker real time mode status",
    # tags=['eqBroker Real Time']
)
def broker_get_id_rt_status(id: int) -> RealTimeStatusModel:
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Broker not found"
        )
    return {"result": "success"}
    # TODO


# -----------------------------------------------------------------------------
@broker.put(
    "/{id}/realtime/stop",
    status_code=204,
    summary="Disable Broker real time mode",
    # tags=['eqBroker Real Time']
)
def broker_put_id_rt_stop(id: int):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Broker not found"
        )
    # TODO


# -----------------------------------------------------------------------------
@broker.get(
    "/{id}/realtime",
    response_model_exclude_defaults=True,
    summary="Get Broker real time messages",
    # tags=['eqBroker Real Time']
)
def broker_get_id_rt(id: int, since: int = 0) -> RealTimeStatusModel:
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Broker not found"
        )
    return {"result": "success"}
    # TODO


# -----------------------------------------------------------------------------
@broker.put(
    "/{id}/realtime/clear",
    response_model_exclude_defaults=True,
    summary="Empty Broker real time log",
    # tags=['eqBroker Real Time']
)
def broker_put_id_rt_clear(id: int):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Broker not found"
        )
    return {"result": "success"}
    # TODO
