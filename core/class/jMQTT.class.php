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
require_once __DIR__  . '/jMQTTBase.class.php';

include_file('core', 'mqttApiRequest', 'class', 'jMQTT');
include_file('core', 'jMQTTCmd', 'class', 'jMQTT');

class jMQTT extends eqLogic {

    const API_TOPIC = 'api';
    const API_ENABLE = 'enable';
    const API_DISABLE = 'disable';
    
    const CLIENT_STATUS = 'status';
    const OFFLINE = 'offline';
    const ONLINE = 'online';
    
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
    const CONF_KEY_API = 'api';
    const CONF_KEY_LOGLEVEL = 'loglevel';
    
    const CONF_KEY_OLD = 'old';
    const CONF_KEY_NEW = 'new';

    const CACHE_INCLUDE_MODE = 'include_mode';
    const CACHE_IGNORE_TOPIC_MISMATCH = 'ignore_topic_mismatch';
    
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
     * Return one or all templates content (from json files) as an array.
     * @param string $_template template name to look for
     * @return array
     */
	public static function templateParameters($_template = ''){
		$return = array();
		foreach (ls(dirname(__FILE__) . '/../config/template', '*.json', false, array('files', 'quiet')) as $file) {
			try {
				$content = file_get_contents(dirname(__FILE__) . '/../config/template/' . $file);
				if (is_json($content)) {
					$return += json_decode($content, true);
				}
			} catch (Throwable $e) {}
		}
		if (isset($_template) && $_template != '') {
			if (isset($return[$_template])) {
				return $return[$_template];
			}
			return array();
		}
		return $return;
	}

    /**
     * apply a template (from json) to the current equipement.
     * @param string $_template name of the template to apply
     */
    public function applyTemplate($_template, $_topic, $_keepCmd = true){

        if ($this->getType() != self::TYP_EQPT) {
            return true;
        }

		$template = self::templateParameters($_template);
		if (!is_array($template)) {
			return true;
		}

        // Raise up the flag that cmd topic mismatch must be ignored
        $this->setCache(self::CACHE_IGNORE_TOPIC_MISMATCH, 1);

        // import template
        $this->import($template, $_keepCmd);

        // complete eqpt topic
        $this->setLogicalId(sprintf($template['logicalId'], $_topic));
        $this->save();

        // complete cmd topics
        foreach ($this->getCmd() as $cmd) {
            $cmd->setConfiguration('topic', sprintf($cmd->getConfiguration('topic'), $_topic));
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
        $baseTopic = $this->getLogicalId();
        if (substr($baseTopic, -1) == '#' || substr($baseTopic, -1) == '+') { $baseTopic = substr($baseTopic, 0, -1); }
        if (substr($baseTopic, -1) == '/') { $baseTopic = substr($baseTopic, 0, -1); }

        // Add string format for logicalId (Topic of eqpt)
        $exportedTemplate[$_template]['logicalId'] = str_replace($baseTopic, '%s', $this->getLogicalId());

        // convert topic to string format
        foreach ($exportedTemplate[$_template]['cmd'] as $key => $cmd) {
            if(isset($cmd['configuration']['topic'])) {
                $exportedTemplate[$_template]['cmd'][$key]['configuration']['topic'] = str_replace($baseTopic, '%s', $cmd['configuration']['topic']);
            }
        }

        // Rename 'cmd' to 'commands' for Jeedom import ...
        // (Why Jeedom used different names in export() and in import() ?!)
        $exportedTemplate[$_template]['commands'] = $exportedTemplate[$_template]['cmd'];
        unset($exportedTemplate[$_template]['cmd']);

        // Remove brkId from eqpt configuration
        unset($exportedTemplate[$_template]['configuration'][self::CONF_KEY_BRK_ID]);

        // Convert and save to file
        $jsonExport = json_encode($exportedTemplate, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $formatedTemplateName = str_replace(' ', '_', $_template);
        $formatedTemplateName = preg_replace('/[^a-zA-Z0-9_]+/', '', $formatedTemplateName);
        file_put_contents(dirname(__FILE__) . '/../config/template/' . $formatedTemplateName . '.json', $jsonExport);
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
        jMQTTBase::subscribe_mqtt_topic(__CLASS__, $this->getBrkId(), $topic, $qos);
    }

    /**
     * Unsubscribe topic ONLY if no other enabled eqpt linked to the same broker subscribes the same topic
     */
    public function unsubscribeTopic($topic, $brkId = null){
        if (empty($topic)) return;

        if (is_null($brkId)) $brkId = $this->getBrkId();

        // If broker eqpt is disabled or MqttClient is not connected or stopped, don't need to send unsubscribe
        $broker = self::getBrokerFromId($brkId);
        if(!$broker->getIsEnable() || $broker->getMqttClientState() == jMQTTBase::MQTTCLIENT_POK || $broker->getMqttClientState() == jMQTTBase::MQTTCLIENT_NOK) return;

        //Find eqLogic using the same topic (which is stored in logicalId)
        $eqLogics = eqLogic::byLogicalId($topic, __CLASS__, true);
        $count = 0;
        foreach ($eqLogics as $eqLogic) {
            // If it's attached to the same broker and enabled and it's not "me"
            if ($eqLogic->getBrkId() == $brkId && $eqLogic->getIsEnable() && $eqLogic->getId() != $this->getId()) $count++;
        }

        //if there is no other eqLogic using the same topic, we can unsubscribe
        if (!$count) {
            $this->log('info', ($this->getType() == self::TYP_EQPT?'Equipement ':'Broker ') . $this->getName() . ': unsubscribes from "' . $topic . '"');
            jMQTTBase::unsubscribe_mqtt_topic(__CLASS__, $brkId, $topic);
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
                if (!boolval($this->_preSaveInformations[self::CONF_KEY_MQTT_TLS])) {
                    // If a CA is specified and this file doesn't exists, remove it
                    if($this->getConf(self::CONF_KEY_MQTT_TLS_CA) != $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CA) && !file_exists(realpath(dirname(__FILE__) . '/../../' . jMQTTBase::PATH_CERTIFICATES . $this->getConf(self::CONF_KEY_MQTT_TLS_CA))))
                        $this->setConfiguration(self::CONF_KEY_MQTT_TLS_CA, $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CA));
                    if($this->getConf(self::CONF_KEY_MQTT_TLS_CLI_CERT) != $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CLI_CERT) && !file_exists(realpath(dirname(__FILE__) . '/../../' . jMQTTBase::PATH_CERTIFICATES . $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_CERT))))
                        $this->setConfiguration(self::CONF_KEY_MQTT_TLS_CLI_CERT, $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CLI_CERT));
                    if($this->getConf(self::CONF_KEY_MQTT_TLS_CLI_KEY) != $this->getDefaultConfiguration(self::CONF_KEY_MQTT_TLS_CLI_KEY) && !file_exists(realpath(dirname(__FILE__) . '/../../' . jMQTTBase::PATH_CERTIFICATES . $this->getConf(self::CONF_KEY_MQTT_TLS_CLI_KEY))))
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

                $stopped = ($this->getMqttClientState() == jMQTTBase::MQTTCLIENT_NOK);
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
                    if ($this->getIsEnable()) $subscribeRequested = true;
                    else {
                        if(!$unsubscribed){
                            //Unsubscribe previous topic (if topic changed too)
                            $this->unsubscribeTopic($this->_preSaveInformations['topic']);
                            $unsubscribed = true;
                        }
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
                    if ($this->getMqttClientState() == jMQTTBase::MQTTCLIENT_NOK) break;
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
    public function cron() {
        self::checkAllMqttClients();
    }
    
    /**
     * callback to get information on the daemon
     */
    public static function deamon_info() {
        return jMQTTBase::deamon_info(__CLASS__);
    }

    /**
     * callback to start daemon
     */
    public static function deamon_start() {
        log::add(__CLASS__, 'info', 'Starting Daemon');
        jMQTTBase::deamon_start(__CLASS__);
        self::checkAllMqttClients();
    }
    
    /**
     * callback to stop daemon
     */
    public static function deamon_stop() {
        log::add(__CLASS__, 'info', 'Stopping Daemon');
        jMQTTBase::deamon_stop(__CLASS__);
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
        if (exec(system::getCmdSudo() . "cat " . dirname(__FILE__) . "/../../resources/vendor/composer/installed.json 2>/dev/null | grep cboden/ratchet | wc -l") < 1) {
            log::add(__CLASS__, 'debug', 'dependancy_info : Composer Ratchet PHP package is missing');
            $return['state'] = 'nok';
        }

        if (exec(system::getCmdSudo() . system::get('cmd_check') . '-Ec "python3\-requests"') < 1) {
            log::add(__CLASS__, 'debug', 'dependancy_info : debian python3-requests package is missing');
            $return['state'] = 'nok';
        }
        if (exec(system::getCmdSudo() . 'pip3 list | grep -E "paho-mqtt|websocket\-client" | wc -l') < 2) {
            log::add(__CLASS__, 'debug', 'dependancy_info : python3 paho-mqtt or websocket-client library is missing');
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
                if ($hn == '' || $hn == $broker->getDefaultConfiguration(self::CONF_KEY_MQTT_ADDRESS) || substr($ip, 0, 4) == '127.') {
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

    ###################################################################################################################
    ##
    ##                   MQTT CLIENT RELATED METHODS
    ##
    ###################################################################################################################
    
    /**
     * Check all MQTT Clients (start them if needed)
     */
    public static function checkAllMqttClients() {
        $daemon_info = jMQTTBase::deamon_info(__CLASS__);
        if ($daemon_info['state'] == 'ok') {
            foreach(self::getBrokers() as $broker) {
                if ($broker->getIsEnable() && $broker->getMqttClientState() == jMQTTBase::MQTTCLIENT_NOK) {
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
        $daemon_info = jMQTTBase::deamon_info(__CLASS__);
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
            if ($return['state'] == jMQTTBase::MQTTCLIENT_NOK && $return['message'] == '')
                $return['message'] = __('Le Client MQTT est arrêté', __FILE__);
            elseif ($return['state'] == jMQTTBase::MQTTCLIENT_POK)
                $return['message'] = __('Le broker est OFFLINE', __FILE__);
        }

        return $return;
    }
    
    /**
     * Return MQTT Client state
     *   - jMQTTBase::MQTTCLIENT_OK: MQTT Client is running and mqtt broker is online
     *   - jMQTTBase::MQTTCLIENT_POK: MQTT Client is running but mqtt broker is offline
     *   - jMQTTBase::MQTTCLIENT_NOK: no cron exists or cron is not running
     * @return string ok or nok
     */
    public function getMqttClientState() {
        return jMQTTBase::get_mqtt_client_state(__CLASS__, $this->getId());
    }
    
    /**
     * Return hex color string depending state passed
     * @return string hex color
     */
    public static function getBrokerColorFromState($state) {
        switch ($state) {
            case jMQTTBase::MQTTCLIENT_OK:
                return '#96C927';
                break;
            case jMQTTBase::MQTTCLIENT_POK:
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
        $daemon_info = jMQTTBase::deamon_info(__CLASS__);
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
            $params['tlscafile']     = realpath(dirname(__FILE__) . '/../../' . jMQTTBase::PATH_CERTIFICATES . $params['tlscafile']);
        if ($params['tlsclicertfile'] != '')
            $params['tlsclicertfile'] = realpath(dirname(__FILE__).'/../../' . jMQTTBase::PATH_CERTIFICATES . $params['tlsclicertfile']);
        else
            $params['tlsclikeyfile'] = '';
        if ($params['tlsclikeyfile'] != '')
            $params['tlsclikeyfile'] = realpath(dirname(__FILE__) . '/../../' . jMQTTBase::PATH_CERTIFICATES . $params['tlsclikeyfile']);

        jMQTTBase::new_mqtt_client(__CLASS__, $this->getId(), $this->getMqttAddress(), $params);

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
        jMQTTBase::remove_mqtt_client(__CLASS__, $this->getId());
    }

    public static function on_daemon_connect($id) {
        $broker = self::getBrokerFromId(intval($id));
        $broker->sendMqttClientStateEvent();
    }
    public static function on_daemon_disconnect($id) {
        $broker = self::getBrokerFromId(intval($id));
        $broker->sendMqttClientStateEvent();
    }
    public static function on_mqtt_connect($id) {
        $broker = self::getBrokerFromId(intval($id));
        $broker->getMqttClientStatusCmd()->event(self::ONLINE);
        $broker->sendMqttClientStateEvent();
    }
    public static function on_mqtt_disconnect($id) {
        $broker = self::getBrokerFromId(intval($id));
        $statusCmd = $broker->getMqttClientStatusCmd();
        if ($statusCmd) $statusCmd->event(self::OFFLINE); //Need to check if statusCmd exists, because during Remove cmd are destroyed first by eqLogic::remove()
        $broker->sendMqttClientStateEvent();
        // if includeMode is enabled, disbale it
        if ($broker->getIncludeMode()) $broker->changeIncludeMode(0);
    }
    public static function on_mqtt_message($id, $topic, $payload, $qos, $retain) {
        $broker = self::getBrokerFromId(intval($id));
        $broker->brokerMessageCallback($topic, $payload);
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
            $eqpt = jMQTT::createEquipment($this, $msgTopicArray[0], ($msgTopic[0] == '/' ? '/' : '') . $msgTopicArray[0] . '/#');
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
                
                // Determine the name of the command.
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
                
                // Looking for cmd in the DB
                $cmds = jMQTTCmd::byEqLogicIdAndTopic($eqpt->getId(), $msgTopic, true);
                $jsonCmds = array();
                
                // if some cmd matches topic
                if (!is_null($cmds)) {

                    // Keep only info cmds
                    foreach($cmds as $k => $cmd) {
                        if($cmd->getType() == 'action') {
                            $this->log('debug', $eqpt->getName() . '|' . $cmd->getName() . ' is an action command: skip');
                            unset($cmds[$k]);
                        }
                    }

                    // Get list of JSON cmds
                    $jsonCmds = array_filter($cmds, function($cmd){
                        return $cmd->isJson();
                    });

                    // Finally Keep only non JSON in $cmds
                    $cmds = array_filter($cmds, function($cmd){
                        return !$cmd->isJson();
                    });
                }

                // If there is no cmd matching exactly with the topic (non JSON)
                if (is_null($cmds) || !count($cmds)) {
                    // Is auto add enabled
                    if ($eqpt->getAutoAddCmd()) {
                        if (is_null($cmds)) $cmds = [];

                        //Create a new cmd
                        $newCmd = jMQTTCmd::newCmd($eqpt, $cmdName, $msgTopic);
                        $newCmd->save();
                        $cmds[] = $newCmd;
                    }
                    else $this->log('debug', 'Command ' . $eqpt->getName() . '|' . $cmdName . ' not created as automatic command creation is disabled');
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
        
        if (is_bool($payload)) {
            // Fix #80
            // One can wonder why not encoding systematically the message?
            // Answer is that it does not work in some cases:
            //   * If payload is empty => "(null)" is sent instead of (null)
            //   * If payload contains ", they are backslashed \"
            $payload = json_encode($payload);
        }
        $payloadLogMsg = ($payload === '') ? '(null)' : $payload;
        $this->log('info', '<- ' . $eqName . '|' . $topic . ' ' . $payloadLogMsg);
        
        $this->log('debug', 'Publishing message ' . $topic . ' ' . $payload . ' (qos=' . $qos . ', retain=' . $retain . ')');

        if ($this->getBroker()->getMqttClientState() == jMQTTBase::MQTTCLIENT_OK) {
            jMQTTBase::publish_mqtt_message(__CLASS__, $this->getBrkId(), $topic, $payload, $qos, $retain);
            
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
        return $this->getMqttClientId() . '/' . jMQTT::CLIENT_STATUS;
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
     * It is stored as the logicalId
     * @return string
     */
    public function getTopic() {
        return $this->getLogicalId();
    }
    
    /**
     * Set this jMQTT object topic
     * It is stored as the logicalId
     * @var string $topic
     */
    public function setTopic($topic) {
        $this->setLogicalId($topic);
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
        $broker = jMQTT::byId($id);
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
