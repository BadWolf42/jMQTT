<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/../../resources/mosquitto_topic_matches_sub.php';

//
if (file_exists(__DIR__ . '/../../resources/JsonPath-PHP/vendor/autoload.php'))
	require_once __DIR__ . '/../../resources/JsonPath-PHP/vendor/autoload.php';

include_file('core', 'mqttApiRequest', 'class', 'jMQTT');
include_file('core', 'jMQTTCmd', 'class', 'jMQTT');

class jMQTT extends eqLogic {

	const FORCE_DEPENDANCY_INSTALL = 'forceDepInstall';

	const API_TOPIC = 'api';
	const API_ENABLE = 'enable';
	const API_DISABLE = 'disable';

	const CLIENT_STATUS = 'status';
	const OFFLINE = 'offline';
	const ONLINE = 'online';

	const MQTTCLIENT_OK = 'ok';
	const MQTTCLIENT_POK = 'pok';
	const MQTTCLIENT_NOK = 'nok';

	const CONF_KEY_TYPE = 'type';
	const CONF_KEY_BRK_ID = 'brkId';
	const CONF_KEY_MQTT_CLIENT_ID = 'mqttId';
	const CONF_KEY_MQTT_ADDRESS = 'mqttAddress';
	const CONF_KEY_MQTT_PORT = 'mqttPort';
	const CONF_KEY_MQTT_USER = 'mqttUser';
	const CONF_KEY_MQTT_PASS = 'mqttPass';
	const CONF_KEY_MQTT_PUB_STATUS = 'mqttPubStatus';
	const CONF_KEY_MQTT_INC_TOPIC = 'mqttIncTopic';
	const CONF_KEY_MQTT_TLS = 'mqttTls';
	const CONF_KEY_MQTT_TLS_CHECK = 'mqttTlsCheck';
	const CONF_KEY_MQTT_TLS_CA = 'mqttTlsCaFile';
	const CONF_KEY_MQTT_TLS_CLI_CERT= 'mqttTlsClientCertFile';
	const CONF_KEY_MQTT_TLS_CLI_KEY = 'mqttTlsClientKeyFile';
	const CONF_KEY_MQTT_PAHO_LOG = 'mqttPahoLog';
	const CONF_KEY_QOS = 'Qos';
	const CONF_KEY_AUTO_ADD_CMD = 'auto_add_cmd';
	const CONF_KEY_AUTO_ADD_TOPIC = 'auto_add_topic';
	const CONF_KEY_API = 'api';
	const CONF_KEY_LOGLEVEL = 'loglevel';

	const CACHE_INCLUDE_MODE = 'include_mode';
	const CACHE_IGNORE_TOPIC_MISMATCH = 'ignore_topic_mismatch';
	const CACHE_DAEMON_CONNECTED = 'daemonConnected';
	const CACHE_MQTTCLIENT_CONNECTED = 'mqttClientConnected';

	const PATH_CERTIFICATES = 'data/jmqtt/certs/';

	const DEFAULT_PYTHON_PORT = 1025;
	const DEFAULT_WEBSOCKET_PORT = 1026;

	/**
	 * To define a standard jMQTT equipment
	 * jMQTT type is either self::TYP_EQPT or self::TYP_BRK.
	 * @var string standard jMQTT equipment
	 */
	const TYP_EQPT = 'eqpt';

	/**
	 * To define a jMQTT broker
	 * jMQTT type is either self::TYP_EQPT or self::TYP_BRK.
	 * @var string broker jMQTT.
	 */
	const TYP_BRK = 'broker';

	/**
	 * Data shared between preSave and postSave
	 * @var array values from preSave used for postSave actions
	 */
	private $_preSaveInformations;

	/**
	 * Data shared between preRemove and postRemove
	 * @var array values from preRemove used for postRemove actions
	 */
	private $_preRemoveInformations;
	/**
	 * Broker jMQTT object related to this object
	 * @var jMQTT broker object
	 */
	private $_broker;

	/**
	 * Status command of the broker related to this object
	 * @var jMQTTCmd
	 */
	private $_statusCmd;

	/**
	 * Log file related to this broker.
	 * Set in the daemon method; it is only visible from functions
	 * that are executed on the same thread as the daemon method.
	 * @var Mosquitto\Client $_client
	 */
	private $_log;

	/**
	 * Return a list of all templates name and file.
	 * @return list of name and file array.
	 */
	public static function templateList(){
		// log::add('jMQTT', 'debug', 'templateList()');
		$return = array();
		// Get personal templates
		foreach (ls(dirname(__FILE__) . '/../../data/template', '*.json', false, array('files', 'quiet')) as $file) {
			try {
				$content = file_get_contents(dirname(__FILE__) . '/../../data/template/' . $file);
				if (is_json($content)) {
					foreach (json_decode($content, true) as $k => $v)
						$return[] = array('[Perso] '.$k, 'plugins/jMQTT/data/template/' . $file);
				}
			} catch (Throwable $e) {}
		}
		// Get official templates
		foreach (ls(dirname(__FILE__) . '/../config/template', '*.json', false, array('files', 'quiet')) as $file) {
			try {
				$content = file_get_contents(dirname(__FILE__) . '/../config/template/' . $file);
				if (is_json($content)) {
					foreach (json_decode($content, true) as $k => $v)
						$return[] = array($k, 'plugins/jMQTT/core/config/template/' . $file);
				}
			} catch (Throwable $e) {}
		}
		return $return;
	}

	/**
	 * Return a template content (from json files).
	 * @param string $_template template name to look for
	 * @return array
	 */
	public static function templateByName($_template){
		// log::add('jMQTT', 'debug', 'templateByName("' . $_template . '")');
		// Get personal templates
		foreach (ls(dirname(__FILE__) . '/../../data/template', '*.json', false, array('files', 'quiet')) as $file) {
			try {
				$content = file_get_contents(dirname(__FILE__) . '/../../data/template/' . $file);
				if (is_json($content)) {
					foreach (json_decode($content, true) as $k => $v)
						if ('[Perso] '.$k == $_template)
							return $v;
				}
			} catch (Throwable $e) {}
		}
		// Get official templates
		foreach (ls(dirname(__FILE__) . '/../config/template', '*.json', false, array('files', 'quiet')) as $file) {
			try {
				$content = file_get_contents(dirname(__FILE__) . '/../config/template/' . $file);
				if (is_json($content)) {
					foreach (json_decode($content, true) as $k => $v)
						if ($k == $_template)
							return $v;
				}
			} catch (Throwable $e) {}
		}
		return null;
	}

	/**
	 * Return one templates content (from json file name).
	 * @param string $_filename template name to look for
	 * @return array
	 */
	public static function templateByFile($_filename = ''){
		// log::add('jMQTT', 'debug', 'templateByFile("' . $_filename . '")');
		$existing_files = self::templateList();
		$exists = false;
		foreach ($existing_files as list($n, $f))
			if ($f == $_filename) {
				$exists = true;
				break;
			}
		if (!$exists)
			throw new Exception(__('Le template demandé n\'existe pas !', __FILE__));
		// log::add('jMQTT', 'debug', '    get='.dirname(__FILE__) . '/../../../../' . $_filename);
		try {
			$content = file_get_contents(dirname(__FILE__) . '/../../../../' . $_filename);
			if (is_json($content)) {
				foreach (json_decode($content, true) as $k => $v)
					return $v;
			}
		} catch (Throwable $e) {}
		return array();
	}

	/**
	 * Split topic and jsonPath of all commands for the template file.
	 * @param string $_filename template name to look for.
	 */
	public static function templateSplitJsonPathByFile($_filename = '') {

		$content = file_get_contents(dirname(__FILE__) . '/../../data/template/' . $_filename);
		if (is_json($content)) {

			// decode template file content to json
			$templateContent = json_decode($content, true);

			// first key is the template itself
			$templateKey = array_keys($templateContent)[0];

			// if 'commands' key exists in this template
			if (array_key_exists('commands', $templateContent[$templateKey])) {

				// for each keys under 'commands'
				foreach ($templateContent[$templateKey]['commands'] as &$cmd) {

					// if 'configuration' key exists in this command
					if (array_key_exists('configuration', $cmd)) {

						// get the topic if it exists
						$topic = (array_key_exists('topic', $cmd['configuration'])) ? $cmd['configuration']['topic'] : '';

						$i = strpos($topic, '{');
						if ($i === false) {
							// Just set empty jsonPath if it doesn't exists
							if (!array_key_exists('jsonPath', $cmd['configuration']))
								$cmd['configuration']['jsonPath'] = '';
						}
						else {
							// Set cleaned Topic
							$cmd['configuration']['topic'] = substr($topic, 0, $i);

							// Split old json path
							$indexes = substr($topic, $i);
							$indexes = str_replace(array('}{', '{', '}'), array('|', '', ''), $indexes);
							$indexes = explode('|', $indexes);

							$jsonPath = '';
							// For each part of the path
							foreach ($indexes as $index) {
								// if this part contains a dot, a space or a slash, escape it
								if (strpos($index, '.') !== false || strpos($index, ' ') !== false || strpos($index, '/') !== false)
									$jsonPath .= '[\'' . $index . '\']';
								else
									$jsonPath .= '[' . $index . ']';
							}
							$cmd['configuration']['jsonPath'] = $jsonPath;
						}
					}
				}
			}

			// Save back template in the file
			$jsonExport = json_encode($templateContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			file_put_contents(dirname(__FILE__) . '/../../data/template/' . $_filename, $jsonExport);
		}
	}

	/**
	 * Split topic and jsonPath of all commands for the template file.
	 * @param string $_filename template name to look for.
	 */
	public static function moveTopicToConfigurationByFile($_filename = '') {

		$content = file_get_contents(dirname(__FILE__) . '/../../data/template/' . $_filename);
		if (is_json($content)) {

			// decode template file content to json
			$templateContent = json_decode($content, true);

			// first key is the template itself
			$templateKey = array_keys($templateContent)[0];

			// if 'configuration' key exists in this template
			if (array_key_exists('configuration', $templateContent[$templateKey])) {

				// if auto_add_cmd doesn't exists in configuration, we need to move topic from logicalId to configuration
				if (!array_key_exists(self::CONF_KEY_AUTO_ADD_TOPIC, $templateContent[$templateKey]['configuration'])) {
					$topic = $templateContent[$templateKey]['logicalId'];
					$templateContent[$templateKey]['configuration'][self::CONF_KEY_AUTO_ADD_TOPIC] = $topic;
					$templateContent[$templateKey]['logicalId'] = '';
				}
			}

			// Save back template in the file
			$jsonExport = json_encode($templateContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			file_put_contents(dirname(__FILE__) . '/../../data/template/' . $_filename, $jsonExport);
		}
	}

	/**
	 * Deletes user defined template by filename.
	 * @param string $_template template name to look for.
	 */
	public static function deleteTemplateByFile($_filename){
		// log::add('jMQTT', 'debug', 'deleteTemplateByFile("' . $_filename . '")');
		if (!isset($_filename) || is_null($_filename) || $_filename == '')
			return false;
		$existing_files = self::templateList();
		$exists = false;
		foreach ($existing_files as list($n, $f))
			if ($f == $_filename) {
				$exists = true;
				break;
			}
		if (!$exists)
			return false;
		return unlink(dirname(__FILE__) . '/../../../../' . $_filename);
	}

	/**
	 * apply a template (from json) to the current equipement.
	 * @param string $_template name of the template to apply
	 */
	public function applyTemplate($_template, $_topic, $_keepCmd = true){

		if ($this->getType() != self::TYP_EQPT) {
			return true;
		}

		$template = self::templateByName($_template);
		if (is_null($template)) {
			return true;
		}

		// Raise up the flag that cmd topic mismatch must be ignored
		$this->setCache(self::CACHE_IGNORE_TOPIC_MISMATCH, 1);

		// import template
		$this->import($template, $_keepCmd);

		// complete eqpt topic
		$this->setTopic(sprintf($template['configuration'][self::CONF_KEY_AUTO_ADD_TOPIC], $_topic));
		$this->save();

		// complete cmd topics
		foreach ($this->getCmd() as $cmd) {
			$cmd->setTopic(sprintf($cmd->getTopic(), $_topic));
			$cmd->save();
		}

		// remove topic mismatch ignore flag
		$this->setCache(self::CACHE_IGNORE_TOPIC_MISMATCH, 0);
	}

	/**
	 * create a template from the current equipement (to json).
	 * @param string $_template name of the template to create
	 */
	public function createTemplate($_template){

		if ($this->getType() != self::TYP_EQPT) {
			return true;
		}

		// Export
		$exportedTemplate[$_template] = $this->export();

		// Looking for baseTopic from equipement
		$baseTopic = $this->getTopic();
		if (substr($baseTopic, -1) == '#' || substr($baseTopic, -1) == '+') { $baseTopic = substr($baseTopic, 0, -1); }
		if (substr($baseTopic, -1) == '/') { $baseTopic = substr($baseTopic, 0, -1); }

		// Add string format to eqLogic configuration
		$exportedTemplate[$_template]['configuration'][self::CONF_KEY_AUTO_ADD_TOPIC] = str_replace($baseTopic, '%s', $this->getTopic());

		// older version of Jeedom (4.2 and bellow) export commands in 'cmd'
		// Fixed here : https://github.com/jeedom/core/commit/05b8ecf34b405d5a0a0bb7356f8e3ecb1cf7fa91
		if (array_key_exists('cmd', $exportedTemplate[$_template]))
		{
			// Rename 'cmd' to 'commands' for Jeedom import ...
			$exportedTemplate[$_template]['commands'] = $exportedTemplate[$_template]['cmd'];
			unset($exportedTemplate[$_template]['cmd']);
		}

		// convert topic to string format
		foreach ($exportedTemplate[$_template]['commands'] as $key => $command) {
			if(isset($command['configuration']['topic'])) {
				$exportedTemplate[$_template]['commands'][$key]['configuration']['topic'] = str_replace($baseTopic, '%s', $command['configuration']['topic']);
			}
		}

		// Remove brkId from eqpt configuration
		unset($exportedTemplate[$_template]['configuration'][self::CONF_KEY_BRK_ID]);

		// Convert and save to file
		$jsonExport = json_encode($exportedTemplate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		$formatedTemplateName = str_replace(' ', '_', $_template);
		$formatedTemplateName = preg_replace('/[^a-zA-Z0-9_]+/', '', $formatedTemplateName);
		file_put_contents(dirname(__FILE__) . '/../../data/template/' . $formatedTemplateName . '.json', $jsonExport);
	}

	/**
	 * Create a new equipment given its name, subscription topic, type and broker the equipment is related to.
	 * IMPORTANT: broker can be null, and then this is the responsability of the caller to attach the new equipment to a broker.
	 * Equipment is enabled, and saved.
	 * @param jMQTT $broker broker the equipment is related to
	 * @param string $name equipment name
	 * @param string $topic subscription topic
	 * return new jMQTT object
	 */
	public static function createEquipment($broker, $name, $topic) {

		log::add('jMQTT', 'debug', 'Initialize equipment ' . $name . ', topic=' . $topic);
		$eqpt = new jMQTT();
		$eqpt->setName($name);
		$eqpt->setIsEnable(1);
		$eqpt->setTopic($topic);

		if (is_object($broker)) {
			$broker->log('info', 'Create equipment ' . $name . ', topic=' . $topic);
			$eqpt->setBrkId($broker->getId());
		}
		$eqpt->save();

		// Advise the desktop page (jMQTT.js) that a new equipment has been added
		event::add('jMQTT::eqptAdded', array('eqlogic_name' => $name));

		return $eqpt;
	}

	/**
	 * Clean this equipment from parameters that are no more used due to plugin evolution
	 */
	public function cleanEquipment() {
		$this->setConfiguration('prev_Qos', null);
		$this->setConfiguration('prev_isActive', null);
		$this->setConfiguration('previousIsEnable', null);
		$this->setConfiguration('previousIsVisible', null);
		$this->setConfiguration('reload_d', null);
		$this->setConfiguration('topic', null);
	}

	/**
	 * Overload the equipment copy method
	 * All information are copied but: suscribed topic (left empty), enable status (left disabled) and
	 * information commands.
	 * @param string $_name new equipment name
	 */
	public function copy($_name) {

		$this->log('info', 'Copying equipment ' . $this->getName() . ' as ' . $_name);

		// Clone the equipment and change properties that shall be changed
		// . new id will be given at saving
		// . suscribing topic let empty to force the user to change it
		// . remove commands: they are defined at the next step (as done in the parent method)
		/** @var jMQTT $eqLogicCopy */
		$eqLogicCopy = clone $this;
		$eqLogicCopy->setId('');
		$eqLogicCopy->setName($_name);
		$eqLogicCopy->setIsEnable(0);
		$eqLogicCopy->setTopic('');
		foreach ($eqLogicCopy->getCmd() as $cmd) {
			$cmd->remove();
		}
		$eqLogicCopy->save();

		// Clone commands
		/** @var jMQTTCmd $cmd */
		foreach ($this->getCmd() as $cmd) {
			/** @var jMQTTCmd $cmdCopy */
			$cmdCopy = clone $cmd;
			$cmdCopy->setId('');
			$cmdCopy->setEqLogic_id($eqLogicCopy->getId());
			$cmdCopy->setEqLogic($eqLogicCopy);
			$cmdCopy->save();
			$this->log('info', 'Cloning ' . $cmd->getType() . ' command ' . $cmd->getName());
		}

		return $eqLogicCopy;
	}

	/**
	 * Return a full export (inc. commands) of this eqLogic as an array.
	 * @return array
	 */
	public function full_export() {
		$return = $this->toArray();
		$return['cmd'] = array();
		foreach ($this->getCmd() as $cmd) {
			$return['cmd'][] = $cmd->full_export();
		}
		return $return;
	}

	/**
	 * Return jMQTT objects of type broker
	 * @return jMQTT[]
	 */
	public static function getBrokers() {
		$type = json_encode(array('type' => self::TYP_BRK));
		/** @var jMQTT[] $brokers */
		$brokers = self::byTypeAndSearhConfiguration(jMQTT::class, substr($type, 1, -1));
		$returns = array();
		foreach ($brokers as $broker) {
			$returns[$broker->getId()] = $broker;
		}
		return $returns;
	}

	/**
	 * Return jMQTT objects of type standard equipement
	 * @return jMQTT[int][int] array of arrays of jMQTT objects
	 */
	public static function getNonBrokers() {
		/** @var jMQTT[] $eqls */
		$eqls = self::byType(jMQTT::class);
		$returns = array();
		foreach ($eqls as $eql) {
			if ($eql->getType() != self::TYP_BRK) {
				$returns[$eql->getBrkId()][] = $eql;
			}
		}
		return $returns;
	}

	/**
	 * subscribe topic
	 */
	public function subscribeTopic($topic, $qos){

		// If broker eqpt is disabled, don't need to send subscribe
		$broker = $this->getBroker();
		if(!$broker->getIsEnable()) return;

		if (empty($topic)){
			$this->log('info', ($this->getType() == self::TYP_EQPT?'Equipement ':'Broker ') . $this->getName() . ': no subscription (empty topic)');
			return;
		}

		$this->log('info', ($this->getType() == self::TYP_EQPT?'Equipement ':'Broker ') . $this->getName() . ': subscribes to "' . $topic . '" with Qos=' . $qos);
		self::subscribe_mqtt_topic($this->getBrkId(), $topic, $qos);
	}

	/**
	 * Unsubscribe topic ONLY if no other enabled eqpt linked to the same broker subscribes the same topic
	 */
	public function unsubscribeTopic($topic, $brkId = null){
		if (empty($topic)) return;

		if (is_null($brkId)) $brkId = $this->getBrkId();

		// If broker eqpt is disabled or MqttClient is not connected or stopped, don't need to send unsubscribe
		$broker = self::getBrokerFromId($brkId);
		if(!$broker->getIsEnable() || $broker->getMqttClientState() == self::MQTTCLIENT_POK || $broker->getMqttClientState() == self::MQTTCLIENT_NOK) return;

		//Find eqLogic using the same topic
		$topicConfiguration = substr(json_encode(array(self::CONF_KEY_AUTO_ADD_TOPIC => $topic)), 1, -1);
		$eqLogics = jMQTT::byTypeAndSearhConfiguration(__CLASS__, $topicConfiguration);
		$count = 0;
		foreach ($eqLogics as $eqLogic) {
			// If it's attached to the same broker and enabled and it's not "me"
			if ($eqLogic->getBrkId() == $brkId && $eqLogic->getIsEnable() && $eqLogic->getId() != $this->getId()) $count++;
		}

		//if there is no other eqLogic using the same topic, we can unsubscribe
		if (!$count) {
			$this->log('info', ($this->getType() == self::TYP_EQPT?'Equipement ':'Broker ') . $this->getName() . ': unsubscribes from "' . $topic . '"');
			self::unsubscribe_mqtt_topic($brkId, $topic);
		}
	}

	/**
	 * Overload preSave to apply some checks/initialization and prepare postSave
	 */
	public function preSave() {

		//Check Type: No Type => self::TYP_EQPT
		if ($this->getType() != self::TYP_BRK && $this->getType() != self::TYP_EQPT){
			$this->setType(self::TYP_EQPT);
		}

		// Check eqType_name: should be __CLASS__
		if ($this->eqType_name != __CLASS__) {
			$this->setEqType_name(__CLASS__);
		}

		// ------------------------ Broker eqpt ------------------------
		if ($this->getType() == self::TYP_BRK) {

			// Check for a broker eqpt with the same name (which is not this)
			foreach(self::getBrokers() as $broker) {
				if ($broker->getName() == $this->getName() && $broker->getId() != $this->getId()) {
					throw new Exception(__('Un broker portant le même nom existe déjà : ', __FILE__) . $this->getName());
				}
			}

			// --- New broker ---
			if ($this->getId() == '') {
			}
			// --- Existing broker ---
			else {
				// Check certificate binding information if TLS is disabled
				if (!boolval($this->getConf(self::CONF_KEY_MQTT_TLS))) {
					// If a CA is specified and this file doesn't exists, remove it
					if($this->getConf(self::CONF_KEY_MQTT_TLS_CA) != $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CA) && !file_exists(realpath(dirname(__FILE__) . '/../../' . self::PATH_CERTIFICATES . $this->getConf(self::CONF_KEY_MQTT_TLS_CA))))
						$this->setConfiguration(self::CONF_KEY_MQTT_TLS_CA, $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CA));
					if($this->getConf(self::CONF_KEY_MQTT_TLS_CLI_CERT) != $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CLI_CERT) && !file_exists(realpath(dirname(__FILE__) . '/../../' . self::PATH_CERTIFICATES . $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_CERT))))
						$this->setConfiguration(self::CONF_KEY_MQTT_TLS_CLI_CERT, $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CLI_CERT));
					if($this->getConf(self::CONF_KEY_MQTT_TLS_CLI_KEY) != $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CLI_KEY) && !file_exists(realpath(dirname(__FILE__) . '/../../' . self::PATH_CERTIFICATES . $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_KEY))))
						$this->setConfiguration(self::CONF_KEY_MQTT_TLS_CLI_KEY, $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CLI_KEY));
				}
			}
		}
		// ------------------------ Normal eqpt ------------------------
		else{

			// --- New eqpt ---
			if ($this->getId() == '') {
			}
			// --- Existing eqpt ---
			else {
			}
		}


		// It's time to gather informations that will be used in postSave
		if ($this->getId() == '') $this->_preSaveInformations = null; // New eqpt => Nothing to collect
		else { // Existing eqpt

			// load eqLogic from DB
			$eqLogic = self::byId($this->getId());
			$this->_preSaveInformations = array(
				'name'                  => $eqLogic->getName(),
				'isEnable'              => $eqLogic->getIsEnable(),
				self::CONF_KEY_API      => $eqLogic->isApiEnable(),
				'topic'                 => $eqLogic->getTopic(),
				self::CONF_KEY_BRK_ID   => $eqLogic->getBrkId()
			);

			$backupVal = array( // load trivials eqLogic from DB
				self::CONF_KEY_LOGLEVEL,        self::CONF_KEY_MQTT_CLIENT_ID,
				self::CONF_KEY_MQTT_ADDRESS,    self::CONF_KEY_MQTT_PORT,
				self::CONF_KEY_MQTT_USER,       self::CONF_KEY_MQTT_PASS,
				self::CONF_KEY_MQTT_PUB_STATUS, self::CONF_KEY_MQTT_INC_TOPIC,
				self::CONF_KEY_MQTT_TLS,        self::CONF_KEY_MQTT_TLS_CHECK,
				self::CONF_KEY_MQTT_TLS_CA,     self::CONF_KEY_MQTT_TLS_CLI_CERT,
				self::CONF_KEY_MQTT_PAHO_LOG,   self::CONF_KEY_MQTT_TLS_CLI_KEY,
				self::CONF_KEY_QOS);
			foreach ($backupVal as $key)
				$this->_preSaveInformations[$key] = $eqLogic->getConf($key);
		}
	}

	/**
	 * postSave apply changes to MqttClient and log
	 */
	public function postSave() {

		// ------------------------ Broker eqpt ------------------------
		if ($this->getType() == self::TYP_BRK) {

			// --- New broker ---
			if (is_null($this->_preSaveInformations)) {

				// Create log of this broker
				config::save('log::level::' . $this->getMqttClientLogFile(), '{"100":"0","200":"0","300":"0","400":"0","1000":"0","default":"1"}', 'jMQTT');

				// Create Status cmd
				$this->createMqttClientStatusCmd();

				// Enabled => Start MqttClient
				if ($this->getIsEnable()) $this->startMqttClient();
			}
			// --- Existing broker ---
			else {

				$stopped = ($this->getMqttClientState() == self::MQTTCLIENT_NOK);
				$startRequested = false;

				// isEnable changed
				if ($this->_preSaveInformations['isEnable'] != $this->getIsEnable()) {
					if ($this->getIsEnable()) {
						$startRequested = true; //If nothing happens in between, it will be restarted
					} else {
						if (!$stopped) {
							$this->stopMqttClient();
							$stopped = true;
						}
					}
				}

				// LogLevel change
				if ($this->_preSaveInformations[self::CONF_KEY_LOGLEVEL] != $this->getConf(self::CONF_KEY_LOGLEVEL)) {
					config::save('log::level::' . $this->getMqttClientLogFile(), $this->getConf(self::CONF_KEY_LOGLEVEL), __CLASS__);
				}

				// Name changed
				if ($this->_preSaveInformations['name'] != $this->getName()) {

					$old_log = __CLASS__ . '_' . str_replace(' ', '_', $this->_preSaveInformations['name']);
					$new_log = $this->getMqttClientLogFile(true);
					if (file_exists(log::getPathToLog($old_log))) {
						rename(log::getPathToLog($old_log), log::getPathToLog($new_log));
					}
					config::save('log::level::' . $new_log, config::byKey('log::level::' . $old_log, __CLASS__), __CLASS__);
					config::remove('log::level::' . $old_log, __CLASS__);
				}

				// 'mqttAddress', 'mqttPort', 'mqttUser', 'mqttPass', etc changed
				$checkChanged = array(self::CONF_KEY_MQTT_ADDRESS,      self::CONF_KEY_MQTT_PORT,
									  self::CONF_KEY_MQTT_USER,         self::CONF_KEY_MQTT_PASS,
									  self::CONF_KEY_MQTT_PUB_STATUS,   self::CONF_KEY_MQTT_TLS,
									  self::CONF_KEY_MQTT_TLS_CHECK,    self::CONF_KEY_MQTT_TLS_CA,
									  self::CONF_KEY_MQTT_TLS_CLI_CERT, self::CONF_KEY_MQTT_TLS_CLI_KEY,
									  self::CONF_KEY_MQTT_PAHO_LOG);
				foreach ($checkChanged as $key) {
					if ($this->_preSaveInformations[$key] != $this->getConf($key)) {
						if (!$stopped) {
							$this->stopMqttClient();
							$stopped = true;
						}
						$startRequested = true;
						break;
					}
				}

				// ClientId changed
				if ($this->_preSaveInformations[self::CONF_KEY_MQTT_CLIENT_ID] != $this->getConf(self::CONF_KEY_MQTT_CLIENT_ID)) {

					// Just Need to restart MqttClient (it needs to reconnect using new ClientId and know about the new willTopic)
					if (!$stopped) {
						$this->stopMqttClient();
						$stopped = true;
					}
					$startRequested = true;
				}

				// IncludeModeTopic changed
				if ($this->_preSaveInformations[self::CONF_KEY_MQTT_INC_TOPIC] != $this->getConf(self::CONF_KEY_MQTT_INC_TOPIC)) {
					// If MqttClient not stopped and includeMode is enabled
					if (!$stopped && $this->getIncludeMode()) {
						//Unsubscribe previous include topic
						$this->unsubscribeTopic($this->_preSaveInformations[self::CONF_KEY_MQTT_INC_TOPIC]);
						//Subscribe the new one
						$this->subscribeTopic($this->getConf(self::CONF_KEY_MQTT_INC_TOPIC), $this->getQos());
					}
				}

				// APIEnabled changed
				if ($this->_preSaveInformations[self::CONF_KEY_API] != $this->isApiEnable()) {
					// If MqttClient not stopped
					if (!$stopped) {
						// If API is now enabled
						if ($this->isApiEnable()) {
							//Subscribe API topic
							$this->subscribeTopic($this->getMqttApiTopic(), $this->getQos());
						}
						else {
							//Unsubscribe API topic
							$this->unsubscribeTopic($this->getMqttApiTopic());
						}
					}
				}

				// In the end, does MqttClient need to be Started
				if($startRequested && $this->getIsEnable()){
					$this->startMqttClient();
				}
			}
		}
		// ------------------------ Normal eqpt ------------------------
		else{

			// --- New eqpt ---
			if (is_null($this->_preSaveInformations)) {

				// Enabled => subscribe
				if ($this->getIsEnable()) $this->subscribeTopic($this->getTopic(), $this->getQos());
			}
			// --- Existing eqpt ---
			else {

				$unsubscribed = false;
				$subscribeRequested = false;

				// isEnable changed
				if ($this->_preSaveInformations['isEnable'] != $this->getIsEnable()) {
					if ($this->getIsEnable()) {
						$subscribeRequested = true;
						$this->listenersAdd();
					} else {
						if(!$unsubscribed){
							//Unsubscribe previous topic (if topic changed too)
							$this->unsubscribeTopic($this->_preSaveInformations['topic']);
							$unsubscribed = true;
						}
						$this->listenersRemove();
					}
				}

				// brkId changed (action moveToBroker in jMQTT.ajax.php)
				if ($this->_preSaveInformations[self::CONF_KEY_BRK_ID] != $this->getConf(self::CONF_KEY_BRK_ID)) {

					//need to unsubscribe the topic on the PREVIOUS Broker
					$this->unsubscribeTopic($this->_preSaveInformations['topic'], $this->_preSaveInformations[self::CONF_KEY_BRK_ID]);
					//and subscribe on the new broker
					$subscribeRequested = true;
				}

				// topic changed
				if ($this->_preSaveInformations['topic'] != $this->getTopic()) {
					if(!$unsubscribed){
						//Unsubscribed previous topic
						$this->unsubscribeTopic($this->_preSaveInformations['topic']);
						$unsubscribed = true;
					}
					$subscribeRequested = true;
				}

				// QoS changed
				if ($this->_preSaveInformations[self::CONF_KEY_QOS] != $this->getConf(self::CONF_KEY_QOS)) {
					// resubscribe will take new QoS over
					$subscribeRequested = true;
				}

				// In the end, does topic need to be subscribed
				if($subscribeRequested && $this->getIsEnable()){
					$this->subscribeTopic($this->getTopic(), $this->getQos());
				}
			}
		}
	}

	/**
	 * preRemove method to check if the MQTT Client shall be restarted
	 */
	public function preRemove() {

		// ------------------------ Broker eqpt ------------------------
		if ($this->getType() == self::TYP_BRK) {

			$this->log('info', 'removing broker ' . $this->getName());

			// Disable first the broker to Stop MqttClient
			if ($this->getIsEnable()) {
				$this->setIsEnable(0);
				$this->save();

				// Wait up to 10s for MqttClient stopped
				for ($i=0; $i < 40; $i++) {
					if ($this->getMqttClientState() == self::MQTTCLIENT_NOK) break;
					usleep(250000);
				}
			}

			// Disable all equipments attached to the broker
			foreach (self::byBrkId($this->getId()) as $eqpt) {
				if ($this->getId() != $eqpt->getId()) {
					$eqpt->setIsEnable(0);
					$eqpt->save();
				}
			}
		}
		// ------------------------ Normal eqpt ------------------------
		else {
			$this->log('info', 'removing equipment ' . $this->getName());
		}


		// load eqLogic from DB
		$this->_preRemoveInformations = array(
			'id' => $this->getId()
		);
	}

	/**
	 * postRemove callback to restart the deamon when deemed necessary (see also preRemove)
	 */
	public function postRemove() {
		// ------------------------ Broker eqpt ------------------------
		if ($this->getType() == self::TYP_BRK) {

			// Suppress the log file
			$log = $this->getMqttClientLogFile();
			if (file_exists(log::getPathToLog($log))) {
				unlink(log::getPathToLog($log));
			}
			config::remove('log::level::' . $log, 'jMQTT');

			// Remove all equipments attached to the removed broker (id saved in _preRemoveInformations)
			foreach (self::byBrkId($this->_preRemoveInformations['id']) as $eqpt) {
				$eqpt->remove();
			}
		}
		// ------------------------ Normal eqpt ------------------------
		else {
			//If eqpt were enabled, just need to unsubscribe
			if($this->getIsEnable()) $this->unsubscribeTopic($this->getTopic());
		}
	}

	public static function health() {
		$return = array();
		foreach(self::getBrokers() as $broker) {
			if(!$broker->getIsEnable())
				continue;
			$mosqHost = $broker->getConf(self::CONF_KEY_MQTT_ADDRESS);
			$mosqPort = $broker->getConf(self::CONF_KEY_MQTT_PORT);
			$socket = socket_create(AF_INET, SOCK_STREAM, 0);
			$state = false;
			if ($socket !== false) {
				$state = socket_connect ($socket , $mosqHost, $mosqPort);
				socket_close($socket);
			}

			$return[] = array(
				'test' => __('Accès au broker', __FILE__) . ' ' . $broker->getName(),
				'result' => $state ? __('OK', __FILE__) : __('NOK', __FILE__),
				'advice' => $state ? '' : __('Vérifier les paramètres de connexion réseau', __FILE__),
				'state' => $state
			);

			if ($state) {
				$info = $broker->getMqttClientInfo();
				$return[] = array(
					'test' => __('Configuration du broker', __FILE__) . ' ' . $broker->getName(),
					'result' => strtoupper($info['launchable']),
					'advice' => ($info['launchable'] != 'ok' ? $info['message'] : ''),
					'state' => ($info['launchable'] == 'ok')
				);
				if (end($return)['state']) {
					$return[] = array(
						'test' => __('Connexion au broker', __FILE__) . ' ' . $broker->getName(),
						'result' => strtoupper($info['state']),
						'advice' => ($info['state'] != 'ok' ? $info['message'] : ''),
						'state' => ($info['state'] == 'ok')
					);
				}
			}
		}

		return $return;
	}

	###################################################################################################################
	##
	##                   PLUGIN RELATED METHODS
	##
	###################################################################################################################

	/**
	 * cron callback
	 * check MQTT Clients are up and connected to Websocket
	 */
	public static function cron() {
		self::checkAllMqttClients();
	}

	/**
	 * callback to get information on the daemon
	 */
	public static function deamon_info() {

		$return = array();
		$return['log'] = __CLASS__;
		$return['state'] = 'nok';
		$return['launchable'] = 'nok';

		$python_daemon = false;
		$websocket_daemon = false;

		$pid_file1 = jeedom::getTmpFolder(__CLASS__) . '/jmqttd.py.pid';
		if (file_exists($pid_file1)) {
			if (@posix_getsid(trim(file_get_contents($pid_file1)))) {
				$python_daemon = true;
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file1 . ' 2>&1 > /dev/null');
				self::deamon_stop();
			}
		}

		$pid_file2 = jeedom::getTmpFolder(__CLASS__) . '/jmqttd.php.pid';
		if (file_exists($pid_file2)) {
			if (@posix_getsid(trim(file_get_contents($pid_file2)))) {
				$websocket_daemon = true;
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file2 . ' 2>&1 > /dev/null');
				self::deamon_stop();
			}
		}

		if($python_daemon && $websocket_daemon){
			$return['state'] = 'ok';
		}

		if (config::byKey('pythonsocketport', __CLASS__, self::DEFAULT_PYTHON_PORT) != config::byKey('websocketport', __CLASS__, self::DEFAULT_WEBSOCKET_PORT)) {
			$return['launchable'] = 'ok';
		}
		return $return;
	}

	/**
	 * callback to start daemon
	 */
	public static function deamon_start() {

		// if FORCE_DEPENDANCY_INSTALL flag is raised in plugin config
		if (config::byKey(self::FORCE_DEPENDANCY_INSTALL, __CLASS__, 0) == 1) {
			$plugin = plugin::byId(__CLASS__);

			//clean dependancy state cache
			$plugin->dependancy_info(true);

			//start dependancy install
			$plugin->dependancy_install();

			//remove flag
			config::remove(self::FORCE_DEPENDANCY_INSTALL, __CLASS__);

			return;
		}

		log::add(__CLASS__, 'info', 'Starting Daemon');
		
		self::deamon_stop();
		$daemon_info = self::deamon_info();
		if ($daemon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}

		// Get default ports for daemons
		$defaultPythonPort = self::DEFAULT_PYTHON_PORT;
		$defaultWebSocketPort = self::DEFAULT_WEBSOCKET_PORT;

		// Check python daemon port is available
		$output=null;
		$retval=null;
		exec(system::getCmdSudo() . 'fuser ' . config::byKey('pythonsocketport', __CLASS__, $defaultPythonPort) . '/tcp', $output, $retval);
		if ($retval == 0 && count($output) > 0) {
			$pid = trim($output[0]);
			unset($output);
			exec(system::getCmdSudo() . 'ps -p ' . $pid . ' -o command=', $output, $retval);
			if ($retval == 0 && count($output) > 0) $commandline = $output[0];
			throw new Exception(__('Le port du démon python (' . config::byKey('pythonsocketport', __CLASS__, $defaultPythonPort) . ') est déjà utilisé par le pid ' . $pid . ' : ' . $commandline, __FILE__));
		}

		// Check websocket daemon port is available
		$output=null;
		$retval=null;
		exec(system::getCmdSudo() . 'fuser ' . config::byKey('websocketport', __CLASS__, $defaultWebSocketPort) . '/tcp', $output, $retval);
		if ($retval == 0 && count($output) > 0) {
			$pid = trim($output[0]);
			unset($output);
			exec(system::getCmdSudo() . 'ps -p ' . $pid . ' -o command=', $output, $retval);
			if ($retval == 0 && count($output) > 0) $commandline = $output[0];
			throw new Exception(__('Le port du démon websocket (' . config::byKey('websocketport', __CLASS__, $defaultWebSocketPort) . ') est déjà utilisé par le pid ' . $pid . ' : ' . $commandline, __FILE__));
		}

		// Start Python daemon
		$path1 = realpath(dirname(__FILE__) . '/../../resources/jmqttd');
		$cmd1 = $path1.'/venv/bin/python3 ' . $path1 . '/jmqttd.py';
		$cmd1 .= ' --plugin ' . __CLASS__;
		$cmd1 .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
		$cmd1 .= ' --socketport ' . config::byKey('pythonsocketport', __CLASS__, $defaultPythonPort);
		$cmd1 .= ' --apikey ' . jeedom::getApiKey(__CLASS__);
		$cmd1 .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/jmqttd.py.pid';
		log::add(__CLASS__, 'info', 'Lancement du démon python jMQTT pour le plugin '.__CLASS__);
		$result1 = exec($cmd1 . ' >> ' . log::getPathToLog(__CLASS__.'_daemon') . ' 2>&1 &');

		// Start WebSocket daemon
		$path2 = realpath(dirname(__FILE__) . '/../../resources/jmqttd/');
		$cmd2 = 'php ' . $path2 . '/jmqttd.php';
		$cmd2 .= ' --plugin ' . __CLASS__;
		$cmd2 .= ' --socketport ' . config::byKey('websocketport', __CLASS__, $defaultWebSocketPort);
		$cmd2 .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/jmqttd.php.pid';
		log::add(__CLASS__, 'info', 'Lancement du démon websocket jMQTT pour le plugin '.__CLASS__);
		$result2 = exec($cmd2 . ' >> ' . log::getPathToLog(__CLASS__) . ' 2>&1 &');

		//wait up to 10 seconds for daemons start
		for ($i = 1; $i <= 40; $i++) {
			$daemon_info = self::deamon_info();
			if ($daemon_info['state'] == 'ok') break;
			usleep(250000);
		}

		if ($daemon_info['state'] != 'ok') {
			// If only one of both daemon runs we still need to stop
			self::deamon_stop();
			log::add(__CLASS__, 'error', __('Impossible de lancer le démon jMQTT, vérifiez le log',__FILE__), 'unableStartDaemon');
			return false;
		}
		message::removeAll(__CLASS__, 'unableStartDaemon');


		self::checkAllMqttClients();
		self::listenersAddAll();
	}

	/**
	 * callback to stop daemon
	 */
	public static function deamon_stop() {
		log::add(__CLASS__, 'info', 'Stopping Daemon');

		$pid_file1 = jeedom::getTmpFolder(__CLASS__) . '/jmqttd.py.pid';
		if (file_exists($pid_file1)) {
			$pid1 = intval(trim(file_get_contents($pid_file1)));
			system::kill($pid1, false);
			//wait up to 10 seconds for python daemon stop
			for ($i = 1; $i <= 40; $i++) {
				if (! @posix_getsid($pid1)) break;
				usleep(250000);
			}
			system::kill($pid1, true);
		}
		$pid_file2 = jeedom::getTmpFolder(__CLASS__) . '/jmqttd.php.pid';
		if (file_exists($pid_file2)) {
			$pid2 = intval(trim(file_get_contents($pid_file2)));
			system::kill($pid2, false);
			//wait up to 10 seconds for websocket daemon stop
			for ($i = 1; $i <= 40; $i++) {
				if (! @posix_getsid($pid2)) break;
				usleep(250000);
			}
			system::kill($pid2, true);
		}

		// If something bad happened, clean anyway
		self::cleanMqttClientStateCache();

		self::listenersRemoveAll();
	}
	/**
	 * Provides dependancy information
	 */
	public static function dependancy_info() {
		$depLogFile = __CLASS__ . '_dep';
		$depProgressFile = jeedom::getTmpFolder(__CLASS__) . '/dependancy';

		$return = array();
		$return['log'] = log::getPathToLog($depLogFile);
		$return['progress_file'] = $depProgressFile;

		$return['state'] = 'ok';
		if (exec(system::getCmdSudo() . "cat " . dirname(__FILE__) . "/../../resources/Ratchet/vendor/composer/installed.json 2>/dev/null | grep cboden/ratchet | wc -l") < 1) {
			log::add(__CLASS__, 'debug', 'dependancy_info : Composer Ratchet PHP package is missing');
			$return['state'] = 'nok';
		}
		if (exec(system::getCmdSudo() . "cat " . dirname(__FILE__) . "/../../resources/JsonPath-PHP/vendor/composer/installed.json 2>/dev/null | grep galbar/jsonpath | wc -l") < 1) {
			log::add(__CLASS__, 'debug', 'dependancy_info : Composer JsonPath PHP package is missing');
			$return['state'] = 'nok';
		}

		if (exec(dirname(__FILE__) . '/../../resources/venv/bin/pip3 list | grep -E "requests|paho-mqtt|websocket\-client" | wc -l') < 3) {
			log::add(__CLASS__, 'debug', 'dependancy_info : python3 requests, paho-mqtt or websocket-client library is missing in venv');
			$return['state'] = 'nok';
		}
		if (config::byKey('installMosquitto', 'jMQTT', 1) && exec(system::getCmdSudo() . system::get('cmd_check') . '-Ec "mosquitto"') < 1) {
			log::add(__CLASS__, 'debug', 'dependancy_info : debian mosquitto package is missing');
			$return['state'] = 'nok';
		}

		return $return;
	}

	/**
	 * Provides dependancy installation script
	 */
	public static function dependancy_install() {
		$depLogFile = __CLASS__ . '_dep';
		$depProgressFile = jeedom::getTmpFolder(__CLASS__) . '/dependancy';

		log::add('jMQTT', 'info', 'Installation des dépendances, voir log dédié (' . $depLogFile . ')');
		log::remove($depLogFile);
		return array(
			'script' => __DIR__ . '/../../resources/install_#stype#.sh ' . $depProgressFile . ' ' . config::byKey('installMosquitto', 'jMQTT', 1),
			'log' => log::getPathToLog($depLogFile)
		);
	}

	/**
	 * Create first broker eqpt if mosquitto has been installed
	 */
	public static function post_dependancy_install() {
		echo "Starting post_dependancy_install()\n";
		// if Mosquitto is installed
		if (config::byKey('installMosquitto', 'jMQTT', 1)) {
			echo "Mosquitto installation requested => looking for Broker eqpt\n";

			//looking for broker pointing to local mosquitto
			$brokerexists = false;
			foreach(self::getBrokers() as $broker) {
				$hn = $broker->getMqttAddress();
				$ip = gethostbyname($hn);
				$localips = explode(' ', exec(system::getCmdSudo() . 'hostname -I'));
				if ($hn == '' || substr($ip, 0, 4) == '127.' || in_array($ip, $localips)) {
					$brokerexists = true;
					echo "Broker eqpt already exists\n";
					break;
				}
			}

			if (!$brokerexists) {
				echo "Broker eqpt not found\n";

				$brokername = 'local';

				//looking for broker name conflict
				$brokernameconflict = false;
				foreach(self::getBrokers() as $broker) {
					if ($broker->getName() == $brokername) {
						$brokernameconflict = true;
						break;
					}
				}
				if ($brokernameconflict) {
					$i = 0;
					do {
						$i++;
						$brokernameconflict = false;
						$brokername = 'local'.$i;
						foreach(self::getBrokers() as $broker) {
							if ($broker->getName() == $brokername) {
								$brokernameconflict = true;
								break;
							}
						}
					} while ($brokernameconflict);
				}

				echo 'Creation of Broker eqpt. name : ' . $brokername . "\n";
				$broker = new jMQTT();
				$broker->setType(self::TYP_BRK);
				$broker->setName($brokername);
				$broker->setIsEnable(1);
				$broker->save();

				echo "Done\n";
			}
		}
	}

// Create or update all autoPub listeners
	public static function listenersAddAll() {
		foreach (cmd::searchConfiguration('"autoPub":"1"', __CLASS__) as $cmd)
			$cmd->listenerUpdate();
	}

// Remove all autoPub listeners
	public static function listenersRemoveAll() {
		foreach (listener::byClass('jMQTTCmd') as $l)
			$l->remove();
	}

// Create or update all autoPub listeners from this eqLogic
	public function listenersAdd() {
		foreach (jMQTTCmd::searchConfigurationEqLogic($this->getId(), '"autoPub":"1"') as $cmd)
			$cmd->listenerUpdate();
	}

// Remove all autoPub listeners from this eqLogic
	public function listenersRemove() {
		$listener = listener::searchClassFunctionOption('jMQTTCmd', 'listenerAction', '"eqLogic":"'.$this->getId().'"');
		foreach ($listener as $l)
			$l->remove();
	}



	###################################################################################################################
	##
	##                   MQTT CLIENT RELATED METHODS
	##
	###################################################################################################################


	private static function getMqttClientStateCache($id, $key, $default = null) {
		return cache::byKey('jMQTT::' . $id . '::' . $key)->getValue($default);
	}

	private static function setMqttClientStateCache($id, $key, $value = null) {
		// Save ids in cache as a list for future cleaning
		$idListInCache = cache::byKey('jMQTT')->getValue([]);
		if (!in_array($id, $idListInCache, true)){
			$idListInCache[] = $id;
			cache::set('jMQTT', $idListInCache);
		}

		return cache::set('jMQTT::' . $id . '::' . $key, $value);
	}
	private static function cleanMqttClientStateCache() {
		// get list of ids
		$idListInCache = cache::byKey('jMQTT')->getValue([]);
		// for each id clean both cached values
		foreach ($idListInCache as $id) {
			cache::delete('jMQTT::' . $id . '::' . self::CACHE_DAEMON_CONNECTED);
			cache::delete('jMQTT::' . $id . '::' . self::CACHE_MQTTCLIENT_CONNECTED);
		}
	}


	/**
	 * Check all MQTT Clients (start them if needed)
	 */
	public static function checkAllMqttClients() {
		$daemon_info = self::deamon_info();
		if ($daemon_info['state'] == 'ok') {
			foreach(self::getBrokers() as $broker) {
				if ($broker->getIsEnable() && $broker->getMqttClientState() == self::MQTTCLIENT_NOK) {
					try {
						log::add(__CLASS__, 'info', 'Starting MqttClient for ' . $broker->getName());
						$broker->startMqttClient();
					}
					catch (Throwable $e) {}
				}
			}
		}
	}

	/**
	 * Return MQTT Client information
	 * @return string[] MQTT Client information array
	 */
	public function getMqttClientInfo() {
		$return = array('message' => '', 'launchable' => 'nok', 'state' => 'nok', 'log' => 'nok');

		if ($this->getType() != self::TYP_BRK)
			return $return;

		$return['brkId'] = $this->getId();

		// Is the MQTT Client launchable
		$return['launchable'] = 'ok';
		$daemon_info = self::deamon_info();
		if ($daemon_info['state'] == 'ok') {
			if (!$this->getIsEnable()) {
				$return['launchable'] = 'nok';
				$return['message'] = __("L'équipement est désactivé", __FILE__);
			}
		}
		else {
			$return['launchable'] = 'nok';
			$return['message'] = __('Démon non démarré', __FILE__);
		}

		$return['log'] = $this->getMqttClientLogFile();
		$return['last_launch'] = $this->getLastMqttClientLaunchTime();
		$return['state'] = $this->getMqttClientState();
		$return['color'] = self::getBrokerColorFromState($return['state']);
		if ($daemon_info['state'] == 'ok') {
			if ($return['state'] == self::MQTTCLIENT_NOK && $return['message'] == '')
				$return['message'] = __('Le Client MQTT est arrêté', __FILE__);
			elseif ($return['state'] == self::MQTTCLIENT_POK)
				$return['message'] = __('Le broker est OFFLINE', __FILE__);
		}

		return $return;
	}

	/**
	 * Return MQTT Client state
	 *   - self::MQTTCLIENT_OK: MQTT Client is running and mqtt broker is online
	 *   - self::MQTTCLIENT_POK: MQTT Client is running but mqtt broker is offline
	 *   - self::MQTTCLIENT_NOK: no cron exists or cron is not running
	 * @return string ok or nok
	 */
	public function getMqttClientState() {
		if (!self::getMqttClientStateCache($this->getId(), self::CACHE_DAEMON_CONNECTED, false)) return self::MQTTCLIENT_NOK;
		if (!self::getMqttClientStateCache($this->getId(), self::CACHE_MQTTCLIENT_CONNECTED, false)) return self::MQTTCLIENT_POK;
		return self::MQTTCLIENT_OK;
	}

	/**
	 * Return hex color string depending state passed
	 * @return string hex color
	 */
	public static function getBrokerColorFromState($state) {
		switch ($state) {
			case self::MQTTCLIENT_OK:
				return '#96C927';
				break;
			case self::MQTTCLIENT_POK:
				return '#ff9b00';
				break;
			default:
				return '#ff0000';
				break;
		}
	}

	/**
	 * Start the MQTT Client of this broker if it is launchable
	 * @throws Exception if the MQTT Client is not launchable
	 */
	public function startMqttClient() {
		// if daemon is not ok, do Nothing
		$daemon_info = self::deamon_info();
		if ($daemon_info['state'] != 'ok') return;

		//If MqttClient is not launchable (daemon is running), throw exception to get message
		$mqttclient_info = $this->getMqttClientInfo();
		if ($mqttclient_info['launchable'] != 'ok')
			throw new Exception(__('Le client MQTT n\'est pas démarrable. Veuillez vérifier la configuration', __FILE__));

		$this->log('info', 'démarre le client MQTT ');
		$this->setLastMqttClientLaunchTime();
		$this->sendMqttClientStateEvent();

		// Preparing some additional data for the broker
		$params = array();
		$params['port']              = $this->getMqttPort();
		$params['clientid']          = $this->getMqttClientId();
		$params['statustopic']       = $this->getMqttClientStatusTopic();
		$params['username']          = $this->getConf(self::CONF_KEY_MQTT_USER);
		$params['password']          = $this->getConf(self::CONF_KEY_MQTT_PASS);
		$params['paholog']           = $this->getConf(self::CONF_KEY_MQTT_PAHO_LOG);
		$params['tls']               = boolval($this->getConf(self::CONF_KEY_MQTT_TLS));

		switch ($this->getConf(self::CONF_KEY_MQTT_TLS_CHECK)) {
			case 'disabled':
				$params['tlsinsecure'] = true;
				$params['tlscafile'] = '';
				break;
			case 'public':
				$params['tlsinsecure'] = false;
				$params['tlscafile'] = '';
				break;
			case 'private':
				$params['tlsinsecure'] = false;
				$params['tlscafile'] = $this->getConf(self::CONF_KEY_MQTT_TLS_CA);
				break;
		}

		$params['tlsclicertfile']    = $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_CERT);
		$params['tlsclikeyfile']     = $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_KEY);
		// Realpaths
		if ($params['tlscafile'] != '')
			$params['tlscafile']     = realpath(dirname(__FILE__) . '/../../' . self::PATH_CERTIFICATES . $params['tlscafile']);
		if ($params['tlsclicertfile'] != '')
			$params['tlsclicertfile'] = realpath(dirname(__FILE__).'/../../' . self::PATH_CERTIFICATES . $params['tlsclicertfile']);
		else
			$params['tlsclikeyfile'] = '';
		if ($params['tlsclikeyfile'] != '')
			$params['tlsclikeyfile'] = realpath(dirname(__FILE__) . '/../../' . self::PATH_CERTIFICATES . $params['tlsclikeyfile']);

		self::new_mqtt_client($this->getId(), $this->getMqttAddress(), $params);

		foreach (self::byBrkId($this->getId()) as $mqtt) {
			if ($mqtt->getIsEnable() && $mqtt->getId() != $this->getId()) {
				$mqtt->subscribeTopic($mqtt->getTopic(), $mqtt->getQos());
			}
		}

		if ($this->isApiEnable()) {
			$this->log('info', 'Subscribes to the API topic "' . $this->getMqttApiTopic() . '"');
			$this->subscribeTopic($this->getMqttApiTopic(), '1');
		} else {
			$this->log('info', 'API is disabled');
		}
	}

	/**
	 * Stop the MQTT Client of this broker type object
	 */
	public function stopMqttClient() {
		$this->log('info', 'arrête le client MQTT');
		self::remove_mqtt_client($this->getId());
	}

	public static function on_daemon_connect($id) {
		// Save in cache that daemon is connected
		self::setMqttClientStateCache($id, self::CACHE_DAEMON_CONNECTED, true);

		try {
			$broker = self::getBrokerFromId(intval($id));
			$broker->sendMqttClientStateEvent();
		} catch (Throwable $t) {
				log::add(__CLASS__, 'error', sprintf('on_daemon_connect raised an Exception : %s', $t->getMessage()));
		}
	}
	public static function on_daemon_disconnect($id) {
		// if daemon is disconnected from Jeedom, consider the MQTT Client as disconnected too
		if (self::getMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED))
			self::on_mqtt_disconnect($id);

		// Save in cache that daemon is disconnected
		self::setMqttClientStateCache($id, self::CACHE_DAEMON_CONNECTED, false);

		try {
			$broker = self::getBrokerFromId(intval($id));
			$broker->sendMqttClientStateEvent();
		} catch (Throwable $t) {
			log::add(__CLASS__, 'error', sprintf('on_daemon_disconnect raised an Exception : %s', $t->getMessage()));
		}
	}
	public static function on_mqtt_connect($id) {
		// Save in cache that Mqtt Client is connected
		self::setMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED, true);

		try {
			$broker = self::getBrokerFromId(intval($id));
			$broker->getMqttClientStatusCmd()->event(self::ONLINE);
			$broker->sendMqttClientStateEvent();
		} catch (Throwable $t) {
				log::add(__CLASS__, 'error', sprintf('on_mqtt_connect raised an Exception : %s', $t->getMessage()));
		}
	}
	public static function on_mqtt_disconnect($id) {
		// Save in cache that Mqtt Client is disconnected
		self::setMqttClientStateCache($id, self::CACHE_MQTTCLIENT_CONNECTED, false);

		try {
			$broker = self::getBrokerFromId(intval($id));
			$statusCmd = $broker->getMqttClientStatusCmd();
			if ($statusCmd) $statusCmd->event(self::OFFLINE); //Need to check if statusCmd exists, because during Remove cmd are destroyed first by eqLogic::remove()
			$broker->sendMqttClientStateEvent();
			// if includeMode is enabled, disbale it
			if ($broker->getIncludeMode()) $broker->changeIncludeMode(0);
		} catch (Throwable $t) {
				log::add(__CLASS__, 'error', sprintf('on_mqtt_disconnect raised an Exception : %s', $t->getMessage()));
		}
	}
	public static function on_mqtt_message($id, $topic, $payload, $qos, $retain) {

		try {
			$broker = self::getBrokerFromId(intval($id));
			$broker->brokerMessageCallback($topic, $payload);
		} catch (Throwable $t) {
			log::add(__CLASS__, 'error', sprintf('on_mqtt_message raised an Exception : %s', $t->getMessage()));
		}
	}


	private static function send_to_mqtt_daemon($params) {
		$daemon_info = self::deamon_info();
		if ($daemon_info['state'] != 'ok') {
			throw new Exception("Le démon n'est pas démarré");
		}
		$params['apikey'] = jeedom::getApiKey(__CLASS__);
		$payload = json_encode($params);
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('pythonsocketport', __CLASS__, self::DEFAULT_PYTHON_PORT));
		socket_write($socket, $payload, strlen($payload));
		socket_close($socket);
	}

	public static function new_mqtt_client($id, $hostname, $params = array()) {
		$params['cmd']                  = 'newMqttClient';
		$params['id']                   = $id;
		$params['hostname']             = $hostname;
		$params['callback']             = 'ws://127.0.0.1:'.config::byKey('websocketport', __CLASS__, self::DEFAULT_WEBSOCKET_PORT).'/plugins/jMQTT/resources/jmqttd/jmqttd.php';

		// set port IF (port not 0 and numeric) THEN (intval) ELSE (default for TLS and clear MQTT) #DoubleTernaryAreCute
		$params['port']=($params['port'] != 0 && is_numeric($params['port'])) ? intval($params['port']) : (($params['tls']) ? 8883 : 1883);

		self::send_to_mqtt_daemon($params);
	}

	public static function remove_mqtt_client($id) {
		$params['cmd']='removeMqttClient';
		$params['id']=$id;
		self::send_to_mqtt_daemon($params);
	}

	public static function subscribe_mqtt_topic($id, $topic, $qos = 1) {
		if (empty($topic)) return;
		$params['cmd']='subscribeTopic';
		$params['id']=$id;
		$params['topic']=$topic;
		$params['qos']=$qos;
		self::send_to_mqtt_daemon($params);
	}

	public static function unsubscribe_mqtt_topic($id, $topic) {
		if (empty($topic)) return;
		$params['cmd']='unsubscribeTopic';
		$params['id']=$id;
		$params['topic']=$topic;
		self::send_to_mqtt_daemon($params);
	}

	public static function publish_mqtt_message($id, $topic, $payload, $qos = 1, $retain = false) {
		if (empty($topic)) return;
		$params['cmd']='messageOut';
		$params['id']=$id;
		$params['topic']=$topic;
		$params['payload']=$payload;
		$params['qos']=$qos;
		$params['retain']=$retain;
		self::send_to_mqtt_daemon($params);
	}

	/**
	 * Return the last MQTT Client launch time
	 * @return string date or unknown
	 */
	public function getLastMqttClientLaunchTime() {
		return $this->getCache('lastMqttClientLaunchTime', __('Inconnue', __FILE__));
	}

	/**
	 * Set the last MQTT Client launch time to the current time
	 */
	public function setLastMqttClientLaunchTime() {
		return $this->setCache('lastMqttClientLaunchTime', date('Y-m-d H:i:s'));
	}

	/**
	 * Send a jMQTT::EventState event to the UI containing MQTT Client info
	 * The method shall be called on a broker equipment eqLogic
	 */
	private function sendMqttClientStateEvent() {
		event::add('jMQTT::EventState', $this->getMqttClientInfo());
	}

	###################################################################################################################
	##
	##                   MQTT BROKER METHODS
	##
	###################################################################################################################


	/**
	 * Callback called each time a message matching subscribed topic is received from the broker.
	 *
	 * @param $msgTopic string
	 *            topic of the message
	 * @param $msgValue string
	 *            payload of the message
	 */
	public function brokerMessageCallback($msgTopic, $msgValue) {

		$this->setStatus(array('lastCommunication' => date('Y-m-d H:i:s'), 'timeout' => 0));

		$this->log('debug', 'Payload ' . $msgValue . ' for topic ' . $msgTopic);

		// If this is the API topic, process the request
		if ($msgTopic == $this->getMqttApiTopic()) {
			$this->processApiRequest($msgValue);
			return;
		}

		// Loop on jMQTT equipments and get ones that subscribed to the current message
		$elogics = array();
		foreach (self::byBrkId($this->getId()) as $eqpt) {
			if (mosquitto_topic_matches_sub($eqpt->getTopic(), $msgTopic)) $elogics[] = $eqpt;
		}

		// If no equipment listening to the current message is found and the automatic inclusion mode is active
		if (empty($elogics) && $this->getIncludeMode()) {

			// Make some check on topic
			if ($msgTopic == '/' || strpos($msgTopic, '//') !== false) {
				$this->log('warning', 'Equipment can\'t be created automatically for the topic "' . $msgTopic . '"');
				return;
			}

			// explode topic
			$msgTopicArray = explode("/", $msgTopic);
			// remove empty strings and reindex
			$msgTopicArray = array_values(array_filter($msgTopicArray));

			if (!count($msgTopicArray)) {
				$this->log('warning', 'Equipment can\'t be created automatically for the topic "' . $msgTopic . '"');
				return;
			}

			// create a new equipment subscribing to all sub-topics starting with the first topic of the current message
			$eqpt = self::createEquipment($this, $msgTopicArray[0], ($msgTopic[0] == '/' ? '/' : '') . $msgTopicArray[0] . '/#');
			$elogics[] = $eqpt;
		}

		// No equipment listening to the current message is found
		// Should not occur: log a warning and return
		if (empty($elogics)) {
			$this->log('warning', 'No equipment listening to topic ' . $msgTopic);
			return;
		}

		//
		// Loop on enabled equipments listening to the current message
		//
		foreach ($elogics as $eqpt) {
			if ($eqpt->getIsEnable()) {
				// Looking for all cmds matching Eq and Topic in the DB
				$cmds = jMQTTCmd::byEqLogicIdAndTopic($eqpt->getId(), $msgTopic, true);
				if (is_null($cmds))
					$cmds = array();
				$jsonCmds = array();
				// Keep only info cmds in $cmds and put all JSON info commands in $jsonCmds
				foreach($cmds as $k => $cmd) {
					if ($cmd->getType() == 'action') {
						$this->log('debug', $eqpt->getName() . '|' . $cmd->getName() . ' is an action command: skip');
						unset($cmds[$k]);
					} elseif ($cmd->isJson()) {
						$this->log('debug', $eqpt->getName() . '|' . $cmd->getName() . ' is a JSON info command: skip');
						unset($cmds[$k]);
						$jsonCmds[] = $cmd;
					}
				}
				// If there is no info cmd matching exactly with the topic (non JSON)
				if (empty($cmds)) {
					// Is automatic command creation enabled?
					if ($eqpt->getAutoAddCmd()) {
						// Determine the futur name of the command.
						// Suppress starting topic levels that are common with the equipment suscribing topic
						$sbscrbTopicArray = explode("/", $eqpt->getTopic());
						$msgTopicArray = explode("/", $msgTopic);
						foreach ($sbscrbTopicArray as $s) {
							if ($s == '#' || $s == '+')
								break;
							else
								next($msgTopicArray);
						}
						$cmdName = current($msgTopicArray) === false ? end($msgTopicArray) : current($msgTopicArray);
						while (next($msgTopicArray) !== false) {
							$cmdName = $cmdName . '/' . current($msgTopicArray);
						}
						$cmdName = substr(trim($cmdName),0,120); // Ensure whitespaces are treated well
						$allCmdsNames = array();
						// Get all commands names for this equipment
						foreach (jMQTTCmd::byEqLogicId($eqpt->getId()) as $cmd)
							$allCmdsNames[] = trim($cmd->getName());
						// If cmdName is already used, add suffix '-<number>'
						if (false !== array_search($cmdName, $allCmdsNames)) {
							$cmdName .= '-';
							$increment = 2;
							while (false !== array_search($cmdName.$increment, $allCmdsNames))
								$increment++;
							$cmdName .= $increment;
						}
						// Create the new cmd
						$newCmd = jMQTTCmd::newCmd($eqpt, $cmdName, $msgTopic);
						$newCmd->save();
						$cmds[] = $newCmd;
						$this->log('debug', $eqpt->getName() . '|' . $cmdName . ' automatically created for topic ' . $msgTopic);
					} else {
						$this->log('debug', 'Command for topic ' . $msgTopic . ' in ' . $eqpt->getName() . ' not created, as automatic command creation is disabled');
					}
				}

				// If there is some cmd matching exactly with the topic
				if (is_array($cmds) && count($cmds)) {
					foreach ($cmds as $cmd) {
						// Update the command value
						$cmd->updateCmdValue($msgValue);
					}
				}

				// If there is some cmd matching exactly with the topic with JSON path
				if (is_array($jsonCmds) && count($jsonCmds)) {

					// decode JSON payload
					$jsonArray = reset($jsonCmds)->decodeJsonMsg($msgValue);
					if (isset($jsonArray)) {

						foreach ($jsonCmds as $cmd) {
							// Update JSON derived commands
							$cmd->updateJsonCmdValue($jsonArray);
						}
					}
				}
			}
		}
	}

	/**
	 * Publish a given message to the MQTT broker attached to this object
	 *
	 * @param string $eqName
	 *            equipment name (for log purpose)
	 * @param string $topic
	 *            topic
	 * @param string $message
	 *            payload
	 * @param string $qos
	 *            quality of service used to send the message ('0', '1' or '2')
	 * @param string $retain
	 *            whether or not the message is a retained message ('0' or '1')
	 */
	public function publish($eqName, $topic, $payload, $qos, $retain) {

		if (is_bool($payload) || is_array($payload)) {
			// Fix #80
			// One can wonder why not encoding systematically the message?
			// Answer is that it does not work in some cases:
			//   * If payload is empty => "(null)" is sent instead of (null)
			//   * If payload contains ", they are backslashed \"
			// Fix #110
			// Since Core commit https://github.com/jeedom/core/commit/430f0049dc74e914c4166b109fb48b4375f11ead
			// payload can be more than int/bool/string
			$payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
		}
		$payloadLogMsg = ($payload === '') ? '(null)' : $payload;
		$this->log('info', '<- ' . $eqName . '|' . $topic . ' ' . $payloadLogMsg);

		$this->log('debug', 'Publishing message ' . $topic . ' ' . $payload . ' (qos=' . $qos . ', retain=' . $retain . ')');

		if ($this->getBroker()->getMqttClientState() == self::MQTTCLIENT_OK) {
			self::publish_mqtt_message($this->getBrkId(), $topic, $payload, $qos, $retain);

			$d = date('Y-m-d H:i:s');
			$this->setStatus(array('lastCommunication' => $d, 'timeout' => 0));

			if ($this->getType() == self::TYP_EQPT) {
				$this->getBroker()->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
			}

			$this->log('debug', 'Message published');
		}
		else $this->log('warning', 'Message cannot be published, daemon is not connected to the broker');
	}

	/**
	 * Return the MQTT topic name of this broker status command
	 * @return string broker status topic name
	 */
	public function getMqttClientStatusTopic()  {
		if (! $this->getConf(self::CONF_KEY_MQTT_PUB_STATUS)) return '';
		return $this->getMqttClientId() . '/' . self::CLIENT_STATUS;
	}

	/**
	 * Return the MQTT status information command of this broker
	 * @return cmd status information command.
	 */
	public function getMqttClientStatusCmd() {
		if (! is_object($this->_statusCmd)) {
			$this->_statusCmd = cmd::byEqLogicIdAndLogicalId($this->getId(), self::CLIENT_STATUS);
		}
		return $this->_statusCmd;
	}

	/**
	 * Create and save the MQTT status information command of this broker if not already existing
	 * It is the responsability of the caller to check that this object is a broker before
	 * calling the method.
	 */
	public function createMqttClientStatusCmd() {
		if (! is_object($this->getMqttClientStatusCmd())) {
			$cmd = jMQTTCmd::newCmd($this, self::CLIENT_STATUS, $this->getMqttClientStatusTopic());
			$cmd->setLogicalId(self::CLIENT_STATUS);
			$cmd->setConfiguration('jsonPath', '');
			$cmd->setIrremovable();
			$cmd->save();
		}
	}

	###################################################################################################################

	/**
	 * Process the API request
	 */
	private function processApiRequest($msg) {
		try {
			$request = new mqttApiRequest($msg, $this);
			$request->processRequest();
		} catch (Throwable $e) {}
	}

	/**
	 * Return the name of the log file attached to this jMQTT object.
	 * The log file is cached for optimization. If $force is set cache refresh is forced.
	 * @var bool $force to force the definition of the log file name
	 * @return string MQTT Client log filename.
	 */
	public function getMqttClientLogFile($force=false) {
		if (!isset($this->_log) || $force) {
			$this->_log = __CLASS__ . '_' . str_replace(' ', '_', $this->getBroker()->getName());
		}
		return $this->_log;
	}

	/**
	 * Log messages
	 * @param string $level
	 * @param string $msg
	 */
	public function log($level, $msg) {
		// log can't be written during removal of an eqLogic next to his broker eqLogic removal
		// the name of the broker can't be found (and log file has already been deleted)
		try {
			$log = $this->getMqttClientLogFile();
			log::add($log, $level, $msg);
		} catch (Throwable $t) {} // nothing to do in that particular case
	}

	/**
	 * Return whether or not the MQTT API is enable
	 * return boolean
	 */
	public function isApiEnable() {
		return $this->getConf(self::CONF_KEY_API) == self::API_ENABLE ? TRUE : FALSE;
	}

	private function getConf($_key) {
		return $this->getConfiguration($_key, $this->getDefaultConfiguration($_key));
	}

	private function getDefaultConfiguration($_key) {
		if ($_key == self::CONF_KEY_MQTT_PORT)
			return (boolval($this->getConf(self::CONF_KEY_MQTT_TLS))) ? 8883 : 1883;
		$defValues = array(
			self::CONF_KEY_MQTT_ADDRESS => 'localhost',
			self::CONF_KEY_MQTT_CLIENT_ID => 'jeedom',
			self::CONF_KEY_QOS => '1',
			self::CONF_KEY_MQTT_PUB_STATUS => '1',
			self::CONF_KEY_MQTT_TLS => '0',
			self::CONF_KEY_MQTT_TLS_CHECK => 'public',
			self::CONF_KEY_AUTO_ADD_CMD => '1',
			self::CONF_KEY_AUTO_ADD_TOPIC => '',
			self::CONF_KEY_MQTT_INC_TOPIC => '#',
			self::CONF_KEY_API => self::API_DISABLE,
			self::CONF_KEY_BRK_ID => -1
		);
		// If not in list, default value is ''
		return array_key_exists($_key, $defValues) ? $defValues[$_key] : '';
	}

	/**
	 * Set the log level
	 * Called when saving a broker eqLogic
	 * If log level is changed, save the new value and restart the MQTT Client
	 * @param string $level
	 */
	public function setLogLevel($log_level) {
		$decodedLogLevel = json_decode($log_level, true);
		$this->setConfiguration(self::CONF_KEY_LOGLEVEL, reset($decodedLogLevel));
	}

	/**
	 * Get this jMQTT object topic
	 * @return string
	 */
	public function getTopic() {
		return $this->getConf(self::CONF_KEY_AUTO_ADD_TOPIC);
	}

	/**
	 * Set this jMQTT object topic
	 * @var string $topic
	 */
	public function setTopic($topic) {
		$this->setConfiguration(self::CONF_KEY_AUTO_ADD_TOPIC, $topic);
	}

	/**
	 * Move this jMQTT object auto_add_topic to configuration
	 */
	public function moveTopicToConfiguration() {
		// Detect presence of auto_add_topic
		$keyPresence = $this->getConfiguration(self::CONF_KEY_AUTO_ADD_TOPIC, 'ThereIsNoKeyHere');
		if ($keyPresence == 'ThereIsNoKeyHere') {
			$this->setTopic($this->getLogicalId());
			$this->setLogicalId('');
			$this->save(true); // Direct save to avoid daemon notification and Exception that daemon is not Up
		}
	}

	/**
	 * Get this jMQTT object type
	 * @return string either jMQTT::TYPE_EQPT, jMQTT::TYP_BRK, or empty string if not defined
	 */
	public function getType() {
		return $this->getConf(self::CONF_KEY_TYPE);
	}

	/**
	 * Set this jMQTT object type
	 * @param string $type either jMQTT::TYP_EQPT or jMQTT::TYP_BRK
	 */
	public function setType($type) {
		if($type != self::TYP_EQPT && $type != self::TYP_BRK) return;
		$this->setConfiguration(self::CONF_KEY_TYPE, $type);
	}

	/**
	 * Get this jMQTT object related broker eqLogic Id
	 * @return int eqLogic Id or -1 if not defined
	 */
	public function getBrkId() {
		if ($this->getType() == self::TYP_BRK) return $this->getId();
		return $this->getConf(self::CONF_KEY_BRK_ID);
	}

	/**
	 * Set this jMQTT object related broker eqLogic Id
	 * @param int
	 */
	public function setBrkId($id) {
		$this->setConfiguration(self::CONF_KEY_BRK_ID, $id);
	}

	/**
	 * Get the MQTT client id used by jMQTT to connect to the broker (default value = jeedom)
	 * @return string MQTT id.
	 */
	public function getMqttClientId() {
		return $this->getConf(self::CONF_KEY_MQTT_CLIENT_ID);
	}

	/**
	 * Get this jMQTT object broker address
	 * @return string
	 */
	public function getMqttAddress() {
		return $this->getConf(self::CONF_KEY_MQTT_ADDRESS);
	}

	/**
	 * Get this jMQTT object broker port
	 * @return string
	 */
	public function getMqttPort() {
		return $this->getConf(self::CONF_KEY_MQTT_PORT);
	}

	/**
	 * Get this jMQTT object Qos
	 * @return string
	 */
	public function getQos() {
		return $this->getConf(self::CONF_KEY_QOS);
	}

	/**
	 * Set this jMQTT object Qos
	 * @var string $Qos
	 */
	public function setQos($Qos) {
		$this->setConfiguration(self::CONF_KEY_QOS, $Qos);
	}

	/**
	 * Get this jMQTT object auto_add_cmd configuration parameter
	 * @return string
	 */
	public function getAutoAddCmd() {
		return $this->getConf(self::CONF_KEY_AUTO_ADD_CMD);
	}

	/**
	 * Set this jMQTT object auto_add_cmd configuration parameter
	 * @var string $auto_add_cmd
	 */
	public function setAutoAddCmd($auto_add_cmd) {
		$this->setConfiguration(self::CONF_KEY_AUTO_ADD_CMD, $auto_add_cmd);
	}

	/**
	 * Get the broker object attached to this jMQTT object.
	 * Broker is cached for optimisation.
	 * @return jMQTT
	 * @throws Exception if the broker is not found
	 */
	public function getBroker() {
		if ($this->getType() == self::TYP_BRK) return $this;
		if (! isset($this->_broker)) {
			$this->_broker = self::getBrokerFromId($this->getBrkId());
		}
		return $this->_broker;
	}

	/**
	 * Check if a certificate file is used by the Broker
	 * @param string $certname Certificate file name
	 * @return boolean true if certificat is used
	 */
	public function isCertUsed($certname) {
		return (boolval($this->getConf(self::CONF_KEY_MQTT_TLS))) &&
				(($certname == $this->getConf(self::CONF_KEY_MQTT_TLS_CA)) ||
				 ($certname == $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_CERT)) ||
				 ($certname == $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_KEY)));
	}

	/**
	 * Get the jMQTT broker object which eqLogic Id is given
	 * @var int $id id of the broker
	 * @return jMQTT
	 * @throws Exception if $id is not a valid broker id
	 */
	public static function getBrokerFromId($id) {
		/** @var jMQTT $broker */
		$broker = self::byId($id);
		if (!is_object($broker)) {
			throw new Exception(__('Pas d\'équipement avec l\'id fourni', __FILE__) . ' (id=' . $id . ')');
		}
		if ($broker->getType() != self::TYP_BRK) {
			throw new Exception(__('L\'équipement n\'est pas de type broker', __FILE__) . ' (id=' . $id . ')');
		}
		return $broker;
	}

	/**
	 * Return all jMQTT objects attached to the specified broker id
	 * @return jMQTT[]
	 */
	public static function byBrkId($id) {
		$brkId = json_encode(array('brkId' => $id));
		/** @var jMQTT[] $eqpts */
		$returns = self::byTypeAndSearhConfiguration(jMQTT::class, substr($brkId, 1, -1));
		return $returns;
	}

	/**
	 * Return the topic used to interact with the jeeAPI using mqtt
	 *
	 * @return string API topic
	 */
	private function getMqttApiTopic() {
		return $this->getMqttClientId() . '/' . self::API_TOPIC;
	}


	/**
	 * Disable the equipment automatic inclusion mode and inform the desktop page
	 * @param string[] $option $option[id]=broker id
	 */
	public static function disableIncludeMode($option) {
		$broker = self::getBrokerFromId($option['id']);
		$broker->changeIncludeMode(0);
	}

	/**
	 * Manage the include_mode of this broker object
	 * Called by ajax when the button is pressed by the user
	 * @param int $mode 0 or 1
	 */
	public function changeIncludeMode($mode) {

		// Update the include mode value
		$this->setCache(self::CACHE_INCLUDE_MODE, $mode);
		$this->log('info', ($mode ? 'active' : 'désactive') . " le mode d'inclusion automatique");
		if (! $mode) {
			// Advise the desktop page (jMQTT.js) that the inclusion mode is disabled
			event::add('jMQTT::disableIncludeMode', array('brkId' => $this->getId()));
		}

		// A cron process is used to reset the automatic mode after a delay

		// If the cron process is already defined, remove it
		$cron = cron::byClassAndFunction(__CLASS__, 'disableIncludeMode', array('id' => $this->getId()));
		if (is_object($cron)) {
			$cron->remove();
		}

		$includeTopic = $this->getConf(self::CONF_KEY_MQTT_INC_TOPIC);

		// If includeMode needs to be enabled
		if ($mode == 1) {
			// Subscribe include topic
			$this->log('debug', 'Subscribe to Inclusion Mode topic');
			$this->subscribeTopic($includeTopic, $this->getQos());

			// Create and configure the cron process to disable include mode later
			$cron = new cron();
			$cron->setClass(__CLASS__);
			$cron->setOption(array('id' => $this->getId()));
			$cron->setFunction('disableIncludeMode');
			// Add 150s => actual delay between 2 and 3min
			$cron->setSchedule(cron::convertDateToCron(strtotime('now') + 150));
			$cron->setOnce(1);
			$cron->save();
		}
		// includeMode needs to be disabled
		else {
			// Unsubscribe include topic
			$this->unsubscribeTopic($includeTopic);
		}
	}

	/**
	 * Return this broker object include mode parameter
	 * @return int 0 or 1
	 */
	public function getIncludeMode() {
		return $this->getCache(self::CACHE_INCLUDE_MODE, 0);
	}
}
