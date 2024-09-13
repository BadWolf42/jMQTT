from aiohttp import ClientSession, ClientError
from asyncio import CancelledError, create_task, sleep, Task
from collections import deque
from json import dumps
from logging import getLogger
from time import time
from typing import Dict, List, Tuple, Union

from models.cmd import CmdValue, CmdTimedValue
from models.eq import EqModel
from models.unions import CmdModel
from settings import settings


logger = getLogger('jmqtt.callbacks')


class Callbacks:
    _retrySnd: int = 0  # number of send retries
    _lastSnd: int = time()  # time of the last snd msg
    _lastHb: int = time()  # time of the last snd HB msg
    _changesQueue: deque[CmdTimedValue] = deque()  # queue of cmd id with changes
    _changesTask: Task = None  # task handling changes sent to Jeedom

    @classmethod
    async def __send(cls, action: str, data: dict = {}):
        try:
            async with ClientSession() as session:
                async with session.post(
                    settings.callback,
                    headers={'Authorization': 'Bearer ' + settings.apikey},
                    params={'a': action},
                    json=data,
                    timeout=10,
                ) as resp:
                    text = await resp.text()
                    status = resp.status
                    logger.trace('%s: Status=%i, Body="%s"', action, status, text)
                    if status == 200:
                        cls._lastSnd = time()
                        cls._retrySnd = 0
                        return True
        except ClientError:
            pass  # TODO Should this method try to reconnect?
        logger.error('COULD NOT send TO Jeedom: %s', dumps(data))
        cls._retrySnd += 1
        return False

    @classmethod
    async def test(cls):
        return await cls.__send('test')

    @classmethod
    async def daemonUp(cls):
        # Let port some time to open in main task
        await sleep(0.5)
        # Send daemonUp signal to Jeedom
        await cls.__send('daemonUp', {'port': settings.socketport})
        # Start sendChangesTask task
        if cls._changesTask is not None and not cls._changesTask.done():
            logger.debug('Send Changes task already started')
        else:
            cls._changesTask = create_task(cls.__changesTask())

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
    async def __changesSend(cls):
        toSend: Dict[int, List[Tuple[int, Union[bool, int, float, str]]]] = {}
        # Prepare to send a list of events (<100)
        while True:
            toSend = {}
            nbVals = 0
            # Wait for new message in queue, then unload messages from queue
            while nbVals < 100:
                try:
                    val = cls._changesQueue.popleft()
                except IndexError:
                    if nbVals == 0:  # Queue is empty at start, then sleep and retry
                        await sleep(0.1)
                        continue
                    else:  # Queue is now empty, then send the values
                        break
                payload = tuple([int(val.ts), val.value])
                if val.id not in toSend:
                    toSend[val.id] = list()
                    toSend[val.id].append(payload)
                    nbVals += 1
                # TODO Check if this fix is still needed (bug in aiomqtt?)
                #  TO FIX duplicated messages when 2 retained subs overlap
                elif payload not in toSend[val.id]:
                    toSend[val.id].append(payload)
                    nbVals += 1
                else:
                    logger.debug('Deduplication on %i of %r', val.id, payload)
            # Send messages to Jeedom (blocking)
            logger.debug('Sending %i changes: %r', nbVals, toSend)
            await cls.__send('values', toSend)

    @classmethod
    async def __changesTask(cls):
        logger.debug('Send Changes task started')
        # Ensure task will restart unless Canceled
        while True:
            try:
                await cls.__changesSend()
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
