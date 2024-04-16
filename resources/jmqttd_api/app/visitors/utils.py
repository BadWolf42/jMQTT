from logging import getLogger
from weakref import ref, WeakValueDictionary

from logics.broker import BrkLogic
from logics.eq import EqLogic
from logics.cmd import CmdLogic


logger = getLogger('jmqtt.visitor.utils')


async def isNotSubscribable(topic: str) -> bool:
    return (
        len(self.value) == 0
        or len(self.value) > 65535
        or "#/" in self.value
        or any(
            "+" in level or "#" in level
            for level in self.value.split("/")
            if len(level) > 1
        )
    )


async def addCmdInBrk(cmd: CmdLogic, brk: BrkLogic) -> None:
    topic = cmd.model.configuration.topic
    # If subscription is OK, just return
    if topic in brk.topics and cmd.model.id in brk.topics[topic]:
        logger.debug('id=%s, cmd already subscribed', cmd.model.id)
        return
    if await isNotSubscribable(topic):
        logger.notice('id=%s, cmd topic "%s" is not subscribable', cmd.model.id, topic)
        return
    # Add topic to BrkLogic if missing
    subNeeded = topic not in brk.topics
    if subNeeded:
        brk.topics[topic] = WeakValueDictionary()
    # Add CmdLogic to topics in BrkLogic
    brk.topics[topic][cmd.model.id] = cmd
    # Subscribe to the topic, after putting cmd in brk
    if subNeeded and brk.model.isEnable:
        # TODO Get QoS when Qos location is in cmd
        await brk.subscribe(topic, 1)


async def delCmdInBrk(cmd: CmdLogic, brk: BrkLogic) -> None:
    topic = cmd.model.configuration.topic
    # If subscription is OK, just return
    if topic not in brk.topics or cmd.model.id not in brk.topics[topic]:
        logger.debug('id=%s, cmd already unsubscribed', cmd.model.id)
        return
    # Remove CmdLogic from Broker topics
    del brk.topics[topic][cmd.model.id]
    # Check if unsubscription is needed
    if len(brk.topics[topic]) == 0:
        await brk.unsubscribe(topic)
        del brk.topics[topic]


async def addCmdInEq(cmd: CmdLogic, eq: EqLogic) -> None:
    # Add the reference to EqLogic
    cmd.weakEq = ref(eq)
    # Add CmdLogic ref in EqLogic
    if cmd.model.type == 'info':
        eq.cmd_i[cmd.model.id] = cmd
    else:
        eq.cmd_a[cmd.model.id] = cmd
        # logger.debug('id=%s, cmd disregarded: not an info', cmd.model.id)


async def delCmdInEq(cmd: CmdLogic, eq: EqLogic) -> None:
    # Remove CmdLogic ref in EqLogic/BrkLogic
    if cmd.model.type == 'info':
        if cmd.model.id in eq.cmd_i:
            del eq.cmd_i[cmd.model.id]
    else:
        if cmd.model.id in eq.cmd_a:
            del eq.cmd_a[cmd.model.id]
    # Remove EqLogic weakref
    cmd.weakEq = None
