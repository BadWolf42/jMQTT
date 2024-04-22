from __future__ import annotations
from asyncio import CancelledError, create_task, sleep, Task
from aiomqtt import Client, Message, MqttError, Will
from logging import getLogger  # , DEBUG
from time import time
from typing import Dict, List
from weakref import WeakValueDictionary

from callbacks import Callbacks
from visitors.abstractvisitor import VisitableLogic, LogicVisitor
from logics.eq import EqLogic
from logics.cmd import CmdLogic
from models.broker import (
    BrkModel,
    MqttProtoModel,
    # TlsCheckModel,
)
from models.messages import (
    MqttMessageModel,
    RealTimeModel,
    RealTimeStatusModel,
)

# from models.cmd import (
#     CmdInfoModel,
#     CmdActionModel
# )
# from settings import settings


logger1 = getLogger('jmqtt.brk')
logger2 = getLogger('jmqtt.cli')
logger3 = getLogger('jmqtt.rt')


def isNotSubscribable(topic: str):
    return (
        len(topic) == 0
        or len(topic) > 65535
        or "#/" in topic
        or any(
            "+" in level or "#" in level for level in topic.split("/") if len(level) > 1
        )
    )


class BrkLogic(VisitableLogic):
    all: Dict[int, BrkLogic] = {}

    def __init__(self, model: BrkModel):
        self.log = getLogger(f'jmqtt.brk.{model.id}')
        self.model = model

        self.eqpts: WeakValueDictionary[int, EqLogic] = {}
        # self.cmds: WeakValueDictionary[int, VisitableLogic] = {}
        # self.cmd_i: WeakValueDictionary[int, VisitableLogic] = {}
        # self.cmd_a: WeakValueDictionary[int, VisitableLogic] = {}
        # TODO: Mutate self.topics in a "TopicMap" class
        self.topics: Dict[str, WeakValueDictionary[int, CmdLogic]] = {}
        self.wildcards: Dict[str, WeakValueDictionary[int, CmdLogic]] = {}

        self.mqttClient: Client = None
        self.mqttTask: Task = None
        self.realtimeClient: Task = None
        self.realtimeTask: Task = None

    async def accept(self, visitor: LogicVisitor) -> None:
        await visitor.visit_brk(self)

    async def addCmd(self, cmd: CmdLogic) -> None:
        topic = cmd.model.configuration.topic
        isWildcard = cmd.isWildcard()
        target = self.wildcards if isWildcard else self.topics
        # If subscription is OK, just return
        if topic in target and cmd.model.id in target[topic]:
            self.log.debug('id=%s, cmd already subscribed', cmd.model.id)
            return
        if isNotSubscribable(topic):
            self.log.notice(
                'id=%s, cmd %s "%s" is not subscribable',
                cmd.model.id,
                'wildcard' if isWildcard else 'topic',
                topic,
            )
            return
        # Add topic to BrkLogic if missing
        subNeeded = topic not in target
        if subNeeded:
            target[topic] = WeakValueDictionary()
        # Add CmdLogic to topics in BrkLogic
        target[topic][cmd.model.id] = cmd
        # Subscribe to the topic, after putting cmd in broker
        if subNeeded and self.model.isEnable:
            # TODO Get QoS when Qos location is in cmd
            await self.subscribe(topic, 1)

    async def delCmd(self, cmd: CmdLogic) -> None:
        topic = cmd.model.configuration.topic
        target = self.wildcards if cmd.isWildcard() else self.topics
        # If subscription is OK, just return
        if topic not in target or cmd.model.id not in target[topic]:
            self.log.debug('id=%s, cmd already unsubscribed', cmd.model.id)
            return
        # Remove CmdLogic from Broker topics
        del target[topic][cmd.model.id]
        # Check if unsubscription is needed
        if len(target[topic]) == 0:
            await self.unsubscribe(topic)
            del target[topic]

    async def __dispatch(self, message: Message) -> None:
        cfg = self.model.configuration
        if cfg.mqttApi and str(message.topic) == cfg.mqttApiTopic:
            payload = str(message.payload)
            self.log.debug('Jeedom API request: "%s"', payload)
            await Callbacks.jeedomApi(self.model.id, payload)
        if cfg.mqttInt:
            payload = str(message.payload)
            if str(message.topic) == cfg.mqttIntTopic:
                self.log.debug('Interaction: "%s"', payload)
                await Callbacks.interact(self.model.id, payload)
            elif str(message.topic) == (cfg.mqttIntTopic + '/advanced'):
                self.log.debug('Interaction (advanced): "%s"', payload)
                await Callbacks.interact(self.model.id, payload, True)
        ts = time()
        if str(message.topic) in self.topics:
            self.log.debug(
                'Got message on topic %s for cmd(s): %s',
                message.topic,
                list(self.topics[str(message.topic)]),
            )
            cmds = self.topics[str(message.topic)].values()
            for cmd in cmds:
                await cmd.mqttMsg(message, ts)
        for sub in self.wildcards:
            if message.topic.matches(sub):
                self.log.debug(
                    'Got message on wildcard %s for cmd(s): %s',
                    message.topic,
                    list(self.wildcards[sub]),
                )
                cmds = self.wildcards[sub].values()
                for cmd in cmds:
                    await cmd.mqttMsg(message, ts)

    def __buildClient(self) -> Client:
        cfg = self.model.configuration
        return Client(
            hostname=cfg.mqttAddress,
            port=cfg.mqttPort if cfg.mqttPort != 0 else 1883,
            transport=(
                'tcp'
                if cfg.mqttProto in [MqttProtoModel.mqtt, MqttProtoModel.mqtts]
                else 'websockets'
            ),
            websocket_path=(
                None
                if cfg.mqttProto not in [MqttProtoModel.ws, MqttProtoModel.wss]
                else ('/' + cfg.mqttWsUrl)
            ),
            websocket_headers=(
                None
                if cfg.mqttProto not in [MqttProtoModel.ws, MqttProtoModel.wss]
                else cfg.mqttWsHeader
            ),
            protocol=cfg.mqttVersion,
            client_id=cfg.mqttIdValue if cfg.mqttId else None,
            # To use with python >=3.8
            # identifier=cfg.mqttIdValue if cfg.mqttId else None,
            username=cfg.mqttUser if cfg.mqttPass is not None else None,
            password=cfg.mqttPass if cfg.mqttUser is not None else None,
            will=(
                Will(
                    topic=cfg.mqttLwtTopic,
                    payload=cfg.mqttLwtOffline,
                    qos=0,  # TODO review this val
                    retain=True,
                )
                if cfg.mqttLwt
                else None
            ),
            # TODO Add other mqtt params
            # transport: Literal['tcp', 'websockets'] = 'tcp',
            # cfg.mqttProto ## MqttProtoModel.mqtt, MqttProtoModel.mqtts, MqttProtoModel.ws, MqttProtoModel.wss
            #
            # tls_insecure: bool | None = None,
            # TLS?
            # https://github.com/sbtinstruments/aiomqtt/issues/15
            # https://github.com/encode/httpx/discussions/2037#discussioncomment-2006795
            #
            # cfg.mqttTlsCheck ## TlsCheckModel.disabled, TlsCheckModel.private, TlsCheckModel.public
            # tls_context: ssl.SSLContext | None = None, ## ssl.CERT_NONE, ssl.CERT_OPTIONAL, ssl.CERT_REQUIRED
            # tls_params: TLSParameters | None = None,
            # TLSParameters(
            #     ca_certs: str | None = None
            #     certfile: str | None = None
            #     keyfile: str | None = None
            #     cert_reqs: ssl.VerifyMode | None = None
            #     tls_version: Any | None = None
            #     ciphers: str | None = None
            #     keyfile_password: str | None = None
            # )
            # self._client.tls_set(
            #     ca_certs=tls_params.ca_certs,
            #     certfile=tls_params.certfile,
            #     keyfile=tls_params.keyfile,
            #     cert_reqs=tls_params.cert_reqs,
            #     tls_version=tls_params.tls_version,
            #     ciphers=tls_params.ciphers,
            #     keyfile_password=tls_params.keyfile_password,
            # )
            #
            # cfg.mqttTlsCa ## Expected CA cert
            # cfg.mqttTlsClient ## bool (use client public & private keys)
            # cfg.mqttTlsClientCert ## Provided Client public key
            # cfg.mqttTlsClientKey ## Provided Client private key
            #
            logger=getLogger(f'jmqtt.cli.{self.model.id}'),
            # logger=getLogger(f'jmqtt.rt.{self.model.id}'),
            # queue_type: type[asyncio.Queue[Message]] | None = None,
            # clean_session: bool | None = None,
            #
            # ####
            # Other options
            # timeout: float | None = None,
            # keepalive: int = 60,
            # bind_address: str = '',
            # bind_port: int = 0,
            # clean_start: mqtt.CleanStartOption = 3,
            # max_queued_incoming_messages: int | None = None,
            # max_queued_outgoing_messages: int | None = None,
            # max_inflight_messages: int | None = None,
            # max_concurrent_outgoing_calls: int | None = None,
            # properties: Properties | None = None,
            # proxy: ProxySettings | None = None,
            # socket_options: Iterable[SocketOption] | None = None,
        )

    async def __initialSubs(self) -> None:
        cfg = self.model.configuration
        if cfg.mqttApi:
            try:
                await self.mqttClient.subscribe(cfg.mqttApiTopic)
            except MqttError:
                self.log.exception('Could not subscribe to API topic')
                raise
        if cfg.mqttInt:
            t = cfg.mqttIntTopic
            try:
                await self.mqttClient.subscribe([(t, 0), (t + '/advanced', 0)])
            except MqttError:
                self.log.exception('Could not subscribe to Interact topic')
                raise
        subsList = list(self.topics) + list(self.wildcards)
        self.log.debug('Mass-subscribing to: %s', subsList)
        q = 0  # TODO review this val
        toSub = [(t, q) for t in subsList]
        try:
            await self.mqttClient.subscribe(toSub)
        except MqttError:
            self.log.exception('Could not mass-subscribe')

    async def __runClient(self, client: Client) -> None:
        cfg = self.model.configuration
        try:
            self.mqttClient = client
            await Callbacks.brokerUp(self.model.id)
            await self.__initialSubs()
            if cfg.mqttLwt:
                await self.publish(
                    topic=cfg.mqttLwtTopic,
                    payload=cfg.mqttLwtOnline,
                    qos=0,  # TODO review this val
                    retain=True,
                )
            # TODO To use with python >=3.8
            #  async for msg in client.messages:
            async with client.messages() as messages:
                async for msg in messages:
                    self.log.trace('Got msg on %s: %s', msg.topic, msg.payload)
                    try:
                        await self.__dispatch(msg)
                    except Exception:
                        self.log.exception(
                            'Exception on message: %s', msg
                        )
        except CancelledError:
            if cfg.mqttLwt:
                await self.publish(
                    topic=cfg.mqttLwtTopic,
                    payload=cfg.mqttLwtOffline,
                    qos=0,  # TODO review this val
                    retain=True,
                )
            raise

    async def __clientTask(self) -> None:
        self.log.trace('Client task started')
        cfg = self.model.configuration
        while True:
            started: bool = False
            try:
                self.log.debug(
                    'Connecting to broker on: %s:%i',
                    cfg.mqttAddress,
                    cfg.mqttPort if cfg.mqttPort != 0 else 1883,
                )
                async with self.__buildClient() as client:
                    started = True
                    await self.__runClient(client)
            except MqttError:
                self.log.info(
                    'Connection lost; Reconnecting in %s seconds...',
                    cfg.mqttRecoInterval,
                )
                self.mqttClient = None
                if started:
                    await Callbacks.brokerDown(self.model.id)
                await sleep(cfg.mqttRecoInterval)
            except CancelledError:
                self.log.debug('Client task canceled')
                raise
            except Exception:
                self.log.exception(
                    'Client died unexpectedly; Reconnecting in %s seconds...',
                    cfg.mqttRecoInterval,
                )
                self.mqttClient = None
                if started:
                    await Callbacks.brokerDown(self.model.id)
                await sleep(cfg.mqttRecoInterval)

    async def start(self) -> None:
        if not self.model.isEnable:
            self.log.debug('Not enabled, Broker not started')
            return
        self.log.debug('Start requested')
        if self.mqttTask is not None:
            if not self.mqttTask.done():
                self.log.debug('Already started')
                return
        self.mqttTask = create_task(self.__clientTask())
        self.log.debug('Started')

    async def stop(self) -> None:
        self.log.debug('Stop requested')
        if self.mqttTask is not None:
            if not self.mqttTask.done():
                self.mqttTask.cancel()
                try:
                    await self.mqttTask
                except CancelledError:
                    pass
        self.mqttTask = None
        self.log.debug('Stopped')

    async def restart(self) -> None:
        await self.stop()
        await self.start()

    async def publish(self, topic: str, payload: str, qos: int, retain: bool) -> None:
        # TODO have a return value?
        if self.mqttTask is None or self.mqttTask.done():
            self.log.debug(
                'CANNOT PUBLISH: topic="%s", payload="%s", qos=%i, retain=%s',
                topic,
                payload,
                qos,
                'True' if retain else 'Flase',
            )
            return
        try:
            await self.mqttClient.publish(
                topic=topic,
                payload=payload,
                qos=qos,
                retain=retain,
            )
            self.log.debug(
                'topic="%s", payload="%s", qos=%i, retain=%s',
                topic,
                payload,
                qos,
                'True' if retain else 'Flase',
            )
        except Exception:
            self.log.info(
                'Could not publish "%s" on "%s" with qos=%i and retain=%s',
                payload,
                topic,
                qos,
                'True' if retain else 'Flase',
            )

    async def subscribe(self, topic: str, qos: int) -> None:
        kind = 'wildcard' if '+' in topic or '#' in topic else 'topic'
        self.log.debug('%s="%s", qos=%i', kind, topic, qos)
        if self.mqttClient is not None:
            await self.mqttClient.subscribe(topic, qos)

    async def unsubscribe(self, topic: str) -> None:
        kind = 'wildcard' if '+' in topic or '#' in topic else 'topic'
        self.log.debug('%s="%s"', kind, topic)
        if self.mqttClient is not None:
            await self.mqttClient.unsubscribe(topic)

    async def realTimeStart(self, params: RealTimeModel) -> bool:
        self.log.debug('TODO')
        # TODO
        return self.model.isEnable
        # return self.client is not None

    async def realTimeStatus(self) -> RealTimeStatusModel:
        self.log.debug('TODO')
        # TODO
        return RealTimeStatusModel(
            eqLogic=self.model.id,
            retained=False,
            enabled=False,
            timeleft=0,
            count=0,
        )

    async def realTimeStop(self) -> None:
        self.log.debug('TODO')
        # TODO

    async def realTimeGet(self, since: int) -> List[MqttMessageModel]:
        self.log.debug('TODO')
        if self.realtimeClient is None:
            return []
        # TODO
        return []

    async def realTimeClear(self) -> None:
        self.log.debug('TODO')
        # TODO
