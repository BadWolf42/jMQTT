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

include_file('3rdparty', 'mosquitto_topic_matches_sub', 'php', 'jMQTT');
include_file('core', 'mqttApiRequest', 'class', 'jMQTT');
include_file('core', 'jMQTTCmd', 'class', 'jMQTT');

class jMQTT extends jMQTTBase {

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
    const CONF_KEY_MQTT_ID = 'mqttId';
    const CONF_KEY_MQTT_ADDRESS = 'mqttAddress';
    const CONF_KEY_MQTT_PORT = 'mqttPort';
    const CONF_KEY_MQTT_USER = 'mqttUser';
    const CONF_KEY_MQTT_PASS = 'mqttPass';
    const CONF_KEY_MQTT_INC_TOPIC = 'mqttIncTopic';
    const CONF_KEY_QOS = 'Qos';
    const CONF_KEY_AUTO_ADD_CMD = 'auto_add_cmd';
    const CONF_KEY_API = 'api';
    const CONF_KEY_LOGLEVEL = 'loglevel';
    
    const CONF_KEY_OLD = 'old';
    const CONF_KEY_NEW = 'new';

    const CACHE_DAEMON_CONNECTED = 'daemonConnected';
    const CACHE_MQTTCLIENT_CONNECTED = 'mqttClientConnected';
    
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
     * Possible value of $_post_data; to restart the MQTT Client.
     * @var integer
     */
    const POST_ACTION_RESTART_MQTTCLIENT = 1;
    
    /**
     * Possible value of $_post_data; set when the broker name has changed
     * @var integer
     */
    const POST_ACTION_BROKER_NAME_CHANGED = 2;
    
    /**
     * Possible value of $_post_data; set when the client id is changed
     * @var integer
     */
    const POST_ACTION_BROKER_CLIENT_ID_CHANGED = 4;
    
    /**
     * Data shared between preSave and postSave, preRemove and postRemove
     * @var jMQTT|jMQTT::POST_ACTION_RESTART_MQTTCLIENT|jMQTT::POST_ACTION_NEW_CLIENT_ID
     */
    private $_post_data;
    
    /**
     * Data shared between preSave and postSave
     * @var array values from preSave used for postSave actions
     */
    private $_preSaveInformations;

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
			} catch (Exception $e) {
				
			}
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

        //put temporary logicalId to format string to avoid cmd alert that not match cmd topic
        $this->setLogicalId($template['logicalId']);

        //import template
        $this->import($template, $_keepCmd);

        # complete eqpt topic
        $this->setLogicalId(sprintf($template['logicalId'], $_topic));
        $this->save(true); // direct save to avoid not mathcing topic alert

        # complete cmd topics
        foreach ($this->getCmd() as $cmd) {
            $cmd->setConfiguration('topic', sprintf($cmd->getConfiguration('topic'), $_topic));
            $cmd->save();
        }
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
     * @param string $type jMQTT type (either jMQTT::TYP_EQPT or jMQTT::TYP_BRK)
     * return new jMQTT object
     */
    public static function createEquipment($broker, $name, $topic, $type) {
        $eqpt = new jMQTT();
        $eqpt->setType($type);
        $eqpt->initEquipment($name, $topic, 1);
        
        if (is_object($broker)) {
            $broker->log('info', 'Create equipment ' . $name . ', topic=' . $topic . ', type=' . $type);
            $eqpt->setBrkId($broker->getId());
        }
        $eqpt->save();
        
        // NOTE: the status command is created in the postSave method
        
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
     * Initialize this equipment with the given data
     * @param string $name equipment name
     * @param string $topic subscription topic
     * @param int $isEnable whether or not the equipment is enable (0 if not present)
     */
    private function initEquipment($name, $topic, $isEnable=0) {
        log::add('jMQTT', 'debug', 'Initialize equipment ' . $name . ', topic=' . $topic);
        $this->setEqType_name('jMQTT');
        parent::setName($name);
        parent::setIsEnable($isEnable);
        parent::setLogicalId($topic);  // logical id is also modified by setTopic
        if ($this->getType() == self::TYP_BRK) {
            $this->setAutoAddCmd('0');
        }
        else {
            $this->setAutoAddCmd('1');
        }
        $this->setQos('1');
        
        if ($this->getType() == self::TYP_BRK) {
            config::save('log::level::' . $this->getMqttClientLogFile(), '{"100":"0","200":"0","300":"0","400":"0","1000":"0","default":"1"}', 'jMQTT');
        }
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

        // Clone commands, only action type commands
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
     * Unsubscribe topic ONLY if no other enabled eqpt linked to the same broker subscribes the same topic
     */
    public function unsubscribeEqLogicTopic($topic){
        if (empty($topic)) return;

        // If broker is disabled, don't need to send unsubscribe
        if(!$this->getBroker()->getIsEnable()) return;

        //Find eqLogic using the same topic (which is stored in logicalId)
        $eqLogics = eqLogic::byLogicalId($topic, __CLASS__, true);
        $count = 0;
        foreach ($eqLogics as $eqLogic) {
            // If it's attached to the same broker and enabled and it's not "me"
            if ($eqLogic->getBrkId() == $this->getBrkId() && $eqLogic->getIsEnable() && $eqLogic->getId() != $this->getId()) $count++;
        }

        //if there is no other eqLogic using the same topic, we can unsubscribe
        if (!$count) self::unsubscribe_mqtt_topic($this->getBrkId(), $topic);
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

            // --- New eqpt ---
            if (!isset($this->id)) {
            }
            // --- Existing eqpt ---
            else {

            }
        }
        // ------------------------ Normal eqpt ------------------------
        else{

            // --- New eqpt ---
            if (!isset($this->id)) {
                //TODO : Will be removed later by default values
                $this->setQos('1');
                $this->setAutoAddCmd('1');
            }
            // --- Existing eqpt ---
            else {

            }
        }


        // It's time to gather informations that will be used in postSave
        if (!isset($this->id)) $this->_preSaveInformations = null; // New eqpt => Nothing to collect
        else { // Existing eqpt

            // load eqLogic from DB
            $eqLogic = self::byId($this->getId());
            $this->_preSaveInformations = array(
                'name' => $eqLogic->getName(),
                'isEnable' => $eqLogic->getIsEnable(),
                self::CONF_KEY_LOGLEVEL => $eqLogic->getConf(self::CONF_KEY_LOGLEVEL),
                self::CONF_KEY_MQTT_ID => $eqLogic->getConf(self::CONF_KEY_MQTT_ID),
                self::CONF_KEY_MQTT_ADDRESS => $eqLogic->getConf(self::CONF_KEY_MQTT_ADDRESS),
                self::CONF_KEY_MQTT_PORT => $eqLogic->getConf(self::CONF_KEY_MQTT_PORT),
                self::CONF_KEY_MQTT_USER => $eqLogic->getConf(self::CONF_KEY_MQTT_USER),
                self::CONF_KEY_MQTT_PASS => $this->getConf(self::CONF_KEY_MQTT_PASS),
                self::CONF_KEY_MQTT_INC_TOPIC => $eqLogic->getConf(self::CONF_KEY_MQTT_INC_TOPIC),
                self::CONF_KEY_QOS => $eqLogic->getConf(self::CONF_KEY_QOS),
                self::CONF_KEY_API => $eqLogic->getConf(self::CONF_KEY_API),
                'topic' => $eqLogic->getTopic()
            );
        }
    }

    /**
     * postSave apply changes to MqttClient and log
     */
    public function postSave() {

        // ------------------------ Broker eqpt ------------------------
        if ($this->getType() == self::TYP_BRK) {

            // --- New eqpt ---
            if (is_null($this->_preSaveInformations)) {

                // Create Status cmd
                $this->createMqttClientStatusCmd();

                // Enabled => Start MqttClient
                if ($this->getIsEnable()) $this->startMqttClient();
            }
            // --- Existing eqpt ---
            else {

                $stopped = false;
                $startRequested = false;

                // isEnable changed
                if ($this->_preSaveInformations['isEnable'] != $this->getIsEnable()) {
                    if ($this->getIsEnable()) $startNeeded = true; //If nothing happens in between, it will be restarted
                    else {
                        if (!$stopped) {
                            $this->stopMqttClient();
                            $stopped = true;
                        }
                    }
                }

                // LogLevel change
                if ($this->_preSaveInformations[self::CONF_KEY_LOGLEVEL] != $this->getConf(self::CONF_KEY_LOGLEVEL)) {
                    config::save('log::level::' . $this->getMqttClientLogFile(), $this->getConf(self::CONF_KEY_LOGLEVEL), __CLASS__);

                    //TODO Verify if Stop/Start is required
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

                    //TODO Verify if Stop/Start is required
                }

                //TODO Later: IncludeModeTopic and api enabled need to be managed differently
                // 'mqttAddress', 'mqttPort', 'mqttUser', 'mqttPass', 'mqttIncTopic', 'api' changed
                if ($this->_preSaveInformations[self::CONF_KEY_MQTT_ADDRESS] != $this->getConf(self::CONF_KEY_MQTT_ADDRESS) ||
                    $this->_preSaveInformations[self::CONF_KEY_MQTT_PORT] != $this->getConf(self::CONF_KEY_MQTT_PORT) ||
                    $this->_preSaveInformations[self::CONF_KEY_MQTT_USER] != $this->getConf(self::CONF_KEY_MQTT_USER) ||
                    $this->_preSaveInformations[self::CONF_KEY_MQTT_PASS] != $this->getConf(self::CONF_KEY_MQTT_PASS) ||
                    $this->_preSaveInformations[self::CONF_KEY_MQTT_INC_TOPIC] != $this->getConf(self::CONF_KEY_MQTT_INC_TOPIC) ||
                    $this->_preSaveInformations[self::CONF_KEY_API] != $this->getConf(self::CONF_KEY_API))
                {
                    if (!$stopped) {
                        $this->stopMqttClient();
                        $stopped = true;
                    }
                    $startRequested = true;
                }

                // ClientId changed
                if ($this->_preSaveInformations[self::CONF_KEY_MQTT_ID] != $this->getConf(self::CONF_KEY_MQTT_ID)) {

                    // Just Need to restart MqttClient (it needs to know about the new willTopic aka StatusCmdTopic here)
                    // (jMQTTCmd logic will fix values in cmd->save done after eqLogic->postSave)
                    if (!$stopped) {
                        $this->stopMqttClient();
                        $stopped = true;
                    }
                    $startRequested = true;
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
                
                // Enabled & Topic => subscribe
                if ($this->getIsEnable() && $this->getTopic() != '') self::subscribe_mqtt_topic($this->getBrkId(), $this->getTopic(), $this->getQos());
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
                            $this->unsubscribeEqLogicTopic($this->_preSaveInformations['topic']);
                            $unsubscribed = true;
                        }
                    }
                }

                // topic changed
                if ($this->_preSaveInformations['topic'] != $this->getTopic()) {
                    if(!$unsubscribed){
                        //Unsubscribed previous topic
                        $this->unsubscribeEqLogicTopic($this->_preSaveInformations['topic']);
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
                    self::subscribe_mqtt_topic($this->getBrkId(), $this->getTopic(), $this->getQos());
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
            $this->setIsEnable(0);
            $this->save();

            // Wait up to 10s for MqttClient stopped
            for ($i=0; $i < 40; $i++) { 
                if ($this->getMqttClientState() == self::MQTTCLIENT_NOK) break;
                usleep(250000);
            }

            // Disable all equipments attached to the broker
            foreach ($this->byBrkId() as $eqpt) {
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

            // Remove all equipments attached to the removed broker
            foreach ($this->byBrkId() as $eqpt) {
                $eqpt->remove();
            }
        }
        // ------------------------ Normal eqpt ------------------------
        else {
            //If eqpt were enabled, just need to unsubscribe
            if($this->getIsEnable()) $this->unsubscribeEqLogicTopic($this->getTopic());
        }
    }
    
    /**
     * Remove all equipments of the given broker
     * Called by a specific cron (see mqttApiRequest::processRequest)
     * @param string[] $option $option[id]=broker id
     */
    public static function removeAllEqpts($option) {
        
        // let the daemon thread which run this cron action terminate the current processing
        // before stopping the daemon
        usleep(500000);
        
        // Disable first the broker to avoid during removal of the equipments to restart the MQTT Client
        $broker = self::getBrokerFromId($option['id']);
        $broker->setIsEnable(0);
        $broker->save();
        
        // remove all equipments attached to the broker
        foreach ($broker->byBrkId() as $eqpt) {
            if ($broker->getId() != $eqpt->getId())
                $eqpt->remove();
        }
        
        // Re-enable the broker
        $broker->setIsEnable(1);
        $broker->save();
    }
    
    public static function health() {
        $return = array();
        foreach(self::getBrokers() as $broker) {
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
     * callback to start daemon
     */
    public static function deamon_start() {
        log::add(__CLASS__, 'info', 'démarre le daemon');
        parent::deamon_start();
        self::checkAllMqttClients();
    }
    
    /**
     * callback to stop daemon
     */
    public static function deamon_stop() {
        log::add(__CLASS__, 'info', 'arrête le daemon');
        parent::deamon_stop();
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
                if ($broker->getMqttAddress() == self::getDefaultConfiguration(self::CONF_KEY_MQTT_ADDRESS)) {
                    $brokerexists = true;
                    echo "Broker eqpt already exists\n";
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
                $broker->initEquipment($brokername, self::getDefaultConfiguration(self::CONF_KEY_MQTT_ID).'/#', '1');
                $broker->save();
                $broker->setBrkId($broker->getId());
                $broker->save();
                $broker->createMqttClientStatusCmd();
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
        $daemon_info = self::deamon_info();
        if ($daemon_info['state'] == 'ok') {
            foreach(self::getBrokers() as $broker) {
                if ($broker->getIsEnable() && $broker->getMqttClientState() == "nok") {
                    try {
                        log::add(__CLASS__, 'info', 'Redémarrage du client MQTT pour ' . $broker->getName());
                        $broker->startMqttClient();
                    }
                    catch (Exception $e) {}
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
        $daemon_info = $this->deamon_info();
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
        if ($daemon_info['state'] == 'ok') {
            if ($return['state'] == self::MQTTCLIENT_NOK && $return['message'] == '')
                $return['message'] = __('Le Client MQTT est arrêté', __FILE__);
            elseif ($return['state'] == self::MQTTCLIENT_POK)
                $return['message'] = __('Le broker est OFFLINE', __FILE__);
        }

        return $return;
    }
    
    /**
     * Return whether or not the MQTT Client shall be restarted after a configuration change that impacts its processing.
     * If $isIncludeMode is true and the broker is in automatic inclusion mode returns false. Otherwise output depends
     * on the launchable status of the broker.
     * 
     * Shall be called for a broker only.
     * 
     * @param bool $isIncludeMode whether or not the automatic inclusion mode of the broker shall be taken into
     *              account in the assessement.
     * @return boolean
     */
    public function isMqttClientToBeRestarted($isIncludeMode=false) {
        if ($isIncludeMode && $this->getBroker()->isIncludeMode()) {
            return false;
        }
        else {
            $info = $this->getMqttClientInfo();
            return $info['launchable'] == 'ok' ? true : false;
        }
    }
    
    /**
     * Return MQTT Client state
     *   - self::MQTTCLIENT_OK: MQTT Client is running and mqtt broker is online
     *   - self::MQTTCLIENT_POK: MQTT Client is running but mqtt broker is offline
     *   - self::MQTTCLIENT_NOK: no cron exists or cron is not running
     * @return string ok or nok
     */
    public function getMqttClientState() {
        if ($this->getCache(self::CACHE_DAEMON_CONNECTED, false)) {
            if ($this->getCache(self::CACHE_MQTTCLIENT_CONNECTED, false)) {
                $return = self::MQTTCLIENT_OK;
            }
            else {
                $return  = self::MQTTCLIENT_POK;
            }
        }
        else
            $return = self::MQTTCLIENT_NOK;
        
        return $return;
    }
    
    /**
     * Start the MQTT Client of this broker if it is launchable
     * @throws Exception if the MQTT Client is not launchable
     */
    public function startMqttClient() {
        $mqttclient_info = $this->getMqttClientInfo();
        if ($mqttclient_info['launchable'] != 'ok') {
            throw new Exception(__('Le client MQTT n\'est pas démarrable. Veuillez vérifier la configuration', __FILE__));
        }

        //TODO : check if it's OK
        $this->createMqttClientStatusCmd();

        $this->log('info', 'démarre le client MQTT ');
        $this->setLastMqttClientLaunchTime();
        $this->sendMqttClientStateEvent();
        self::new_mqtt_client($this->getId(), $this->getMqttAddress(), $this->getMqttPort(), $this->getMqttId(), $this->getMqttClientStatusTopic(), $this->getConf(self::CONF_KEY_MQTT_USER), $this->getConf(self::CONF_KEY_MQTT_PASS));

        
        // Subscribe to all necessary topics
        if ($this->isIncludeMode()) { // auto inclusion mode
            $topic = $this->getConf(self::CONF_KEY_MQTT_INC_TOPIC);
            // Subscribe to topic (root by default)
            self::subscribe_mqtt_topic($this->getId(), $topic, 1);
            $this->log('debug', 'Subscribe to Inclusion Mode topic "' . $topic . '" with Qos=1');
            
            if ($this->isApiEnable()) {
                if (! mosquitto_topic_matches_sub($topic, $this->getMqttApiTopic())) {
                    $this->log('info', 'Subscribes to the API topic "' . $this->getMqttApiTopic() . '"');
                    self::subscribe_mqtt_topic($this->getId(), $this->getMqttApiTopic(), '1');
                }
                else
                    $this->log('info', 'No need to subscribe to the API topic "' . $this->getMqttApiTopic() . '"');
            }
            else {
                $this->log('info', 'API is disable');
            }
        }
        else { // manual inclusion mode
            // Loop on all equipments and subscribe
            foreach ($this->byBrkId() as $mqtt) {
                if ($mqtt->getIsEnable()) {
                    $topic = $mqtt->getTopic();
                    $qos = (int) $mqtt->getQos();
                    if (empty($topic)) {
                        $this->log('info', 'Equipment ' . $mqtt->getName() . ': no subscription (empty topic)');
                    }
                    else {
                        $this->log('info', 'Equipment ' . $mqtt->getName() . ': subscribes to "' . $topic . '" with Qos=' . $qos);
                        self::subscribe_mqtt_topic($this->getId(), $topic, $qos);
                    }
                }
            }
            
            if ($this->isApiEnable()) {
                $this->log('info', 'Subscribes to the API topic "' . $this->getMqttApiTopic() . '"');
                self::subscribe_mqtt_topic($this->getId(), $this->getMqttApiTopic(), '1');
            } else {
                $this->log('info', 'API is disable');
            }
        }
    }
    
    /**
     * Stop the MQTT Client of this broker type object
     */
    public function stopMqttClient() {
        $this->log('info', 'arrête le client MQTT');
        self::remove_mqtt_client($this->getId());
    
        $cmd = $this->getMqttClientStatusCmd();
        // Status cmd may not exist on object removal for instance
        if (is_object($cmd)) {
            $cmd->event(self::OFFLINE);
        }
        
        $this->sendMqttClientStateEvent();
    }

    public static function on_daemon_connect($id) {
        $broker = self::getBrokerFromId(intval($id));
        $broker->setCache(self::CACHE_DAEMON_CONNECTED, true);
    }
    public static function on_daemon_disconnect($id) {
        $broker = self::getBrokerFromId(intval($id));
        $broker->setCache(self::CACHE_DAEMON_CONNECTED, false);
        // if daemon is disconnected from Jeedom, consider the MQTT Client as disconnected too
        self::on_mqtt_disconnect($id);
    }
    public static function on_mqtt_connect($id) {
        $broker = self::getBrokerFromId(intval($id));
        $broker->setCache(self::CACHE_MQTTCLIENT_CONNECTED, true);
        $broker->getMqttClientStatusCmd()->event(self::ONLINE);
        $broker->sendMqttClientStateEvent();
    }
    public static function on_mqtt_disconnect($id) {
        $broker = self::getBrokerFromId(intval($id));
        $broker->setCache(self::CACHE_MQTTCLIENT_CONNECTED, false);
        $broker->getMqttClientStatusCmd()->event(self::OFFLINE);
        $broker->sendMqttClientStateEvent();
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
        
        // In case of topic starting with /, remove the starting character (fix Issue #7)
        // And set the topic prefix (fix issue #15)
        if ($msgTopic[0] === '/') {
            $this->log('debug', 'Message topic starts with /');
            $topicPrefix = '/';
            $topicContent = substr($msgTopic, 1);
        }
        else {
            $topicPrefix = '';
            $topicContent = $msgTopic;
        }
        
        // Return in case of invalid topic
        if (empty($topicContent) || ! jMQTTCmd::isConfigurationValid($msgTopic)) {
            if (! empty($topicContent)) {
                $msgTopic = strtoupper(bin2hex($msgTopic));
            }
            $this->log('warning', 'Message skipped: "' . $msgTopic . '" is not a valid topic');
            return;
        }
        
        // Return in case of invalid payload (only ascii payload are supported) - fix issue #46
        if (! jMQTTCmd::isConfigurationValid($msgValue)) {
            $this->log('warning',
                'Message skipped: payload ' . strtoupper(bin2hex($msgValue)) . ' is not valid for topic ' . $msgTopic);
            return;
        }
        
        $this->log('debug', 'Payload ' . $msgValue . ' for topic ' . $msgTopic);
        
        // If this is the API topic, process the request
        // Do not return, which means that the message can be registered
        if ($msgTopic == $this->getMqttApiTopic()) {
            $this->processApiRequest($msgValue);
        }
        
        $msgTopicArray = explode("/", $topicContent);
        
        // Loop on jMQTT equipments and get ones that subscribed to the current message
        $elogics = array();
        foreach (self::byBrkId() as $eqpt) {
            if (mosquitto_topic_matches_sub($eqpt->getTopic(), $msgTopic)) {
                $elogics[] = $eqpt;
            }
        }
        
        // If no equipment listening to the current message is found and the
        // automatic inclusion mode is active => create a new equipment
        // subscribing to all sub-topics starting with the first topic of the
        // current message
        if (empty($elogics) && $this->isIncludeMode()) {
            $eqpt = jMQTT::createEquipment($this, $msgTopicArray[0], $topicPrefix . $msgTopicArray[0] . '/#', self::TYP_EQPT);
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
                
                /** @var array[jMQTTCmd] $cmdlogics array of the commands related to the current message */
                $cmdlogics = jMQTTCmd::byEqLogicIdAndTopic($eqpt->getId(), $msgTopic, true);
                
                // If the command associated to the topic has not been found, try to create one
                if (is_null($cmdlogics) || $cmdlogics[0]->getTopic() != $msgTopic) {
                    if ($eqpt->getAutoAddCmd()) {
                        if (is_null($cmdlogics)) {
                            $cmdlogics = [];
                        }
                        array_unshift($cmdlogics, jMQTTCmd::newCmd($eqpt, $cmdName, $msgTopic));
                        $cmdlogics[0]->save();
                    }
                    else {
                        $this->log('debug',
                            'Command ' . $eqpt->getName() . '|' . $cmdName .
                            ' not created as automatic command creation is disabled');
                    }
                }
                
                if (is_array($cmdlogics)) {
                    
                    // If the found command is an action command, skip
                    if ($cmdlogics[0]->getType() == 'action') {
                        $this->log('debug',
                            $eqpt->getName() . '|' . $cmdlogics[0]->getName() . ' is an action command: skip');
                        continue;
                    }
                    
                    // Update the command value
                    if ($cmdlogics[0]->getTopic() == $msgTopic) {
                        $cmdlogics[0]->updateCmdValue($msgValue);
                        $i0 = 1;
                    }
                    else {
                        $i0 = 0;
                    }
                        
                    // Update JSON derived commands if any
                    if (count($cmdlogics) > 1) {
                        $jsonArray = $cmdlogics[0]->decodeJsonMsg($msgValue);
                        if (isset($jsonArray)) {
                            for ($i=$i0 ; $i<count($cmdlogics) ; $i++) {
                                $cmdlogics[$i]->updateJsonCmdValue($jsonArray);
                            }
                        }
                    }
                                        
                    // On reception of a the broker status topic, generate an state event
                    if ($this->getId() == $eqpt->getId() && $cmdlogics[0]->getTopic() == $this->getMqttClientStatusTopic()) {
                        $this->sendMqttClientStateEvent();
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
    public function publishMosquitto($eqName, $topic, $payload, $qos, $retain) {
        
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
        
        $this->log('debug', 'Publication du message ' . $topic . ' ' . $payload . ' (qos=' . $qos . ', retain=' . $retain . ')');

        self::publish_mqtt_message($this->getBrkId(), $topic, $payload, $qos, $retain);
        
        $d = date('Y-m-d H:i:s');
        $this->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
        // if ($this->getType() == self::TYP_EQPT) {
        //     $this->getBroker()->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
        // }
        
        $this->log('debug', 'Message publié');
    }

    /**
     * Return the MQTT topic name of this broker status command
     * @return string broker status topic name
     */
    public function getMqttClientStatusTopic()  {
        return self::_getMqttClientStatusTopic($this->getMqttId());
    }
    
    /**
     * Return the MQTT topic name of the status command having $mqttId as MQTT connection id.
     * @param string $mqttId
     */
    private static function _getMqttClientStatusTopic($mqttId) {
        return $mqttId . '/' . jMQTT::CLIENT_STATUS;
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
        } catch (Exception $e) {}
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
        log::add($this->getMqttClientLogFile(), $level, $msg);
    }
    
    /**
     * Return whether or not the MQTT API is enable
     * return boolean
     */
    public function isApiEnable() {
        return $this->getConf(self::CONF_KEY_API) == self::API_ENABLE ? TRUE : FALSE;
    }
    
    private function getConf($_key) {
        return $this->getConfiguration($_key, self::getDefaultConfiguration($_key));
    }
    
    private static function getDefaultConfiguration($_key) {
        $defValues = array(
            self::CONF_KEY_MQTT_ADDRESS => 'localhost',
            self::CONF_KEY_MQTT_PORT => '1883',
            self::CONF_KEY_MQTT_ID => 'jeedom',
            self::CONF_KEY_QOS => '1',
            self::CONF_KEY_AUTO_ADD_CMD, '1',
            self::CONF_KEY_MQTT_INC_TOPIC => '#',
            self::CONF_KEY_API => self::API_DISABLE,
            self::CONF_KEY_BRK_ID => -1
        );
        
        return array_key_exists($_key, $defValues) ? $defValues[$_key] : '';
    }
    
    /**
     * Set the log level
     * Called when saving a broker eqLogic
     * If log level is changed, save the new value and restart the MQTT Client
     * @param string $level
     */
    public function setLogLevel($log_level) {
        $this->setConfiguration(self::CONF_KEY_LOGLEVEL, reset(json_decode($log_level, true)));
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
    public function getMqttId() {
        return $this->getConf(self::CONF_KEY_MQTT_ID);
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
     * Get this jMQTT object broker topic
     * Built from the MQTT id used by the broker client to connect to the broker or from the
     * given mqtt id if provided.
     * @var string $mqttId
     * @return string
     */
    public function getBrokerTopic($mqttId='') {
        if ($mqttId == '') {
            $mqttId = $this->getMqttId();
        }
        
        return $mqttId . '/#';
    }

    /**
     * Get the broker object attached to this jMQTT object.
     * Broker is cached for optimisation.
     * @return jMQTT
     * @throws Exception if the broker is not found
     */
    public function getBroker() {
        if (! isset($this->_broker)) {
            if ($this->getType() == self::TYP_BRK)
                $this->_broker = $this;
            else
                $this->_broker = self::getBrokerFromId($this->getBrkId());
        }
        return $this->_broker;
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
     * Return all jMQTT objects having the same broker id as this object
     * Note: this object is also returned
     * @return jMQTT[]
     */
    public function byBrkId() {
        $brkId = json_encode(array('brkId' => $this->getBrkId()));
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
        return $this->getMqttId() . '/' . self::API_TOPIC;
    }

    
    /**
     * Disable the equipment automatic inclusion mode and inform the desktop page
     * @param string[] $option $option[id]=broker id
     */
    public static function disableIncludeMode($option) {
        $broker = self::getBrokerFromId($option['id']);
        $broker->setIncludeMode(0);

        // Restart the MQTT Client
        if ($broker->isMqttClientToBeRestarted()) {
            $broker->startMqttClient();
        }
    }

    /**
     * Manage the include_mode of this broker object
     * Called by ajax when the button is pressed by the user
     * @param int $mode 0 or 1
     */
    public function changeIncludeMode($mode) {

        // Update the include_mode configuration parameter
        $this->setIncludeMode($mode);
        
        // A cron process is used to reset the automatic mode after a delay

        // If the cron process is already defined, remove it
        $cron = cron::byClassAndFunction(__CLASS__, 'disableIncludeMode', array('id' => $this->getId()));
        if (is_object($cron)) {
            $cron->remove();
        }

        // Create and configure the cron process when automatic mode is enabled
        if ($mode == 1) {
            $cron = new cron();
            $cron->setClass(__CLASS__);
            $cron->setOption(array('id' => $this->getId()));
            $cron->setFunction('disableIncludeMode');
            // Add 150s => actual delay between 2 and 3min
            $cron->setSchedule(cron::convertDateToCron(strtotime('now') + 150));
            $cron->setOnce(1);
            $cron->save();
        }

        // Restart the MQTT deamon to manage topic subscription
        $this->startMqttClient();
    }
    
    /**
     * Set the include mode of this broker object
     * @param int $mode 0 or 1
     */
    public function setIncludeMode($mode) {
        $this->setCache('include_mode', $mode);
        $this->log('info', ($mode ? 'active' : 'désactive') . " le mode d'inclusion automatique");
        if (! $mode) {
            // Advise the desktop page (jMQTT.js) that the inclusion mode is disabled
            event::add('jMQTT::disableIncludeMode', array('brkId' => $this->getId()));
        }
    }
    
    /**
     * Return this broker object include mode parameter
     * @return int 0 or 1
     */
    public function getIncludeMode() {
        return $this->getCache('include_mode', 0);
    }
    
    /**
     * Return whether or not the include mode of this broker object is active
     * @return bool
     */
    private function isIncludeMode() {
        return ($this->getIncludeMode() == 1);
    }

    /**
     * To log the traceback (utility function for debug purpose)
     */
    private static function log_backtrace() {
        $e = new Exception();
        $s = print_r(str_replace('/var/www/html', '', $e->getTraceAsString()), true);
        log::add(__CLASS__, 'debug', $s);
    }
}
