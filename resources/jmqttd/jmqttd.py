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

import argparse
from binascii import b2a_base64
import json
import logging
import os
import paho.mqtt.client as mqtt
import queue
import signal
import sys
import threading
import time
import websocket
from zlib import decompress as zlib_decompress

try:
	from jeedom.jeedom import jeedom_socket
	from jeedom.jeedom import jeedom_utils
	from jeedom.jeedom import jeedom_com
except ImportError:
	print("Error: importing module jeedom.jeedom")
	sys.exit(1)

class MqttClient:
	def __init__(self, queue, message):
#        logging.debug('MqttClient.init(): message=%r', message)
		self.q = queue
		self.id = message['id']
		self.mqtthostname = message['hostname']
		self.mqttport = message['port'] if 'port' in message else 1883
		self.mqttstatustopic = message['statustopic'] if 'statustopic' in message else ''
		if 'clientid' not in message:
			message['clientid'] = ''
		if 'username' not in message:
			message['username'] = ''
		if 'password' not in message:
			message['password'] = ''
		self.mqttsub_lock = threading.Lock()
		self.mqttsubscribedtopics = {}
		self.connected = False
#		logging.debug('MqttClient.init() SELF dump: %r', [(attr, getattr(self, attr)) for attr in vars(self) if not callable(getattr(self, attr)) and not attr.startswith("__")])

		# Create MQTT Client
		self.mqttclient = mqtt.Client(message['clientid'])
		# Enable Paho logging functions
		if 'paholog' in message and message['paholog'] != '':
			logger = logging.getLogger()
			self.mqttclient.enable_logger(logger)
		else:
			self.mqttclient.disable_logger()
		if message['username'] != '':
			if message['password'] != '':
				self.mqttclient.username_pw_set(message['username'], message['password'])
			else:
				self.mqttclient.username_pw_set(message['username'])
		if message['tls']:
			try:
				self.mqttclient.tls_set(ca_certs=message['tlscafile'], certfile=message['tlsclicertfile'], keyfile=message['tlsclikeyfile'])
				self.mqttclient.tls_insecure_set(('tlsinsecure' in message) and message['tlsinsecure'])
			except:
				logging.exception('Fatal TLS Certificate import Exception, this connection will most likely fail!')

		self.mqttclient.reconnect_delay_set(5, 15)
		self.mqttclient.on_connect = self.on_connect
		self.mqttclient.on_disconnect = self.on_disconnect
		self.mqttclient.on_message = self.on_message

	def on_connect(self, client, userdata, flags, rc):
		self.connected = True
		if self.mqttstatustopic != '':
			client.will_set(self.mqttstatustopic, 'offline', 1, True)
			client.publish(self.mqttstatustopic, 'online', 1, True)
		logging.info('BrkId: % 4s : Connected to broker %s:%d', self.id, self.mqtthostname, self.mqttport)
		with self.mqttsub_lock:
			for topic in self.mqttsubscribedtopics:
				self.subscribe_topic(topic, self.mqttsubscribedtopics[topic])
		self.q.put([{"cmd":"brokerUp","id":self.id}])

	def on_disconnect(self, client, userdata, rc):
		self.connected = False
		self.q.put([{"cmd":"brokerDown","id":self.id}])
		if rc == mqtt.MQTT_ERR_SUCCESS:
			logging.info('BrkId: % 4s : Disconnected from broker.', self.id)
		else:
			logging.error('BrkId: % 4s : Unexpected disconnection from broker!', self.id)

	def on_message(self, client, userdata, message):
		try:
			usablePayload = message.payload.decode('utf-8')
			form = '' # Successfully decoded as utf8
		except:
			try: # jMQTT will try automaticaly to decompress the payload (requested in issue #135)
				unzip = zlib_decompress(message.payload, wbits=-15)
				usablePayload = unzip.decode('utf-8')
				form = ' (decompressed)'
			except: # If payload cannot be decoded or decompressed it is returned in base64
				usablePayload = b2a_base64(message.payload, newline=False).decode('utf-8')
				form = ' (bin in base64)'
			logging.info('BrkId: % 4s : Message received (topic="%s", payload="%s"%s, QoS=%s, retain=%s)', self.id, message.topic, usablePayload, form, message.qos, bool(message.retain))
		self.q.put([{"cmd":"messageIn","id":self.id,"topic":message.topic,"payload":usablePayload,"qos":message.qos,"retain":bool(message.retain)}])

	def subscribe_topic(self, topic, qos):
		try:
			res = self.mqttclient.subscribe(topic, qos)
			if res[0] == mqtt.MQTT_ERR_SUCCESS or res[0] == mqtt.MQTT_ERR_NO_CONN:
				with self.mqttsub_lock:
					self.mqttsubscribedtopics[topic] = qos
				logging.info('BrkId: % 4s : Topic subscribed "%s"', self.id, topic)
				return
		except ValueError: # Only catch ValueError
			pass
		logging.error('BrkId: % 4s : Topic subscription failed "%s"', self.id, topic)

	def unsubscribe_topic(self, topic):
		with self.mqttsub_lock:
			if topic not in self.mqttsubscribedtopics:
				logging.info('BrkId: % 4s : Can\'t unsubscribe not subscribed topic "%s"', self.id, topic)
				return
			try:
				res = self.mqttclient.unsubscribe(topic)
				if res[0] == mqtt.MQTT_ERR_SUCCESS or res[0] == mqtt.MQTT_ERR_NO_CONN:
					del self.mqttsubscribedtopics[topic]
					logging.info('BrkId: % 4s : Topic unsubscribed "%s"', self.id, topic)
					return
			except ValueError: # Only catch ValueError
				pass
			logging.error('BrkId: % 4s : Topic unsubscription failed "%s"', self.id, topic)

	def publish(self, topic, payload, qos, retain):
		self.mqttclient.publish(topic, payload, qos, retain)
		# Python Client : publish(topic, payload=None, qos=0, retain=False)
		# Returns a MQTTMessageInfo which expose the following attributes and methods:
		#  - rc, the result of the publishing. It could be MQTT_ERR_SUCCESS to indicate success, MQTT_ERR_NO_CONN if the client is not currently connected, or MQTT_ERR_QUEUE_SIZE when max_queued_messages_set is used to indicate that message is neither queued nor sent.
		#  - mid is the message ID for the publish request. The mid value can be used to track the publish request by checking against the mid argument in the on_publish() callback if it is defined. wait_for_publish may be easier depending on your use-case.
		#  - wait_for_publish() will block until the message is published. It will raise ValueError if the message is not queued (rc == MQTT_ERR_QUEUE_SIZE).
		#  - is_published returns True if the message has been published. It will raise ValueError if the message is not queued (rc == MQTT_ERR_QUEUE_SIZE).
		#  - A ValueError will be raised if topic is None, has zero length or is invalid (contains a wildcard), if qos is not one of 0, 1 or 2, or if the length of the payload is greater than 268435455 bytes.
		logging.info('BrkId: % 4s : Sending message to broker (topic="%s", payload="%s", QoS=%s, retain=%s)', self.id, topic, payload, qos, retain)

	def start(self):
		try:
			self.mqttclient.connect(self.mqtthostname, self.mqttport, 30)
			self.mqttclient.loop_start()
		except:
			if logging.getLogger().isEnabledFor(logging.DEBUG):
				logging.exception('BrkId: % 4s : MqttClient.start() Exception', self.id)

	def stop(self):
		if self.mqttstatustopic != '':
			self.mqttclient.publish(self.mqttstatustopic, 'offline', 1, True)
		self.mqttclient.disconnect()
		self.mqttclient.loop_stop()
		logging.debug('BrkId: % 4s : MqttClient.stop() ended', self.id)

# ----------------------------------------------------------------------------

class JeedomClient:
	def __init__(self, queue, message):
		self.q = queue
		self.id = message['id']
		self.apikey = message['apikey']
		self.callback = message['callback']

		# Create jeedom_com Client
		self.jcom = jeedom_com(apikey=self.apikey, url=self.callback)
		self.stopworker = False
		self.workerthread = threading.Thread(target=self.worker)
		self.workerthread.daemon = True

	def worker(self):
		logging.debug('BrkId: % 4s : Message worker to Jeedom started.', self.id)
		while not self.stopworker or not self.q.empty():
			# empty() method is faster that Exception handling
			if self.q.empty():
				time.sleep(0.1)
				continue # Check if should_stop changed
			try:
				msg = self.q.get(block=False)
				self.jcom.thread_change(msg)
				logging.info('BrkId: % 4s : Sending message to Jeedom : %s', self.id, msg)
			except queue.Empty:
				pass
			except:
				if logging.getLogger().isEnabledFor(logging.DEBUG):
					logging.exception('BrkId: % 4s : JeedomClient.worker() Exception', self.id)
		logging.debug('BrkId: % 4s : Message worker to Jeedom ended.', self.id)

	def start(self):
		self.stopworker = False
		self.workerthread.start()

	def stop(self):
		self.stopworker = True
		self.workerthread.join()

# ----------------------------------------------------------------------------

class jMqttClient:
	def __init__(self, message):
		self.m = message
		self.q = queue.Queue()
		self.mqtt = None
		self.jc = None

	def start(self):
		if self.mqtt is None:
			self.mqtt = MqttClient(self.q, self.m)
			self.mqtt.start()
		if self.jc is None:
			self.jc = JeedomClient(self.q, self.m)
			self.jc.start()

	def stop(self):
		if self.mqtt is not None:
			self.mqtt.stop()
			self.mqtt = None
		if self.jc is not None:
			self.jc.stop()
			self.jc = None

	def restart(self, message=None):
		if message is not None:
			self.m = message
		self.stop()
		self.start()

	# def __del__(self):
		# self.stop()

# ----------------------------------------------------------------------------

# Takes a message "msg" and an array of (key, mandatory, default_val, expected_type) tupples to validate
#   - "key" is an orderable and printable value representing a slot in msg (msg[key])
#   - "mandatory" is a bool stating if msg[key] is mandatory or just expected
#   - "default_val" can by any value to be placed in msg[key] if missing
#         if "default_val" is None and mandatory is True, an error is logged if key is missing
#   - "expected_type" is the expected type of msg[key], or None if no type is expected
#         if msg[key] is not of expected type and cast atempt fails, an error is printed
#   The return value is False if an error occurs, True otherwise
def validate_params(msg, constraints):
	res = True
	bid = "% 4s" % (str(msg['id']))  if  'id' in msg else "????"
	cmd = "%16s" % (str(msg['cmd'])) if 'cmd' in msg else "?               "
	for (key, mandatory, default_val, expected_type) in constraints:
		if key not in msg or msg[key] == '':
			if mandatory:
				if default_val is None:
					logging.error('BrkId: %s : Cmd: %s -> missing parameter "%s" dump=%s', bid, cmd, key, json.dumps(msg))
					res = False
				else:
					msg[key] = default_val
		elif (expected_type is not None) and (not isinstance(msg[key], expected_type)):
			try:
				if expected_type is bool:
					msg[key] = str(msg[key]).lower() in ['true', '1']
				else:
					msg[key] = expected_type(msg[key])
			except:
				logging.error('BrkId: %s : Cmd: %s -> Incorrect parameter "%s" (is %s, should be %s) dump=%s', bid, cmd, key, type(msg[key]), expected_type, json.dumps(msg))
				res = False
	return res

# ----------------------------------------------------------------------------

class Main():
	def __init__(self, flag):
		# Default values
		self._log_level   = "error"
		self._socket_port = 55666
		self._socket_host = '127.0.0.1'
		self._pidfile     = '/tmp/jmqttd.pid'
		self._apikey      = ''

		# Class logger
		self.log = logging.getLogger('Main')

		# Handle Run & Shutdown
		self.should_stop  = flag
		self.has_stopped  = threading.Event()
		self.has_stopped.clear()

		# Tables for the meal
		self.message_map  = {'newMqttClient':    self.handle_newMqttClient,
							'removeMqttClient': self.handle_removeMqttClient,
							'subscribeTopic':   self.handle_subscribeTopic,
							'unsubscribeTopic': self.handle_unsubscribeTopic,
							'messageOut':       self.handle_messageOut}
		self.jmqttclients = {}
		self.jeedomsocket = None
		self.jcom = None

	def prepare(self):
		# Parsing arguments
		parser = argparse.ArgumentParser(description='Daemon for Jeedom plugin')
		parser.add_argument("--loglevel",   help="Log Level for the daemon", type=str)
		parser.add_argument("--socketport", help="Socketport for server",    type=int)
		parser.add_argument("--callback",   help="Comm. url to Jeedom",      type=str)
		parser.add_argument("--apikey",     help="Apikey",                   type=str)
		parser.add_argument("--pid",        help="Pid file",                 type=str)
		args = parser.parse_args()

		# Callback is mandatory
		if args.callback:
			self._callback = args.callback
		else:
			self.log.critical('Missing callback url (use parameter --callback <url>)')
			sys.exit(2)
		if args.loglevel:
			self._log_level = args.loglevel
		if args.socketport:
			self._socket_port = args.socketport
		if args.pid:
			self._pidfile = args.pid
		if args.apikey:
			self._apikey = args.apikey

		# Set the global logging level
		logging.getLogger().setLevel(jeedom_utils.convert_log_level(self._log_level))

		# Check the PID file
		if os.path.isfile(self._pidfile):
			self.log.debug('PID File "%s" already exists.', self._pidfile)
			pidfile = open(self._pidfile, "r")
			pidfile.seek(0)
			pid = int(pidfile.readline())
			try:
				# Try to ping the pid
				os.kill(pid, 0)
			except OSError: # PID does not run we can continue
				pass
			except: # just in case
				self.log.exception("Unexpected error when checking PID")
				sys.exit(3)
			else: # PID is alive -> we die
				self.log.error('This daemon already runs! Exit 0')
				sys.exit(0)
		try:
			# Try to write PID to file
			jeedom_utils.write_pid(str(self._pidfile))
		except:
			self.log.exception('Could not write PID file')
			sys.exit(4)

		# All set, ready to run
		self.log.info('Start jMQTT python daemon')
		self.log.info('Log level  : %s', self._log_level)
		self.log.info('Socket port: %s', self._socket_port)
		self.log.info('PID file   : %s', self._pidfile)
		self.log.debug('Apikey    : %s', self._apikey)

	def run(self):
		# Create communication channel to reveive instructions from Jeedom
		try:
			self.jeedomsocket = jeedom_socket(port = self._socket_port, address = self._socket_host)
		except:
			self.log.exception('Run         : Failed to Create the communication channel for Jeedom')
			self.should_stop.set()
		else:
			# Open communication channel
			try:
				self.jeedomsocket.open()

			except:
				self.log.exception('Run         : Failed to Open the communication channel for Jeedom')
				self.should_stop.set()
			else:
				try:
					self.jcom = jeedom_com(apikey=self._apikey, url=self._callback)
					self.jcom.test()
				except:
					self.log.exception('Run         : Failed to Open the communication channel to Jeedom')
					self.should_stop.set()

		# Send daemon Up signal
		self.jcom.thread_change([{"cmd":"daemonUp"}])
		self.log.debug('Run         : Sent Daemon Up signal to Jeedom')

		# Wait for instructions
		while not self.should_stop.is_set():
			# empty() method is faster that Exception handling
			if self.jeedomsocket.empty():
				time.sleep(0.1)
				continue # Check if should_stop changed

			# Get some new raw data and cook it
			try:
				jeedom_raw = self.jeedomsocket.get(block=False)
				jeedom_msg = jeedom_raw.decode('utf-8')
				self.log.debug('Run         : Received from Jeedom: %s', jeedom_msg)
				message = json.loads(jeedom_msg)
			except queue.Empty:
				continue # More chance next time
			except:
				if self.log.isEnabledFor(logging.DEBUG):
					self.log.exception('Run         : Unable to get a message or decode JSON')
				continue # Let's retry
			# Check API key
			if 'apikey' not in message or message['apikey'] != self._apikey:
				self.log.error('Run         : Invalid apikey from socket : %s', message)
				continue # Ignore unauthorized messages

			# Check for mandatory parameters before handling the message
			if not validate_params(message,  # key, mandatory, default_val, expected_type
											[['id',      True,        None, str],
											 ['cmd',     True,        None, str]]):
				continue

			# Register the call
			self.message_map.get(message['cmd'], self.handle_unknown)(message)
		self.has_stopped.set()

	def handle_newMqttClient(self, message):
		# Check for                      key, mandatory, default_val, expected_type
		if not validate_params(message, [['callback',      True,        None, str],
										 ['hostname',      True,        None, str],
										 ['port',         False,        None, int],
										 ['tls',           True,       False, bool]]):
			return

		if message['tls']:
			if 'tlscafile' not in message or message['tlscafile'] == '':
				message['tlscafile'] = None
			elif not os.access(message['tlscafile'], os.R_OK):
				self.log.warning('BrkId: % 4s : Cmd:    newMqttClient -> Unable to read CA file "%s"', message['id'], message['tlscafile'])
				return
			if 'tlsclicertfile' not in message or message['tlsclicertfile'] == '':
				message['tlsclicertfile'] = None
			elif not os.access(message['tlsclicertfile'], os.R_OK):
				self.log.warning('BrkId: % 4s : Cmd:    newMqttClient -> Unable to read Client Certificate file "%s"', message['id'], message['tlsclicertfile'])
				return
			if 'tlsclikeyfile' not in message or message['tlsclikeyfile'] == '':
				message['tlsclikeyfile'] = None
			elif not os.access(message['tlsclikeyfile'], os.R_OK):
				self.log.warning('BrkId: % 4s : Cmd:    newMqttClient -> Unable to read Client Key file "%s"', message['id'], message['tlsclikeyfile'])
				return
			if message['tlsclicertfile'] is None and message['tlsclikeyfile'] is not None:
				self.log.warning('BrkId: % 4s : Cmd:    newMqttClient -> Client Certificate is defined but Client Key is NOT', message['id'])
				return
			if message['tlsclicertfile'] is not None and message['tlsclikeyfile'] is None:
				self.log.warning('BrkId: % 4s : Cmd:    newMqttClient -> Client Key is defined but Client Certificate is NOT', message['id'])
				return
		# if jmqttclient already exists then restart it
		if message['id'] in self.jmqttclients:
			self.log.info('BrkId: % 4s : Cmd:    newMqttClient -> Client already exists. Restarting it.', message['id'])
			self.jmqttclients[message['id']].restart(message)
		else: # create requested jmqttclient
			self.log.info('BrkId: % 4s : Cmd:    newMqttClient -> Creating Client.', message['id'])
			newjMqttClient = jMqttClient(message)
			newjMqttClient.start()
			self.jmqttclients[message['id']] = newjMqttClient

	def handle_removeMqttClient(self, message):
		# if jmqttclient exists then remove it
		if message['id'] in self.jmqttclients:
			self.log.info('BrkId: % 4s : Starting Client removal', message['id'])
			self.jmqttclients[message['id']].stop()
			del self.jmqttclients[message['id']]
		else:
			self.log.info('BrkId: % 4s : Cmd: removeMqttClient -> No client found with this Broker', message['id'])

	def handle_subscribeTopic(self, message):
		# Check for                  key, mandatory, default_val, expected_type
		if not validate_params(message, [['topic',     True,        None, str],
										 ['qos',       True,        None, int]]):
			return
		if message['id'] in self.jmqttclients:
			self.jmqttclients[message['id']].mqtt.subscribe_topic(message['topic'], message['qos'])
		else:
			self.log.debug('BrkId: % 4s : Cmd:   subscribeTopic -> No client found with this Broker', message['id'])

	def handle_unsubscribeTopic(self, message):
		# Check for                   key, mandatory, default_val, expected_type
		if not validate_params(message, [['topic',      True,        None, str]]):
			return
		if message['id'] in self.jmqttclients:
			self.jmqttclients[message['id']].mqtt.unsubscribe_topic(message['topic'])
		else:
			self.log.debug('BrkId: % 4s : Cmd: unsubscribeTopic -> No client found with this Broker', message['id'])

	def handle_messageOut(self, message):
		# Check for                   key, mandatory, default_val, expected_type
		if not validate_params(message, [['topic',      True,        None, str],
										 ['payload',    True,          '', str],
										 ['qos',        True,        None, int],
										 ['retain',     True,        None, bool]]):
			return
		# Supplementary test on qos
		if message['qos'] < 0 or message['qos'] > 2:
			self.log.error('BrkId: % 4s : Cmd:       messageOut -> wrong value for qos "%d"', message['id'], message['qos'])
			return
		if message['id'] in self.jmqttclients:
			self.jmqttclients[message['id']].mqtt.publish(message['topic'], message['payload'], message['qos'], message['retain'])
		else:
			self.log.debug('BrkId: % 4s : Cmd:       messageOut -> No client found with this Broker', message['id'])

	def handle_unknown(self, message):
		# Message when Cmd is not found
		self.log.debug('BrkId: % 4s : Cmd: %16s -> Unknown cmd dump="%s"', message['id'], message['cmd'], json.dumps(message))

	def shutdown(self):
		self.log.info('Stop jMQTT python daemon')
		self.should_stop.set()
		self.has_stopped.wait(timeout=6)

		# Close the open communication channel for Jeedom
		try:
			self.jeedomsocket.close()
			self.log.debug("Socket for Jeedom closed")
		except:
			self.log.debug("Failed to close Socket for Jeedom")

		# Stop all the Clients
		try:
			for id in list(self.jmqttclients):
				self.jmqttclients[id].stop()
		except:
			if self.log.isEnabledFor(logging.DEBUG):
				self.log.exception('Clients Stop Exception')

		# Kill all the Clients
		try:
			for id in list(self.jmqttclients):
				del self.jmqttclients[id]
		except:
			if self.log.isEnabledFor(logging.DEBUG):
				self.log.exception('Clients Kill Exception')

		# Send daemon Down signal
		self.jcom.thread_change([{"cmd":"daemonDown"}])
		self.log.debug('Run         : Sent Daemon Down signal to Jeedom')

		#Remove PID file if exists
		# if os.path.isfile(self._pidfile):
			# try:
				# os.remove(self._pidfile)
				# self.log.debug("Removed PID file %s", self._pidfile)
			# except:
				# self.log.debug("Failed to remove PID file %s", self._pidfile)

		# List all living thread
		# import traceback
		# for thread in threading.enumerate():
			# if threading.current_thread() == thread:
				# continue
			# self.log.debug("Thread %s is still active! Dict: %r Stack Trace:", thread.getName(), thread)
			# stack = sys._current_frames()[thread.ident]
			# for filename, lineno, name, line in traceback.extract_stack(stack):
				# if line:
					# self.log.debug('File: "%s", line %d, in %s  %s', filename, lineno, name, line.strip())
				# else:
					# self.log.debug('File: "%s", line %d, in %s', filename, lineno, name)

		#This the end my friend
		self.log.debug("Exit 0")
		sys.stdout.flush()

# ----------------------------------------------------------------------------

if __name__ == '__main__':
	# Formater for the output of the logger
	formatter = logging.Formatter('[%(asctime)s][%(levelname)s] %(message)s')

	# STDOUT will get all logs
	ch = logging.StreamHandler()
	ch.setLevel(logging.DEBUG)
	ch.setFormatter(formatter)

	# Attach the handler to the main logger
	logger = logging.getLogger()
	logger.handlers = []
	logger.addHandler(ch)

	# Disable debug in http/url/req libs
	debuglevel = False
	requests_log = logging.getLogger("requests.packages.urllib3")
	pool_log = logging.getLogger("urllib3.connectionpool")
	if debuglevel:
		# self.log.verbose('Debug log active for requests & urllib3')
		requests_log.setLevel(logging.DEBUG)
		pool_log.setLevel(logging.DEBUG)
	else:
		requests_log.setLevel(logging.WARNING)
		pool_log.setLevel(logging.WARNING)
	requests_log.propagate = debuglevel
	pool_log.propagate = debuglevel


	# Get an instance of Main
	should_stop = threading.Event()
	should_stop.clear()
	m = Main(should_stop)

	# Interrupt handler
	def signal_handler(signum = None, frame = None):
		logging.debug("Signal %d caught, exiting...", signum)
		should_stop.set()

	# Connect the signals to the handler
	signal.signal(signal.SIGINT, signal_handler)
	signal.signal(signal.SIGTERM, signal_handler)

	# Ready ? Let's do something now
	m.prepare()
	m.run()
	m.shutdown()

	# Always exit well
	sys.exit(0)
