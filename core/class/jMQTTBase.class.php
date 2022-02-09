<?php
/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__  . '/../../resources/mosquitto_topic_matches_sub.php';

class jMQTTBase {

	const MQTTCLIENT_OK = 'ok';
	const MQTTCLIENT_POK = 'pok';
	const MQTTCLIENT_NOK = 'nok';

	const CACHE_DAEMON_CONNECTED = 'daemonConnected';
	const CACHE_MQTTCLIENT_CONNECTED = 'mqttClientConnected';


	// on_daemon_disconnect is called by jmqttd.php then it calls on_daemon_disconnect method in plugin class
	public static function on_daemon_disconnect($pluginClass, $id) {

		// if daemon is disconnected from Jeedom, consider the MQTT Client as disconnected too
		if (jMQTT::getMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED))
			self::on_mqtt_disconnect($pluginClass, $id);

		// Save in cache that daemon is disconnected
		jMQTT::setMqttClientStateCache($id, self::CACHE_DAEMON_CONNECTED, false);
		// And call on_daemon_disconnect()
		try {
				$pluginClass::on_daemon_disconnect($id);
		} catch (Throwable $t) {
				log::add($pluginClass, 'error', sprintf('on_daemon_disconnect raised an Exception : %s', $t->getMessage()));
		}
	}

	// on_mqtt_connect is called by jmqttd.php then it calls on_mqtt_connect method in plugin class
	public static function on_mqtt_connect($pluginClass, $id) {
		// Save in cache that Mqtt Client is connected
		jMQTT::setMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED, true);
		// And call on_mqtt_connect()
		try {
				$pluginClass::on_mqtt_connect($id);
		} catch (Throwable $t) {
				log::add($pluginClass, 'error', sprintf('on_mqtt_connect raised an Exception : %s', $t->getMessage()));
		}
	}

	// on_mqtt_disconnect is called by jmqttd.php then it calls on_mqtt_disconnect method in plugin class
	public static function on_mqtt_disconnect($pluginClass, $id) {
		// Save in cache that Mqtt Client is disconnected
		jMQTT::setMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED, false);
		// And call on_mqtt_disconnect()
		try {
				$pluginClass::on_mqtt_disconnect($id);
		} catch (Throwable $t) {
				log::add($pluginClass, 'error', sprintf('on_mqtt_disconnect raised an Exception : %s', $t->getMessage()));
		}
	}

	// on_mqtt_message is called by jmqttd.php then it calls on_mqtt_message method in plugin class
	public static function on_mqtt_message($pluginClass, $id, $topic, $payload, $qos, $retain) {
		// call on_mqtt_message()
		try {
				$pluginClass::on_mqtt_message($id, $topic, $payload, $qos, $retain);
		} catch (Throwable $t) {
				log::add($pluginClass, 'error', sprintf('on_mqtt_message raised an Exception : %s', $t->getMessage()));
		}
	}

	private static function send_to_mqtt_daemon($pluginClass, $params) {
		$daemon_info = jMQTT::deamon_info();
		if ($daemon_info['state'] != 'ok') {
			throw new Exception("Le démon n'est pas démarré");
		}
		$params['apikey'] = jeedom::getApiKey($pluginClass);
		$payload = json_encode($params);
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('pythonsocketport', $pluginClass, jMQTT::DEFAULT_PYTHON_PORT));
		socket_write($socket, $payload, strlen($payload));
		socket_close($socket);
	}

	public static function new_mqtt_client($pluginClass, $id, $hostname, $params = array()) {
		$params['cmd']                  = 'newMqttClient';
		$params['id']                   = $id;
		$params['hostname']             = $hostname;
		$params['callback']             = 'ws://127.0.0.1:'.config::byKey('websocketport', $pluginClass, jMQTT::DEFAULT_WEBSOCKET_PORT).'/plugins/jMQTT/resources/jmqttd/jmqttd.php';

		// set port IF (port not 0 and numeric) THEN (intval) ELSE (default for TLS and clear MQTT) #DoubleTernaryAreCute
		$params['port']=($params['port'] != 0 && is_numeric($params['port'])) ? intval($params['port']) : (($params['tls']) ? 8883 : 1883);

		self::send_to_mqtt_daemon($pluginClass, $params);
	}

	public static function remove_mqtt_client($pluginClass, $id) {
		$params['cmd']='removeMqttClient';
		$params['id']=$id;
		self::send_to_mqtt_daemon($pluginClass, $params);
	}

	public static function subscribe_mqtt_topic($pluginClass, $id, $topic, $qos = 1) {
		if (empty($topic)) return;
		$params['cmd']='subscribeTopic';
		$params['id']=$id;
		$params['topic']=$topic;
		$params['qos']=$qos;
		self::send_to_mqtt_daemon($pluginClass, $params);
	}

	public static function unsubscribe_mqtt_topic($pluginClass, $id, $topic) {
		if (empty($topic)) return;
		$params['cmd']='unsubscribeTopic';
		$params['id']=$id;
		$params['topic']=$topic;
		self::send_to_mqtt_daemon($pluginClass, $params);
	}

	public static function publish_mqtt_message($pluginClass, $id, $topic, $payload, $qos = 1, $retain = false) {
		if (empty($topic)) return;
		$params['cmd']='messageOut';
		$params['id']=$id;
		$params['topic']=$topic;
		$params['payload']=$payload;
		$params['qos']=$qos;
		$params['retain']=$retain;
		self::send_to_mqtt_daemon($pluginClass, $params);
	}
}
