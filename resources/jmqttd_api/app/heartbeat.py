import asyncio

from logging import getLogger
from os import getpid, kill
from signal import SIGTERM
from time import time

from callbacks import Callbacks
from settings import timeout_cancel


logger = getLogger('jmqtt.heartbeat')


class Heartbeat:
    _retry_max: int = 5  # max number of send retries
    _snd_timeout: float = 135.0  # seconds before send timeout
    _last_rcv: int = time()  # time of the last rcv msg
    _hb_delay: float = 45.0  # seconds between 2 heartbeat emission
    _hb_retry: float = _hb_delay / 2  # seconds before retrying
    _hb_timeout: float = _hb_delay * 7  # seconds before timeout

    _task: asyncio.Task = None  # Heartbeat task initialised by daemonUp method

    @classmethod
    def onReceive(cls):
        cls._last_rcv = time()

    @classmethod
    async def __heartbeat(cls):
        logger.debug('Heartbeat task started')
        while True:
            now = time()
            # Kill daemon if we cannot send for a total of X seconds
            #  and/or a total of Y retries "Jeedom is no longer available"
            if (
                now - Callbacks._last_snd > cls._snd_timeout
                and Callbacks._retry_snd > cls._retry_max
            ):
                logger.error(
                    "Nothing could be sent for %ds (max %ds) AND after %d attempts (max %d), "
                    "Jeedom/Apache is probably dead.",
                    now - Callbacks._last_snd,
                    cls._snd_timeout,
                    Callbacks._retry_snd,
                    cls._retry_max
                )
                kill(getpid(), SIGTERM)
                return
            if now - cls._last_rcv > cls._hb_timeout:
                logger.error(
                    "Nothing has been received for %ds (max %ds), "
                    "Jeedom does not want me any longer.",
                    now - cls._last_rcv,
                    cls._hb_timeout
                )
                kill(getpid(), SIGTERM)
                return

            if now - Callbacks._last_snd > cls._hb_delay:
                # Avoid sending heartbeats continuously
                if now - Callbacks._last_hb > cls._hb_retry:
                    logger.debug(
                        "Sending a heartbeat to Jeedom, nothing sent since %ds (max %ds)",
                        now - Callbacks._last_snd,
                        cls._hb_delay
                    )
                    await Callbacks.daemonHB()
            # logger.debug('<3 Heartbeat <3')
            await asyncio.sleep(15)
        logger.debug('Heartbeat task ended unexpectidely')

    @classmethod
    async def start(cls):
        # Start heart beat task
        cls._task = asyncio.create_task(cls.__heartbeat())
        logger.debug('Heartbeat task created')

    @classmethod
    async def stop(cls):
        if cls._task is None:
            return
        try:
            # Cancel tasks and join it for `timeout_cancel` seconds
            cls._task.cancel()
            await asyncio.wait_for(cls._task, timeout=timeout_cancel)
        except asyncio.CancelledError:
            logger.debug('Heartbeat task canceled')
        except asyncio.TimeoutError:
            logger.debug('Heartbeat task timeouted')
