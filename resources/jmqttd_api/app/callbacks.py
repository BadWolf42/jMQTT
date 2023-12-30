from asyncio import sleep

from aiohttp import ClientSession
from json import dumps
from logging import getLogger
from pydantic import BaseModel
from time import time
from typing import Union, List

from models.eq import EqModel
from models.unions import CmdModel
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
    _retry_snd: int = 0  # number of send retries
    _last_snd: int = time()  # time of the last snd msg
    _last_hb: int = time()  # time of the last snd HB msg

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
                logger.trace(
                    '%s: Status=%i, Body="%s"',
                    action, resp.status, await resp.text()
                )
                await resp.text()
                if resp.status in [200, 204]:
                    cls._last_snd = time()
                    cls._retry_snd = 0
                    return True
                logger.error('COULD NOT send TO Jeedom: %s', dumps(data))
                cls._retry_snd += 1
                return False
        # TODO Handle Exceptions on "async with", ex:
        # aiohttp.client_exceptions.ClientConnectorError:
        #     Cannot connect to host x [No route to host]

    @classmethod
    async def test(cls):
        return await cls.__send('test')

    @classmethod
    async def daemonUp(cls):
        # Let port some time to open in main task
        await sleep(0.5)
        return await cls.__send('daemonUp', {'port': settings.socketport})

    @classmethod
    async def daemonHB(cls):
        cls._last_hb = time()
        return await cls.__send('daemonHB')

    @classmethod
    async def daemonDown(cls):
        return await cls.__send('daemonDown')

    @classmethod
    async def brokerUp(cls, id: int):
        return await cls.__send('brokerUp', {'id': id})

    @classmethod
    async def brokerDown(cls, id: int):
        return await cls.__send('brokerDown', {'id': id})

    @classmethod
    async def message(
        cls,
        id: int,
        topic: str,
        payload: str,
        qos: int = 1,
        retain: bool = False
    ):
        return await cls.__send(
            'message',
            {
                'id': id,
                'topic': topic,
                'payload': payload,
                'qos': qos,
                'retain': retain
            }
        )

    @classmethod
    async def values(cls, values: List[JmqttdValue]):
        return await cls.__send('values', [val.model_dump() for val in values])
        # data = []
        # for val in values:
        #     if isinstance(val, JmqttdValue):
        #         data.append(val.model_dump())
        # return await cls.__send('values', data))

    @classmethod
    async def saveEq(cls, eqLogic: EqModel):
        return await cls.__send('saveEq', eqLogic.model_dump())

    @classmethod
    async def saveCmd(cls, cmd: CmdModel):
        return await cls.__send('saveCmd', cmd.model_dump())
