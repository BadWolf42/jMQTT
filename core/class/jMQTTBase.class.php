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

	// Do not rely on backtrace as it is designed for debug and performances cannot be guaranteed
	// private static function get_calling_class() {
	//    $backtrace = debug_backtrace();
	//    foreach ($backtrace as $stackFrame) {
	//       if (array_key_exists('class', $stackFrame) && $stackFrame['class'] != __CLASS__) return $stackFrame['class'];
	//    }
	// }


	public static function deamon_info($pluginClass) {
		$return = array();
		$return['log'] = $pluginClass;
		$return['state'] = 'nok';
		$return['launchable'] = 'nok';

		$python_daemon = false;
		$websocket_daemon = false;

		$pid_file1 = jeedom::getTmpFolder($pluginClass) . '/jmqttd.py.pid';
		if (file_exists($pid_file1)) {
			if (@posix_getsid(trim(file_get_contents($pid_file1)))) {
				$python_daemon = true;
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file1 . ' 2>&1 > /dev/null');
				jMQTT::deamon_stop();
			}
		}

		$pid_file2 = jeedom::getTmpFolder($pluginClass) . '/jmqttd.php.pid';
		if (file_exists($pid_file2)) {
			if (@posix_getsid(trim(file_get_contents($pid_file2)))) {
				$websocket_daemon = true;
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file2 . ' 2>&1 > /dev/null');
				jMQTT::deamon_stop();
			}
		}

		if($python_daemon && $websocket_daemon){
			$return['state'] = 'ok';
		}

		if (config::byKey('pythonsocketport', $pluginClass, jMQTT::DEFAULT_PYTHON_PORT) != config::byKey('websocketport', $pluginClass, jMQTT::DEFAULT_WEBSOCKET_PORT)) {
			$return['launchable'] = 'ok';
		}
		return $return;
	}

	public static function log_missing_callback($pluginClass, $functionName) {
		switch ($functionName) {
			case 'on_daemon_connect':
				log::add($pluginClass, 'debug', 'You need to implement "public static function on_daemon_connect($id)" in the class \''.$pluginClass.'\' to handle daemon connect event.');
				break;
			case 'on_daemon_disconnect':
				log::add($pluginClass, 'debug', 'You need to implement "public static function on_daemon_disconnect($id)" in the class \''.$pluginClass.'\' to handle daemon disconnect event.');
				break;
			case 'on_mqtt_connect':
				log::add($pluginClass, 'debug', 'You need to implement "public static function on_mqtt_connect($id)" in the class \''.$pluginClass.'\' to handle mqtt connect event.');
				break;
			case 'on_mqtt_disconnect':
				log::add($pluginClass, 'debug', 'You need to implement "public static function on_mqtt_disconnect($id)" in the class \''.$pluginClass.'\' to handle mqtt disconnect event.');
				break;
			case 'on_mqtt_message':
				log::add($pluginClass, 'debug', 'You need to implement "public static function on_mqtt_message($id, $topic, $payload, $qos, $retain)" in the class \''.$pluginClass.'\' to handle mqtt messages.');
				break;
			default:
				log::add($pluginClass, 'debug', 'You need to implement ... "public static ... function on_mq... What?! You should have never came back here! Don\'t read this! The faulty plugin is here => \''.$pluginClass . '\'');
				break;
		}
	}

	// on_daemon_connect is called by jmqttd.php then it calls on_daemon_connect method in plugin class
	public static function on_daemon_connect($pluginClass, $id) {
		// Save in cache that daemon is connected
		jMQTT::setMqttClientStateCache($id, self::CACHE_DAEMON_CONNECTED, true);
		// And call plugin on_daemon_connect()
		if(method_exists($pluginClass, 'on_daemon_connect')) {
			try {
				$pluginClass::on_daemon_connect($id);
			} catch (Throwable $t) {
					log::add($pluginClass, 'error', sprintf('on_daemon_connect raised an Exception : %s', $t->getMessage()));
			}
		} else {
			self::log_missing_callback($pluginClass, 'on_daemon_connect');
		}
	}

	// on_daemon_disconnect is called by jmqttd.php then it calls on_daemon_disconnect method in plugin class
	public static function on_daemon_disconnect($pluginClass, $id) {

		// if daemon is disconnected from Jeedom, consider the MQTT Client as disconnected too
		if (jMQTT::getMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED))
			self::on_mqtt_disconnect($pluginClass, $id);

		// Save in cache that daemon is disconnected
		jMQTT::setMqttClientStateCache($id, self::CACHE_DAEMON_CONNECTED, false);
		// And call plugin on_daemon_disconnect()
		if(method_exists($pluginClass, 'on_daemon_disconnect')) {
			try {
					$pluginClass::on_daemon_disconnect($id);
			} catch (Throwable $t) {
					log::add($pluginClass, 'error', sprintf('on_daemon_disconnect raised an Exception : %s', $t->getMessage()));
			}
		} else {
			self::log_missing_callback($pluginClass, 'on_daemon_disconnect');
		}
	}

	// on_mqtt_connect is called by jmqttd.php then it calls on_mqtt_connect method in plugin class
	public static function on_mqtt_connect($pluginClass, $id) {
		// Save in cache that Mqtt Client is connected
		jMQTT::setMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED, true);
		// And call plugin on_mqtt_connect()
		if(method_exists($pluginClass, 'on_mqtt_connect')) {
			try {
					$pluginClass::on_mqtt_connect($id);
			} catch (Throwable $t) {
					log::add($pluginClass, 'error', sprintf('on_mqtt_connect raised an Exception : %s', $t->getMessage()));
			}
		} else {
			self::log_missing_callback($pluginClass, 'on_mqtt_connect');
		}
	}

	// on_mqtt_disconnect is called by jmqttd.php then it calls on_mqtt_disconnect method in plugin class
	public static function on_mqtt_disconnect($pluginClass, $id) {
		// Save in cache that Mqtt Client is disconnected
		jMQTT::setMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED, false);
		// And call plugin on_mqtt_disconnect()
		if(method_exists($pluginClass, 'on_mqtt_disconnect')) {
			try {
					$pluginClass::on_mqtt_disconnect($id);
			} catch (Throwable $t) {
					log::add($pluginClass, 'error', sprintf('on_mqtt_disconnect raised an Exception : %s', $t->getMessage()));
			}
		} else {
			self::log_missing_callback($pluginClass, 'on_mqtt_disconnect');
		}
	}

	// on_mqtt_message is called by jmqttd.php then it calls on_mqtt_message method in plugin class
	public static function on_mqtt_message($pluginClass, $id, $topic, $payload, $qos, $retain) {
		// call plugin on_mqtt_message()
		if(method_exists($pluginClass, 'on_mqtt_message')) {
			try {
					$pluginClass::on_mqtt_message($id, $topic, $payload, $qos, $retain);
			} catch (Throwable $t) {
					log::add($pluginClass, 'error', sprintf('on_mqtt_message raised an Exception : %s', $t->getMessage()));
			}
		} else {
			self::log_missing_callback($pluginClass, 'on_mqtt_message');
		}
	}

	private static function send_to_mqtt_daemon($pluginClass, $params) {
		$daemon_info = self::deamon_info($pluginClass);
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
