from __future__ import annotations
from asyncio import CancelledError, create_task, sleep, Task
from aiomqtt import Client, Message, MqttError, Will
from logging import getLogger  # , DEBUG
from typing import Dict, List
from weakref import WeakValueDictionary

from callbacks import Callbacks
from healthcheck import Healthcheck
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


"""
https://github.com/sbtinstruments/aiomqtt/issues/237
https://sbtinstruments.github.io/aiomqtt/subscribing-to-a-topic.html

from aiomqtt import Client, MqttError

TLS?
https://github.com/sbtinstruments/aiomqtt/issues/15
https://github.com/encode/httpx/discussions/2037#discussioncomment-2006795

"""

logger1 = getLogger('jmqtt.brk')
logger2 = getLogger('jmqtt.cli')
logger3 = getLogger('jmqtt.rt')


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

        self.mqttClient: Client = None
        self.mqttTask: Task = None
        self.realtimeClient: Task = None
        self.realtimeTask: Task = None

    async def accept(self, visitor: LogicVisitor) -> None:
        await visitor.visit_brk(self)

    # def getBrokerId(self) -> int:
    #     return self.model.id

    # def getBroker(self) -> BrkLogic:
    #     return self

    # def isEnabled(self) -> bool:
    #     return self.model.isEnable

    async def __listen(self, message: Message):
        self.log.debug('Got msg on %s: %s', message.topic, message.payload)

    async def __clientTask(self):
        while True:
            try:
                started: bool = False
                cfg = self.model.configuration
                self.log.debug('Connecting to: %s', cfg.mqttAddress)
                async with Client(
                    hostname=cfg.mqttAddress,
                    port=cfg.mqttPort if cfg.mqttPort != 0 else 1883,
                    transport=(
                        'tcp'
                        if cfg.mqttProto in [MqttProtoModel.mqtt, MqttProtoModel.mqtts]
                        else 'websockets'
                    ),

                    # TODO Add other mqtt params
                    # transport: Literal['tcp', 'websockets'] = 'tcp',
                    # cfg.mqttProto ## MqttProtoModel.mqtt, MqttProtoModel.mqtts, MqttProtoModel.ws, MqttProtoModel.wss
                    # websocket_path: str | None = None,
                    # websocket_headers: WebSocketHeaders | None = None

                    # protocol: ProtocolVersion | None = None, ## ProtocolVersion.V31, ProtocolVersion.V311, ProtocolVersion.V5

                    client_id=cfg.mqttIdValue if cfg.mqttId else None,
                    # To use with python >=3.8
                    # identifier=cfg.mqttIdValue if cfg.mqttId else None,
                    username=cfg.mqttUser if cfg.mqttPass is not None else None,
                    password=cfg.mqttPass if cfg.mqttUser is not None else None,
                    will=None if not cfg.mqttLwt else Will(
                        topic=cfg.mqttLwtTopic,
                        payload=cfg.mqttLwtOffline,
                        qos=0,  # TODO review this val
                        retain=True,
                    ),

                    # tls_insecure: bool | None = None,
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

                    # cfg.mqttTlsCa ## Expected CA cert
                    # cfg.mqttTlsClient ## bool (use client public & private keys)
                    # cfg.mqttTlsClientCert ## Provided Client public key
                    # cfg.mqttTlsClientKey ## Provided Client private key

                    logger=getLogger(f'jmqtt.cli.{self.model.id}'),
                    # logger=getLogger(f'jmqtt.rt.{self.model.id}'),
                    # queue_type: type[asyncio.Queue[Message]] | None = None,
                    # clean_session: bool | None = None,

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
                ) as client:
                    try:
                        self.mqttClient = client
                        await Callbacks.brokerUp(self.model.id)
                        started = True

                        # if cfg.mqttApi:
                        #     await client.subscribe(topic=cfg.mqttApiTopic)

                        # if cfg.mqttInt:
                        #     await client.subscribe(topic=cfg.mqttIntTopic)

                        for t in list(self.topics):
                            try:
                                await client.subscribe(topic=t)
                            except:
                                self.log.info('Could not subscribe to "%s"', t)
                        if cfg.mqttLwt:
                            await self.publish(
                                topic=cfg.mqttLwtTopic,
                                payload=cfg.mqttLwtOnline,
                                qos=0,  # TODO review this val
                                retain=True,
                            )
                        async with client.messages() as messages:
                            async for message in messages:
                                await self.__listen(message)
                        # To use with python >=3.8
                        # async for message in client.messages:
                        #     self.__listen(message)
                    except CancelledError:
                        if cfg.mqttLwt:
                            await self.publish(
                                topic=cfg.mqttLwtTopic,
                                payload=cfg.mqttLwtOffline,
                                qos=0,  # TODO review this val
                                retain=True,
                            )
                        raise

            except MqttError:
                self.log.info(
                    'Connection lost; Reconnecting in %s seconds...',
                    cfg.mqttRecoInterval,
                )
                self.mqttClient = None
                if started:
                    await Callbacks.brokerDown(self.model.id)
                await sleep(cfg.mqttRecoInterval)
            except Exception:
                self.log.exception(
                    'Client died unexpectedly; Reconnecting in %s seconds...',
                    cfg.mqttRecoInterval,
                )
                self.mqttClient = None
                if started:
                    await Callbacks.brokerDown(self.model.id)
                await sleep(cfg.mqttRecoInterval)

    async def start(self):
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

    async def stop(self):
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

    async def restart(self):
        await self.stop()
        await self.start()

    async def publish(self, topic: str, payload: str, qos: int, retain: bool):
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
        except:
            self.log.info(
                'Could not publish "%s" on "%s" with qos=%i and retain=%s',
                payload,
                topic,
                qos,
                'True' if retain else 'Flase',
            )
        # TODO
        await Healthcheck.onReceive()

    async def subscribe(self, topic: str, qos: int) -> None:
        self.log.debug('topic="%s", qos=%i', topic, qos)
        if self.mqttClient is not None:
            await self.mqttClient.subscribe(topic, qos)

    async def unsubscribe(self, topic: str) -> None:
        self.log.debug('topic="%s"', topic)
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
