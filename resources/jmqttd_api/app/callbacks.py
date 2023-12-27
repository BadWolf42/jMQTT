from asyncio import sleep

from aiohttp import ClientSession
from json import dumps
from logging import getLogger
from pydantic import BaseModel
from typing import Union, List

from heartbeat import Heartbeat
from models import EqModel, CmdModel
from settings import pid, settings


logger = getLogger('jmqtt.callbacks')


###############################################################################
# --- Messages TO jeedom models -----------------------------------------------

# {"id":int, "value":[bool/int/str]}
class JmqttdValue(BaseModel):
    id: int
    value: Union[bool, int, str]

# class JmqttdValueList(RootModel):
#     root: List[JmqttdValue]


class Callbacks:
    @classmethod
    async def __send(cls, action: str, data: dict = {}):
        async with ClientSession() as session:
            async with session.post(
                settings.callback,
                # TODO Remove PID from headers
                headers={
                    'Authorization': 'Bearer ' + settings.apikey,
                    'PID': pid
                },
                params={'a': action},
                json=data
            ) as resp:
                logger.debug(
                    '%s: Status=%i, Body="%s"',
                    action, resp.status, await resp.text()
                )
                await resp.text()
                if resp.status in [200, 204]:
                    return True
                logger.error('COULD NOT send TO Jeedom: %s', dumps(data))
                return False
        # TODO Handle Exceptions on "async with", ex:
        # aiohttp.client_exceptions.ClientConnectorError:
        #     Cannot connect to host x [No route to host]

    @classmethod
    async def test(cls):
        return Heartbeat.onSend(await cls.__send('test'))

    @classmethod
    async def daemonUp(cls):
        # Let port some time to open in main task
        await sleep(1)
        return Heartbeat.onSend(
            await cls.__send('daemonUp', {'port': settings.socketport})
        )

    @classmethod
    async def daemonHB(cls):
        return Heartbeat.onHB(await cls.__send('daemonHB'))

    @classmethod
    async def daemonDown(cls):
        return Heartbeat.onSend(await cls.__send('daemonDown'))

    @classmethod
    async def brokerUp(cls, id: int):
        return Heartbeat.onSend(await cls.__send('brokerUp', {'id': id}))

    @classmethod
    async def brokerDown(cls, id: int):
        return Heartbeat.onSend(await cls.__send('brokerDown', {'id': id}))

    @classmethod
    async def message(
        cls,
        id: int,
        topic: str,
        payload: str,
        qos: int = 1,
        retain: bool = False
    ):
        return Heartbeat.onSend(
            await cls.__send(
                'message',
                {
                    'id': id,
                    'topic': topic,
                    'payload': payload,
                    'qos': qos,
                    'retain': retain
                }
            )
        )

    @classmethod
    async def values(cls, values: List[JmqttdValue]):
        return Heartbeat.onSend(
            await cls.__send('values', [val.model_dump() for val in values])
        )
        # data = []
        # for val in values:
        #     if isinstance(val, JmqttdValue):
        #         data.append(val.model_dump())
        # return await cls.__send('values', data))

    @classmethod
    async def saveEq(cls, eqLogic: EqModel):
        return Heartbeat.onSend(await cls.__send('saveEq', eqLogic.model_dump()))

    @classmethod
    async def saveCmd(cls, cmd: CmdModel):
        return Heartbeat.onSend(await cls.__send('saveCmd', cmd.model_dump()))


# TODO TESTS:

# import asyncio, callbacks, models
# c = callbacks.callbacks

# asyncio.run(c.values([callbacks.JmqttdValue(id=666, value='jMQTT'), callbacks.JmqttdValue(id=11, value=True), callbacks.JmqttdValue(id=0xcafe, value='cafe')]))
# res check bool

# asyncio.run(c.saveEq(models.EqModel(id=42, eqType_name='jMQTT', isEnable=True, configuration=models.EqConfigModel(type='eqpt', eqLogic=4))))
# res KO: {"type":"saveEq","eqLogic":{"id":42,"name":null,"logicalId":null,"eqType_name":"jMQTT","isEnable":true,"configuration":{"type":"eqpt","eqLogic":4,"icone":"","auto_add_cmd":false,"auto_add_topic":"","Qos":1,"availability_cmd":0,"battery_cmd":0}}}

# asyncio.run(c.saveCmd(models.CmdInfoModel(id=42, eqType='jMQTT', isEnable=True, eqLogic_id=4, type='info', subType='binary', configuration=models.CmdInfoConfigModel(topic='my/topic'))))
# res ??: {"type":"saveCmd","cmd":{"id":42,"logicalId":null,"eqType":"jMQTT","name":null,"eqLogic_id":4,"type":"info","subType":"binary","configuration":{"topic":"my\/topic","jsonPath":""}}}
