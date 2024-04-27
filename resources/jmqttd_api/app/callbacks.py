from aiohttp import ClientSession
from asyncio import CancelledError, create_task, sleep, Task
from collections import deque
from json import dumps
from logging import getLogger
from pydantic import BaseModel
from time import time
from typing import List, Union

from models.eq import EqModel
from models.unions import CmdModel
from settings import pid, settings


logger = getLogger('jmqtt.callbacks')


###############################################################################
# --- Messages TO jeedom models -----------------------------------------------
class CmdValue(BaseModel):
    id: int
    value: Union[bool, int, float, str]


class CmdTimedValue(CmdValue):
    ts: float


class Callbacks:
    _retrySnd: int = 0  # number of send retries
    _lastSnd: int = time()  # time of the last snd msg
    _lastHb: int = time()  # time of the last snd HB msg
    _changesQueue: deque[CmdTimedValue] = deque()  # queue of cmd id with changes
    _changesTask: Task = None  # task handling changes sent to Jeedom

    @classmethod
    async def __send(cls, action: str, data: dict = {}):
        async with ClientSession() as session:
            async with session.post(
                settings.callback,
                # TODO Remove PID from headers
                headers={'Authorization': 'Bearer ' + settings.apikey, 'PID': pid},
                params={'a': action},
                json=data,
            ) as resp:
                logger.trace(
                    '%s: Status=%i, Body="%s"', action, resp.status, await resp.text()
                )
                await resp.text()
                if resp.status in [200, 204]:
                    cls._lastSnd = time()
                    cls._retrySnd = 0
                    return True
                logger.error('COULD NOT send TO Jeedom: %s', dumps(data))
                cls._retrySnd += 1
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
        # Send daemonUp signal to Jeedom
        dUp = await cls.__send('daemonUp', {'port': settings.socketport})
        # Start sendChangesTask task
        if cls._changesTask is not None:
            if not cls._changesTask.done():
                logger.debug('Send Changes task already started')
                return
        cls._changesTask = create_task(cls.__changesTask())
        return dUp

    @classmethod
    async def daemonHB(cls):
        cls._lastHb = time()
        return await cls.__send('daemonHB')

    @classmethod
    async def daemonDown(cls):
        # Stop sendChanges task here
        if cls._changesTask is not None:
            if not cls._changesTask.done():
                cls._changesTask.cancel()
                try:
                    await cls._changesTask
                except CancelledError:
                    pass
        cls._changesTask = None
        # Send daemonDown signal to Jeedom
        return await cls.__send('daemonDown')

    @classmethod
    async def brokerUp(cls, id: int):
        return await cls.__send('brokerUp', {'id': id})

    @classmethod
    async def brokerDown(cls, id: int):
        return await cls.__send('brokerDown', {'id': id})

    @classmethod
    async def __changesTask(cls):
        logger.debug('Send Changes task started')
        while True:
            try:
                while True:
                    logger.debug('Changes queue: %r', cls._changesQueue)
                    await sleep(10)  # TODO Remove this & Implement send mechanism to Jeedom
            except CancelledError:
                logger.debug('Send Changes task canceled')
                raise
            except Exception:
                logger.exception('Send Changes task died unexpectedly...')

    @classmethod
    async def change(cls, cmdId: int, value: str, ts: float) -> None:
        cls._changesQueue.append(CmdTimedValue(id=cmdId, ts=ts, value=value))

    @classmethod
    async def values(cls, values: List[CmdValue]):
        return await cls.__send('values', [val.model_dump() for val in values])
        # data = []
        # for val in values:
        #     if isinstance(val, CmdValue):
        #         data.append(val.model_dump())
        # return await cls.__send('values', data))

    @classmethod
    async def interact(cls, id: int, query: str, advanced: bool = False):
        return await cls.__send(
            'interact', {'id': id, 'query': query, 'advanced': advanced}
        )

    @classmethod
    async def jeedomApi(cls, id: int, query: str = ''):
        return await cls.__send('jeedomApi', {'id': id, 'query': query})

    @classmethod
    async def saveEq(cls, eqLogic: EqModel):
        return await cls.__send('saveEq', eqLogic.model_dump())

    @classmethod
    async def saveCmd(cls, cmd: CmdModel):
        return await cls.__send('saveCmd', cmd.model_dump())
