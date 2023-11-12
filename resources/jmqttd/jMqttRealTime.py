# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

from binascii import b2a_base64
from datetime import datetime
import json
import logging
import sys
import threading
from os import unlink
from os.path import isfile
from tempfile import NamedTemporaryFile
from zlib import decompress as zlib_decompress

# import AddLogging

try:
    import paho.mqtt.client as mqtt
except ImportError:
    print("Error: importing module paho.mqtt")
    sys.exit(1)


class jMqttRealTime:
    def __init__(
        self,
        jcom,
        message,
        filename,
        subscribe=[],
        exclude=[],
        retained=False,
        duration=180
    ):
        self._log = logging.getLogger('ClientRT')
        # self._log.debug('jMqttRealTime.init(): message=%r', message)
        self.jcom = jcom
        self.message = message
        self.realtimeFile = filename
        self.realtimeInc = subscribe
        self.realtimeExc = exclude
        self.realtimeRet = retained
        self.realtimeDur = duration
        self.realtimeTab = []
        self.connected = False
        self.mqttclient = None

    def on_connect(self, client, userdata, flags, rc):
        self.connected = True
        self._log.debug(
            'Connected to broker %s:%d (%s)',
            self.mqtthostname,
            self.mqttport,
            mqtt.connack_string(rc)
        )
        self.jcom.send_async({'cmd': 'realTimeStarted', 'id': self.id})
        for topic in self.realtimeInc:
            try:
                res = self.mqttclient.subscribe(topic, 1)
                if (
                    res[0] == mqtt.MQTT_ERR_SUCCESS
                    or res[0] == mqtt.MQTT_ERR_NO_CONN
                ):
                    self._log.info('Topic subscribed "%s"', topic)
                    return
            except ValueError:
                # Only catch ValueError
                pass
            self._log.error('Topic subscription failed "%s"', topic)

    def on_disconnect(self, client, userdata, rc):
        self.connected = False
        self._log.debug(
            'Disconnected from broker %s:%d (%s)',
            self.mqtthostname,
            self.mqttport,
            mqtt.connack_string(rc)
        )
        nb = len(self.realtimeTab)
        self._log.info('Real Time Stopped: %i msgs received', nb)
        with open(self.realtimeFile, 'w') as f:
            json.dump(self.realtimeTab, f)
        self.jcom.send_async(
            {
                'cmd': 'realTimeStopped',
                'id': self.id,
                'nbMsgs': nb
            }
        )

    def on_message(self, client, userdata, message):
        try:
            usablePayload = message.payload.decode('utf-8')
            # Successfully decoded as utf8
            form = ''
        except Exception:
            # jMQTT will try automaticaly to decompress the payload (requested in issue #135)
            try:
                unzip = zlib_decompress(message.payload, wbits=-15)
                usablePayload = unzip.decode('utf-8')
                form = ' (decompressed)'
            except Exception:
                # If payload cannot be decoded or decompressed it is returned in base64
                usablePayload = b2a_base64(message.payload, newline=False).decode('utf-8')
                form = ' (bin in base64)'
        # self._log.debug(
        #     'Message received (topic="%s", payload="%s"%s, QoS=%s, retain=%s)',
        #     message.topic,
        #     usablePayload,
        #     form,
        #     message.qos,
        #     bool(message.retain)
        # )

        if bool(message.retain) and not self.realtimeRet:
            return
        for e in self.realtimeExc:
            if mqtt.topic_matches_sub(e, message.topic):
                return
        self._log.info(
            'Message in Real Time (topic="%s", payload="%s"%s, QoS=%s, retain=%s)',
            message.topic,
            usablePayload,
            form,
            message.qos,
            bool(message.retain)
        )
        d = datetime.now().strftime('%F %T.%f')[:-3]
        self.realtimeTab.append(
            {
                'date': d,
                'topic': message.topic,
                'payload': usablePayload,
                'qos': message.qos,
                'retain': bool(message.retain)
            }
        )
        with open(self.realtimeFile, 'w') as f:
            json.dump(self.realtimeTab, f)

    def start(self):
        if self.mqttclient is not None:
            self._log.info(
                'jMqttRealTime already started (start ignored), should have used restart?'
            )
            return
        self.id = self.message['id']
        self._log = logging.getLogger('ClientRT'+self.id)
        self.mqtthostname = self.message['hostname']
        if 'proto' not in self.message:
            self.message['proto'] = 'mqtt'
        if 'port' not in self.message:
            self.message['port'] = ''
        if self.message['port'] == '':
            self.mqttport = {
                'mqtt': 1883,
                'mqtts': 8883,
                'ws': 1884,
                'wss': 8884
            }.get(self.message['proto'], 1883)
        self.mqttport = self.message['port'] if 'port' in self.message else 1883
        if 'username' not in self.message:
            self.message['username'] = ''
        if 'password' not in self.message:
            self.message['password'] = ''
        self.connected = False
        # self._log.debug(
        #     'jMqttRealTime.init() SELF dump: %r',
        #     [
        #         (
        #             attr, getattr(self, attr)
        #         ) for attr in vars(self) if (
        #             not callable(getattr(self, attr)) and not attr.startswith("__")
        #         )
        #     ]
        # )

        # Load back previouly received realtime messages
        if isfile(self.realtimeFile):
            with open(self.realtimeFile, 'r') as f:
                self.realtimeTab = json.load(f)

        # Create MQTT Client
        if self.message['proto'].startswith('ws'):
            self.mqttclient = mqtt.Client(transport="websockets")
            if 'mqttWsUrl' in self.message and self.message['mqttWsUrl'] != '':
                if self.message['mqttWsUrl'][0] != '/':
                    self.message['mqttWsUrl'] = '/' + self.message['mqttWsUrl']
                self.mqttclient.ws_set_options(self.message['mqttWsUrl'])
        else:
            self.mqttclient = mqtt.Client()
        # Enable Paho logging functions
        if self._log.isEnabledFor(logging.VERBOSE):
            self.mqttclient.enable_logger(self._log)
        else:
            self.mqttclient.disable_logger()
        if self.message['username'] != '':
            if self.message['password'] != '':
                self.mqttclient.username_pw_set(self.message['username'], self.message['password'])
            else:
                self.mqttclient.username_pw_set(self.message['username'])
        if self.message['proto'] == 'mqtts' or self.message['proto'] == 'wss':
            try:
                # Get authority type
                tlscheck = 'public' if ('tlscheck' not in self.message) else self.message['tlscheck']
                insecure = tlscheck == 'disabled'
                reqs = mqtt.ssl.CERT_NONE if insecure else mqtt.ssl.CERT_REQUIRED
                # Get CA cert if needed
                certs = None
                if tlscheck == 'private' and 'tlsca' in self.message:
                    fca = NamedTemporaryFile(delete=False)
                    fca.write(str.encode(self.message['tlsca']))
                    fca.close()
                    certs = fca.name
                # Get Private Cert / Key if needed
                cert = None
                key = None
                if (
                    {'tlscli', 'tlsclicert', 'tlsclikey'} <= self.message.keys()
                    and self.message['tlscli']
                ):
                    fcert = NamedTemporaryFile(delete=False)
                    fcert.write(str.encode(self.message['tlsclicert']))
                    fcert.close()
                    cert = fcert.name
                    fkey = NamedTemporaryFile(delete=False)
                    fkey.write(str.encode(self.message['tlsclikey']))
                    fkey.close()
                    key = fkey.name
                # Setup TLS
                self.mqttclient.tls_set(ca_certs=certs, cert_reqs=reqs, certfile=cert, keyfile=key)
                self.mqttclient.tls_insecure_set(insecure)
                # Remove temporary files
                if certs is not None:
                    unlink(fca.name)
                if cert is not None:
                    unlink(fcert.name)
                if key is not None:
                    unlink(fkey.name)
            except Exception:
                self._log.exception(
                    'Fatal TLS Certificate import Exception, this connection will most likely fail!'
                )

        self.mqttclient.reconnect_delay_set(5, 15)
        self.mqttclient.on_connect = self.on_connect
        self.mqttclient.on_disconnect = self.on_disconnect
        self.mqttclient.on_message = self.on_message
        self._log.info(
            'Real Time Started: subscribe=%s, exclude=%s, retained=%s, duration=%i',
            json.dumps(self.realtimeInc),
            json.dumps(self.realtimeExc),
            self.realtimeRet,
            self.realtimeDur
        )
        try:
            self.mqttclient.connect(self.mqtthostname, self.mqttport, 30)
            self.mqttclient.loop_start()
            if self.mqttclient._thread is not None:
                self.mqttclient._thread.name = 'Brk' + self.id + 'RTTh'
            self.timer = threading.Timer(self.realtimeDur, self.stop)
            self.timer.start()
        except Exception as e:
            if self._log.isEnabledFor(logging.DEBUG):
                self._log.exception('jMqttRealTime.start() Exception')
            else:
                self._log.error('Could not start MQTT client: %s', e)

    def stop(self):
        if self.mqttclient is not None:
            self.timer.cancel()
            self.mqttclient.disconnect()
            self.mqttclient.loop_stop()
            self.mqttclient = None
        self._log.debug('jMqttRealTime ended')

    def clear(self):
        self.realtimeTab = []
