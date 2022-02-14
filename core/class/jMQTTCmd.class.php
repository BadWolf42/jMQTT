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

/**
 * Extends the cmd core class
 */
class jMQTTCmd extends cmd {

	/**
	 * @var int maximum length of command name supported by the database scheme
	 */
	private static $_cmdNameMaxLength;

	/**
	 * Data shared between preSave and postSave
	 * @var array values from preSave used fro postSave actions
	 */
	private $_preSaveInformations;

	/**
	 * Create a new command. Command IS NOT saved.
	 * @param jMQTT $eqLogic jMQTT equipment the command belongs to
	 * @param string $name command name
	 * @param string $topic command mqtt topic
	 * @return jMQTTCmd new command (NULL if not created)
	 */
	public static function newCmd($eqLogic, $name, $topic, $jsonPath = '') {

		$cmd = new jMQTTCmd();
		$cmd->setEqLogic($eqLogic);
		$cmd->setEqLogic_id($eqLogic->getId());
		$cmd->setEqType('jMQTT');
		$cmd->setIsVisible(1);
		$cmd->setType('info');
		$cmd->setSubType('string');
		$cmd->setTopic($topic);
		$cmd->setJsonPath($jsonPath);

		// Check cmd name does not exceed the max lenght of the database scheme (fix issue #58)
		$cmd->setName(self::checkCmdName($eqLogic, $name));

		return $cmd;
	}

	/**
	 * Inform that a command has been created (in the log and to the ui though an event)
	 * @param bool $reload indicate if the desktop page shall be reloaded
	 */
	private function eventNewCmd($reload=false) {
		$eqLogic = $this->getEqLogic();
		$eqLogic->log('info', 'Command added ' . $this->getType() . ' ' . $this->getLogName());

		// Advise the desktop page (jMQTT.js) that a new command has been added
		event::add('jMQTT::cmdAdded',
			array('eqlogic_id' => $eqLogic->getId(), 'eqlogic_name' => $eqLogic->getName(),
				'cmd_name' => $this->getName(), 'reload' => $reload));
	}

	private function eventTopicMismatch() {
		$eqLogic = $this->getEqLogic();
		$eqLogic->log('warning', 'Le topic de la commande ' . $this->getLogName() .
			" est incompatible du topic de l'équipement associé");

		// Advise the desktop page (jMQTT.js) of the topic mismatch
		event::add('jMQTT::cmdTopicMismatch',
			array('eqlogic_name' => $eqLogic->getName(), 'cmd_name' => $this->getName()));
	}

	/**
	 * Return a full export of this command as an array.
	 * @return array
	 */
	public function full_export() {
		$cmd = clone $this;
		$cmdValue = $cmd->getCmdValue();
		if (is_object($cmdValue)) {
			$cmd->setValue($cmdValue->getName());
		} else {
			$cmd->setValue('');
		}
		$return = utils::o2a($cmd);
		return $return;
	}

	/**
	 * Update this command value, and inform all stakeholders about the new value
	 * @param string $value new command value
	 */
	public function updateCmdValue($value) {
		if(in_array(strtolower($this->getName()), ["color","colour","couleur","rgb"]) || $this->getGeneric_type() == "LIGHT_COLOR") {
			if(is_numeric($value)) {
				$value=jMQTTCmd::DECtoHEX($value);
			} else {
				$json=json_decode($value);
				if($json != null){
					if(isset($json->x) && isset($json->y)){
						$value=jMQTTCmd::XYtoHTML($json->x,$json->y);
					} elseif(isset($json->r) && isset($json->g) && isset($json->b)) {
						$value=jMQTTCmd::RGBtoHTML($json->r,$json->g,$json->b);
					}
				}
			}
		}
		$this->getEqLogic()->checkAndUpdateCmd($this, $value);
		$this->getEqLogic()->log('info', '-> ' . $this->getLogName() . ' ' . $value);

		if ($this->isBattery() && !in_array($value[0], ['{','[',''])) {
			if ($this->getSubType() == 'binary') {
				$this->getEqLogic()->batteryStatus($value ? 100 : 10);
			} else {
				$this->getEqLogic()->batteryStatus($this->getCache('value'));
			}
			$this->getEqLogic()->log('info', '-> Update battery status');
		}
	}

	/**
	 * Update this command value knowing that this command is derived from a JSON payload
	 * Inform all stakeholders about the new value
	 * @param array $jsonArray associative array
	 */
	public function updateJsonCmdValue($jsonArray) {
		// Create JsonObject for JsonPath
		$jsonobject=new JsonPath\JsonObject($jsonArray);

		// Get and prepare the jsonPath
		$jsonPath = $this->getJsonPath();
		if ($jsonPath == '') return;
		if ($jsonPath[0] != '$') $jsonPath = '$' . $jsonPath;

		try {
			$value = $jsonobject->get($jsonPath);
			if ($value !== false)
				$this->updateCmdValue(json_encode($value[0], JSON_UNESCAPED_SLASHES));
			else
				$this->getEqLogic()->log('info', 'valeur de la commande ' . $this->getLogName() . ' non trouvée');
		}
		catch (Throwable $e) {
			$this->getEqLogic()->log('error', 'Chemin JSON de la commande ' . $this->getLogName() . ' incorrect : "' . $this->getJsonPath() . '"');
		}

	}

	/**
	 * Decode and return the JSON payload being received by this command
	 * @param string $payload JSON payload being received
	 * @return null|array|object null if the JSON payload cannot de decoded
	 */
	public function decodeJsonMsg($payload) {
		$jsonArray = json_decode($payload, true);
		if (is_array($jsonArray) && json_last_error() == JSON_ERROR_NONE)
			return $jsonArray;
		else {
			$this->getEqLogic()->log('warning', 'Erreur de format JSON sur la commande ' . $this->getLogName() .
				': ' . json_last_error_msg());
			return null;
		}
	}

	/**
	 * Returns whether or not a given parameter is valid and can be processed by the setConfiguration method
	 * @param string $value given configuration parameter value
	 * @return boolean TRUE of the parameter is valid, FALSE if not
	 */
	public static function isConfigurationValid($value) {
		return (json_encode(array('v' => $value), JSON_UNESCAPED_UNICODE) !== FALSE);
	}

	/**
	 * This method is called when a command is executed
	 */
	public function execute($_options = null) {

		if ($this->getType() != 'action')
			return;

		$request = $this->getConfiguration('request', "");
		$topic = $this->getTopic();
		$qos = $this->getConfiguration('Qos', 1);
		$retain = $this->getConfiguration('retain', 0);

		switch ($this->getSubType()) {
			case 'slider':
				$request = str_replace('#slider#', $_options['slider'], $request);
				break;
			case 'color':
				$request = str_replace('#color#', $_options['color'], $request);
				break;
			case 'message':
				if ($_options != null)  {
					$replace = array('#title#', '#message#');
					$replaceBy = array($_options['title'], $_options['message']);
					$request = str_replace($replace, $replaceBy, $request);
				}
				break;
			case 'select':
				$request = str_replace('#select#', $_options['select'], $request);
				break;
		}

		$request = jeedom::evaluateExpression($request);
		$this->getEqLogic()->publish($this->getEqLogic()->getName(), $topic, $request, $qos, $retain);

		return $request;
	}

	/**
	 * preSave callback called by the core before saving this command in the DB
	 */
	public function preSave() {

		foreach(array('request') as $key) {
			$conf = $this->getConfiguration($key);

			// Add/remove special char before JSON starting by '{' because Jeedom Core breaks integer, boolean and null values
			// TODO : To Be delete when fix reached Jeedom Core stable
			// https://github.com/jeedom/core/pull/1825
			// https://github.com/jeedom/core/pull/1829
			if (is_string($conf) && strlen($conf) >= 1 && $conf[0] == chr(6)) $this->setConfiguration($key, substr($conf, 1));

			// If request is an array, it means a JSON (starting by '{') has been parsed in 'request' field (parsed by getValues in jquery.utils.js)
			if (is_array($conf) && (($conf = json_encode($conf, JSON_UNESCAPED_UNICODE)) !== FALSE))
				$this->setConfiguration($key, $conf);
		}

		// Specific command : status for Broker eqpt
		if ($this->getLogicalId() == jMQTT::CLIENT_STATUS && $this->getEqLogic()->getType() == jMQTT::TYP_BRK) {
			if (!isset($this->name)) $this->setName(jMQTT::CLIENT_STATUS);
			if ($this->getSubType() != 'string') $this->setSubType('string');
			$this->setTopic($this->getEqLogic()->getMqttClientStatusTopic()); // just for display as it's not used to start the MqttClient
			$this->setJsonPath(''); // just for display as it's not used to start the MqttClient
		}

		// --- New cmd ---
		if ($this->getId() == '') {

		}
		// --- Existing cmd ---
		else {

		}

		// Reset autoPub if info cmd (should not happen or be possible)
		if ($this->getType() == 'info' && $this->getConfiguration('autoPub', 0))
			$this->setConfiguration('autoPub', 0);
		// Check "request" if autoPub enabled
		if ($this->getType() == 'action' && $this->getConfiguration('autoPub', 0)) {
			$req = $this->getConfiguration('request', '');
			// Must check If New cmd, autoPub changed or Request changed
			$must_chk = $this->getId() == '';
			$must_chk = $must_chk || !(self::byId($this->getId())->getConfiguration('autoPub', 0));
			$must_chk = $must_chk || (self::byId($this->getId())->getConfiguration('request', '') != $req);
			if ($must_chk) {
				// Get all commands
				preg_match_all("/#([0-9]*)#/", $req, $matches);
				$cmds = array_unique($matches[1]);
				if (count($cmds) > 0) { // There are commands
					foreach ($cmds as $cmd_id) {
						$cmd = cmd::byId($cmd_id);
						if (!is_object($cmd))
							throw new Exception('Impossible d\'activer la publication automatique sur <b>'.$this->getHumanName().'</b> car la commande <b>'.$cmd_id.'</b> est invalide.');
						if ($cmd->getType() != 'info')
							throw new Exception('Impossible d\'activer la publication automatique sur <b>'.$this->getHumanName().'</b> car la commande <b>'.$cmd->getHumanName().'</b> n\'est pas de type info.');
						if ($cmd->getEqType() =='jMQTT') {
							$cmd_topic = $cmd->isJson() ? substr($cmd->getTopic(), 0, strpos($cmd->getTopic(), '{')) : $cmd->getTopic();
							if ($this->getTopic() == $cmd_topic)
								throw new Exception('Impossible d\'activer la publication automatique sur <b>'.$this->getHumanName().'</b> car la commande <b>'.$cmd->getHumanName().'</b> référence le même topic.');
						}
					}
				}
			}
		}

		// It's time to gather informations that will be used in postSave
		if ($this->getId() == '') $this->_preSaveInformations = null;
		else {
			$cmd = self::byId($this->getId());
			$this->_preSaveInformations = array(
				'retain' => $cmd->getConfiguration('retain', 0),
				'brokerStatusTopic' => $cmd->getTopic(),
				'autoPub' => $cmd->getConfiguration('autoPub', 0),
				'request' => $cmd->getConfiguration('request', ''),
				'isBattery' => $cmd->isBattery()
			);
		}
	}

	/**
	 * Callback called by the core after having saved this command in the DB
	 */
	public function postSave() {
		$eqLogic = $this->getEqLogic();

		// If _preSaveInformations is null, It's a fresh new cmd.
		if (is_null($this->_preSaveInformations)) {

			// Type Info and deriving from a JSON payload : Initialize value from "root" cmd
			if ($this->getType() == 'info' && $this->isJson()) {

				$root_topic = $this->getTopic();

				/** @var jMQTTCmd $root_cmd root JSON command */
				$root_cmd = jMQTTCmd::byEqLogicIdAndTopic($this->getEqLogic_id(), $root_topic, false);

				if (isset($root_cmd)) {
					$value = $root_cmd->execCmd();
					if (! empty($value)) {
						$jsonArray = $root_cmd->decodeJsonMsg($value);
						if (isset($jsonArray)) {
							$this->updateJsonCmdValue($jsonArray);
						}
					}
				}
			}

			// Only update listener on Eq (not Broker) at creation
			if ($eqLogic->getType() == jMQTT::TYP_EQPT)
				$this->listenerUpdate();

			$this->eventNewCmd();
		}
		else { // the cmd has been updated

			// If retain mode changed
			if ($this->_preSaveInformations['retain'] != $this->getConfiguration('retain', 0)) {

				$cmdLogName = $this->getLogName();

				// It's enabled now
				if ($this->getConfiguration('retain', 0)) {
					$eqLogic->log('info', $cmdLogName . ': mode retain activé');
				}
				else{
					//If broker eqpt is enabled
					if ($eqLogic->getBroker()->getIsEnable()) {
						// A null payload should be sent to the broker to erase the last retained value
						// Otherwise, this last value remains retained at broker level
						$eqLogic->log('info', $cmdLogName . ': mode retain désactivé, efface la dernière valeur mémorisée sur le broker');
						$eqLogic->publish($eqLogic->getName(), $this->getTopic(), '', 1, 1);
					}
				}
			}

			// Specific command : status for Broker eqpt
			if ($this->getLogicalId() == jMQTT::CLIENT_STATUS && $eqLogic->getType() == jMQTT::TYP_BRK && $eqLogic->getIsEnable()) {
				// If it's topic changed
				if ($this->_preSaveInformations['brokerStatusTopic'] != $this->getTopic()) {
					// Just try to remove the previous status topic
					$eqLogic->publish($eqLogic->getName(), $this->_preSaveInformations['brokerStatusTopic'], '', 1, 1);
				}
			}

			// Remove batteryStatus from eqLogic if cmd is no longer a battery
			if (!$this->isBattery() && $this->_preSaveInformations['isBattery']) {
				$eqLogic->setStatus('battery', null);
				$eqLogic->setStatus('batteryDatetime', null);
			}

			// Only Update listener if "autoPub" or "request" has changed
			if ($eqLogic->getType() == jMQTT::TYP_EQPT &&
					($this->_preSaveInformations['autoPub'] != $this->getConfiguration('autoPub', 0) ||
					 $this->_preSaveInformations['request'] != $this->getConfiguration('request', '')))
				$this->listenerUpdate();
		}

		// For Equipments
		if ($eqLogic->getType() == jMQTT::TYP_EQPT) {
			// For info commands, check that the topic is compatible with the subscription command
			if ($this->getType() == 'info' && !$eqLogic->getCache(jMQTT::CACHE_IGNORE_TOPIC_MISMATCH, 0)) {
				if (! $this->topicMatchesSubscription($eqLogic->getTopic())) {
					$this->eventTopicMismatch();
				}
			}
		}
	}

// Listener for autoPub action command
	public function listenerUpdate() {
		$cmds = array();
		if ($this->getEqLogic()->getIsEnable() && $this->getType() == 'action' && $this->getConfiguration('autoPub', 0)) {
			preg_match_all("/#([0-9]*)#/", $this->getConfiguration('request', ''), $matches);
			$cmds = array_unique($matches[1]);
		}
		$listener = listener::searchClassFunctionOption(__CLASS__, 'listenerAction', '"cmd":"'.$this->getId().'"');
		if (count($listener) == 0) { // No listener found
			$listener = null;
		} else {
			while (count($listener) > 1) // Too many listener for this cmd, let's cleanup
				array_pop($listener)->remove();
			$listener = $listener[0]; // Get the last listener
		}
		if (count($cmds) > 0) { // We need a listener
			if (!is_object($listener))
				$listener = new listener();
			$listener->setClass(__CLASS__);
			$listener->setFunction('listenerAction');
			$listener->emptyEvent();
			foreach ($cmds as $cmd_id) {
				$cmd = cmd::byId($cmd_id);
				if (is_object($cmd) && $cmd->getType() == 'info')
					$listener->addEvent($cmd_id);
			}
			$listener->setOption('cmd', $this->getId());
			$listener->setOption('eqLogic', $this->getEqLogic_id());
			$listener->setOption('background', false);
			$listener->save();
			log::add('jMQTT', 'debug', 'Listener Installed on #'.$this->getHumanName().'#');
		} else { // We don't want a listener
			if (is_object($listener)) {
				$listener->remove();
				log::add('jMQTT', 'debug', 'Listener Removed from #'.$this->getHumanName().'#');
			}
		}
	}

	public static function listenerAction($_options) {
		$cmd = self::byId($_options['cmd']);
		if (!is_object($cmd) || !$cmd->getEqLogic()->getIsEnable() || !$cmd->getType() == 'action' || !$cmd->getConfiguration('autoPub', 0)) {
			listener::byId($_options['listener_id'])->remove();
			log::add('jMQTT', 'debug', 'Listener Removed from #'.$_options['cmd'].'#');
		} else {
			log::add('jMQTT', 'debug', 'Auto Publish on #'.$cmd->getHumanName().'#');
			$cmd->execute();
		}
	}

	/**
	 * preRemove method to log that a command is removed
	 */
	public function preRemove() {
		$eqLogic = $this->getEqLogic();
		if ($eqLogic) {
			$eqLogic->log('info', 'Removing command ' . $this->getLogName());
			// Remove batteryStatus from eqLogic on delete
			if ($this->isBattery()) {
				$eqLogic->setStatus('battery', null);
				$eqLogic->setStatus('batteryDatetime', null);
			}
		} else {
			log::add('jMQTT', 'info', 'Removing orphan command #'.$this->getId().'#');
		}
		$listener = listener::searchClassFunctionOption(__CLASS__, 'listenerAction', '"cmd":"'.$this->getId().'"');
		foreach ($listener as $l) {
			log::add('jMQTT', 'debug', 'Listener Removed from #'.$l->getOption('cmd').'#');
			$l->remove();
		}
	}

	public function setName($name) {
		// Since 3.3.22, the core removes / from command names
		$name = str_replace("/", ":", $name);
		parent::setName($name);
	}

	/**
	 * Set this command as irremovable
	 */
	public function setIrremovable() {
		$this->setConfiguration('irremovable', 1);
	}

	public function setTopic($topic) {
		$this->setConfiguration('topic', $topic);
	}

	public function getTopic() {
		return $this->getConfiguration('topic');
	}

	public function setJsonPath($jsonPath) {
		$this->setConfiguration('jsonPath', $jsonPath);
	}

	public function getJsonPath() {
		return $this->getConfiguration('jsonPath', '');
	}

	public function splitTopicAndJsonPath() {
		// Try to find '{'
		$topic = $this->getTopic();
		$i = strpos($topic, '{');
		// If no '{' then return
		if ($i === false)
			return;

		// Set cleaned Topic
		$this->setTopic(substr($topic, 0, $i));
		$jsonPath = substr($topic, $i);
		$jsonPath = str_replace('{', '[', $jsonPath);
		$jsonPath = str_replace('}', ']', $jsonPath);
		$this->setJsonPath($jsonPath);
		$this->save();
	}

	/**
	 * Return the list of commands of the given equipment which topic is related to the given one
	 * (i.e. equal to the given one if multiple is false, or having the given topic as mother JSON related
	 * topic if multiple is true)
	 * For JSON related topic, mother command is always returned first if existing.
	 *
	 * @param int $eqLogic_id of the eqLogic
	 * @param string $topic topic to search
	 * @param boolean $multiple true if the cmd related topic and associated JSON derived commands are requested
	 * @return NULL|jMQTTCmd|array(jMQTTCmd)
	 */
	public static function byEqLogicIdAndTopic($eqLogic_id, $topic, $multiple=false) {
		// JSON_UNESCAPED_UNICODE used to correct #92
		$confTopic = substr(json_encode(array('topic' => $topic), JSON_UNESCAPED_UNICODE), 1, -1);
		$confTopic = str_replace('\\', '\\\\', $confTopic);

		$values = array(
			'topic' => '%' . $confTopic . '%',
			'emptyJsonPath' => '%"jsonPath":""%',
			'eqLogic_id' => $eqLogic_id,
		);
		$sql = 'SELECT ' . DB::buildField(__CLASS__) . 'FROM cmd WHERE eqLogic_id=:eqLogic_id AND configuration LIKE :topic AND ';

		if ($multiple) {
			$values['AllJsonPath'] = '%"jsonPath":"%';
			// Union is used to have the mother command returned first
			$sql .= 'configuration LIKE :emptyJsonPath UNION ' . $sql . 'configuration LIKE :AllJsonPath';
		}
		else {
			$sql .= 'configuration LIKE :emptyJsonPath';
		}
		$cmds = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);

		if (count($cmds) == 0)
			return null;
		else
			return $multiple ? $cmds : $cmds[0];
	}

	/**
	 * Return whether or not this command may contain a battery value
	 * @return boolean
	 */
	public function isBattery() {
		return $this->getType() == 'info' && ($this->getGeneric_type() == 'BATTERY' || preg_match('/(battery|batterie)$/i', $this->getName()));
	}

	/**
	 * Return whether or not this command is derived from a Json payload
	 * @return boolean
	 */
	public function isJson() {
		return $this->getJsonPath() != '';
	}

	/**
	 * Return the name of this command for logging purpose
	 * (basically, return concatenation of the eqLogic name and the cmd name)
	 * @return string
	 */
	private function getLogName() {
		return $this->getEqLogic()->getName()  . '|' . $this->getName();
	}

	/**
	 * Returns true if the topic of this command matches the given subscription description
	 * @param string $subscription subscription to match
	 * @return boolean
	 */
	public function topicMatchesSubscription($subscription) {
		return mosquitto_topic_matches_sub($subscription, $this->getTopic());
	}

	/**
	 * @param jMQTT eqLogic the command belongs to
	 * @param string $name
	 * @return string input name if the command name is valid, corrected cmd name otherwise
	 */
	private static function checkCmdName($eqLogic, $name) {
		if (! isset(self::$_cmdNameMaxLength)) {
			$field = 'character_maximum_length';
			$sql = "SELECT " . $field . " FROM information_schema.columns WHERE table_name='cmd' AND column_name='name'";
			$res = DB::Prepare($sql, array());
			self::$_cmdNameMaxLength = $res[$field];
			$eqLogic->log('debug', 'Cmd name max length retrieved from the DB: ' . strval(self::$_cmdNameMaxLength));
		}

		if (strlen($name) > self::$_cmdNameMaxLength)
			return hash("md4", $name);
		else
			return $name;
	}


	/**
	 * Converts RGB values to XY values
	 * Based on: http://stackoverflow.com/a/22649803
	 *
	 * @param int $red   Red value
	 * @param int $green Green value
	 * @param int $blue  Blue value
	 *
	 * @return array x, y, bri key/value
	 */
	public static function HTMLtoXY($color) {

		$color = str_replace('0x','', $color);
		$color = str_replace('#','', $color);
		$red = hexdec(substr($color, 0, 2));
		$green = hexdec(substr($color, 2, 2));
		$blue = hexdec(substr($color, 4, 2));

		// Normalize the values to 1
		$normalizedToOne['red'] = $red / 255;
		$normalizedToOne['green'] = $green / 255;
		$normalizedToOne['blue'] = $blue / 255;

		// Make colors more vivid
		foreach ($normalizedToOne as $key => $normalized) {
			if ($normalized > 0.04045) {
				$color[$key] = pow(($normalized + 0.055) / (1.0 + 0.055), 2.4);
			} else {
				$color[$key] = $normalized / 12.92;
			}
		}

		// Convert to XYZ using the Wide RGB D65 formula
		$xyz['x'] = $color['red'] * 0.664511 + $color['green'] * 0.154324 + $color['blue'] * 0.162028;
		$xyz['y'] = $color['red'] * 0.283881 + $color['green'] * 0.668433 + $color['blue'] * 0.047685;
		$xyz['z'] = $color['red'] * 0.000000 + $color['green'] * 0.072310 + $color['blue'] * 0.986039;

		// Calculate the x/y values
		if (array_sum($xyz) == 0) {
			$x = 0;
			$y = 0;
		} else {
			$x = $xyz['x'] / array_sum($xyz);
			$y = $xyz['y'] / array_sum($xyz);
		}

		return array(
			'x' => $x,
			'y' => $y,
			'bri' => round($xyz['y'] * 255),
		);
	}
	/**
	 * Converts XY (and brightness) values to RGB
	 *
	 * @param float $x X value
	 * @param float $y Y value
	 * @param int $bri Brightness value
	 *
	 * @return array red, green, blue key/value
	 */
	public static function XYtoHTML($x, $y, $bri = 255) {
		// Calculate XYZ
		$z = 1.0 - $x - $y;
		$xyz['y'] = $bri / 255;
		$xyz['x'] = ($xyz['y'] / $y) * $x;
		$xyz['z'] = ($xyz['y'] / $y) * $z;
		// Convert to RGB using Wide RGB D65 conversion
		$color['r'] = $xyz['x'] * 1.656492 - $xyz['y'] * 0.354851 - $xyz['z'] * 0.255038;
		$color['g'] = -$xyz['x'] * 0.707196 + $xyz['y'] * 1.655397 + $xyz['z'] * 0.036152;
		$color['b'] = $xyz['x'] * 0.051713 - $xyz['y'] * 0.121364 + $xyz['z'] * 1.011530;
		$maxValue = 0;
		foreach ($color as $key => $normalized) {
			// Apply reverse gamma correction
			if ($normalized <= 0.0031308) {
				$color[$key] = 12.92 * $normalized;
			} else {
				$color[$key] = (1.0 + 0.055) * pow($normalized, 1.0 / 2.4) - 0.055;
			}
			$color[$key] = max(0, $color[$key]);
			if ($maxValue < $color[$key]) {
				$maxValue = $color[$key];
			}
		}
		foreach ($color as $key => $normalized) {
			if ($maxValue > 1) {
				$color[$key] /= $maxValue;
			}
			// Scale back from a maximum of 1 to a maximum of 255
			$color[$key] = round($color[$key] * 255);
		}
		return sprintf("#%02X%02X%02X", $color['r'], $color['g'], $color['b']);
	}
	public static function RGBtoHTML($r, $g=-1, $b=-1)
	{
		if (is_array($r) && sizeof($r) == 3)
			list($r, $g, $b) = $r;

		$r = intval($r); $g = intval($g);
		$b = intval($b);

		$r = dechex($r<0?0:($r>255?255:$r));
		$g = dechex($g<0?0:($g>255?255:$g));
		$b = dechex($b<0?0:($b>255?255:$b));

		$color = (strlen($r) < 2?'0':'').$r;
		$color .= (strlen($g) < 2?'0':'').$g;
		$color .= (strlen($b) < 2?'0':'').$b;
		return '#'.$color;
	}
	public static function HEXtoDEC($s) {
		$s = str_replace("#", "", $s);
		$output = 0;
		for ($i=0; $i<strlen($s); $i++) {
			$c = $s[$i]; // you don't need substr to get 1 symbol from string
			if ( ($c >= '0') && ($c <= '9') )
				$output = $output*16 + ord($c) - ord('0'); // two things: 1. multiple by 16 2. convert digit character to integer
			elseif ( ($c >= 'A') && ($c <= 'F') ) // care about upper case
				$output = $output*16 + ord($s[$i]) - ord('A') + 10; // note that we're adding 10
			elseif ( ($c >= 'a') && ($c <= 'f') ) // care about lower case
				$output = $output*16 + ord($c) - ord('a') + 10;
		}

		return $output;
	}
	public static function DECtoHEX($d) {
		return("#".substr("000000".dechex($d),-6));
	}
}
