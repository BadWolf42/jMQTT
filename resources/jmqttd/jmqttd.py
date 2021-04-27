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

import logging
import string
import sys
import os
import time
import datetime
import argparse
import re
import signal
from optparse import OptionParser
from os.path import join
import json
import websocket
import paho.mqtt.client as mqtt
from threading import Thread
from queue import Queue
import gc

try:
	from jeedom.jeedom import jeedom_socket
	from jeedom.jeedom import jeedom_utils
	from jeedom.jeedom import JEEDOM_SOCKET_MESSAGE
except ImportError:
	print("Error: importing module jeedom.jeedom")
	sys.exit(1)

class MqttClient:
	def __init__(self, queue, message):
		self.q = queue
		self.id = message['id']

		self.mqtthostname = message['hostname']

		self.mqttport = 1883
		if 'port' in message:
			self.mqttport = message['port']

		self.mqttclientid = ''
		if 'clientid' in message:
			self.mqttclientid = message['clientid']

		self.mqttusername = ''
		if 'username' in message:
			self.mqttusername = message['username']

		self.mqttpassword = ''
		if 'password' in message:
			self.mqttpassword = message['password']

		self.mqttstatustopic = ''
		if 'statustopic' in message:
			self.mqttstatustopic = message['statustopic']

		self.mqttsubscribedtopics = {}
		self.connected = False

		# Create MQTT Client
		self.mqttclient = mqtt.Client(self.mqttclientid)
		if self.mqttusername != '':
			if self.mqttpassword != '':
				self.mqttclient.username_pw_set(self.mqttusername, self.mqttpassword)
			else:
				self.mqttclient.username_pw_set(self.mqttusername)
		self.mqttclient.reconnect_delay_set(5, 15)
		self.mqttclient.on_connect = self.on_connect
		self.mqttclient.on_disconnect = self.on_disconnect
		self.mqttclient.on_message = self.on_message
		self.mqttthread = Thread(target=self.mqttclient.loop_forever)

		self.start()

	def on_connect(self, client, userdata, flags, rc):
		self.connected = True
		if self.mqttstatustopic != '':
			client.will_set(self.mqttstatustopic, 'offline', 1, True)
			client.publish(self.mqttstatustopic, 'online', 1, True)
		logging.info('Id ' + str(self.id) + ' : Connected to broker ' + self.mqtthostname + ':' + str(self.mqttport))
		for topic in self.mqttsubscribedtopics:
			self.subscribe_topic(topic, self.mqttsubscribedtopics[topic])
		self.q.put('{"cmd":"connection","state":' + self.is_connected() + '}')

	def on_disconnect(self, client, userdata, rc):
		self.connected = False
		self.q.put('{"cmd":"connection","state":' + self.is_connected() + '}')
		if rc == mqtt.MQTT_ERR_SUCCESS:
			logging.info('Id ' + str(self.id) + ' : Disconnected from broker.')
		else:
			logging.error('Id ' + str(self.id) + ' : Unexpected disconnection from broker!')

	def on_message(self, client, userdata, message):
		logging.info('Id ' + str(self.id) + ' : message received (topic="' + message.topic + '", payload="' + message.payload.decode('utf-8') + '", QoS='+ str(message.qos) + ', retain=' + str(message.retain) + ')')
		self.q.put(json.dumps({"cmd":"messageIn", "topic":message.topic, "payload":message.payload.decode('utf-8'), "qos":message.qos, "retain":message.retain}))

	def is_connected(self):
		return str(self.connected).lower()

	def subscribe_topic(self, topic, qos):
		res = self.mqttclient.subscribe(topic, qos)
		if res[0] == mqtt.MQTT_ERR_SUCCESS or res[0] == mqtt.MQTT_ERR_NO_CONN:
			self.mqttsubscribedtopics[topic] = qos
			logging.info('Id ' + str(self.id) + ' : Topic subscribed "' + topic + '"')
		else:
			logging.error('Id ' + str(self.id) + ' : Topic subscription failed "' + topic + '"')

	def unsubscribe_topic(self, topic):
		if topic in self.mqttsubscribedtopics:
			res = self.mqttclient.unsubscribe(topic)
			if res[0] == mqtt.MQTT_ERR_SUCCESS or res[0] == mqtt.MQTT_ERR_NO_CONN:
				del self.mqttsubscribedtopics[topic]
				logging.info('Id ' + str(self.id) + ' : Topic unsubscribed "' + topic + '"')
			else:
				logging.error('Id ' + str(self.id) + ' : Topic unsubscription failed "' + topic + '"')
		else:
			logging.info('Id ' + str(self.id) + ' : Can\'t unsubscribe not subscribed topic "' + topic)

	def publish(self, topic, payload, qos, retain):
		self.mqttclient.publish(topic, payload, qos, retain)
		logging.info('Id ' + str(self.id) + ' : Sending message to broker (topic="' + topic + '", payload="' + payload + '", QoS='+ str(qos) + ', retain=' + str(retain) + ')')

	def start(self):
		try:
			self.mqttclient.connect(self.mqtthostname, self.mqttport, 30)
		except:
			pass
		self.mqttthread.start()

	def stop(self):
		if self.mqttstatustopic != '':
			self.mqttclient.publish(self.mqttstatustopic, 'offline', 1, True).wait_for_publish()
		self.mqttclient.disconnect()
		self.mqttthread.join()
	
class WebSocketClient:
	def __init__(self, queue, message, fnismqttconnected):
		self.q = queue
		self.id = message['id']
		self.apikey = message['apikey']
		self.wscallback = message['callback']
		self.fnismqttconnected = fnismqttconnected
		
		# Create WebSocket Client
		self.wsclient = websocket.WebSocketApp(
			url=self.wscallback,
			header={"apikey" : self.apikey, "id" : str(self.id)},
			on_open=self.on_open,
			on_message=self.on_message,
			on_close=self.on_close,
			on_error=self.on_error)

		self.wsthread = Thread(target=self.autorestart_run_forever)
		self.workerthread = Thread(target=self.worker)

		self.start()

	def autorestart_run_forever(self):
		while True:
			try:
				self.wsclient.run_forever(skip_utf8_validation=True, ping_interval=150, ping_timeout=1)
			except:
				pass
			if self.stopautorestart:
				break
			time.sleep(5)

	def worker(self):
		while True:
			try:
				msg = self.q.get(block=True, timeout=0.1)
				self.wsclient.send(msg)
				logging.info('Id ' + str(self.id) + ' : Sending message to Jeedom ' + msg)
			except:
				pass
			if self.stopworker and self.q.empty():
				break

	def on_open(self, ws):
		ws.send('{"cmd":"connection","state":' + str(self.fnismqttconnected()) + '}')
		logging.info('Id ' + str(self.id) + ' : Connected to Jeedom using ' + self.wscallback)

	def on_message(self, ws, message):
		logging.debug('Id ' + str(self.id) + ' : Received a message through WebSocket !!!')

	def on_error(self, ws, error):
		if not isinstance(error, AttributeError) or not self.stopautorestart:
			logging.error('Id ' + str(self.id) + ' : WebSocket client encountered an Error!')

	def on_close(self, ws):
		logging.info('Id ' + str(self.id) + ' : Disconnected from Jeedom')

	def start(self):
		self.stopautorestart = False
		self.wsthread.start()
		self.stopworker = False
		self.workerthread.start()

	def stop(self):
		self.stopworker = True
		self.workerthread.join()
		self.stopautorestart = True
		self.wsclient.close()
		self.wsthread.join()


class jMqttClient:
	def __init__(self, message):
		self.q = Queue()
		self.mqtt = MqttClient(self.q, message)
		self.ws = WebSocketClient(self.q, message, self.mqtt.is_connected)

	def start(self):
		self.mqtt.start()
		self.ws.start()

	def stop(self):
		self.mqtt.stop()
		self.ws.stop()


def cmd_handler(message):
	# Make some controls on received message
	if 'id' not in message:
		logging.error('!!! id is missing !!! : ' + json.dumps(message))
		return
	if 'cmd' not in message:
		logging.error('!!! cmd is missing !!! : ' + json.dumps(message))
		return

	
	# Make some automatic convertions on received message
	if type(message['id']) is str:
		try:
			message['id'] = int(message['id'])
		except:
			logging.error('!!! Incorrect id provided : ' + message['id'])
			return
	if 'port' in message and type(message['port']) is str:
		try:
			message['port'] = int(message['port'])
		except:
			logging.error('Id ' + str(message['id']) + ' !!! Incorrect port provided : ' + message['port'])
			return
	if 'qos' in message and type(message['qos']) is str:
		try:
			message['qos'] = int(message['qos'])
		except:
			logging.error('Id ' + str(message['id']) + ' !!! Incorrect qos provided : ' + message['qos'])
			return
	if 'retain' in message and type(message['retain']) is str:
		try:
			message['retain'] = bool(message['retain'])
		except:
			logging.error('Id ' + str(message['id']) + ' !!! Incorrect retain provided : ' + message['retain'])
			return

	# ------------------------------ newMqttClient ------------------------------
	if message['cmd'] == 'newMqttClient':
		#Make more controls on received message
		if not (message.keys() >= {'callback', 'hostname'}):
			logging.error('Id ' + str(message['id']) + ' !!! newMqttClient - missing parameter : ' + json.dumps(message))
			return

		# if jmqttclient already exists then remove it first
		if message['id'] in jmqttclients:
			logging.info('Id ' + str(message['id']) + ' : Client already exists. Starting removal')
			jmqttclients[message['id']].stop()
			del jmqttclients[message['id']]

		# create requested jmqttclient
		logging.info('Id ' + str(message['id']) + ' : Starting Client creation')
		newjMqttClient = jMqttClient(message)
		jmqttclients[message['id']] = newjMqttClient
		

	# ------------------------------ removeMqttClient ------------------------------
	elif message['cmd'] == 'removeMqttClient':

		# if jmqttclient exists then remove it
		if message['id'] in jmqttclients:
			logging.info('Id ' + str(message['id']) + ' : Starting Client removal')
			jmqttclients[message['id']].stop()
			del jmqttclients[message['id']]
		else:
			logging.info('Id ' + str(message['id']) + ' : No client found with this Id')
			

	# ------------------------------ subscribeTopic ------------------------------
	elif message['cmd'] == 'subscribeTopic':
		#Make more controls on received message
		if not (message.keys() >= {'topic', 'qos'}):
			logging.error('Id ' + str(message['id']) + ' !!! subscribeTopic - missing parameter : ' + json.dumps(message))
			return
		if message['topic'] == '':
			logging.error('Id ' + str(message['id']) + ' !!! subscribeTopic - topic cannot be empty : ' + json.dumps(message))
			return

		if message['id'] in jmqttclients:
			jmqttclients[message['id']].mqtt.subscribe_topic(message['topic'], message['qos'])

	# ------------------------------ unsubscribeTopic ------------------------------
	elif message['cmd'] == 'unsubscribeTopic':
		#Make more controls on received message
		if not (message.keys() >= {'topic'}):
			logging.error('Id ' + str(message['id']) + ' !!! unsubscribeTopic - missing parameter : ' + json.dumps(message))
			return
		if message['topic'] == '':
			logging.error('Id ' + str(message['id']) + ' !!! unsubscribeTopic - topic cannot be empty : ' + json.dumps(message))
			return

		if message['id'] in jmqttclients:
			jmqttclients[message['id']].mqtt.unsubscribe_topic(message['topic'])

	# ------------------------------ messageOut ------------------------------
	elif message['cmd'] == 'messageOut':
		if not (message.keys() >= {'topic','payload','qos','retain'}):
			logging.error('Id ' + str(message['id']) + ' !!! messageOut - missing parameter : ' + json.dumps(message))
			return
		if message['topic'] == '':
			logging.error('Id ' + str(message['id']) + ' !!! messageOut - topic cannot be empty : ' + json.dumps(message))
			return
		if message['qos'] < 0 or message['qos'] > 2:
			logging.error('Id ' + str(message['id']) + ' !!! messageOut - qos wrong value : ' + json.dumps(message))
			return

		if message['id'] in jmqttclients:
			jmqttclients[message['id']].mqtt.publish(message['topic'], message['payload'], message['qos'], message['retain'])

	else:
		logging.error('Unknown cmd : ' + json.dumps(message))


def listen():
	global JEEDOM_SOCKET_MESSAGE
	jeedomsocket.open()
	while True:
		try:
			jeedom_msg = JEEDOM_SOCKET_MESSAGE.get(block=True, timeout=0.1).decode('utf-8')
		except KeyboardInterrupt:
			shutdown()
		except:
			pass
		else:
			logging.debug('jeedom_socket received message : ' + jeedom_msg)
			try:
				message = json.loads(jeedom_msg)
			except:
				logging.debug('jeedom_socket received message is not a correct JSON')
			else:
				if message['apikey'] != _apikey:
					logging.error("Invalid apikey from socket : " + str(message))
					return
				cmd_handler(message)


# ----------------------------------------------------------------------------

def signal_handler(signum=None, frame=None):
	logging.debug("Signal %i caught, exiting..." % int(signum))
	shutdown()

def shutdown():
	logging.debug("Shutdown")
	try:
		jeedomsocket.close()
	except:
		pass
	# try:
	# 	jeedom_serial.close()
	# except:
	# 	pass
	try:
		for id in list(jmqttclients):
			jmqttclients[id].stop()
			del jmqttclients[id]
	except:
		pass
	logging.debug("Removing PID file " + str(_pidfile))
	try:
		os.remove(_pidfile)
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

# ----------------------------------------------------------------------------

_plugin = ''
_log_level = "error"
_socket_port = 55666
_socket_host = '127.0.0.1'
_pidfile = '/tmp/jmqttd.pid'
_apikey = ''


parser = argparse.ArgumentParser(description='Daemon for Jeedom plugin')
parser.add_argument("--plugin", help="Name of the plugin", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--socketport", help="Socketport for server", type=int)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
args = parser.parse_args()

#  Plugin name is mandatory
if args.plugin:
	_plugin = args.plugin
else:
	shutdown()
if args.loglevel:
	_log_level = args.loglevel
if args.socketport:
	_socket_port = args.socketport
if args.apikey:
	_apikey = args.apikey
if args.pid:
	_pidfile = args.pid


jeedom_utils.set_log_level(_log_level)

logging.info('Start jMQTT python daemon')
logging.info('Plugin : '+str(_plugin))
logging.info('Log level : '+str(_log_level))
logging.info('Socket port : '+str(_socket_port))
logging.info('PID file : '+str(_pidfile))
logging.debug('Apikey : '+str(_apikey))

signal.signal(signal.SIGINT, signal_handler)
signal.signal(signal.SIGTERM, signal_handler)

jmqttclients = {}

try:
	jeedom_utils.write_pid(str(_pidfile))
	jeedomsocket = jeedom_socket(port=_socket_port,address=_socket_host)
	listen()
except Exception as e:
	logging.error('Fatal error : '+str(e))
	shutdown()
