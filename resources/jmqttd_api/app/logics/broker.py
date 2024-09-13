from __future__ import annotations
from asyncio import CancelledError, create_task, sleep, Task
from aiomqtt import Client, Message, MqttError, Will
from logging import getLogger  # , DEBUG
from os import remove
import ssl
from tempfile import NamedTemporaryFile
from typing import Dict, List, TYPE_CHECKING, Union
from weakref import WeakValueDictionary

from comm.callbacks import Callbacks
from visitors.abstractvisitor import VisitableLogic, LogicVisitor

if TYPE_CHECKING:
    from logics.eq import EqLogic
    from logics.cmd import CmdLogic
from logics.topicmap import Dispatcher, TopicMap

if TYPE_CHECKING:
    from models.broker import BrkModel
from models.broker import (
    MqttProtoEnum,
    TlsCheckEnum,
    MqttVersionEnum,
)
from models.messages import (
    MqttMessageModel,
    RealTimeModel,
    RealTimeStatusModel,
)
from settings import settings


logger1 = getLogger('jmqtt.brk')
logger2 = getLogger('jmqtt.cli')
logger3 = getLogger('jmqtt.rt')


# -----------------------------------------------------------------------------
# Utilitary function to export str to tmp file
def strToTmpFile(content) -> str:
    res = NamedTemporaryFile(delete=False)
    res.write(str.encode(content))
    res.close()
    return res.name


# -----------------------------------------------------------------------------
# Utilitary function to delete tmp file
def deleteTmpFile(filename: Union[str, None]) -> None:
    if filename is not None:
        remove(filename)


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
class BrkLogic(VisitableLogic, Dispatcher):
    all: Dict[int, BrkLogic] = {}

    # -----------------------------------------------------------------------------
    def __init__(self, model: BrkModel):
        self.log = getLogger(f'jmqtt.brk.{model.id}')
        self.model = model

        self.eqpts: WeakValueDictionary[int, EqLogic] = {}
        self.map = TopicMap(model.id, self.subscribe, self.unsubscribe)
        self.mqttClient: Client = None
        self.mqttTask: Task = None
        self.realtimeClient: Task = None

    # -----------------------------------------------------------------------------
    async def accept(self, visitor: LogicVisitor) -> None:
        await visitor.visit_brk(self)

    # -----------------------------------------------------------------------------
    def getDispatcherId(self) -> str:
        return f'broker={self.model.id}'

    # -----------------------------------------------------------------------------
    async def addCmd(self, cmd: CmdLogic) -> None:
        topic = cmd.model.configuration.topic
        eq = cmd.weakEq()
        qos = eq.model.configuration.Qos if eq else 1  # TODO review this QOS ?
        await self.map.add(topic, qos, cmd)

    # -----------------------------------------------------------------------------
    async def delCmd(self, cmd: CmdLogic) -> None:
        await self.map.delete(cmd.model.configuration.topic, cmd)

    # -----------------------------------------------------------------------------
    async def dispatch(self, message: Message, ts: float) -> Union[int, None]:
        cfg = self.model.configuration
        topic = str(message.topic)
        payload = message.payload.decode('utf-8')
        if cfg.mqttApi and topic == cfg.mqttApiTopic:
            self.log.debug('Jeedom API request: "%s"', payload)
            await Callbacks.jeedomApi(self.model.id, payload)
        if cfg.mqttInt:
            if topic == cfg.mqttIntTopic:
                self.log.debug('Interaction: "%s"', payload)
                await Callbacks.interact(self.model.id, payload)
            elif topic == (cfg.mqttIntTopic + '/advanced'):
                self.log.debug('Interaction (advanced): "%s"', payload)
                await Callbacks.interact(self.model.id, payload, True)
        # TODO if realtime is enable, put message in buffer
        return None

    # -----------------------------------------------------------------------------
    def __buildClient(self) -> Client:
        cfg = self.model.configuration
        tlsInsecure = None
        tlsContext = None
        tmpTlsClientCert = None
        tmpTlsClientKey = None
        # Handle SSL parameters
        if cfg.mqttProto in [MqttProtoEnum.mqtts, MqttProtoEnum.wss]:
            try:
                # Do we need to check if certificat is valid
                tlsInsecure = cfg.mqttTlsCheck == TlsCheckEnum.disabled
                tlsContext = ssl.create_default_context()
                tlsContext.load_default_certs()
                tlsContext.check_hostname = not tlsInsecure
                tlsContext.verify_mode = (
                    ssl.CERT_NONE if tlsInsecure else ssl.CERT_REQUIRED
                )
                # Get CA cert if needed
                if (
                    cfg.mqttTlsCheck == TlsCheckEnum.private
                    and cfg.mqttTlsCa.strip() != ''
                ):
                    tlsContext.load_verify_locations(cadata=cfg.mqttTlsCa)
                # Get Private Cert / Key if needed
                if (
                    cfg.mqttTlsClient
                    and cfg.mqttTlsClientCert.strip() != ''
                    and cfg.mqttTlsClientKey.strip() != ''
                ):
                    tmpTlsClientCert = strToTmpFile(cfg.mqttTlsClientCert)
                    tmpTlsClientKey = strToTmpFile(cfg.mqttTlsClientKey)
                    tlsContext.load_cert_chain(
                        certfile=tmpTlsClientCert, keyfile=tmpTlsClientKey
                    )
            except Exception:
                self.log.exception(
                    'Fatal TLS Certificate import Exception, this connection will most likely fail!'
                )
                raise
        # Build client
        useWS = cfg.mqttProto in [MqttProtoEnum.ws, MqttProtoEnum.wss]
        client = Client(
            hostname=cfg.mqttAddress,
            port=cfg.mqttPort if cfg.mqttPort != 0 else 1883,
            transport='websockets' if useWS else 'tcp',
            websocket_path=cfg.mqttWsUrl if useWS else None,
            websocket_headers=cfg.mqttWsHeader if useWS else None,
            protocol=cfg.mqttVersion,
            client_id=cfg.mqttIdValue if cfg.mqttId else None,
            # TODO To use `identifier` instead of `client_id` with aiomqtt>=2.0.1
            #  identifier=cfg.mqttIdValue if cfg.mqttId else None,
            username=cfg.mqttUser,
            password=cfg.mqttPass if cfg.mqttUser is not None else None,
            will=(
                Will(
                    topic=cfg.mqttLwtTopic,
                    payload=cfg.mqttLwtOffline,
                    qos=cfg.mqttLwtQos,
                    retain=cfg.mqttLwtRetain,
                )
                if cfg.mqttLwt
                else None
            ),
            tls_context=tlsContext,
            tls_insecure=tlsInsecure,
            logger=getLogger(f'jmqtt.cli.{self.model.id}'),
            # logger=getLogger(f'jmqtt.rt.{self.model.id}'),
            clean_session=True,
            # TODO Add other mqtt params?
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
            # socket_options: Iterable[SocketOption] | None = None,
        )
        # Cleanup temporary files
        deleteTmpFile(tmpTlsClientCert)
        deleteTmpFile(tmpTlsClientKey)
        return client

    # -----------------------------------------------------------------------------
    async def __runClient(self, client: Client) -> None:
        cfg = self.model.configuration
        try:
            self.mqttClient = client
            await Callbacks.brokerUp(self.model.id)
            # TODO To use with python >=3.8
            #  remove `async with client.messages() as messages:`
            async with client.messages() as messages:
                if cfg.mqttApi:
                    await self.map.add(cfg.mqttApiTopic, 1, self)
                if cfg.mqttInt:
                    await self.map.add(cfg.mqttIntTopic, 1, self)
                    advancedTopic = cfg.mqttIntTopic + '/advanced'
                    await self.map.add(advancedTopic, 1, self)
                await self.map.massSubscribe()
                if cfg.mqttLwt:
                    await self.publish(
                        topic=cfg.mqttLwtTopic,
                        payload=cfg.mqttLwtOnline,
                        qos=cfg.mqttLwtQos,
                        retain=cfg.mqttLwtRetain,
                    )
                # TODO To use with python >=3.8
                #  async for msg in client.messages:
                async for msg in messages:
                    self.log.trace(
                        'Got msg topic=%s, payload=%s, qos=%i, retain=%s',
                        msg.topic,
                        msg.payload,
                        msg.qos,
                        msg.retain,
                    )
                    try:
                        await self.map.dispatch(msg)
                    except Exception:
                        self.log.exception('Exception on message: %s', msg)
        except CancelledError:
            if cfg.mqttLwt:
                await self.publish(
                    topic=cfg.mqttLwtTopic,
                    payload=cfg.mqttLwtOffline,
                    qos=cfg.mqttLwtQos,
                    retain=cfg.mqttLwtRetain,
                )
            raise

    # -----------------------------------------------------------------------------
    async def __clientTask(self) -> None:
        self.log.trace('Client task started')
        cfg = self.model.configuration
        failures: int = 0
        while True:
            running: bool = False
            try:
                # Retry N times with short timer, then use long timer
                interval: int = (
                    settings.mqtt_short_reco_interval
                    if failures < settings.mqtt_short_reco_number
                    else settings.mqtt_long_reco_interval
                )
                # Retry using MQTTv5 if MQTTv3.11 fails
                version: MqttVersionEnum = (
                    cfg.mqttVersion
                    if failures % 2 == 0
                    else (
                        MqttVersionEnum.V5
                        if cfg.mqttVersion == MqttVersionEnum.V311
                        else MqttVersionEnum.V311
                    )
                )
                self.log.debug(
                    'Connecting to %s (%s) broker: %s:%i%s',
                    cfg.mqttProto.name,
                    version.name,
                    cfg.mqttAddress,
                    cfg.mqttPort if cfg.mqttPort != 0 else 1883,
                    (
                        cfg.mqttWsUrl
                        if cfg.mqttProto in [MqttProtoEnum.ws, MqttProtoEnum.wss]
                        else ''
                    ),
                )
                async with self.__buildClient() as client:
                    running = True
                    # TODO if failures > 0 and version != cfg.mqttVersion: udpate MQTT version in eqBroker
                    failures = 0
                    await self.__runClient(client)
            except MqttError as e:
                failures += 1
                self.log.warning('%s; Reconnecting in %s seconds...', e, interval)
                self.mqttClient = None
                if running:
                    await Callbacks.brokerDown(self.model.id)
                await sleep(interval)
            except CancelledError:
                self.log.debug('Client task canceled')
                if running:
                    await Callbacks.brokerDown(self.model.id)
                raise
            except Exception:
                failures += 1
                self.log.exception(
                    'Client died unexpectedly; Reconnecting in %s seconds...',
                    interval,
                )
                self.mqttClient = None
                if running:
                    await Callbacks.brokerDown(self.model.id)
                await sleep(interval)

    # -----------------------------------------------------------------------------
    async def start(self) -> None:
        if not self.model.isEnable:
            self.log.debug('Not enabled, Broker task not created')
            return
        self.log.debug('Start requested')
        if self.mqttTask is not None:
            if not self.mqttTask.done():
                self.log.debug('Broker task already running')
                return
        self.mqttTask = create_task(self.__clientTask())
        self.log.debug('Started')

    # -----------------------------------------------------------------------------
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

    # -----------------------------------------------------------------------------
    async def restart(self) -> None:
        await self.stop()
        await self.start()

    # -----------------------------------------------------------------------------
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

    # -----------------------------------------------------------------------------
    async def subscribe(self, topic: str, qos: int) -> None:
        kind = 'wildcard' if '+' in topic or '#' in topic else 'topic'
        self.log.debug('%s="%s", qos=%i', kind, topic, qos)
        if self.mqttClient is not None:
            await self.mqttClient.subscribe(topic, qos)

    # -----------------------------------------------------------------------------
    async def unsubscribe(self, topic: str) -> None:
        kind = 'wildcard' if '+' in topic or '#' in topic else 'topic'
        self.log.debug('%s="%s"', kind, topic)
        if self.mqttClient is not None:
            await self.mqttClient.unsubscribe(topic)

    # -----------------------------------------------------------------------------
    async def realTimeStart(self, params: RealTimeModel) -> bool:
        # Do nothing if Broker is disabled
        if not self.model.isEnable:
            return False

        # TODO realTimeStart
        self.log.debug('Todo')
        # Store params
        # Add self to topicmap for params.subscribe topics
        # Start timer of params.duration for realTimeStop
        return self.model.isEnable
        # return self.mqttClient is not None

    # -----------------------------------------------------------------------------
    async def realTimeStatus(self) -> RealTimeStatusModel:
        self.log.debug('Todo')
        # TODO realTimeStatus
        return RealTimeStatusModel(
            retained=False,
            enabled=False,
            timeleft=0,
            count=0,
        )

    # -----------------------------------------------------------------------------
    async def realTimeStop(self) -> None:
        self.log.debug('Todo')
        # TODO realTimeStop

    # -----------------------------------------------------------------------------
    async def realTimeGet(self, since: int) -> List[MqttMessageModel]:
        self.log.debug('Todo')
        if self.realtimeClient is None:
            return []
        # TODO realTimeGet
        return []

    # -----------------------------------------------------------------------------
    async def realTimeClear(self) -> None:
        self.log.debug('Todo')
        # TODO realTimeClear
