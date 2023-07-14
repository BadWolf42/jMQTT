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

// Load JsonPath-PHP library
if (file_exists(__DIR__ . '/../../resources/JsonPath-PHP/vendor/autoload.php'))
	require_once __DIR__ . '/../../resources/JsonPath-PHP/vendor/autoload.php';

include_file('core', 'mqttApiRequest', 'class', 'jMQTT');
include_file('core', 'jMQTTCmd', 'class', 'jMQTT');

class jMQTT extends eqLogic {
	// TODO (low) use trait to simplify this class -> Need PHP 8.2.0 (consts)

	const FORCE_DEPENDANCY_INSTALL      = 'forceDepInstall';

	const CLIENT_STATUS                 = 'status';
	const CLIENT_STATUS_ONLINE          = 'online';
	const CLIENT_STATUS_OFFLINE         = 'offline';
	const CLIENT_CONNECTED              = 'connected';

	const CLIENT_OK                     = 'ok';
	const CLIENT_POK                    = 'pok';
	const CLIENT_NOK                    = 'nok';

	const CONF_KEY_TYPE                 = 'type';
	const CONF_KEY_BRK_ID               = 'eqLogic';
	const CONF_KEY_JMQTT_UUID           = 'installUUID';
	const CONF_KEY_MQTT_ADDRESS         = 'mqttAddress';
	const CONF_KEY_MQTT_PORT            = 'mqttPort';
	const CONF_KEY_MQTT_WS_URL          = 'mqttWsUrl';
	const CONF_KEY_MQTT_USER            = 'mqttUser';
	const CONF_KEY_MQTT_PASS            = 'mqttPass';
	const CONF_KEY_MQTT_ID              = 'mqttId';
	const CONF_KEY_MQTT_ID_VALUE        = 'mqttIdValue';
	const CONF_KEY_MQTT_LWT             = 'mqttLwt';
	const CONF_KEY_MQTT_LWT_TOPIC       = 'mqttLwtTopic';
	const CONF_KEY_MQTT_LWT_ONLINE      = 'mqttLwtOnline';
	const CONF_KEY_MQTT_LWT_OFFLINE     = 'mqttLwtOffline';
	const CONF_KEY_MQTT_PROTO           = 'mqttProto';
	const CONF_KEY_MQTT_TLS_CHECK       = 'mqttTlsCheck';
	const CONF_KEY_MQTT_TLS_CA          = 'mqttTlsCa';
	const CONF_KEY_MQTT_TLS_CLI         = 'mqttTlsClient';
	const CONF_KEY_MQTT_TLS_CLI_CERT    = 'mqttTlsClientCert';
	const CONF_KEY_MQTT_TLS_CLI_KEY     = 'mqttTlsClientKey';
	const CONF_KEY_MQTT_INT             = 'mqttInt';
	const CONF_KEY_MQTT_INT_TOPIC       = 'mqttIntTopic';
	const CONF_KEY_MQTT_API             = 'mqttApi';
	const CONF_KEY_MQTT_API_TOPIC       = 'mqttApiTopic';
	const CONF_KEY_QOS                  = 'Qos';
	const CONF_KEY_AUTO_ADD_CMD         = 'auto_add_cmd';
	const CONF_KEY_AUTO_ADD_TOPIC       = 'auto_add_topic';
	const CONF_KEY_BATTERY_CMD          = 'battery_cmd';
	const CONF_KEY_AVAILABILITY_CMD     = 'availability_cmd';
	const CONF_KEY_TEMPLATE_UUID        = 'templateUUID';
	const CONF_KEY_LOGLEVEL             = 'loglevel';

	const CACHE_DAEMON_LAST_SND         = 'daemonLastSnd';
	const CACHE_DAEMON_LAST_RCV         = 'daemonLastRcv';
	const CACHE_DAEMON_PORT             = 'daemonPort';
	const CACHE_DAEMON_UID              = 'daemonUid';
	const CACHE_IGNORE_TOPIC_MISMATCH   = 'ignore_topic_mismatch';
	const CACHE_JMQTT_NEXT_STATS        = 'nextStats';
	const CACHE_LAST_LAUNCH_TIME        = 'lastLaunchTime';
	const CACHE_MQTTCLIENT_CONNECTED    = 'mqttClientConnected';
	const CACHE_REALTIME_MODE           = 'realtime_mode';
	const CACHE_REALTIME_INC_TOPICS     = 'mqttIncTopic';
	const CACHE_REALTIME_EXC_TOPICS     = 'mqttExcTopic';
	const CACHE_REALTIME_RET_TOPICS     = 'mqttRetTopic';
	const CACHE_REALTIME_DURATION       = 'mqttDuration';

	const PATH_BACKUP                   = 'data/backup/';
	const PATH_TEMPLATES_PERSO          = 'data/template/';
	const PATH_TEMPLATES_JMQTT          = 'core/config/template/';


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
	 * Connected command of the broker related to this object
	 * @var jMQTTCmd
	 */
	private $_connectedCmd;

	private static function templateRead($_file) {
		// read content from file without error handeling!
		$content = file_get_contents($_file);
		// decode template file content to json (or raise)
		$templateContent = json_decode($content, true);
		// first key is the template itself
		$templateKey = array_keys($templateContent)[0];
		// return tuple of name & value
		return [$templateKey, $templateContent[$templateKey]];
	}

	/**
	 * Return a list of all templates name and file.
	 * @return list of name and file array.
	 */
	public static function templateList(){
		// self::logger('debug', 'templateList()');
		$return = array();
		// Get personal templates
		foreach (ls(__DIR__ . '/../../' . self::PATH_TEMPLATES_PERSO, '*.json', false, array('files', 'quiet')) as $file) {
			try {
				[$templateKey, $templateValue] = self::templateRead(__DIR__ . '/../../' . self::PATH_TEMPLATES_PERSO . $file);
				$return[] = array('[Perso] '.$templateKey, 'plugins/jMQTT/' . self::PATH_TEMPLATES_PERSO . $file);
			} catch (Throwable $e) {
				self::logger('warning', sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), self::PATH_TEMPLATES_PERSO . $file));
			}
		}
		// Get official templates
		foreach (ls(__DIR__ . '/../../' . self::PATH_TEMPLATES_JMQTT, '*.json', false, array('files', 'quiet')) as $file) {
			try {
				[$templateKey, $templateValue] = self::templateRead(__DIR__ . '/../../' . self::PATH_TEMPLATES_JMQTT . $file);
				$return[] = array($templateKey, 'plugins/jMQTT/' . self::PATH_TEMPLATES_JMQTT . $file);
			} catch (Throwable $e) {
				self::logger('warning', sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), self::PATH_TEMPLATES_JMQTT . $file));
			}
		}
		return $return;
	}

	/**
	 * Return a template content (from json files).
	 * @param string $_template template name to look for
	 * @return array
	 */
	public static function templateByName($_name){
		// self::logger('debug', 'templateByName: ' . $_name);
		if (strpos($_name , '[Perso] ') === 0) {
			// Get personal templates
			$name = substr($_name, strlen('[Perso] '));
			$folder = '/../../' . self::PATH_TEMPLATES_PERSO;
		} else {
			// Get official templates
			$name = $_name;
			$folder = '/../../' . self::PATH_TEMPLATES_JMQTT;
		}
		foreach (ls(__DIR__ . $folder, '*.json', false, array('files', 'quiet')) as $file) {
			try {
				[$templateKey, $templateValue] = self::templateRead(__DIR__ . $folder . $file);
				if ($templateKey == $name)
					return $templateValue;
			} catch (Throwable $e) {
				self::logger('warning', sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), $_name));
			}
		}
		return null;
	}

	/**
	 * Return one templates content (from json file name).
	 * @param string $_filename template name to look for
	 * @return array
	 */
	public static function templateByFile($_filename = ''){
		// self::logger('debug', 'templateByFile: ' . $_filename);
		$existing_files = self::templateList();
		$exists = false;
		foreach ($existing_files as list($n, $f))
			if ($f == $_filename) {
				$exists = true;
				break;
			}
		if (!$exists)
			throw new Exception(__("Le template demandé n'existe pas !", __FILE__));
		// self::logger('debug', '    get='.__DIR__ . '/../../../../' . $_filename);
		try {
			[$templateKey, $templateValue] = self::templateRead(__DIR__ . '/../../../../' . $_filename);
			return $templateValue;
		} catch (Throwable $e) {
			throw new Exception(sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), $_filename));
		}
		return array();
	}

	/**
	 * Split topic and jsonPath of all commands for the template file.
	 * @param string $_filename template name to look for.
	 */
	public static function moveTopicToConfigurationByFile($_filename = '') {

		try {
			[$templateKey, $templateValue] = self::templateRead(__DIR__ . '/../../' . self::PATH_TEMPLATES_PERSO . $_filename);

			// if 'configuration' key exists in this template
			if (isset($templateValue['configuration'])) {

				// if auto_add_cmd doesn't exists in configuration, we need to move topic from logicalId to configuration
				if (!isset($templateValue['configuration'][self::CONF_KEY_AUTO_ADD_TOPIC])) {
					$topic = $templateValue['logicalId'];
					$templateValue['configuration'][self::CONF_KEY_AUTO_ADD_TOPIC] = $topic;
					$templateValue['logicalId'] = '';
				}
			}

			// Save back template in the file
			$jsonExport = json_encode(array($templateKey=>$templateValue), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
			file_put_contents(__DIR__ . '/../../' . self::PATH_TEMPLATES_PERSO . $_filename, $jsonExport);
		} catch (Throwable $e) {
			throw new Exception(sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), $_filename));
		}
	}

	/**
	 * Deletes user defined template by filename.
	 * @param string $_template template name to look for.
	 */
	public static function deleteTemplateByFile($_filename){
		// self::logger('debug', 'deleteTemplateByFile: ' . $_filename);
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
		return unlink(__DIR__ . '/../../../../' . $_filename);
	}

	/**
	 * apply a template (from json) to the current equipement.
	 * @param array $_template content of the template to apply
	 * @param string $topic subscription topic
	 * @param bool   $_keepCmd keep existing commands
	 */
	public function applyATemplate($_template, $_baseTopic, $_keepCmd = true){
		if ($this->getType() != self::TYP_EQPT || is_null($_template))
			return;

		// Cleanup base topic (remove '/', '#' and '+' at the end)
		if (substr($_baseTopic, -1) == '#' || substr($_baseTopic, -1) == '+') { $_baseTopic = substr($_baseTopic, 0, -1); }
		if (substr($_baseTopic, -1) == '/') { $_baseTopic = substr($_baseTopic, 0, -1); }

		// Raise up the flag that cmd topic mismatch must be ignored
		$this->setCache(self::CACHE_IGNORE_TOPIC_MISMATCH, 1);

		// import template
		$this->import($_template, $_keepCmd);

		// Ensure topic has a wildcard at the end
		$mainTopic = sprintf($_template['configuration'][self::CONF_KEY_AUTO_ADD_TOPIC], $_baseTopic);
		if (substr($mainTopic, -1) != '/' && substr($mainTopic, -1) != '#' && substr($mainTopic, -1) != '+') { $mainTopic .= '/'; }
		if (substr($mainTopic, -1) != '#' && substr($mainTopic, -1) != '+') { $mainTopic .= '#'; }
		$this->setTopic($mainTopic);
		$this->save();

		// Create a replacement array with cmd names & id for further use
		$cmdsId = array();
		$cmdsName = array();
		foreach ($this->getCmd() as $cmd) {
			$cmdsId[] = '#' . $cmd->getId() . '#';
			$cmdsName[] = '#[' . $cmd->getName() . ']#';
			// Update battery linked info command
			if ($this->getConf(self::CONF_KEY_BATTERY_CMD) == $cmd->getName())
				$this->setConfiguration(self::CONF_KEY_BATTERY_CMD, $cmd->getId());
			// Update availability linked info command
			if ($this->getConf(self::CONF_KEY_AVAILABILITY_CMD) == $cmd->getName())
				$this->setConfiguration(self::CONF_KEY_AVAILABILITY_CMD, $cmd->getId());
		}
		if ($this->getConf(self::CONF_KEY_BATTERY_CMD) != "" || $this->getConf(self::CONF_KEY_AVAILABILITY_CMD) != "")
			$this->save();

		// complete cmd topics and replace template cmd names by cmd ids
		foreach ($this->getCmd() as $cmd) {
			$cmd->setTopic(sprintf($cmd->getTopic(), $_baseTopic));
			$cmd->replaceCmdIds($cmdsName, $cmdsId);
			$cmd->save();
		}

		// remove topic mismatch ignore flag
		$this->setCache(self::CACHE_IGNORE_TOPIC_MISMATCH, 0);
	}

	/**
	 * create a template from the current equipement (to json).
	 * @param string $_tName name of the template to create
	 */
	public function createTemplate($_tName) {

		if ($this->getType() != self::TYP_EQPT)
			return true;

		// Cleanup template name
		$_tName = ucfirst(str_replace('  ', ' ', trim($_tName)));
		$_tName = preg_replace('/[^a-zA-Z0-9 ()_-]+/', '', $_tName);

		// Export
		$exportedTemplate[$_tName] = $this->export();
		$exportedTemplate[$_tName]['name'] = $_tName;

		// Remove brkId from eqpt configuration
		unset($exportedTemplate[$_tName]['configuration'][self::CONF_KEY_BRK_ID]);

		// TODO (deprecation) Remove when Jeedom 4.2 is no longer supported
		// older version of Jeedom (4.2.6 and bellow) export commands in 'cmd'
		// Fixed here : https://github.com/jeedom/core/commit/05b8ecf34b405d5a0a0bb7356f8e3ecb1cf7fa91
		if (isset($exportedTemplate[$_tName]['cmd'])) {
			// Rename 'cmd' to 'commands' for Jeedom import ...
			$exportedTemplate[$_tName]['commands'] = $exportedTemplate[$_tName]['cmd'];
			unset($exportedTemplate[$_tName]['cmd']);
		}

		// Create a replacement array with cmd names & id for further use
		$cmdsId = array();
		$cmdsName = array();
		foreach ($this->getCmd() as $cmd) {
			$cmdsId[] = '#' . $cmd->getId() . '#';
			$cmdsName[] = '#[' . $cmd->getName() . ']#';
			// Update battery linked info command
			if ($cmd->isBattery())
				$exportedTemplate[$_tName]['configuration'][self::CONF_KEY_BATTERY_CMD] = $cmd->getName();
			// Update availability linked info command
			if ($cmd->isAvailability())
				$exportedTemplate[$_tName]['configuration'][self::CONF_KEY_AVAILABILITY_CMD] = $cmd->getName();
		}

		// Convert and save to file
		$jsonExport = json_encode($exportedTemplate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		// Convert relative cmd id to '#[Name]#' format in request
		$jsonExport = str_replace($cmdsId, $cmdsName, $jsonExport);

		// Looking for baseTopic from equipement
		$baseTopic = $this->getTopic();
		if (substr($baseTopic, -1) == '#' || substr($baseTopic, -1) == '+') { $baseTopic = substr($baseTopic, 0, -1); }
		if (substr($baseTopic, -1) == '/') { $baseTopic = substr($baseTopic, 0, -1); }

		// Convert topic to string format
		if ($baseTopic != '') {
			$toReplace = array('"'.self::CONF_KEY_AUTO_ADD_TOPIC.'": "'.$baseTopic, '"topic": "'.$baseTopic);
			$replaceBy = array('"'.self::CONF_KEY_AUTO_ADD_TOPIC.'": "%s',          '"topic": "%s');
			$jsonExport = str_replace($toReplace, $replaceBy, $jsonExport);
		}

		// Write template file
		file_put_contents(__DIR__ . '/../../' . self::PATH_TEMPLATES_PERSO . str_replace(' ', '_', $_tName) . '.json', $jsonExport);
	}

	/**
	 * Create a new equipment given its name, subscription topic and broker the equipment is related to.
	 * IMPORTANT: broker can be null, and then this is the responsability of the caller to attach the new equipment to a broker.
	 * Equipment is enabled, and saved.
	 * @param jMQTT $broker broker the equipment is related to
	 * @param string $name equipment name
	 * @param string $topic subscription topic
	 * return new jMQTT object
	 */
	public static function createEquipment($broker, $name, $topic) {
		$eqpt = new jMQTT();
		$eqpt->setName($name);
		$eqpt->setIsEnable(1);
		$eqpt->setTopic($topic);

		if (is_object($broker)) {
			$broker->log('info', sprintf(__("Création de l'équipement %1\$s pour le topic %2\$s", __FILE__), $name, $topic));
			$eqpt->setBrkId($broker->getId());
		}
		$eqpt->save();

		// Advise the desktop page (jMQTT.js) that a new equipment has been added
		event::add('jMQTT::eqptAdded', array('eqlogic_name' => $name));

		return $eqpt;
	}

	/**
	 * Create a new equipment given its name, subscription topic and broker the equipment is related to.
	 * IMPORTANT: broker can be null, and then this is the responsability of the caller to attach the new equipment to a broker.
	 * If a command already exists with the same logicalId, then it will be kept and updated, otherwise a new cmd will be created.
	 * Equipment is enabled, and saved.
	 * @param string $brk_addr is the IP/hostname of an EXISTING broker
	 * @param string $name of the new equipment to create
	 * @param string $template_path to the template json file
	 * @param string $topic is the subscription base topic to apply to the template file
	 * @param string $uuid is a unique ID provided at creation time to enable this equipment to be found later on
	 * return jMQTT object of a new eqLogic or an existing one if matched
	 * raise Exception is Broker could not be found
	 */
	public static function createEqWithTemplate($brk_addr, $name, $template_path, $topic, $uuid = null) {
		// self::logger('debug', 'createEqWithTemplate: name=' . $name . ', brk_addr=' . $brk_addr . ', topic=' . $topic . ', template_path=' . $template_path . ', uuid=' . $uuid);
		// Check if file is in Jeedom directory and exists
		if (strpos(realpath($template_path), getRootPath()) === false)
			throw new Exception(__("Le fichier template est en-dehors de Jeedom.", __FILE__));
		if (!file_exists($template_path))
			throw new Exception(__("Le fichier template n'a pas pu être trouvé.", __FILE__));

		// Locate the expected broker, if not found then raise !
		$brk_addr = (is_null($brk_addr) || $brk_addr == '') ? '127.0.0.1' : gethostbyname($brk_addr);
		$broker = null;
		foreach(self::getBrokers() as $brk) {
			$ip = gethostbyname($brk->getConf(self::CONF_KEY_MQTT_ADDRESS));
			if ($ip == $brk_addr || (substr($ip, 0, 4) == '127.' && substr($brk_addr, 0, 4) == '127.')) {
				$broker = $brk;
				self::logger('debug', sprintf(__("createEqWithTemplate %1\$s: Le Broker #%2\$s# a été trouvé", __FILE__), $name, $broker->getName()));
				break;
			}
		}
		if (!is_object($broker))
			throw new Exception(__("Aucun Broker n'a pu être identifié, créez un Broker dans jMQTT avant de créer un équipement.", __FILE__));

		$eq = null;
		// Try to locate the Eq is uuid is provided
		if (!is_null($uuid)) {
			// Search for a jMQTT Eq with $uuid, if found apply template to it
			$type = json_encode(array(self::CONF_KEY_TEMPLATE_UUID => $uuid));
			$eqpts = self::byTypeAndSearchConfiguration(__CLASS__, substr($type, 1, -1));
			foreach ($eqpts as $eqpt) {
				// If it's attached to correct broker
				if ($eqpt->getBrkId() == $broker->getId()) {
					self::logger('debug', sprintf(__("createEqWithTemplate %1\$s: L'Eq #%2\$s# a été trouvé", __FILE__), $name, $eqpt->getHumanName()));
					self::logger('debug', 'createEqWithTemplate ' . $name . ': Found matching Eq '.$eqpt->getHumanName());
					$eq = $eqpt;
					break;
				}
				self::logger('debug', sprintf(__("createEqWithTemplate %1\$s: L'Eq #%2\$s# a été trouvé avec cet UUID, mais sur le mauvais Broker", __FILE__), $name, $eqpt->getHumanName()));
			}
			if (is_null($eq))
				self::logger('debug', sprintf(__("createEqWithTemplate %s: Impossible de trouver un Eq correspondant à l'UUID sur ce Broker", __FILE__), $name));
		}
		// If the Eq is not located create it
		if (is_null($eq)) {
			$eq = self::createEquipment($broker, $name, $topic);
			self::logger('debug', sprintf(__("createEqWithTemplate %s: Nouvel équipement créé", __FILE__), $name));
			if (!is_null($uuid)) {
				$eq->setConfiguration(self::CONF_KEY_TEMPLATE_UUID, $uuid);
				$eq->save();
			}
		}

		// Get template content from file
		try {
			[$templateKey, $templateValue] = self::templateRead($template_path);
		} catch (Throwable $e) {
			throw new Exception(sprintf(__('Erreur lors de la lecture du ficher Template %s', __FILE__), $template_path));
		}

		// Apply the template
		$eq->applyATemplate($templateValue, $topic, true);

		// Return the Eq with the applied template
		return $eq;
	}

	/**
	 * Overload the equipment copy method
	 * All information are copied but: suscribed topic (left empty), enable status (left disabled) and
	 * information commands.
	 * @param string $_name new equipment name
	 */
	public function copy($_name) {

		$this->log('info', sprintf(__("Copie de l'équipement %1\$s depuis l'équipement #%2\$s#", __FILE__), $_name, $this->getHumanName()));

		// Clone the equipment and change properties that shall be changed
		// . new id will be given at saving
		// . suscribing topic let empty to force the user to change it
		// . remove commands: they are defined at the next step (as done in the parent method)
		/** @var jMQTT $eqLogicCopy */
		$eqLogicCopy = clone $this;
		$eqLogicCopy->setId('');
		$eqLogicCopy->setName($_name);
		if ($eqLogicCopy->getIsEnable())
			$eqLogicCopy->setIsEnable(0);
		foreach ($eqLogicCopy->getCmd() as $cmdCopy)
			$cmdCopy->remove();
		$eqLogicCopy->save(); // Needed here to get an Id

		$cmdsNameList = array();
		$cmdsOldId = array();
		$cmdsNewId = array();
		// Clone commands
		foreach ($this->getCmd() as $cmd) {
			$cmdCopy = clone $cmd;
			$cmdCopy->setId('');
			$cmdCopy->setEqLogic_id($eqLogicCopy->getId());
			$cmdCopy->setEqLogic($eqLogicCopy);
			if ($cmd->getType() == 'action') { // Replace linked info cmd Id by its Name
				$cmdValue = $cmd->getCmdValue();
				$cmdCopy->setValue(is_object($cmdValue) ? $cmdValue->getName() : '');
			}
			$cmdCopy->save(); // Needed here to get an Id

			// Gather mapping data
			$cmdsNameList[$cmdCopy->getName()] = $cmdCopy->getId(); // Store all cmd names -> id for further usage
			$cmdsOldId[] = '#' . $cmd->getId() . '#';
			$cmdsNewId[] = '#' . $cmdCopy->getId() . '#';

			// Update battery linked info command
			if ($cmd->isBattery())
				$eqLogicCopy->setConfiguration(self::CONF_KEY_BATTERY_CMD, $cmdCopy->getId());

			// Update availability linked info command
			if ($cmd->isAvailability())
				$eqLogicCopy->setConfiguration(self::CONF_KEY_AVAILABILITY_CMD, $cmdCopy->getId());
			$this->log('info', sprintf(__("Copie de la commande %1\$s #%2\$s# vers la commande #%3\$s#", __FILE__), $cmd->getType(), $cmd->getHumanName(), $cmdCopy->getHumanName()));
		}
		if ($eqLogicCopy->getConf(self::CONF_KEY_BATTERY_CMD) != "" || $eqLogicCopy->getConf(self::CONF_KEY_AVAILABILITY_CMD) != "")
			$eqLogicCopy->save();

		foreach ($eqLogicCopy->getCmd() as $cmdCopy) {
			if ($cmdCopy->getType() != 'action') // Only on action cmds
				continue;
			// Update linked info cmd
			if ($cmdCopy->getValue() != '')
				$cmdCopy->setValue($cmdsNameList[$cmdCopy->getValue()]);
			// Update relative (to eqLogic) info cmd in action cmd payload
			$request = $cmdCopy->getConfiguration(jMQTTCmd::CONF_KEY_REQUEST, "");
			$cmdCopy->setConfiguration(jMQTTCmd::CONF_KEY_REQUEST, str_replace($cmdsOldId, $cmdsNewId, $request));
			$cmdCopy->save();
		}
		return $eqLogicCopy;
	}

	/**
	 * Return a full export (inc. commands) of all eqLogic as an array starting by Brokers.
	 * @param boolean $clean irrelevant values to Daemon must be removed from the return
	 * @return array representing the eqLogic and its cmd
	 */
	public static function full_export($clean=false) {
		$returns = array();
		foreach (eqLogic::byType(__CLASS__) as $eq) {
			$exp = $eq->toArray();
			$obj_name = (is_object($eq->getObject())) ? $eq->getObject()->getName() : __('Aucun', __FILE__);
			$exp['name'] = $obj_name.':'.$exp['name'];
			if ($clean) { // Remove unneeded informations
				unset($exp['category']);
				unset($exp['configuration']['battery_type']);
				unset($exp['configuration']['createtime']);
				unset($exp['configuration']['commentaire']);
				unset($exp['configuration']['icone']);
				unset($exp['configuration']['updatetime']);
				unset($exp['comment']);
				unset($exp['display']);
				unset($exp['isVisible']);
				unset($exp['object_id']);
				unset($exp['status']);
				unset($exp['tags']);
				unset($exp['timeout']);
			}
			$exp['commands'] = array();
			foreach ($eq->getCmd() as $cmd)
				$exp['commands'][] = $cmd->full_export($clean);
			$returns[] = $exp;
		}
		function fsort($a, $b) {
			$x = ((isset($a['configuration']) && isset($a['configuration']['type'])) ?
					$a['configuration']['type'] : "z").$a['id'];
			$y = ((isset($b['configuration']) && isset($b['configuration']['type'])) ?
					$b['configuration']['type'] : "z").$b['id'];
			return strcmp($x, $y);
		}
		usort($returns, 'fsort'); // Put the Broker first (needed)
		return $returns;
	}

	/**
	 * Return jMQTT objects of type broker
	 * @return jMQTT[]
	 */
	public static function getBrokers() {
		$type = json_encode(array('type' => self::TYP_BRK));
		/** @var jMQTT[] $brokers */
		$brokers = self::byTypeAndSearchConfiguration(__CLASS__, substr($type, 1, -1));
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
		$eqls = self::byType(__CLASS__);
		$returns = array();
		foreach ($eqls as $eql) {
			if ($eql->getType() != self::TYP_BRK) {
				$returns[$eql->getBrkId()][] = $eql;
			}
		}
		return $returns;
	}

	/**
	 * subscribe topic, ALWAYS
	 */
	public function subscribeTopic($topic, $qos) {
		// No Topic provided
		if (empty($topic)) {
			if ($this->getType() == self::TYP_EQPT)
				$this->log('info', sprintf(__("L'équipement #%s# n'est pas Inscrit à un Topic", __FILE__), $this->getHumanName()));
			else
				$this->log('info', sprintf(__("Le Broker %s n'a pas de Topic de souscription", __FILE__), $this->getName()));
			return;
		}
		$broker = $this->getBroker();
		// If broker eqpt is disabled, don't need to send subscribe
		if(!$broker->getIsEnable()) {
			$this->log('debug', sprintf(__("Le Broker %1\$s n'est pas actif, impossible de s'inscrire au topic '%2\$s' avec une Qos de %3\$s", __FILE__), $this->getName(), $topic, $qos));
			return;
		}
		if ($this->getType() == self::TYP_EQPT)
			$this->log('info', sprintf(__("L'équipement #%1\$s# s'inscrit au topic '%2\$s' avec une Qos de %3\$s", __FILE__), $this->getHumanName(), $topic, $qos));
		else
			$this->log('info', sprintf(__("Le Broker %1\$s s'inscrit au topic '%2\$s' avec une Qos de %3\$s", __FILE__), $this->getName(), $topic, $qos));
		self::toDaemon_subscribe($broker->getId(), $topic, $qos);
	}

	/**
	 * Unsubscribe topic, ONLY if no other enabled eqpt linked to the same broker subscribes the same topic
	 */
	public function unsubscribeTopic($topic, $brkId = null) { // old Broker can be provided when switching Eq to another Broker
		// No Topic provided
		if (empty($topic)) {
			if ($this->getType() == self::TYP_EQPT)
				$this->log('info', sprintf(__("L'équipement #%s# n'est pas Inscrit à un Topic", __FILE__), $this->getHumanName()));
			else
				$this->log('info', sprintf(__("Le Broker %s n'a pas de Topic de souscription", __FILE__), $this->getName()));
			return;
		}
		$broker = is_null($brkId) ? $this->getBroker() : self::getBrokerFromId($brkId);
		// If broker eqpt is disabled, don't need to send unsubscribe
		if(!$broker->getIsEnable())
			return;
		// Find eqLogic using the same topic AND the same Broker
		$topicConfiguration = array(self::CONF_KEY_AUTO_ADD_TOPIC => $topic, self::CONF_KEY_BRK_ID => $broker->getBrkId());
		$eqLogics = self::byTypeAndSearchConfiguration(__CLASS__, $topicConfiguration);
		foreach ($eqLogics as $eqLogic) {
			if ($eqLogic->getIsEnable() && $eqLogic->getId() != $this->getId()) { // If it's enabled AND it's not "me"
				$this->log('info', sprintf(__("Un autre équipement a encore besoin du topic '%s'", __FILE__), $topic));
				return;
			}
		}
		// If there is no other eqLogic using the same topic, we can unsubscribe
		if ($this->getType() == self::TYP_EQPT)
			$this->log('info', sprintf(__("L'équipement #%1\$s# se désinscrit du topic '%2\$s'", __FILE__), $this->getHumanName(), $topic));
		else
			$this->log('info', sprintf(__("Le Broker %1\$s se désinscrit du topic '%2\$s'", __FILE__), $this->getName(), $topic));
		self::toDaemon_unsubscribe($broker->getId(), $topic);
	}

	/**
	 * Overload preSave to apply some checks/initialization and prepare postSave
	 */
	public function preSave() {
		// Check Type: No Type => self::TYP_EQPT
		if ($this->getType() != self::TYP_BRK && $this->getType() != self::TYP_EQPT) {
			$this->setType(self::TYP_EQPT);
		}

		// Check eqType_name: should be __CLASS__
		if ($this->eqType_name != __CLASS__) {
			$this->setEqType_name(__CLASS__);
		}

		// ------------------------ New or Existing Broker eqpt ------------------------
		if ($this->getType() == self::TYP_BRK) {
			// Check for a broker eqpt with the same name (which is not this)
			foreach(self::getBrokers() as $broker) {
				if ($broker->getName() == $this->getName() && $broker->getId() != $this->getId()) {
					throw new Exception(sprintf(__("Le Broker #%s# porte déjà le même nom", __FILE__), $this->getHumanName()));
				}
			}

			// TODO (low) Check if certificates are OK
			// self::CONF_KEY_MQTT_TLS_CHECK
			// self::CONF_KEY_MQTT_TLS_CA
			// self::CONF_KEY_MQTT_TLS_CLI_CERT
			// self::CONF_KEY_MQTT_TLS_CLI_KEY
		}

		// ------------------------ New or Existing Broker or Normal eqpt ------------------------

		// It's time to gather informations that will be used in postSave
		if ($this->getId() == '')
			$this->_preSaveInformations = null; // New eqpt => Nothing to collect
		else { // Existing eqpt

			// load eqLogic from DB
			$eqLogic = self::byId($this->getId());
			$this->_preSaveInformations = array(
				'name'                  => $eqLogic->getName(),
				'isEnable'              => $eqLogic->getIsEnable(),
				'topic'                 => $eqLogic->getTopic(),
				self::CONF_KEY_BRK_ID   => $eqLogic->getBrkId()
			);

			// load trivials eqLogic from DB
			$backupVal = array(
				self::CONF_KEY_LOGLEVEL,
				self::CONF_KEY_MQTT_PROTO,
				self::CONF_KEY_MQTT_ADDRESS,
				self::CONF_KEY_MQTT_PORT,
				self::CONF_KEY_MQTT_WS_URL,
				self::CONF_KEY_MQTT_USER,
				self::CONF_KEY_MQTT_PASS,
				self::CONF_KEY_MQTT_ID,
				self::CONF_KEY_MQTT_ID_VALUE,
				self::CONF_KEY_MQTT_LWT,
				self::CONF_KEY_MQTT_LWT_TOPIC,
				self::CONF_KEY_MQTT_LWT_ONLINE,
				self::CONF_KEY_MQTT_LWT_OFFLINE,
				self::CONF_KEY_MQTT_TLS_CHECK,
				self::CONF_KEY_MQTT_TLS_CA,
				self::CONF_KEY_MQTT_TLS_CLI,
				self::CONF_KEY_MQTT_TLS_CLI_CERT,
				self::CONF_KEY_MQTT_TLS_CLI_KEY,
				self::CONF_KEY_MQTT_INT,
				self::CONF_KEY_MQTT_INT_TOPIC,
				self::CONF_KEY_MQTT_API,
				self::CONF_KEY_MQTT_API_TOPIC,
				self::CONF_KEY_BATTERY_CMD,
				self::CONF_KEY_AVAILABILITY_CMD,
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
				config::save('log::level::' . $this->getMqttClientLogFile(), '{"100":"0","200":"0","300":"0","400":"0","1000":"0","default":"1"}', __CLASS__);

				// Create status and connected cmds
				$this->getMqttClientStatusCmd(true);
				$this->getMqttClientConnectedCmd(true);

				// Enabled => Start MqttClient
				if ($this->getIsEnable()) $this->startMqttClient();
			}
			// --- Existing broker ---
			else {

				$stopped = ($this->getMqttClientState() == self::CLIENT_NOK);
				$startRequested = false;

				// isEnable changed
				if ($this->_preSaveInformations['isEnable'] != $this->getIsEnable()) {
					if ($this->getIsEnable()) {
						$this->getMqttClientStatusCmd(true)->event(self::CLIENT_STATUS_OFFLINE); // Force current status to offline
						$this->getMqttClientConnectedCmd(true)->event(0); // Force current connected to 0
						$this->setStatus('warning', 1); // And a warning
						$startRequested = true; //If nothing happens in between, it will be restarted
					} else {
						// Note that $stopped is always true here
						$this->stopMqttClient();
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
					if (file_exists(log::getPathToLog($old_log)))
						rename(log::getPathToLog($old_log), log::getPathToLog($new_log));
					config::save('log::level::' . $new_log, config::byKey('log::level::' . $old_log, __CLASS__), __CLASS__);
					config::remove('log::level::' . $old_log, __CLASS__);
				}

				// Check changes that would trigger MQTT Client reload
				$checkChanged = array(
					self::CONF_KEY_MQTT_PROTO,
					self::CONF_KEY_MQTT_ADDRESS,
					self::CONF_KEY_MQTT_PORT,
					self::CONF_KEY_MQTT_WS_URL,
					self::CONF_KEY_MQTT_USER,
					self::CONF_KEY_MQTT_PASS,
					self::CONF_KEY_MQTT_ID,
					self::CONF_KEY_MQTT_ID_VALUE,
					self::CONF_KEY_MQTT_LWT,
					self::CONF_KEY_MQTT_LWT_TOPIC,
					self::CONF_KEY_MQTT_LWT_ONLINE,
					self::CONF_KEY_MQTT_LWT_OFFLINE,
					self::CONF_KEY_MQTT_TLS_CHECK,
					self::CONF_KEY_MQTT_TLS_CA,
					self::CONF_KEY_MQTT_TLS_CLI,
					self::CONF_KEY_MQTT_TLS_CLI_CERT,
					self::CONF_KEY_MQTT_TLS_CLI_KEY,
					self::CONF_KEY_MQTT_INT,
					self::CONF_KEY_MQTT_INT_TOPIC,
					self::CONF_KEY_MQTT_API,
					self::CONF_KEY_MQTT_API_TOPIC);
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

				// LWT Topic changed
				if ($this->_preSaveInformations[self::CONF_KEY_MQTT_LWT]       != $this->getConf(self::CONF_KEY_MQTT_LWT) ||
					$this->_preSaveInformations[self::CONF_KEY_MQTT_LWT_TOPIC] != $this->getConf(self::CONF_KEY_MQTT_LWT_TOPIC)) {
					if (!$stopped) {
						// Just try to remove the previous status topic
						$this->publish($this->getName(), $this->_preSaveInformations[self::CONF_KEY_MQTT_LWT], '', 1, 1);
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

				// brkId changed
				if ($this->_preSaveInformations[self::CONF_KEY_BRK_ID] != $this->getConf(self::CONF_KEY_BRK_ID)) {
					// Get new Broker
					$new_broker = self::getBrokerFromId($this->getBrkId());
					if ($this->_preSaveInformations[self::CONF_KEY_BRK_ID] <= 0) { // Orphan
						$new_broker->log('info', sprintf(__("Ajout de l'Equipement orphelin #%1\$s#", __FILE__), $this->getHumanName()));
					} else {
						// Get old Broker
						$old_broker = self::getBrokerFromId($this->_preSaveInformations[self::CONF_KEY_BRK_ID]);
						// Log on old and new Broker
						$old_broker->log('info', sprintf(__("Déplacement de l'Equipement #%1\$s# vers le broker %2\$s", __FILE__), $this->getHumanName(), $new_broker->getName()));
						$new_broker->log('info', sprintf(__("Déplacement de l'Equipement #%1\$s# depuis le broker %2\$s", __FILE__), $this->getHumanName(), $old_broker->getName()));
						//need to unsubscribe the PREVIOUS topic on the PREVIOUS Broker
						$this->unsubscribeTopic($this->_preSaveInformations['topic'], $this->_preSaveInformations[self::CONF_KEY_BRK_ID]);
					}
					//force Broker change in current object
					$this->_broker = $new_broker;
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

				// Battery removed -> Clear Battery status
				if ($this->_preSaveInformations[self::CONF_KEY_BATTERY_CMD] != '' && $this->getConf(self::CONF_KEY_BATTERY_CMD) == '') {
					$this->setStatus('battery', null);
					$this->setStatus('batteryDatetime', null);
					$this->log('debug', sprintf(__("Nettoyage de la Batterie de l'équipement #%s#", __FILE__), $this->getHumanName()));
				}

				// Availability removed -> Clear Availability (Timeout) status
				if ($this->_preSaveInformations[self::CONF_KEY_AVAILABILITY_CMD] != '' && $this->getConf(self::CONF_KEY_AVAILABILITY_CMD) == '') {
					$this->setStatus('warning', null);
					$this->log('debug', sprintf(__("Nettoyage de la Disponibilité de l'équipement #%s#", __FILE__), $this->getHumanName()));
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

			$this->log('info', sprintf(__("Suppression du Broker %s", __FILE__), $this->getName()));

			// Disable first the broker to Stop MqttClient
			if ($this->getIsEnable()) {
				$this->setIsEnable(0);
				$this->save();

				// Wait up to 10s for MqttClient stopped
				for ($i=0; $i < 40; $i++) {
					if ($this->getMqttClientState() != self::CLIENT_OK) break;
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
			$this->log('info', sprintf(__("Suppression de l'équipement #%s#", __FILE__), $this->getHumanName()));
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
			config::remove('log::level::' . $log, __CLASS__);
			@cache::delete('jMQTT::' . $this->getId() . '::' . self::CACHE_MQTTCLIENT_CONNECTED);

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
			if(!$broker->getIsEnable()) {
				$return[] = array(
					'test' => __('Accès au broker', __FILE__) . ' <b>' . $broker->getName() . '</b>',
					'result' => __('Client jMQTT désactivé', __FILE__),
					'advice' => '',
					'state' => true
				);
				continue;
			}
			$mosqHost = $broker->getConf(self::CONF_KEY_MQTT_ADDRESS);
			$mosqPort = $broker->getConf(self::CONF_KEY_MQTT_PORT);
			$socket = socket_create(AF_INET, SOCK_STREAM, 0);
			$state = false;
			if ($socket !== false) {
				$state = socket_connect($socket , $mosqHost, $mosqPort);
				socket_close($socket);
			}

			$return[] = array(
				'test' => __('Accès au broker', __FILE__) . ' <b>' . $broker->getName() . '</b>',
				'result' => $state ? __('OK', __FILE__) : __('NOK', __FILE__),
				'advice' => $state ? '' : __('Vérifiez les paramètres de connexion réseau', __FILE__),
				'state' => $state
			);

			if ($state) {
				$info = $broker->getMqttClientInfo();
				$return[] = array(
					'test' => __('Configuration du broker', __FILE__) . ' <b>' . $broker->getName() . '</b>',
					'result' => strtoupper($info['launchable']),
					'advice' => ($info['launchable'] != self::CLIENT_OK ? $info['message'] : ''),
					'state' => ($info['launchable'] == self::CLIENT_OK)
				);
				if (end($return)['state']) {
					$return[] = array(
						'test' => __('Connexion au broker', __FILE__) . ' <b>' . $broker->getName() . '</b>',
						'result' => strtoupper($info['state']),
						'advice' => ($info['state'] != self::CLIENT_OK ? $info['message'] : ''),
						'state' => ($info['state'] == self::CLIENT_OK)
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
	 * check MQTT Clients are up and connected
	 */
	public static function cron() {
		self::checkAllMqttClients();
		self::pluginStats();
	}

	public static function pluginStats($_reason = 'cron') {
		// Check last reporting (or if forced)
		$nextStats = @cache::byKey('jMQTT::'.self::CACHE_JMQTT_NEXT_STATS)->getValue(0);
		if ($_reason === 'cron' && (time() < $nextStats)) { // No reason to force send stats
			// self::logger('debug', sprintf(__("Aucune raison d'envoyer des données statistiques avant le %s", __FILE__), date('Y-m-d H:i:s', $nextStats)));
			return;
		}
		// Ensure next attempt will be in at least 5 minutes
		cache::set('jMQTT::'.self::CACHE_JMQTT_NEXT_STATS, time() + 300 + rand(0, 300)); // in 5-10 mins

		$url = 'https://stats.bad.wf/jmqtt.php';
		$data = array();
		$data['version'] = 1;
		$data['hardwareKey'] = jeedom::getHardwareKey();
		// Ensure system unicity using a rotating UUID
		$data['lastUUID'] = config::byKey(self::CONF_KEY_JMQTT_UUID, __CLASS__, $data['hardwareKey']);
		$data['UUID'] = base64_encode(hash('sha384', microtime() . random_bytes('107'), true));
		$data['hardwareName'] = jeedom::getHardwareName();
		// TODO (low) Detect VM and docker
		$data['distrib'] = trim(shell_exec('. /etc/*-release && echo $ID $VERSION_ID'));
		$data['phpVersion'] = phpversion();
		// TODO (low) Add Python version
		$data['jeedom'] = jeedom::version();
		$data['lang'] = config::byKey('language', 'core', 'fr_FR');
		$jplugin = update::byLogicalId(__CLASS__);
		$data['source'] = $jplugin->getSource();
		$data['branch'] = $jplugin->getConfiguration('version', 'unknown');
		$data['configVersion'] = config::byKey('version', __CLASS__, -1);
		// TODO (low) Add int nb eqBroker, eqLogic & cmd
		$data['reason'] = $_reason;
		if ($_reason == 'uninstall' || $_reason == 'noStats')
			$data['removeMe'] = true;
		else
			$data['next'] = time() + 432000 + rand(0, 172800); // Next stats in 5-7 days
		$options = array('http' => array(
			'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
			'method'  => 'POST', 'content' => http_build_query($data)
		));
		self::logger('debug', sprintf(__('Transmission des données statistiques suivantes : %s', __FILE__), json_encode($data)));
		$context = stream_context_create($options);
		$result = @file_get_contents($url, false, $context);

		if ($result === false) {
			// Could not send or invalid data
			self::logger('debug', __('Impossible de communiquer avec le serveur de statistiques (Réponse : false)', __FILE__));
			return;
		}
		$response = @json_decode($result, true);
		if (!isset($response['status']) || $response['status'] != 'success') {
			// Could not send or invalid data
			self::logger('debug', sprintf(__('Impossible de communiquer avec le serveur de statistiques (Réponse : %s)', __FILE__), $result));
		} else {
			config::save(self::CONF_KEY_JMQTT_UUID, $data['UUID'], __CLASS__);
			if (isset($data['removeMe']) && $data['removeMe']) {
				self::logger('info', __('Données statistiques supprimées', __FILE__));
				cache::set('jMQTT::'.self::CACHE_JMQTT_NEXT_STATS, PHP_INT_MAX);
			} else {
				self::logger('debug', sprintf(__('Données statistiques envoyées (Réponse : %s)', __FILE__), $result));
				// Set last sent datetime
				cache::set('jMQTT::'.self::CACHE_JMQTT_NEXT_STATS, $data['next']);
			}
		}
	}

	private static function clean_cache() {
		// for each id, clean cached values
		foreach (self::getBrokers() as $broker) {
			$broker->setCache(self::CACHE_MQTTCLIENT_CONNECTED, false);
			$broker->setCache(self::CACHE_REALTIME_MODE, false);
		}
		@cache::delete('jMQTT::' . self::CACHE_DAEMON_UID);
		@cache::delete('jMQTT::' . self::CACHE_DAEMON_PORT);
		@cache::delete('jMQTT::' . self::CACHE_DAEMON_LAST_SND);
		@cache::delete('jMQTT::' . self::CACHE_DAEMON_LAST_RCV);
		self::sendMqttDaemonStateEvent(false);
	}

	public static function valid_uid($ruid) {
		$cuid = @cache::byKey('jMQTT::'.self::CACHE_DAEMON_UID)->getValue("0:0");
		if ($cuid === "0:0")
			 return null;
		return $cuid === $ruid;
	}

	/**
	 * Validates that a daemon is connected, running and is communicating
	 */
	private static function daemon_check() {
		//self::logger('debug', 'daemon_check() ['.getmypid().']: ref='.$_SERVER['HTTP_REFERER']);	// VERY VERBOSE (1/5s to 1/m): Do not activate if not needed!
		// Get Cached PID and PORT
		$cuid = @cache::byKey('jMQTT::'.self::CACHE_DAEMON_UID)->getValue("0:0");
		if ($cuid == "0:0") { // If UID nul -> not running
			//self::logger('debug', __('Démon avec un UID nul.', __FILE__));						// VERY VERBOSE (1/5s to 1/m): Do not activate if not needed!
			return false;
		}
		list($cpid, $cport) = array_map('intval', explode(":", $cuid));
		if (!@posix_getsid($cpid)) { // PID IS NOT alive
			self::logger('debug', __('Démon avec un PID mort.', __FILE__));
			self::deamon_stop(); // Cleanup and put jmqtt in a good state
			return false;
		}
		if ((@cache::byKey('jMQTT::'.self::CACHE_DAEMON_PORT)->getValue(0)) != $cport) {
			self::logger('debug', __('Démon avec un mauvais port.', __FILE__));
			self::deamon_stop(); // Cleanup and put jmqtt in a good state
			return false;
		}
		if (time() - (@cache::byKey('jMQTT::'.self::CACHE_DAEMON_LAST_RCV)->getValue(0)) > 300) {
			self::logger('debug', __('Pas de message ou de Heartbeat reçu depuis >300s, le Démon est probablement mort.', __FILE__));
			self::deamon_stop(); // Cleanup and put jmqtt in a good state
			return false;
		}
		if (time() - (@cache::byKey('jMQTT::'.self::CACHE_DAEMON_LAST_SND)->getValue(0)) > 45) {
			self::logger('debug', __("Envoi d'un Heartbeat au Démon (rien n'a été envoyé depuis >45s).", __FILE__));
			self::toDaemon_hb();
			return true;
		}
		//self::logger('debug', __('Démon OK', __FILE__));											// VERY VERBOSE (1/5s to 1/m): Do not activate if not needed!
		return true;
	}

	/**
	 * Simple tests if a daemon is connected (do not validate it)
	 */
	public static function daemon_state() {
		return (@cache::byKey('jMQTT::'.self::CACHE_DAEMON_UID)->getValue("0:0")) !== "0:0";
	}

	/**
	 * Jeedom callback to get information on the daemon
	 */
	public static function deamon_info() {
		$return = array('launchable' => self::CLIENT_OK, 'log' => __CLASS__);
		$return['state'] = (self::daemon_check()) ? self::CLIENT_OK : self::CLIENT_NOK;
		return $return;
	}

	/**
	 * jMQTT static function returning an automatically detected callback url to Jeedom for the daemon
	 */
	public static function get_callback_url() {
		$prot = config::byKey('internalProtocol', 'core', 'http://');		// To fix let's encrypt issue like: https://community.jeedom.com/t/87060/26
		$port = config::byKey('internalPort', 'core', 80);					// To fix port issue like: https://community.jeedom.com/t/87060/30
		$comp = trim(config::byKey('internalComplement', 'core', ''), '/');	// To fix path issue like: https://community.jeedom.com/t/87872/15
		if ($comp !== '') $comp .= '/';
		return $prot.'127.0.0.1:'.$port.'/'.$comp.'plugins/jMQTT/core/php/callback.php';
	}

	/**
	 * Jeedom callback to start daemon
	 */
	public static function deamon_start() {
		// if FORCE_DEPENDANCY_INSTALL flag is raised in plugin config
		if (config::byKey(self::FORCE_DEPENDANCY_INSTALL, __CLASS__, 0) == 1) {
			self::logger('info', __("Installation/Vérification forcée des dépendances, le démon jMQTT démarrera au prochain essai", __FILE__));
			$plugin = plugin::byId(__CLASS__);
			//clean dependancy state cache
			$plugin->dependancy_info(true);
			//start dependancy install
			$plugin->dependancy_install();
			//remove flag
			config::remove(self::FORCE_DEPENDANCY_INSTALL, __CLASS__);
			// Installation of the dependancies occures in another process, this one must end.
			return;
		}
		self::logger('info', __('Démarrage du démon jMQTT', __FILE__));
		// Always stop first.
		self::deamon_stop();
		// Ensure cron is enabled (removing the key or setting it to 1 is equivalent to enabled)
		config::remove('functionality::cron::enable', __CLASS__);
		// Check if daemon is launchable
		$dep_info = self::dependancy_info();
		if ($dep_info['state'] != self::CLIENT_OK) {
			throw new Exception(__('Veuillez vérifier la configuration et les dépendances', __FILE__));
		}
		// Reset timers to let Daemon start
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_SND, time());
		// Start Python daemon
		$path = realpath(__DIR__ . '/../../resources/jmqttd');
		$callbackURL = self::get_callback_url();
		// To fix issue: https://community.jeedom.com/t/87727/39
		if ((file_exists('/.dockerenv') || config::byKey('forceDocker', __CLASS__, '0')) && config::byKey('urlOverrideEnable', __CLASS__, '0') == '1')
			$callbackURL = config::byKey('urlOverrideValue', __CLASS__, $callbackURL);
		$shellCmd  = 'LOGLEVEL=' . log::convertLogLevel(log::getLogLevel(__CLASS__));
		$shellCmd .= ' CALLBACK="'.$callbackURL.'"';
		$shellCmd .= ' APIKEY=' . jeedom::getApiKey(__CLASS__);
		$shellCmd .= ' PIDFILE=' . jeedom::getTmpFolder(__CLASS__) . '/jmqttd.py.pid ';
		$shellCmd .= $path.'/venv/bin/python3 ' . $path . '/jmqttd.py';
		$shellCmd .= ' >> ' . log::getPathToLog(__CLASS__.'d') . ' 2>&1 &';
		if (log::getLogLevel(__CLASS__) > 100)
			self::logger('info', __('Lancement du démon jMQTT', __FILE__));
		else
			self::logger('info', sprintf(__("Lancement du démon jMQTT, commande shell: '%s'", __FILE__), $shellCmd));
		exec($shellCmd);
		// Wait up to 10 seconds for daemon to start
		for ($i = 1; $i <= 40; $i++) {
			if (self::daemon_state()) {
				self::logger('info', __('Démon démarré', __FILE__));
				break;
			}
			usleep(250000);
		}
		// If daemon has not correctly started
		if (!self::daemon_state()) {
			self::deamon_stop();
			log::add(__CLASS__, 'error', __('Impossible de lancer le démon jMQTT, vérifiez les logs de jMQTT', __FILE__), 'unableStartDaemon'); // Use log::add() to set 'unableStartDaemon' as logicalId
			return;
		}
		// Else all good
		message::removeAll(__CLASS__, 'unableStartDaemon');
	}

	/**
	 * callback to stop daemon
	 */
	public static function deamon_stop() {
		// Get cached PID and PORT
		$cuid = @cache::byKey('jMQTT::'.self::CACHE_DAEMON_UID)->getValue("0:0");
		list($cpid, $cport) = array_map('intval', explode(":", $cuid));
		// If PID is available and running
		if ($cpid != 0 && @posix_getsid($cpid)) {
			self::logger('info', __("Arrêt du démon jMQTT", __FILE__));
			posix_kill($cpid, 15);  // Signal SIGTERM
			self::logger('debug', __("Envoi du signal SIGTERM au Démon", __FILE__));
			for ($i = 1; $i <= 40; $i++) {	//wait max 10 seconds for python daemon stop
				if (!self::daemon_state()) {
					self::logger('info', __("Démon jMQTT arrêté", __FILE__));
					break;
				}
				usleep(250000);
			}
			if (self::daemon_state()) {
				// Signal SIGKILL
				posix_kill($cpid, 9);
				self::logger('debug', __("Envoi du signal SIGKILL au Démon", __FILE__));
			}
		}
		// If something bad happened, clean anyway
		self::logger('debug', __("Nettoyage du Démon", __FILE__));
		self::fromDaemon_daemonDown($cuid);
	}

	/**
	 * Daemon callback to tell Jeedom it is started
	 */
	public static function fromDaemon_daemonUp($ruid) {
		// If we get here, apikey is OK!
		//self::logger('debug', 'fromDaemon_daemonUp(ruid='.$ruid.')');
		// Verify that daemon RemoteUID contains ':' or die
		if (is_null($ruid) || !is_string($ruid) || (strpos($ruid, ':') === false)) {
			self::logger('warning', sprintf(__("Démon [%s] : Inconsistant", __FILE__), $ruid));
			return '';
		}
		// Verify that this daemon is not already initialized
		$cuid = @cache::byKey('jMQTT::'.self::CACHE_DAEMON_UID)->getValue("0:0");
		if ($cuid == $ruid) {
			self::logger('info', sprintf(__("Démon [%s] : Déjà initialisé", __FILE__), $ruid));
			return '';
		}
		list($rpid, $rport) = array_map('intval', explode(":", $ruid));
		// Verify Remote UID coherence
		if ($rpid == 0) { // If Remote PID is NOT available
			self::logger('warning', sprintf(__("Démon [%s] : Pas d'identifiant d'exécution", __FILE__), $ruid));
			return '';
		}
		if (!@posix_getsid($rpid)) { // Remote PID is not running
			self::logger('warning', sprintf(__("Démon [%s] : Mauvais identifiant d'exécution", __FILE__), $ruid));
			return '';
		}
		// Searching a match for RemoteUID (PID and PORT) in listening ports
		$retval = 255;
		exec("ss -Htulpn 'sport = :" . $rport ."' 2> /dev/null | grep -E '[:]" . $rport . "[ \t]+.*[:][*][ \t]+.+pid=" . $rpid . "' 2> /dev/null", $output, $retval);
		if ($retval != 0) { // Execution issue with ss? Try netstat!
			unset($output); // Be sure to clear $output first
			exec("netstat -lntp 2> /dev/null | grep -E '[:]" . $rport . "[ \t]+.*[:][*][ \t]+.+[ \t]+" . $rpid . "/python3' 2> /dev/null", $output, $retval);
		}
		if ($retval != 0) { // Execution issue with netstat? Try lsof!
			unset($output); // Be sure to clear $output first
			exec("lsof -nP -iTCP -sTCP:LISTEN | grep -E 'python3[ \t]+" . $rpid . "[ \t]+.+[:]" . $rport ."[ \t]+' 2> /dev/null", $output, $retval);
		}
		if ($retval != 0 || count($output) == 0) { // Execution issue, could not get a match
			self::logger('warning', sprintf(__("Démon [%s] : N'a pas pu être authentifié", __FILE__), $ruid));
			return '';
		}
		// Verify if another daemon is not running
		list($cpid, $cport) = array_map('intval', explode(":", $cuid));
		if ($cpid != 0) { // Cached PID is available
			if (!@posix_getsid($cpid)) { // Cached PID is NOT running
				self::logger('warning', sprintf(__("Démon [%1\$s] va remplacer le Démon [%2\$s] !", __FILE__), $ruid, $cuid));
				self::deamon_stop(); // Must NOT `return ''` here, new daemon still needs to be accepted
			} else { // Cached PID IS running
				self::logger('warning', sprintf(__("Démon [%1\$s] essaye de remplacer le Démon [%2\$s] !", __FILE__), $ruid, $cuid));
				exec(system::getCmdSudo() . 'fuser ' . $cport . '/tcp 2> /dev/null', $output, $retval);
				if ($retval != 0 || count($output) == 0) { // Execution issue, could not get a match
					self::logger('warning', sprintf(__("Démon [%s] : N'a pas pu être identifié", __FILE__), $cuid));
					self::deamon_stop(); // Must NOT `return ''` here, new daemon still needs to be accepted
				} elseif (intval(trim($output[0])) != $cpid) { // No match for old daemon
					self::logger('warning', sprintf(__("Démon [%s] : Reprend la main", __FILE__), $ruid));
					self::deamon_stop(); // Must NOT `return ''` here, new daemon still needs to be accepted
				} else { // Old daemon is still alive. If Daemon is semi-dead, it may die by missing enough heartbeats
					self::logger('warning', sprintf(__("Démon [%1\$s] va survivre au Démon [%2\$s] !", __FILE__), $cuid, $ruid));
					posix_kill($rpid, 15);
					return '';
				}
			}
		}
		//self::logger('debug', sprintf(__("Démon [%s] est vivant", __FILE__), $ruid)); // VERY VERBOSE (1/5s to 1/m): Do not activate if not needed!
		// Save in cache the daemon RemoteUID (as it is connected)
		cache::set('jMQTT::'.self::CACHE_DAEMON_UID, $ruid);
		cache::set('jMQTT::'.self::CACHE_DAEMON_PORT, $rport);
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_SND, time());
		self::sendMqttDaemonStateEvent(true);
		// Launch MQTT Clients
		self::checkAllMqttClients();
		// Active listeners
		self::listenersAddAll();
		// Prepare and send initial data
		// TODO (high) Edit Daemon to be enable to receive this information
		// $returns = self::full_export(true); // TODO (low) FIXME: there is only a jMQTT->full_export() no static one !!!
		// TODO (code idea) use array_filter on each level of the array?
		// return json_encode($returns, JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Daemon callback to tell Jeedom it is OK
	 */
	public static function fromDaemon_hb($uid) {
		self::logger('debug', sprintf(__("Démon [%s] est en vie", __FILE__), $uid));
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
	}

	/**
	 * Daemon callback to tell Jeedom it is stopped
	 */
	public static function fromDaemon_daemonDown($uid) {
		//self::logger('debug', 'fromDaemon_daemonDown(uid='.$uid.')');
		// Remove PID file
		if (file_exists($pid_file = jeedom::getTmpFolder(__CLASS__) . '/jmqttd.py.pid'))
			shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
		// Delete in cache the daemon uid (as it is disconnected)
		@cache::delete('jMQTT::' . self::CACHE_DAEMON_UID);
		// Send state to WebUI
		self::sendMqttDaemonStateEvent(false);
		// Remove listeners
		self::listenersRemoveAll();
		// Get all brokers and set them as disconnected
		foreach(self::getBrokers() as $broker) {
			try {
				self::fromDaemon_brkDown($broker->getId());
			} catch (Throwable $e) {
				if (log::getLogLevel(__CLASS__) > 100)
					self::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __METHOD__, $e->getMessage()));
				else
					self::logger('error', str_replace("\n",' <br/> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
								"<br/>@Stack: %3\$s,<br/>@BrkId: %4\$s.",
								__METHOD__, $e->getMessage(), $e->getTraceAsString(), $broker->getId())));
			}
		}
	}

	public static function sendToDaemon($params, $except = true) {
		if (!self::daemon_state()) {
			if ($except)
				throw new Exception(__("Le démon n'est pas démarré", __FILE__));
			else
				return;
		}
		$params['apikey'] = jeedom::getApiKey(__CLASS__);
		$payload = json_encode($params);
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		$port = @cache::byKey('jMQTT::'.self::CACHE_DAEMON_PORT)->getValue(0);
		if (!socket_connect($socket, '127.0.0.1', $port)) {
			self::logger('debug', sprintf(__("Impossible de se connecter du Démon sur le port %1\$s, erreur %2\$s", __FILE__), $port, socket_strerror(socket_last_error($socket))));
			return;
		}
		if (socket_write($socket, $payload, strlen($payload)) === false) {
			self::logger('debug', sprintf(__("Impossible d'envoyer un message au Démon sur le port %1\$s, erreur %2\$s", __FILE__), $port, socket_strerror(socket_last_error($socket))));
			return;
		}
		socket_close($socket);
		// self::logger('debug', sprintf(__("sendToDaemon: port=%1\$s, payload=%2\$s", __FILE__), $port, $payload));
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_SND, time());
	}

	public static function toDaemon_hb() {
		$params['cmd']      = 'hb';
		self::sendToDaemon($params, false);
	}

	public static function toDaemon_setLogLevel($_level=null) {
		$params['cmd']      = 'loglevel';
		$params['id']       = 0;
		$params['level']    = is_null($_level) ? log::getLogLevel(__class__) : $_level;
		if ($params['level'] == 'default') // Replace 'default' log level
			$params['level'] = log::getConfig('log::level');
		if (is_numeric($params['level'])) // Replace numeric log level par text level
			$params['level'] = log::convertLogLevel($params['level']);
		self::sendToDaemon($params);
	}

	public static function toDaemon_changeApiKey($newApiKey) {
		$params['cmd']       = 'changeApiKey';
		$params['id']        = 0;
		$params['newApiKey'] = $newApiKey;
		// Inform Daemon without generating exception if not running
		self::sendToDaemon($params, false);
	}

/* TODO (medium) Implemented for later
	public static function toDaemon_brkRestart($brkId) {
		$params['cmd']      = 'brkRestart';
		$params['brkId']    = $brkId;
		self::sendToDaemon($params);
	}

	public static function toDaemon_incMode($brkId, $mode) {
		$params['cmd']      = 'incMode';
		$params['brkId']    = $brkId;
		$params['inc']      = $mode == 1;
		self::sendToDaemon($params);
	}

	public static function toDaemon_cfgChange($conf) {
		$params['cmd']      = 'cfgChange';
		$params['conf']     = $conf;
		self::sendToDaemon($params, false);
	}

	public static function toDaemon_delCmd($cmdId) {
		$params['cmd']      = 'delCmd';
		$params['cmdId']    = $cmdId;
		self::sendToDaemon($params, false);
	}

	public static function toDaemon_delEq($eqId) {
		$params['cmd']      = 'delEq';
		$params['eqId']     = $eqId;
		self::sendToDaemon($params, false);
	}

	public static function toDaemon_terminate() {
		$params['cmd']      = 'terminate';
		self::sendToDaemon($params, false);
	}
*/

	public static function toDaemon_newClient($id, $params = array()) {
		$params['cmd']      = 'newMqttClient';
		$params['id']       = $id;
		self::sendToDaemon($params);
	}

	public static function toDaemon_removeClient($id) {
		$params['cmd']      = 'removeMqttClient';
		$params['id']       = $id;
		self::sendToDaemon($params);
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
		$return['state'] = self::CLIENT_OK;

		if (file_exists($depProgressFile)) {
			self::logger('debug', sprintf(__("Dépendances en cours d'installation... (%s%%)", __FILE__), trim(file_get_contents($depProgressFile))));
			$return['state'] = self::CLIENT_NOK;
			return $return;
		}

		if (exec(system::getCmdSudo() . "cat " . __DIR__ . "/../../resources/JsonPath-PHP/vendor/composer/installed.json 2>/dev/null | grep galbar/jsonpath | wc -l") < 1) {
			self::logger('debug', __('Relancez les dépendances, le package PHP JsonPath est manquant', __FILE__));
			$return['state'] = self::CLIENT_NOK;
		}

		if (!file_exists(__DIR__ . '/../../resources/jmqttd/venv/bin/pip3') || !file_exists(__DIR__ . '/../../resources/jmqttd/venv/bin/python3')) {
			self::logger('debug', __("Relancez les dépendances, le venv Python n'a pas encore été créé", __FILE__));
			$return['state'] = self::CLIENT_NOK;
		} else {
			exec(__DIR__ . '/../../resources/jmqttd/venv/bin/pip3 freeze --no-cache-dir -r '.__DIR__ . '/../../resources/python-requirements/requirements.txt 2>&1 >/dev/null', $output);
			if (count($output) > 0) {
				self::logger('error', __('Relancez les dépendances, au moins une bibliothèque Python requise est manquante dans le venv :', __FILE__).' <br/>'.implode('<br/>', $output));
				$return['state'] = self::CLIENT_NOK;
			}
		}

		if ($return['state'] == self::CLIENT_OK)
			self::logger('debug', sprintf(__('Dépendances installées.', __FILE__)));
		return $return;
	}

	/**
	 * Provides dependancy installation script
	 */
	public static function dependancy_install() {
		$depLogFile = __CLASS__ . '_dep';
		$depProgressFile = jeedom::getTmpFolder(__CLASS__) . '/dependancy';

		self::logger('info', sprintf(__('Installation des dépendances, voir log dédié (%s)', __FILE__), $depLogFile));

		$update = update::byLogicalId(__CLASS__);
		shell_exec(
			'echo "\n\n================================================================================\n'.
			'== Jeedom '.jeedom::version().' '.jeedom::getHardwareName().
			' in $(lsb_release -d -s | xargs echo -n) on $(arch | xargs echo -n)/'.
			'$(dpkg --print-architecture | xargs echo -n)/$(getconf LONG_BIT | xargs echo -n)bits\n'.
			'== $(python3 -VV | xargs echo -n)\n'.
			'== '.__CLASS__.' v'.config::byKey('version', __CLASS__, 'unknown', true).
			' ('.$update->getLocalVersion().') branch:'.$update->getConfiguration()['version'].
			' previously:v'.config::byKey('previousVersion', __CLASS__, 'unknown', true).
			'" >> '.log::getPathToLog($depLogFile)
		);

		return array(
			'script' => __DIR__ . '/../../resources/install_#stype#.sh ' . $depProgressFile,
			'log' => log::getPathToLog($depLogFile)
		);
	}

// Check install status of Mosquitto service
	public static function mosquittoCheck() {
		$res = array('installed' => false, 'by' => '', 'message' => '', 'service' => '', 'config' => '');
		$retval = 255;
		$output = null;
		// Check if Mosquitto package is installed
		exec('dpkg -s mosquitto 2> /dev/null 1> /dev/null', $output, $retval); // retval = 1 not installed ; 0 installed

		// Not installed return default values
		if ($retval != 0) {
			$res['message'] = __("Mosquitto n'est pas installé en tant que service.", __FILE__);
			try {
				// Checking for Mosquitto installed in Docker by MQTT Manager
				if (is_object(update::byLogicalId('mqtt2')) && plugin::byId('mqtt2')->isActive() && config::byKey('mode', 'mqtt2', 'NotThere') == 'docker') {
					// Plugin Active and mqtt2 mode is docker
					$res['by'] = __('MQTT Manager (en docker)', __FILE__);
					$res['message'] = __('Mosquitto est installé <b>en docker</b> par', __FILE__);
					$res['message'] .= ' <a class="control-label danger" href="index.php?v=d&p=plugin&id=mqtt2">' . __('MQTT Manager', __FILE__) . '</a> (' . __('mqtt2', __FILE__) . ').';
				}
			} catch (Throwable $e) {}
			return $res;
		}

		// Otherwise, it is Installed
		$res['installed'] = true;
		// Get service active status
		$res['service'] = ucfirst(shell_exec('systemctl status mosquitto.service | grep "Active:" | sed -r "s/^[^:]*: (.*)$/\1/"'));
		// Get service config file (***unused for now***)
		// $res['config'] = shell_exec('systemctl status mosquitto.service | grep -- " -c " | sed -r "s/^.* -c (.*)$/\1/"');

		// Read in Core config who is supposed to have installed Mosquitto
		$res['core'] = config::byKey('mosquitto::installedBy', '', 'Unknown');
		// TODO (important) When config key will be widely used, resolve Mosquitto installer here

		// Check if mosquitto.service has been changed by mqtt2
		if (file_exists('/lib/systemd/system/mosquitto.service')
				&& strpos(file_get_contents('/lib/systemd/system/mosquitto.service'), 'mqtt2') !== false) {
			$res['by'] = __('MQTT Manager (en local)', __FILE__);
			$res['message'] = __('Mosquitto est installé par', __FILE__);
			$res['message'] .= ' <a class="control-label danger" target="_blank" href="index.php?v=d&p=plugin&id=mqtt2">';
			$res['message'] .= 'MQTT Manager</a> (mqtt2).';
		}
		// Check if ZigbeeLinker has modified Mosquitto config
		elseif (file_exists('/etc/mosquitto/mosquitto.conf')
				&& preg_match('#^include_dir.*zigbee2mqtt/data/mosquitto/include#m',
					file_get_contents('/etc/mosquitto/mosquitto.conf'))) {
			$res['by'] = __('ZigbeeLinker', __FILE__);
			$res['message'] = __('Mosquitto est installé par', __FILE__);
			$res['message'] .= ' <a class="control-label danger" target="_blank" href="index.php?v=d&p=plugin&id=zigbee2mqtt">';
			$res['message'] .= 'ZigbeeLinker</a> (zigbee2mqtt).';
		}
		// Check if jMQTT config file is in place
		elseif (file_exists('/etc/mosquitto/conf.d/jMQTT.conf')) {
			$res['by'] = 'jMQTT';
			$res['message'] = __('Mosquitto est installé par', __FILE__);
			$res['message'] .= ' <a class="control-label success disabled">jMQTT</a>.';
		}
		// Otherwise its considered to be a custom install
		else {
			$res['by'] = __("Inconnu", __FILE__);
			$res['message'] = __("Mosquitto n'a pas été installé par un plugin connu.", __FILE__);
		}
		return $res;
	}

// Install Mosquitto service and create first broker eqpt
	public static function mosquittoInstall() {
		$retval = 255;
		$output = null;
		// Check if Mosquitto package is installed
		exec('dpkg -s mosquitto 2> /dev/null 1> /dev/null', $output, $retval); // retval = 1 not installed ; 0 installed
		if ($retval == 0) {
			self::logger('warning', __("Mosquitto est déjà installé sur ce système !", __FILE__));
			return;
		}

		// Apt-get mosquitto
		self::logger('info', __("Mosquitto : Démarrage de l'installation, merci de patienter...", __FILE__));
		shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive apt-get install -y -o Dpkg::Options::="--force-confask,confnew,confmiss" mosquitto');

		$retval = 255;
		$output = null;
		// Check if mosquitto has already been configured (/etc/mosquitto/conf.d/jMQTT.conf is present)
		exec('ls /etc/mosquitto/conf.d/jMQTT.conf 2>/dev/null | wc -w', $output, $retval); // retval = 1 conf ok ; 0 no conf
		if ($retval == 0) {
			shell_exec(system::getCmdSudo() . ' cp ' . __DIR__ . '/../../resources/mosquitto_jMQTT.conf /etc/mosquitto/conf.d/jMQTT.conf');
		}

		// Cleanup, just in case
		shell_exec(system::getCmdSudo() . ' systemctl daemon-reload');
		shell_exec(system::getCmdSudo() . ' systemctl enable mosquitto');
		shell_exec(system::getCmdSudo() . ' systemctl stop mosquitto');
		shell_exec(system::getCmdSudo() . ' systemctl start mosquitto');

		// Write in Core config that jMQTT has installed Mosquitto
		config::save('mosquitto::installedBy', 'jMQTT');
		self::logger('info', __("Mosquitto : Fin de l'installation.", __FILE__));

		// Looking for eqBroker pointing to local mosquitto
		$brokerexists = false;
		foreach(self::getBrokers() as $broker) {
			$hn = $broker->getConf(self::CONF_KEY_MQTT_ADDRESS);
			$ip = gethostbyname($hn);
			$localips = explode(' ', exec(system::getCmdSudo() . 'hostname -I'));
			if ($hn == '' || substr($ip, 0, 4) == '127.' || in_array($ip, $localips)) {
				$brokerexists = true;
				self::logger('info', sprintf(__("L'équipement Broker local #%s# existe déjà, pas besoin d'en créer un.", __FILE__), $broker->getHumanName()));
				break;
			}
		}

		// Could not find a local eqBroker
		if (!$brokerexists) {
			self::logger('info', __("Aucun équipement Broker local n'a été trouvé, création en cours...", __FILE__));
			$brokername = 'local';

			// Looking for a conflict with eqBroker name
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

			// Creating a new eqBroker to communicate with local Mosquitto
			$broker = new jMQTT();
			$broker->setType(self::TYP_BRK);
			$broker->setName($brokername);
			$broker->setIsEnable(1);
			$broker->save();
			self::logger('info', sprintf(__("L'équipement Broker #%s# a été créé.", __FILE__), $broker->getHumanName()));
		}
	}

// Reinstall Mosquitto service over previous install
	public static function mosquittoRepare() {
		// Stop service
		shell_exec(system::getCmdSudo() . ' systemctl stop mosquitto');
		// Ensure no config is remaining
		shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive rm -rf /etc/mosquitto');
		// Reinstall and force reapply service default config
		shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive apt-get install --reinstall -y -o Dpkg::Options::="--force-confask,confnew,confmiss" mosquitto');
		// Apply jMQTT config
		shell_exec(system::getCmdSudo() . ' cp ' . __DIR__ . '/../../resources/mosquitto_jMQTT.conf /etc/mosquitto/conf.d/jMQTT.conf');
		// Cleanup, just in case
		shell_exec(system::getCmdSudo() . ' systemctl daemon-reload');
		shell_exec(system::getCmdSudo() . ' systemctl stop mosquitto');
		shell_exec(system::getCmdSudo() . ' systemctl enable mosquitto');
		shell_exec(system::getCmdSudo() . ' systemctl start mosquitto');
		// Write in Core config that jMQTT has installed Mosquitto
		config::save('mosquitto::installedBy', 'jMQTT');
	}

// Purge Mosquitto service and all related config files
	public static function mosquittoRemove() {
		$retval = 255;
		$output = null;
		// Check if Mosquitto package is installed
		exec('dpkg -s mosquitto 2> /dev/null 1> /dev/null', $output, $retval); // retval = 1 not installed ; 0 installed
		if ($retval == 1) {
			event::add('jeedom::alert', array('level' => 'danger', 'page' => 'plugin', 'message' => __("Mosquitto n'est pas installé sur ce système !", __FILE__),));
			return;
		}
		// Remove package and /etc folder
		shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive apt-get purge -y mosquitto');
		shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive rm -rf /etc/mosquitto');
		// Remove from Core config that Mosquitto is installed
		config::remove('mosquitto::installedBy');
	}

// Create or update all autoPub listeners
	public static function listenersAddAll() {
		foreach (cmd::searchConfiguration('"'.jMQTTCmd::CONF_KEY_AUTOPUB.'":"1"', __CLASS__) as $cmd)
			$cmd->listenerUpdate();
	}

// Remove all autoPub listeners
	public static function listenersRemoveAll() {
		foreach (listener::byClass('jMQTTCmd') as $l)
			$l->remove();
	}

// Create or update all autoPub listeners from this eqLogic
	public function listenersAdd() {
		foreach (jMQTTCmd::searchConfigurationEqLogic($this->getId(), '"'.jMQTTCmd::CONF_KEY_AUTOPUB.'":"1"') as $cmd)
			$cmd->listenerUpdate();
	}

// Remove all autoPub listeners from this eqLogic
	public function listenersRemove() {
		$listener = listener::searchClassFunctionOption('jMQTTCmd', 'listenerAction', '"eqLogic":"'.$this->getId().'"');
		foreach ($listener as $l)
			$l->remove();
	}

	/**
	 * Callback on daemon auto mode change
	 */
	public static function deamon_changeAutoMode($_mode) {
		if ($_mode)
			self::logger('info', __("Le démarrage automatique du Démon est maintenant Activé", __FILE__));
		else
			self::logger('warning', __("Le démarrage automatique du Démon est maintenant Désactivé", __FILE__));
	}

	/**
	 * Callback to check daemon auto mode status
	 */
	public static function getDaemonAutoMode() {
		return (config::byKey('deamonAutoMode', __CLASS__, 1) == 1);
	}

	/**
	 * Send a jMQTT::EventDaemonState event to the UI containing current daemon state
	 * @param $_state bool true if Daemon is running and connected
	 */
	private static function sendMqttDaemonStateEvent($_state) {
		event::add('jMQTT::EventDaemonState', $_state);
	}

	/**
	 * Core callback on API key change
	 * @param $_value string New API key
	 */
	public static function preConfig_api($_apikey) {
		// Different message when API key is not set
		$oldApiKey = config::byKey('api', __CLASS__);
		if ($oldApiKey == '') {
			if (log::getLogLevel(__CLASS__) > 100)
				self::logger('info', __('Définition de la clé API de jMQTT', __FILE__));
			else // Append more info in debug
				self::logger('info', sprintf(__('Définition de la clé API de jMQTT : %1$.8s...', __FILE__), $_apikey));
		} else {
			if (log::getLogLevel(__CLASS__) > 100)
				self::logger('info', __('Changement de la clé API de jMQTT', __FILE__));
			else // Append more info in debug
				self::logger('info', sprintf(__('Changement de la clé API de jMQTT : %1$.8s... est remplacé par %2$.8s...', __FILE__), $oldApiKey, $_apikey));
		}
		// Inform Daemon only if API key changed (to prevent a recursion loop)
		if ($oldApiKey != '')
			self::toDaemon_changeApiKey($_apikey);
		// Always return new API key
		return $_apikey;
	}


	###################################################################################################################
	##
	##                   MQTT CLIENT RELATED METHODS
	##
	###################################################################################################################

	/**
	 * Check all MQTT Clients (start them if needed)
	 */
	public static function checkAllMqttClients() {
		if (self::daemon_check() != self::CLIENT_OK)
			return;
		foreach(self::getBrokers() as $broker) {
			if (!$broker->getIsEnable() || $broker->getMqttClientState() == self::CLIENT_OK)
				continue;
			try {
				$broker->startMqttClient();
			} catch (Throwable $e) {
				if (log::getLogLevel(__CLASS__) > 100)
					self::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __METHOD__, $e->getMessage()));
				else
					self::logger('error', str_replace("\n",' <br/> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
								"@Stack: %3\$s,<br/>@BrkId: %4\$s.",
								__METHOD__, $e->getMessage(), $e->getTraceAsString(), $broker->getId())));
			}
		}
	}


	/**
	 * Return MQTT Client information
	 * @return string[] MQTT Client information array
	 */
	public function getMqttClientInfo() {
		// Not a Broker
		if ($this->getType() != self::TYP_BRK)
			return array('message' => '', 'launchable' => self::CLIENT_NOK, 'state' => self::CLIENT_NOK);

		// Daemon is down
		if (!self::daemon_state())
			return array('launchable' => self::CLIENT_NOK, 'state' => self::CLIENT_NOK, 'message' => __("Démon non démarré", __FILE__));

		// Client is connected to the Broker
		if ($this->getCache(self::CACHE_MQTTCLIENT_CONNECTED, false))
			return array('launchable' => self::CLIENT_OK, 'state' => self::CLIENT_OK, 'message' => __("Le Démon jMQTT est correctement connecté à ce Broker", __FILE__));

		// Client is disconnected from the Broker
		if ($this->getIsEnable())
			return array('launchable' => self::CLIENT_OK, 'state' => self::CLIENT_POK, 'message' => __("Le Démon jMQTT n'arrive pas à se connecter à ce Broker", __FILE__));

		// Client is disabled
		return array('launchable' => self::CLIENT_NOK, 'state' => self::CLIENT_NOK, 'message' => __("La connexion à ce Broker est désactivée", __FILE__));
	}

	/**
	 * Return MQTT Client state
	 *   - self::CLIENT_OK: MQTT Client is running and mqtt broker is online
	 *   - self::CLIENT_POK: MQTT Client is running but mqtt broker is offline
	 *   - self::CLIENT_NOK: daemon is not running or Eq is disabled
	 * @return string ok or nok
	 */
	public function getMqttClientState() {
		if (!self::daemon_state() || $this->getType() != self::TYP_BRK)
			return self::CLIENT_NOK;
		if ($this->getCache(self::CACHE_MQTTCLIENT_CONNECTED, false))
			return self::CLIENT_OK;
		if ($this->getIsEnable())
			return self::CLIENT_POK;
		return self::CLIENT_NOK;
	}

	/**
	 * Start the MQTT Client of this broker if it is launchable
	 * @throws Exception if the MQTT Client is not launchable
	 */
	public function startMqttClient() {
		// if daemon is not ok, do Nothing
		$daemon_info = self::deamon_info();
		if ($daemon_info['state'] != self::CLIENT_OK) return;
		//If MqttClient is not launchable (daemon is running), throw exception to get message
		$mqttclient_info = $this->getMqttClientInfo();
		if ($mqttclient_info['launchable'] != self::CLIENT_OK)
			throw new Exception(__("Le client MQTT n'est pas démarrable :", __FILE__) . ' ' . $mqttclient_info['message']);
		$this->log('info', __('Démarrage du Client MQTT', __FILE__));
		$this->setCache(self::CACHE_LAST_LAUNCH_TIME, date('Y-m-d H:i:s'));
		$this->sendMqttClientStateEvent(); // Need to send current state before brkUp give OK
		// Preparing some additional data for the broker
		$params = array();
		$params['hostname']          = $this->getConf(self::CONF_KEY_MQTT_ADDRESS);
		$params['proto']             = $this->getConf(self::CONF_KEY_MQTT_PROTO);
		$params['port']              = intval($this->getConf(self::CONF_KEY_MQTT_PORT));
		$params['wsUrl']             = $this->getConf(self::CONF_KEY_MQTT_WS_URL);
		$params['mqttId']            = $this->getConf(self::CONF_KEY_MQTT_ID) == "1";
		$params['mqttIdValue']       = $this->getConf(self::CONF_KEY_MQTT_ID_VALUE);
		$params['lwt']               = ($this->getConf(self::CONF_KEY_MQTT_LWT) == '1');
		$params['lwtTopic']          = $this->getConf(self::CONF_KEY_MQTT_LWT_TOPIC);
		$params['lwtOnline']         = $this->getConf(self::CONF_KEY_MQTT_LWT_ONLINE);
		$params['lwtOffline']        = $this->getConf(self::CONF_KEY_MQTT_LWT_OFFLINE);
		$params['username']          = $this->getConf(self::CONF_KEY_MQTT_USER);
		$params['password']          = $this->getConf(self::CONF_KEY_MQTT_PASS);
		$params['tlscheck']          = $this->getConf(self::CONF_KEY_MQTT_TLS_CHECK);
		switch ($this->getConf(self::CONF_KEY_MQTT_TLS_CHECK)) {
			case 'disabled':
				$params['tlsinsecure'] = true;
				break;
			case 'public':
				$params['tlsinsecure'] = false;
				break;
			case 'private':
				$params['tlsinsecure'] = false;
				$params['tlsca']       = $this->getConf(self::CONF_KEY_MQTT_TLS_CA);
				break;
		}
		$params['tlscli']            = ($this->getConf(self::CONF_KEY_MQTT_TLS_CLI) == '1');
		if ($params['tlscli']) {
			$params['tlsclicert']    = $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_CERT);
			$params['tlsclikey']     = $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_KEY);
			if ($params['tlsclicert'] == '' || $params['tlsclikey'] == '') {
				$params['tlscli']    = false;
				unset($params['tlsclicert']);
				unset($params['tlsclikey']);
			}
		}
		self::toDaemon_newClient($this->getId(), $params);
	}

	/**
	 * Stop the MQTT Client of this broker type object
	 */
	public function stopMqttClient() {
		$daemon_info = self::deamon_info();
		if ($daemon_info['state'] == self::CLIENT_NOK)
			return; // Return if client is not running
		$this->log('info', __('Arrêt du Client MQTT', __FILE__));
		self::toDaemon_removeClient($this->getId());
		$this->sendMqttClientStateEvent();  // Need to send current state before brkDown give NOK
	}


	/**
	 * Manage the Real Time mode of this broker object
	 * Called by ajax when the button is pressed by the user
	 * @param int $mode 0 or 1
	 */
	public static function changeRealTimeMode($id, $mode, $subscribe='#', $exclude='homeassistant/#', $retained=false, $duration=180) {
		$broker = self::getBrokerFromId($id);
		$broker->log('info', $mode ? __("Lancement du Mode Temps Réel...", __FILE__) : __("Arrêt du Mode Temps Réel...", __FILE__));
		// $broker->log('debug', sprintf(__("changeRealTimeMode(id=%1\$s, mode=%2\$s, subscribe=%3\$s, exclude=%4\$s, retained=%5\$s, duration=%6)", __FILE__), $id, $mode, $subscribe, $exclude, $retained, $duration));
		if($mode) { // If Real Time mode needs to be enabled
			// Check if a subscription topic is provided
			if (trim($subscribe) == '')
				throw new Exception(__("Impossible d'activer le mode Temps Réel avec un topic de souscription vide", __FILE__));
			$subscribe = (trim($subscribe) == '') ? [] : explode('|', $subscribe);
			// Cleanup subscriptions
			$subscriptions = [];
			foreach ($subscribe as $t) {
				$t = trim($t);
				if ($t == '')
					continue;
				$subscriptions[] = $t;
			}
			if (count($subscriptions) == 0)
				throw new Exception(__("Impossible d'activer le mode Temps Réel sans topic de souscription", __FILE__));
			// Cleanup exclusions
			$exclude = (trim($exclude) == '') ? [] : explode('|', $exclude);
			$exclusions = [];
			foreach ($exclude as $t) {
				$t = trim($t);
				if ($t == '')
					continue;
				$exclusions[] = $t;
			}
			// Cleanup retained
			$retained = is_bool($retained) ? $retained : ($retained == '1' || $retained == 'true');
			// Cleanup duration
			$duration = min(max(1, intval($duration)), 3600);
			// Start Real Time Mode (must be started before subscribe)
			self::toDaemon_realTimeStart($id, $subscriptions, $exclusions, $retained, $duration);
			// Update cache
			$broker->setCache(self::CACHE_REALTIME_INC_TOPICS, implode($subscriptions, '|'));
			$broker->setCache(self::CACHE_REALTIME_EXC_TOPICS, implode($exclusions, '|'));
			$broker->setCache(self::CACHE_REALTIME_RET_TOPICS, $retained);
			$broker->setCache(self::CACHE_REALTIME_DURATION, $duration);
		} else { // Real Time mode needs to be disabled
			// Stop Real Time mode
			self::toDaemon_realTimeStop($id);
		}
	}

	/**
	 * Returns this broker object Real Time mode status
	 * @return int 0 or 1
	 */
	public function getRealTimeMode() {
		return $this->getCache(self::CACHE_REALTIME_MODE, 0);
	}


	public static function fromDaemon_brkUp($id) {
		try { // Catch if broker is unknown / deleted
			$broker = self::getBrokerFromId(intval($id));
			$broker->setCache(self::CACHE_MQTTCLIENT_CONNECTED, true); // Save in cache that Mqtt Client is connected
			$broker->checkAndUpdateCmd($broker->getMqttClientStatusCmd(true), self::CLIENT_STATUS_ONLINE); // If not existing at brkUp, create it
			$broker->checkAndUpdateCmd($broker->getMqttClientConnectedCmd(true), 1); // If not existing at brkUp, create it
			$broker->setStatus('warning', null);
			cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
			$broker->log('info', __('Client MQTT connecté au Broker', __FILE__));
			$broker->sendMqttClientStateEvent();
			// Subscribe to topics
			foreach (self::byBrkId($id) as $eq) {
				if ($eq->getIsEnable() && $eq->getId() != $broker->getId()) {
					$eq->subscribeTopic($eq->getTopic(), $eq->getQos());
				}
			}

			// Enable Interactions
			if ($broker->getConf(self::CONF_KEY_MQTT_INT)) {
				$broker->log('info', sprintf(__("Souscription au topic d'Interaction '%s'", __FILE__), $broker->getConf(self::CONF_KEY_MQTT_INT_TOPIC)));
				$broker->subscribeTopic($broker->getConf(self::CONF_KEY_MQTT_INT_TOPIC), '1');
				$broker->subscribeTopic($broker->getConf(self::CONF_KEY_MQTT_INT_TOPIC) . '/advanced', '1');
			} else
				$broker->log('debug', __("L'accès aux Interactions est désactivé", __FILE__));

			// Enable API
			if ($broker->getConf(self::CONF_KEY_MQTT_API)) {
				$broker->log('info', sprintf(__("Souscription au topic API '%s'", __FILE__), $broker->getConf(self::CONF_KEY_MQTT_API_TOPIC)));
				$broker->subscribeTopic($broker->getConf(self::CONF_KEY_MQTT_API_TOPIC), '1');
			} else
				$broker->log('debug', __("L'accès à l'API est désactivé", __FILE__));

			// Active listeners
			self::listenersAddAll();
		} catch (Throwable $e) {
			if (log::getLogLevel(__CLASS__) > 100)
				self::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __METHOD__, $e->getMessage()));
			else
				self::logger('error', str_replace("\n",' <br/> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
							"@Stack: %3\$s,<br/>@BrkId: %4\$s.",
							__METHOD__, $e->getMessage(), $e->getTraceAsString(), $id)));
		}
	}

	public static function fromDaemon_brkDown($id) {
		try { // Catch if broker is unknown / deleted
			$broker = self::byId($id); // Don't use getBrokerFromId here!
			if (!is_object($broker)) {
				self::logger('debug', sprintf(__("Pas d'équipement avec l'id %s (il vient probablement d'être supprimé)", __FILE__), $id));
				return;
			}
			if ($broker->getType() != self::TYP_BRK) {
				self::logger('error', sprintf(__("L'équipement %s n'est pas de type Broker", __FILE__), $id));
				return;
			}
			// Save in cache that Mqtt Client is disconnected
			$broker->setCache(self::CACHE_MQTTCLIENT_CONNECTED, false);

			// If command exists update the status (used to get broker connection status inside Jeedom)
			$broker->checkAndUpdateCmd($broker->getMqttClientStatusCmd(), self::CLIENT_STATUS_OFFLINE); // Need to check if statusCmd exists, because during Remove cmd are destroyed first by eqLogic::remove()
			$broker->checkAndUpdateCmd($broker->getMqttClientConnectedCmd(), 0); // Need to check if connectedCmd exists, because during Remove cmd are destroyed first by eqLogic::remove()
			$broker->setStatus('warning', $broker->getIsEnable() ? 1 : null); // Also set a warning if eq is enabled (should be always true)

			// Clear Real Time mode
			$broker->setCache(self::CACHE_REALTIME_MODE, 0);

			cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
			$broker->log('info', __('Client MQTT déconnecté du Broker', __FILE__));
			$broker->sendMqttClientStateEvent();
		} catch (Throwable $e) {
			if (log::getLogLevel(__CLASS__) > 100)
				self::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __METHOD__, $e->getMessage()));
			else
				self::logger('error', str_replace("\n",' <br/> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
							"@Stack: %3\$s,<br/>@BrkId: %4\$s.",
							__METHOD__, $e->getMessage(), $e->getTraceAsString(), $id)));
		}
	}

	public static function fromDaemon_msgIn($id, $topic, $payload, $qos, $retain) {
		try {
			$broker = self::getBrokerFromId(intval($id));
			$broker->brokerMessageCallback($topic, $payload, $qos, $retain);
			cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
		} catch (Throwable $e) {
			if (log::getLogLevel(__CLASS__) > 100)
				self::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __METHOD__, $e->getMessage()));
			else
				self::logger('error', str_replace("\n",' <br/> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
							"@Stack: %3\$s,<br/>@BrkId: %4\$s,<br/>@Topic: %5\$s,<br/>@Payload: %6\$s,<br/>@Qos: %7\$s,<br/>@Retain: %8\$s.",
							__METHOD__, $e->getMessage(), $e->getTraceAsString(), $id, $topic, $payload, $qos, $retain)));
		}
	}

	public static function fromDaemon_value($cmdId, $value) {
		$cmd = jMQTTCmd::byId(intval($cmdId));
		$cmd->getEqLogic()->getBroker()->setStatus(array('lastCommunication' => date('Y-m-d H:i:s'), 'timeout' => 0));
		$cmd->updateCmdValue($value);
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
	}

	public static function fromDaemon_realTimeStarted($id) {
		$brk = self::getBrokerFromId(intval($id));
		// Update cache
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
		$brk->setCache(self::CACHE_REALTIME_MODE, 1);
		// Send event to WebUI
		$brk->log('info', __("Mode Temps Réel activé", __FILE__));
		$brk->sendMqttClientStateEvent();
	}

	public static function fromDaemon_realTimeStopped($id, $nbMsgs) {
		$brk = self::getBrokerFromId(intval($id));
		// Update cache
		cache::set('jMQTT::'.self::CACHE_DAEMON_LAST_RCV, time());
		$brk->setCache(self::CACHE_REALTIME_MODE, 0);
		// Send event to WebUI
		$brk->log('info', sprintf(__("Mode Temps Réel désactivé, %s messages disponibles", __FILE__), $nbMsgs));
		$brk->sendMqttClientStateEvent();
	}

	public static function toDaemon_realTimeStart($id, $subscribe, $exclude, $retained, $duration = 180) {
		$params['cmd']       = 'realTimeStart';
		$params['id']        = $id;
		$params['file']      = jeedom::getTmpFolder(__CLASS__).'/rt' . $id . '.json';
		$params['subscribe'] = $subscribe;
		$params['exclude']   = $exclude;
		$params['retained']  = $retained;
		$params['duration']  = $duration;
		self::sendToDaemon($params);
	}

	public static function toDaemon_realTimeStop($id) {
		$params['cmd'] = 'realTimeStop';
		$params['id']  = $id;
		self::sendToDaemon($params);
	}

	public static function toDaemon_realTimeClear($id) {
		$params['cmd']  = 'realTimeClear';
		$params['id']   = $id;
		$params['file'] = jeedom::getTmpFolder(__CLASS__).'/rt' . $id . '.json';
		self::sendToDaemon($params);
	}

	public static function toDaemon_subscribe($id, $topic, $qos = 1) {
		if (empty($topic)) return;
		$params['cmd']   = 'subscribeTopic';
		$params['id']    = $id;
		$params['topic'] = $topic;
		$params['qos']   = $qos;
		self::sendToDaemon($params);
	}

	public static function toDaemon_unsubscribe($id, $topic) {
		if (empty($topic)) return;
		$params['cmd']   = 'unsubscribeTopic';
		$params['id']    = $id;
		$params['topic'] = $topic;
		self::sendToDaemon($params);
	}

	public static function toDaemon_publish($id, $topic, $payload, $qos = 1, $retain = false) {
		if (empty($topic)) return;
		$params['cmd']     = 'messageOut';
		$params['id']      = $id;
		$params['topic']   = $topic;
		$params['payload'] = $payload;
		$params['qos']     = $qos;
		$params['retain']  = $retain;
		self::sendToDaemon($params);
	}

	/**
	 * Send a jMQTT::EventState event to the UI containing eqLogic
	 */
	private function sendMqttClientStateEvent() {
		event::add('jMQTT::EventState', $this->toArray());
	}


	###################################################################################################################
	##
	##                   MQTT BROKER METHODS
	##
	###################################################################################################################

	/**
	 * Function to handle message matching Interaction subscribed topic.
	 * Reply Payload is sent on self::CONF_KEY_MQTT_INT_TOPIC/reply
	 *       with value in json like: $param + {"query": string, "reply": string}
	 *
	 * @param $query string Interaction Query message
	 * @param $param array Interaction Query advanced options
	 */
	private function interactMessage($query, $param=array()) {
		try {
			// Validate query
			if (is_null($query) || is_string($query) || $query == '')
				$param['query'] = '';
			else
				$param['query'] = $query;
			// Process parameters
			if (isset($param['utf8']) && $param['utf8'])
				$query = utf8_encode($query);
			if (isset($param['reply_cmd'])) {
				$reply_cmd = cmd::byId($param['reply_cmd']);
				if (is_object($reply_cmd)) {
					$param['reply_cmd'] = $reply_cmd;
					$param['force_reply_cmd'] = 1;
				}
			}

			// Process Interactions
			$reply = interactQuery::tryToReply($query, $param);

			// Put some logs on the Broker
			$this->log('info', sprintf(__("Interaction demandée '%1\$s', réponse '%2\$s'", __FILE__), $query, $reply['reply']));

			// Send reply on a /reply subtopic
			if (!is_array($reply))
				$reply = array('reply' => $reply);
			$reply = array_merge(array('status' => ''), $param, $reply, array('status' => 'ok'));
			$this->publish($this->getName(), $this->getConf(self::CONF_KEY_MQTT_INT_TOPIC) . '/reply', json_encode($reply, true), 1, 0);
		} catch (Throwable $e) {
			if (log::getLogLevel(__CLASS__) > 100)
				self::logger('warning', sprintf(__("L'Interaction '%1\$s' a levé l'Exception: %2\$s", __FILE__), $query, $e->getMessage()));
			else // More info in debug mode, no big log otherwise
				self::logger('warning', str_replace("\n",' <br/> ', sprintf(__("L'Interaction '%1\$s' a levé l'Exception: %2\$s", __FILE__).
							",<br/>@Stack: %3\$s.", $query, $e->getMessage(), $e->getTraceAsString())));
			// Send reply on a /reply subtopic
			$reply = array_merge(array('status' => ''), $param, array('reply' => '', 'status' => 'nok', 'error' => $e->getMessage()));
			$this->publish($this->getName(), $this->getConf(self::CONF_KEY_MQTT_INT_TOPIC) . '/reply', json_encode($reply, true), 1, 0);
		}
	}

	/**
	 * Callback called each time a message matching subscribed topic is received from the broker.
	 *
	 * @param $msgTopic string
	 *            topic of the message
	 * @param $msgValue string
	 *            payload of the message
	 */
	public function brokerMessageCallback($msgTopic, $msgValue, $msgQos, $msgRetain) {

		$start_t = microtime(true);
		$this->setStatus(array('lastCommunication' => date('Y-m-d H:i:s'), 'timeout' => 0));

		// Is Interact topic enabled ?
		if ($this->getConf(self::CONF_KEY_MQTT_INT)) {
			// If "simple" Interact topic, process the request
			if ($msgTopic == $this->getConf(self::CONF_KEY_MQTT_INT_TOPIC)) {
				// Request Payload: string
				$this->interactMessage($msgValue);
				// Reply Payload on /reply: {"query": string, "reply": string}
			}
			// If "advanced" Interact topic, process the request
			if ($msgTopic == $this->getConf(self::CONF_KEY_MQTT_INT_TOPIC) . '/advanced') {
				// Request Payload on /advanced: {"query": string, "utf8": bool, "emptyReply": ???, profile": ???, "reply_cmd": <cmdId>, "force_reply_cmd": bool}
				$param = json_decode($msgValue, true);
				$this->interactMessage($param['query'], $param);
				// Reply Payload on /reply: $param + {"reply": string}
			}
		}

		// If this is the API topic, process the request
		if ($this->getConf(self::CONF_KEY_MQTT_API) && $msgTopic == $this->getConf(self::CONF_KEY_MQTT_API_TOPIC)) {
			$this->processApiRequest($msgValue);
		}

		// Loop on jMQTT equipments and get ones that subscribed to the current message
		$elogics = array();
		foreach (self::byBrkId($this->getId()) as $eqpt) {
			if (mosquitto_topic_matches_sub($eqpt->getTopic(), $msgTopic)) $elogics[] = $eqpt;
		}

		//
		// Loop on enabled equipments listening to the current message
		//
		$related_cmd = '';
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
						$this->log('debug', sprintf(__('Cmd #%s# est de type action : ignorée', __FILE__), $cmd->getHumanName()));
						unset($cmds[$k]);
					} elseif ($cmd->isJson()) {
						$this->log('debug', sprintf(__('Cmd #%s# est de type info JSON : ignorée', __FILE__), $cmd->getHumanName()));
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
						try {
							$newCmd->save();
							$cmds[] = $newCmd;
							$this->log('debug', sprintf(__("Cmd #%1\$s# créée automatiquement pour le topic '%2\$s'", __FILE__), $newCmd->getHumanName(), $msgTopic));
						} catch (Throwable $e) {
							if (log::getLogLevel(__CLASS__) > 100)
								$this->log('error', sprintf(__("L'enregistrement de la nouvelle commande #%1\$s# a levé l'Exception: %2\$s", __FILE__), $newCmd->getHumanName(), $e->getMessage()));
							else // More info in debug mode, no big log otherwise
								$this->log('error', str_replace("\n",' <br/> ', sprintf(__("L'enregistrement de la nouvelle commande #%1\$s# a levé l'Exception: %2\$s", __FILE__).
											",<br/>@Stack: %3\$s,<br/>@Dump: %4\$s.", $newCmd->getHumanName(), $e->getMessage(), $e->getTraceAsString(), json_encode($newCmd))));
						}
					} else
						$this->log('debug', sprintf(__("Aucune commande n'a été créée pour le topic %1\$s dans l'équipement #%2\$s#, car la création automatique de commande est désactivée sur cet équipement", __FILE__), $msgTopic, $eqpt->getHumanName()));
				}

				// If there is some cmd matching exactly with the topic
				if (is_array($cmds) && count($cmds)) {
					foreach ($cmds as $cmd) {
						// Update the command value
						$cmd->updateCmdValue($msgValue);
						$related_cmd .= ', #' . $cmd->getHumanName() . '#';
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
							$related_cmd .= ', #' . $cmd->getHumanName() . '#';
						}
					}
				}
			}
		}

		if (strlen($related_cmd) == 0) {
			$related_cmd = __(": Aucune", __FILE__);
		} else {
			$related_cmd[0] = ':';
		}
		$duration_ms = round((microtime(true) - $start_t)*1000);
		if ($duration_ms > 300) {
			$this->log('warning', sprintf(__("Attention, ", __FILE__) .
										__("Payload '%1\$s' reçu sur le Topic '%2\$s' traité en %3\$dms", __FILE__) .
										__(" (très long), vérifiez les commandes affiliées %4\$s", __FILE__), $msgValue, $msgTopic, $duration_ms, $related_cmd));
		} elseif (log::getLogLevel(__CLASS__) <= 100) {
			$this->log('debug', sprintf(__("Payload '%1\$s' reçu sur le Topic '%2\$s' traité en %3\$dms, commandes affiliées %4\$s", __FILE__), $msgValue, $msgTopic, $duration_ms, $related_cmd));
		}
	}

	/**
	 * Publish a given message to the MQTT broker attached to this object
	 *
	 * @param string $cmdName
	 *            command name (for log purpose only)
	 * @param string $topic
	 *            topic
	 * @param string $message
	 *            payload
	 * @param string $qos
	 *            quality of service used to send the message ('0', '1' or '2')
	 * @param string $retain
	 *            whether or not the message should be retained ('0' or '1')
	 */
	public function publish($cmdName, $topic, $payload, $qos, $retain) {
		if (is_bool($payload) || is_array($payload)) {
			// Fix #80
			// One can wonder why not encoding systematically the message?
			// Answer is that it does not work in some cases:
			//   * If payload is empty => "(null)" is sent instead of (null)
			//   * If payload contains ", they are backslashed \"
			// Fix #110
			// Since Core commit https://github.com/jeedom/core/commit/430f0049dc74e914c4166b109fb48b4375f11ead
			// payload can become more than int/bool/string
			$payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		$payloadLogMsg = ($payload === '') ? '\'\' (null)' : "'".$payload."'";
		if (!self::daemon_state()) {
			if (!self::getDaemonAutoMode()) {
				$this->log('info', sprintf(__("Cmd #%1\$s# -> %2\$s Message non publié, car le démon jMQTT est désactivé", __FILE__), $cmdName, $payloadLogMsg));
				return;
			}
			$this->log('info', sprintf(__("Cmd #%1\$s# -> %2\$s Message non publié, car le démon jMQTT n'est pas démarré/connecté", __FILE__), $cmdName, $payloadLogMsg));
			return;
		}
		$broker = $this->getBroker();
		if (!$broker->getIsEnable()) {
			$this->log('info', sprintf(__("Cmd #%1\$s# -> %2\$s Message non publié, car le Broker jMQTT %3\$s n'est pas activé", __FILE__), $cmdName, $payloadLogMsg, $broker->getName()));
			return;
		}
		if ($broker->getMqttClientState() != self::CLIENT_OK) {
			$this->log('warning', sprintf(__("Cmd #%1\$s# -> %2\$s Message non publié, car le Broker jMQTT %3\$s n'est pas connecté au Broker MQTT", __FILE__), $cmdName, $payloadLogMsg, $broker->getName()));
			return;
		}
		if (log::getLogLevel(__CLASS__) > 100)
			$this->log('info', sprintf(__("Cmd #%1\$s# -> %2\$s sur le topic '%3\$s'", __FILE__), $cmdName, $payloadLogMsg, $topic));
		else
			$this->log('info', sprintf(__("Cmd #%1\$s# -> %2\$s sur le topic '%3\$s' (qos=%4\$s, retain=%5\$s)", __FILE__), $cmdName, $payloadLogMsg, $topic, $qos, $retain));
		self::toDaemon_publish($this->getBrkId(), $topic, $payload, $qos, $retain);
		$d = date('Y-m-d H:i:s');
		$this->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
		if ($this->getType() == self::TYP_EQPT)
			$broker->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
		// $this->log('debug', __('Message publié', __FILE__));
	}

	/**
	 * Return the MQTT status information command of this broker
	 * It is the responsability of the caller to check that this object is a broker before calling the method.
	 * If $create, then create and save the MQTT status information command of this broker if not already existing
	 * @param $create bool create the command if it does not exist
	 * @return cmd status information command.
	 */
	public function getMqttClientStatusCmd($create = false) {
		if (!is_object($this->_statusCmd)) // Get cmd if it exists
			$this->_statusCmd = cmd::byEqLogicIdAndLogicalId($this->getId(), self::CLIENT_STATUS);
		if ($create && !is_object($this->_statusCmd)) { // cmd does not exist
			$cmd = jMQTTCmd::newCmd($this, self::CLIENT_STATUS, '', ''); // Topic and jsonPath are irrelevant here
			$cmd->setLogicalId(self::CLIENT_STATUS);
			$cmd->setConfiguration('irremovable', 1);
			$cmd->save();
			$this->_statusCmd = $cmd;
		}
		return $this->_statusCmd;
	}

	/**
	 * Return the MQTT connected binary information command of this broker
	 * It is the responsability of the caller to check that this object is a broker before calling the method.
	 * If $create, then create and save the MQTT connected information command of this broker if not already existing
	 * @param $create bool create the command if it does not exist
	 * @return cmd connected information command.
	 */
	public function getMqttClientConnectedCmd($create = false) {
		if (!is_object($this->_connectedCmd)) // Get cmd if it exists
			$this->_connectedCmd = cmd::byEqLogicIdAndLogicalId($this->getId(), self::CLIENT_CONNECTED);
		if ($create && !is_object($this->_connectedCmd)) { // cmd does not exist
			$cmd = jMQTTCmd::newCmd($this, self::CLIENT_CONNECTED, '', ''); // Topic and jsonPath are irrelevant here
			$cmd->setLogicalId(self::CLIENT_CONNECTED);
			$cmd->setSubType('binary');
			$cmd->setConfiguration('irremovable', 1);
			$cmd->save();
			$this->_connectedCmd = $cmd;
		}
		return $this->_connectedCmd;
	}

	###################################################################################################################

	/**
	 * Process the API request
	 */
	private function processApiRequest($msg) {
		try {
			$request = new mqttApiRequest($msg, $this);
			$request->processRequest($this->getConf(self::CONF_KEY_MQTT_API));
		} catch (Throwable $e) {
			if (log::getLogLevel(__CLASS__) > 100)
				self::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __METHOD__, $e->getMessage()));
			else
				self::logger('error', str_replace("\n",' <br/> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
							"@Stack: %3\$s,<br/>@BrkId: %4\$s,<br/>@Topic: %5\$s,<br/>@Payload: %6\$s.",
							__METHOD__, $e->getMessage(), $e->getTraceAsString(), $id, $topic, $payload)));
		}
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
		} catch (Throwable $e) {} // nothing to do in that particular case
	}

	/**
	 * Log messages to jMQTT log file
	 * @param string $level
	 * @param string $msg
	 */
	public static function logger($level, $msg) {
		log::add(__CLASS__, $level, $msg);
	}

	private function getConf($_key) {
		// Default value is returned if config is null or an empty string
		return $this->getConfiguration($_key, $this->getDefaultConfiguration($_key));
	}

	private function getDefaultConfiguration($_key) {
		if ($_key == self::CONF_KEY_MQTT_PORT) {
			$proto = $this->getConf(self::CONF_KEY_MQTT_PROTO);
			if ($proto == 'mqtt')
				return 1883;
			elseif ($proto == 'mqtts')
				return 8883;
			elseif ($proto == 'ws')
				return 1884;
			elseif ($proto == 'wss')
				return 8884;
			else
				return 0;
		}
		$defValues = array(
			self::CONF_KEY_MQTT_PROTO => 'mqtt',
			self::CONF_KEY_MQTT_ADDRESS => 'localhost',
			self::CONF_KEY_MQTT_ID => '0',
			self::CONF_KEY_QOS => '1',
			self::CONF_KEY_MQTT_LWT => '1',
			self::CONF_KEY_MQTT_LWT_TOPIC => 'jeedom/status',
			self::CONF_KEY_MQTT_LWT_ONLINE => 'online',
			self::CONF_KEY_MQTT_LWT_OFFLINE => 'offline',
			self::CONF_KEY_MQTT_TLS_CHECK => 'public',
			self::CONF_KEY_MQTT_TLS_CLI => '0',
			self::CONF_KEY_AUTO_ADD_CMD => '1',
			self::CONF_KEY_MQTT_INT => '0',
			self::CONF_KEY_MQTT_INT_TOPIC => 'jeedom/interact',
			self::CONF_KEY_MQTT_API => '0',
			self::CONF_KEY_MQTT_API_TOPIC => 'jeedom/api',
			self::CONF_KEY_BRK_ID => -1
		);
		// If not in list, default value is ''
		return isset($defValues[$_key]) ? $defValues[$_key] : '';
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
	// Used by utils::a2o to set de Broker Id...
	public function setEqLogic($id) {
		$this->setConfiguration(self::CONF_KEY_BRK_ID, $id);
	}

	/**
	 * Get this jMQTT object Qos
	 * @return string
	 */
	public function getQos() {
		return $this->getConf(self::CONF_KEY_QOS);
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
	 * Get the Battery command defined in this eqLogic
	 * @return string Return the Battery command defined
	 */
	public function getBatteryCmd() {
		return $this->getConf(self::CONF_KEY_BATTERY_CMD);
	}

	/**
	 * Get the Availability command defined in this eqLogic
	 * @return string Return the Availability command defined
	 */
	public function getAvailabilityCmd() {
		return $this->getConf(self::CONF_KEY_AVAILABILITY_CMD);
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
			throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s.", __FILE__), $id));
		}
		if ($broker->getType() != self::TYP_BRK) {
			throw new Exception(__("L'équipement n'est pas de type Broker", __FILE__) . ' (id=' . $id . ')');
		}
		return $broker;
	}

	/**
	 * Return all jMQTT objects attached to the specified broker id
	 * @return jMQTT[]
	 */
	public static function byBrkId($id) {
		$brkId = json_encode(array('eqLogic' => $id));
		/** @var jMQTT[] $eqpts */
		$returns = self::byTypeAndSearchConfiguration(__CLASS__, substr($brkId, 1, -1));
		return $returns;
	}
}
