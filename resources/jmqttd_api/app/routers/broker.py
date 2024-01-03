from logging import getLogger
from fastapi import APIRouter, HTTPException, status
from typing import List

from logics.broker import BrkLogic
from models.broker import BrkModel
from models.messages import (
    MqttMessageModel,
    RealTimeModel,
    RealTimeStatusModel,
)
from visitors.print import PrintVisitor
from visitors.register import RegisteringLogicVisitor
from visitors.unregister import UnregisteringLogicVisitor
from visitors.update import UpdatingLogicVisitor


logger = getLogger('jmqtt.rest')


# -----------------------------------------------------------------------------
broker = APIRouter(
    prefix="/broker",
    tags=["eqBroker"],
)


# -----------------------------------------------------------------------------
@broker.get(
    "",
    response_model_exclude_defaults=True,
    summary="List all Brokers in Daemon",
)
async def broker_get() -> List[BrkModel]:
    return [brk.model for brk in BrkLogic.all.values()]


# -----------------------------------------------------------------------------
@broker.post("", status_code=204, summary="Create or update a Broker in Daemon")
async def broker_post(broker: BrkModel):
    if broker.id in BrkLogic.all:
        # If Logic exist in register, then update it
        await UpdatingLogicVisitor.update(BrkLogic.all[broker.id], broker)
    else:
        # Else register it
        await RegisteringLogicVisitor.register(BrkLogic(broker))


# -----------------------------------------------------------------------------
@broker.delete("", status_code=204, summary="Delete all Brokers in Daemon")
async def broker_delete():
    for k in BrkLogic.all.copy():
        await broker_delete_id(k)


# -----------------------------------------------------------------------------
# -----------------------------------------------------------------------------
@broker.get(
    "/{id}",
    response_model_exclude_defaults=True,
    summary="Get Broker properties in Daemon",
)
async def broker_get_id(id: int) -> BrkModel:
    if id in BrkLogic.all:
        return BrkLogic.all[id].model  # TODO Check if should be something else
    raise HTTPException(
        status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
    )


# -----------------------------------------------------------------------------
@broker.delete(
    "/{id}",
    status_code=204,
    summary="Remove Broker from Daemon",
)
async def broker_delete_id(id: int):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )
    await UnregisteringLogicVisitor.unregister(BrkLogic.all[id])


# -----------------------------------------------------------------------------
@broker.post(
    "/{id}/publish",
    status_code=204,
    summary="Send an MQTT message to an existing Broker",
)
async def broker_post_id_sendmsg(id: int, data: MqttMessageModel):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )
    await BrkLogic.all[id].publish(data.topic, data.payload, data.qos, data.retain)


# -----------------------------------------------------------------------------
@broker.get(
    "/{id}/restart",
    status_code=204,
    summary="Restart connection to the broker",
)
async def broker_get_id_restart(id: int):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )
    await BrkLogic.all[id].restart()


###################################################################
# Real Time
@broker.put(
    "/{id}/realtime/start",
    status_code=204,
    summary="Enable Broker real time mode",
    # tags=['eqBroker Real Time']
)
async def broker_put_id_rt_start(id: int, option: RealTimeModel) -> None:
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )
    if not await BrkLogic.all[id].realTimeStart(option):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST, detail="Broker not enabled"
        )


# -----------------------------------------------------------------------------
@broker.get(
    "/{id}/realtime/status",
    # response_model_exclude_defaults=True,
    summary="Get Broker real time mode status",
    # tags=['eqBroker Real Time']
)
async def broker_get_id_rt_status(id: int) -> RealTimeStatusModel:
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )
    return await BrkLogic.all[id].realTimeStatus()


# -----------------------------------------------------------------------------
@broker.get(
    "/{id}/realtime/stop",
    status_code=204,
    summary="Disable Broker real time mode",
    # tags=['eqBroker Real Time']
)
async def broker_get_id_rt_stop(id: int) -> None:
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )
    await BrkLogic.all[id].realTimeStop()


# -----------------------------------------------------------------------------
@broker.get(
    "/{id}/realtime",
    response_model_exclude_defaults=True,
    summary="Get Broker real time messages",
    # tags=['eqBroker Real Time']
)
async def broker_get_id_rt(id: int, since: int = 0) -> List[MqttMessageModel]:
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )
    return await BrkLogic.all[id].realTimeGet(since)


# -----------------------------------------------------------------------------
@broker.get(
    "/{id}/realtime/clear",
    status_code=204,
    summary="Empty Broker real time log",
    # tags=['eqBroker Real Time']
)
async def broker_get_id_rt_clear(id: int) -> None:
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )
    await BrkLogic.all[id].realTimeClear()


# -----------------------------------------------------------------------------
@broker.get("/{id}/debug/tree", status_code=204, summary="Log this brk/eq/cmd tree")
async def broker_get_debug_tree(id: int):
    if id not in BrkLogic.all:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND, detail="Broker not found"
        )

    await PrintVisitor.print(BrkLogic.all[id])
