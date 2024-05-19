from abc import ABC, abstractmethod
from aiomqtt import Message, MqttError
from collections.abc import Awaitable, Callable
from logging import getLogger  # , DEBUG
from sys import version_info
from time import time
from typing import Dict, Union
from weakref import WeakSet

if version_info >= (3, 10):
    from typing import TypeAlias
else:
    from typing_extensions import TypeAlias

from visitors.abstractvisitor import VisitableLogic, LogicVisitor

T_Target: TypeAlias = "Dict[str, WeakSet[Dispatcher]]"
# T_Subscribe: TypeAlias = "Callable[[str, int], Awaitable[None]]"
# T_Unsubscribe: TypeAlias = "Callable[[str], Awaitable[None]]"


logger = getLogger('jmqtt.topicmap')


# -----------------------------------------------------------------------------
class Dispatcher(ABC):
    """Abstract dispatcher class to handle """

    @abstractmethod
    def getDispatcherId(self) -> str:
        pass

    @abstractmethod
    async def dispatch(self, message: Message, ts: float) -> Union[int, None]:
        pass

# -----------------------------------------------------------------------------
def isNotSubscribable(topic: str):
    return (
        len(topic) == 0
        or len(topic) > 65535
        or "#/" in topic
        or any(
            "+" in level or "#" in level for level in topic.split("/") if len(level) > 1
        )
    )


# -----------------------------------------------------------------------------
class TopicMap(VisitableLogic):
    """Register all subscription on a Broker"""

    # -----------------------------------------------------------------------------
    def __init__(
        self,
        brokerId: int,
        subscribe: Callable[[str, int], Awaitable[None]],
        unsubscribe: Callable[[str], Awaitable[None]]
    ):
        self.log = getLogger(f'jmqtt.topicmap.{brokerId}')
        self.__subscribe = subscribe
        self.__unsubscribe = unsubscribe

        self.topics: T_Target = {}
        self.wildcards: T_Target = {}
        self.defaults: T_Target = {}

    # -----------------------------------------------------------------------------
    async def accept(self, visitor: LogicVisitor) -> None:
        await visitor.visit_topicmap(self)

    # -----------------------------------------------------------------------------
    async def _add(
        self, target: T_Target, topic: str, qos: int, dispatcher: Dispatcher
    ) -> None:
        """Add a subscription to target and subscribe if needed"""
        inTarget = topic in target
        # If subscription is OK, just return
        if inTarget and dispatcher in target[topic]:
            self.log.debug('%s, already subscribed', dispatcher.getDispatcherId())
            return
        # Add topic to BrkLogic if missing
        if not inTarget:
            target[topic] = WeakSet()
        # Add Dispatcher to topics in BrkLogic
        target[topic].add(dispatcher)
        # Subscribe to the topic, after putting cmd in broker
        if not inTarget:
            # TODO Get QoS when Qos location is in cmd
            qos = 1
            await self.__subscribe(topic, qos)

    # -----------------------------------------------------------------------------
    async def add(self, topic: str, qos: int, dispatcher: Dispatcher) -> None:
        """Add a cmd subscription and subscribe if needed"""
        isWildcard = '+' in topic or '#' in topic
        if isNotSubscribable(topic):
            self.log.notice(
                '%s, %s "%s" is not subscribable',
                dispatcher.getDispatcherId(),
                'wildcard' if isWildcard else 'topic',
                topic,
            )
            return
        target = self.wildcards if isWildcard else self.topics
        await self._add(target, topic, qos, dispatcher)

    # -----------------------------------------------------------------------------
    async def addDefault(self, topic: str, qos: int, dispatcher: Dispatcher) -> None:
        """Add an eq subscription to auto add new commands and subscribe if needed"""
        if isNotSubscribable(topic):
            self.log.notice(
                '%s, auto_add_topic "%s" is not subscribable',
                dispatcher.getDispatcherId(),
                topic,
            )
            return
        await self._add(self.defaults, topic, qos, dispatcher)

    # -----------------------------------------------------------------------------
    async def _delete(
        self, target: T_Target, topic: str, dispatcher: Dispatcher
    ) -> None:
        """Remove a subscription from target and unsubscribe if needed"""
        # If subscription is OK, just return
        inTarget = topic in target
        if not inTarget or dispatcher not in target[topic]:
            self.log.debug('%s, already unsubscribed', dispatcher.getDispatcherId())
            return
        # Remove Dispatcher from Broker topics
        target[topic].remove(dispatcher)
        # Check if unsubscription is needed
        if len(target[topic]) == 0:
            await self.__unsubscribe(topic)
            del target[topic]

    # -----------------------------------------------------------------------------
    async def delete(self, topic: str, dispatcher: Dispatcher) -> None:
        """Remove a cmd subscription and unsubscribe if needed"""
        isWildcard = '+' in topic or '#' in topic
        target = self.wildcards if isWildcard else self.topics
        await self._delete(target, topic, dispatcher)

    # -----------------------------------------------------------------------------
    async def deleteDefault(self, topic: str, dispatcher: Dispatcher) -> None:
        """Remove an eq subscription to auto add new commands and unsubscribe if needed"""
        await self._delete(self.defaults, topic, dispatcher)

    # # -----------------------------------------------------------------------------
    # async def replace(
    #     self, oldTopic: str, newTopic: str, qos: int, callback: Dispatcher
    # ) -> None:
    #     """Replace a subscription by another and subscribe/unsubscribe if needed"""
    #     pass

    # # -----------------------------------------------------------------------------
    # async def replaceDefault(
    #     self, oldTopic: str, newTopic: str, qos: int, callback: Dispatcher
    # ) -> None:
    #     """Replace a subscription by another and subscribe/unsubscribe if needed"""
    #     pass

    # -----------------------------------------------------------------------------
    async def massSubscribe(self) -> None:
        """Get the consolidated list of all subscriptions to init broker"""
        qos = 0  # TODO review this value
        subsList = set(self.topics) | set(self.wildcards) | set(self.defaults)
        self.log.debug('Mass-subscribing to: %s', subsList)
        for topic in subsList:
            try:
                await self.__subscribe(topic, qos)
            except MqttError:
                self.log.exception('Could not subscribe to %s', topic)

    # -----------------------------------------------------------------------------
    async def _dispatchToCmd(
        self, cmds: WeakSet[Dispatcher], message: Message, ts: float
    ) -> set:
        res: set = set()
        for cmd in cmds:
            cmdId = await cmd.dispatch(message, ts)
            if cmdId is not None:
                res.add(cmdId)
        return res

    # -----------------------------------------------------------------------------
    async def dispatch(self, message: Message) -> None:
        """Call the relevant(s) message dispatchers with this message"""
        ts = time()
        topic = str(message.topic)
        dispatchedToCmd: set = set()
        # TODO Create new commands here for eqLogic if cfg.auto_add_cmd
        #  Add a 'dummy' Cmd in targeted Eq to avoid multiple commands creation
        if topic in self.topics:
            self.log.debug(
                'Got message on topic %s for cmd(s): %s',
                topic,
                list(self.topics[topic]),
            )
            dispatchedToCmd |= await self._dispatchToCmd(self.topics[topic], message, ts)
        for sub in self.wildcards:
            if message.topic.matches(sub):
                self.log.debug(
                    'Got message on wildcard %s for cmd(s): %s',
                    sub,
                    list(self.wildcards[sub]),
                )
                dispatchedToCmd |= await self._dispatchToCmd(self.wildcards[sub], message, ts)
        for sub in self.defaults:
            if message.topic.matches(sub):
                self.log.debug(
                    'Got message on wildcard %s for eq(s): %s',
                    sub,
                    list(self.defaults[sub]),
                )
                for eq in self.defaults[sub]:
                    if eq not in dispatchedToCmd:
                        await eq.dispatch(message, ts)
