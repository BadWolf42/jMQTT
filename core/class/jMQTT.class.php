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

include_file('core', 'mqttApiRequest', 'class', 'jMQTT');
include_file('core', 'jMQTTCmd', 'class', 'jMQTT');

// @TODO : création broker -> choisir nom de connexion unique
class jMQTT extends eqLogic {

    const API_TOPIC = 'api';
    const API_ENABLE = 'enable';
    const API_DISABLE = 'disable';
    
    const CLIENT_STATUS = 'status';
    const OFFLINE = 'offline';
    const ONLINE = 'online';
    
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
     * Name of the plugin conf parameter storing the last successfull connexion to the broker (linux time, in s)
     * @var string
     */
    const LAST_CONNECT_TIME = 'lastClientConnectTime';
    
    /**
     * Possible value of $_post_data; to restart the daemon.
     * @var integer
     */
    const POST_ACTION_NONE = 0;
    
    /**
     * Possible value of $_post_data; to restart the daemon.
     * @var integer
     */
    const POST_ACTION_RESTART_DAEMON = 1;
    
    /**
     * Possible value of $_post_data; to restart the daemon with a new client id
     * @var integer
     */
    const POST_ACTION_NEW_CLIENT_ID = 2;
    
    /**
     * Data shared between preSave and postSave, preRemove and postRemove
     * @var jMQTT|jMQTT::POST_ACTION_RESTART_DAEMON|jMQTT::POST_ACTION_RESTART_DAEMON|jMQTT::POST_ACTION_NEW_CLIENT_ID
     */
    private $_post_data;
    
    /**
     * MQTT client.
     * Set in the daemon method; it is only visible from functions
     * that are executed on the same thread as the daemon method.
     * @var Mosquitto\Client $_client
     */
    private $_client;

    /**
     * Log file related to this broker.
     * Set in the daemon method; it is only visible from functions
     * that are executed on the same thread as the daemon method.
     * @var Mosquitto\Client $_client
     */
    private $_log;
    
    /**
     * @var string Dependancy installation log file
     */
    private static $_depLogFile;

    /**
     * @var string Dependancy installation progress value log file
     */
    private static $_depProgressFile;
    
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
        $eqpt->initEquipment($name, $topic);
        
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
     * Initialize this equipment with the given data
     * @param string $name equipment name
     * @param string $topic subscription topic
     */
    private function initEquipment($name, $topic) {
        log::add('jMQTT', 'debug', 'Initialize equipment ' . $name . ', topic=' . $topic);
        $this->setEqType_name('jMQTT');
        $this->setName($name);
        
        // Topic is memorized as the LogicalId
        $this->setLogicalId($topic);
        $this->setTopic($topic);
        $this->setAutoAddCmd('1');
        $this->setQos('1');
        $this->initPreviousConfiguration();
    }
    
    private function initPreviousConfiguration() {
        $this->setPrevQos($this->getQos());
        $this->setPrevIsEnable($this->getIsEnable());
        if ($this->getType() == self::TYP_BRK) {
            $this->setPrevMqttId($this->getMqttId());
        }
    }

    /**
     * Overload the equipment copy method
     * All information are copied but: suscribed topic (left empty), enable status (left disabled) and
     * information commands.
     * @param string $_name new equipment name
     */
    public function copy($_name) {

        log::add('jMQTT', 'info', 'Copying equipment ' . $this->getName() . ' as ' . $_name);

        // Clone the equipment and change properties that shall be changed
        // . new id will be given at saving
        // . suscribing topic let empty to force the user to change it
        // . remove commands: they are defined at the next step (as done in the parent method)
        /** @var jMQTT $eqLogicCopy */
        $eqLogicCopy = clone $this;
        $eqLogicCopy->setId('');
        $eqLogicCopy->setName($_name);
        $eqLogicCopy->setIsEnable(0);
        $eqLogicCopy->setLogicalId('');
        $eqLogicCopy->initPreviousConfiguration();
        $eqLogicCopy->setTopic('');
        foreach ($eqLogicCopy->getCmd() as $cmd) {
            $cmd->remove();
        }
        $eqLogicCopy->save();

        // Clone commands, only action type commands
        foreach ($this->getCmd() as $cmd) {
            if ($cmd->getType() == 'action') {
                $cmdCopy = clone $cmd;
                $cmdCopy->setId('');
                $cmdCopy->setEqLogic_id($eqLogicCopy->getId());
                $cmdCopy->save();
                log::add('jMQTT', 'info', 'Cloning action command "' . $cmd->getName());
            }
        }

        return $eqLogicCopy;
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
        $type = json_encode(array('type' => self::TYP_EQPT));
        /** @var jMQTT[] $non_brokers */
        $non_brokers = self::byTypeAndSearhConfiguration(jMQTT::class, substr($type, 1, -1));
        $returns = array();
        foreach ($non_brokers as $non_broker) {
            $returns[$non_broker->getBrkId()][] = $non_broker;
        }
        return $returns;
    }
    
    /**
     * Overload preSave to manage changes in subscription parameters to the MQTT broker
     */
    public function preSave() {

        // Initialise the equipment at creation if not already initialized (this is the case 
        // when equipment is created manually from the user interface)
        // FIXME: what is the reason for the logicalId test?
        if (!isset($this->id) && $this->logicalId == '') {
            $this->initEquipment($this->getName(), $this->getType() == self::TYP_BRK ? $this->getBrokerTopic() : '');
        }
        
        $this->_post_data = array('action' => self::POST_ACTION_NONE);
        
        // For a broker, check if the MQTT client has changed. It yes:
        //   * Set logicalId and the topic
        //   * log file is built from the old MQTT client id
        //   * set _post_data to restart the daemon in postAjax
        if ($this->getType() == self::TYP_BRK) {
            $prevMqttId = $this->getPrevMqttId();
            $mqttId = $this->getMqttId();
            if ($prevMqttId != $mqttId) {
                $this->setPrevMqttId($mqttId);
                $topic = $this->getBrokerTopic();
                $this->setTopic($topic);
                $this->setLogicalId($topic);
                $this->_log = 'jMQTT_' . $prevMqttId;
                $this->_post_data['action'] = self::POST_ACTION_NEW_CLIENT_ID;
                $this->_post_data['prevMqttId'] = $prevMqttId;
                $this->_post_data['mqttId'] = $mqttId;
                $this->stopDaemon();
                return;
            }
        }
        
        // Check if MQTT subscription parameters have changed for this equipment
        // Applies to the manual inclusion mode only as in automatic mode, # is suscribed (i.e. all topics)
        if ($this->getBrkId() >= 0 && ! self::getBroker($this->getBrkId())->isIncludeMode()) {  // manual inclusion mode

            $prevTopic    = $this->getLogicalId();
            $topic        = $this->getTopic();
            $prevQos      = $this->getPrevQos();
            $qos          = $this->getQos();
            $prevIsActive = $this->getPrevIsEnable();
            $isActive     = $this->getIsEnable();

            // Subscription topic
            if ($prevTopic != $topic) {
                $this->log('debug', $this->getName() . '.preSave: prevTopic=' . $prevTopic .
                    ', topic=' . $topic);
                $this->setLogicalId($topic);
                $this->_post_data['action'] = self::POST_ACTION_RESTART_DAEMON;
            }

            // Quality of service
            if ($qos != $prevQos) {
                $this->log('debug', $this->getName() . '.preSave: prevQos=' . $prevQos . ', qos=' . $qos);
                $this->setPrevQos($qos);
                $this->_post_data['action'] = self::POST_ACTION_RESTART_DAEMON;
            }

            // Equipment enable status
            if ($isActive != $prevIsActive) {
                $this->log('debug',
                    $this->getName() . '.preSave: prevIsActive=' . $prevIsActive . ', isActive=' . $isActive);
                $this->setPrevIsEnable($isActive);
                if ($this->getType() == self::TYP_BRK && ! $isActive)
                    $this->stopDaemon();
                else
                    $this->_post_data['action'] = self::POST_ACTION_RESTART_DAEMON;
            }
        }        
    }

    /**
     * To log the traceback (utility function for debug purpose)
     */
    private static function log_backtrace() {
        $e = new Exception();
        $s = print_r(str_replace('/var/www/html', '', $e->getTraceAsString()), true);
        log::add('jMQTT', 'debug', $s);
    }

    /**
     * To remove all equipments (for test purpose ONLY, should never be used!)
     */
    private static function removeAll() {
        foreach (eqLogic::byType('jMQTT', false) as $eqpt) {
            $eqpt->remove();
        }
    }

    /**
     * postSave callback to restart the deamon when deemed necessary (see also preSave)
     */
    public function postSave() {
        if ($this->_post_data['action'] == self::POST_ACTION_RESTART_DAEMON) {
            $this->log('debug', 'postSave: restart daemon, current pid is ' . getmypid());
            self::getBroker($this->getBrkId())->startDaemon(true);
        }
    }
    
    /**
     * postAjax callback:
     *   - On broker MQTT client id modification:
     *     . Update command topics
     *     . Rename log file
     *     . Start daemon (which was stopped on preSave 

     *   - At broker equipment creation:
     *     . create the status command of a broker
     *     . define brkId of a broker
     */
    public function postAjax() {
        
        // Done first (before creation of MQTT client status cmd below)
        // Done in postAjax and not in postSave as commands coming from the MMI are saved between
        // postSave and postAjax.
        if ($this->_post_data['action'] ==  self::POST_ACTION_NEW_CLIENT_ID) {
            $this->log('debug', 'postSave: new MQTT client id (' . $this->getMqttId() . ') restart daemon, current pid is ' . getmypid());
            
            // Commands of the equipment are modified to change the subscription topic
            /** @var jMQTTCmd[] $cmds */
            $cmds = cmd::byEqLogicId($this->getId());
            foreach ($cmds as $cmd) {
                if (strpos($this->_post_data['prevMqttId'], $cmd->getTopic()) == 0) {
                    $cmd->setTopic(str_replace($this->_post_data['prevMqttId'], $this->_post_data['mqttId'], $cmd->getTopic()));
                    $cmd->save();
                }
            }
            
            rename(log::getPathToLog($this->_log), log::getPathToLog($this->getDaemonLogFile()));
            unset($this->_log);
            
            if ($this->getIsEnable()) {
                $this->startDaemon();
            }
        }
        
        if ($this->getType() == self::TYP_BRK) {
            if (! is_object($this->getMqttClientStatusCmd())) {
                jMQTTCmd::newCmd($this, self::CLIENT_STATUS, $this->getMqttClientStatusTopic())->save();
            }
            if ($this->getBrkId() < 0) {
                $this->setBrkId($this->getId());
                $this->save(true);
            }
        }        
    }

    /**
     * preRemove method to check if the daemon shall be restarted
     */
    public function preRemove() {
        $this->log('info', 'Removing equipment ' . $this->getName());
        $this->_post_data = null;
        if ($this->getType() == self::TYP_BRK) {
            $this->stopDaemon();
            $cron = $this->getDaemonCron();
            if (is_object($cron)) {
                $cron->remove(true);
            }
            // suppress the log file
            if (file_exists(log::getPathToLog($this->getDaemonLogFile()))) {
                unlink(log::getPathToLog($this->getDaemonLogFile()));
            }
            // remove all equipments attached to the broker
            foreach ($this->byBrkId() as $eqpt) {
                $eqpt->remove();
            }
        }
        else {
            $broker = self::getBroker($this->getBrkId());
            if (! $broker->isIncludeMode()) {
                $this->_post_data = $broker;
            }
        }
    }

    /**
     * postRemove callback to restart the deamon when deemed necessary (see also preRemove)
     */
    public function postRemove() {
        if (is_object($this->_post_data)) {
            $this->_post_data->log('debug', 'postRemove: restart daemon');
            $this->startDaemon(true);
        }
    }

    ###################################################################################################################
    ##
    ##                   PLUGIN RELATED METHODS
    ##
    ###################################################################################################################
    
    /**
     * cron callback
     * check daemons are started
     */
    public function cron() {
        //log::add('jMQTT', 'info', 'démarre le plugin');
        self::checkAllDaemons();
    }
    
    /**
     * callback to start this plugin
     */
    public function start() {
        log::add('jMQTT', 'info', 'démarre le plugin');
        self::checkAllDaemons();
    }
    
    /**
     * callback to stop this plugin
     */
    public function stop() {
        log::add('jMQTT', 'info', 'arrête le plugin');
        foreach(self::getBrokers() as $broker) {
            $broker->stopDaemon();
        }
    }
    
    ###################################################################################################################
    ##
    ##                   DAEMON RELATED METHODS
    ##
    ###################################################################################################################
    
    /**
     * Check all daemons (start them if needed)
     */
    public static function checkAllDaemons() {
        foreach(self::getBrokers() as $broker) {
            if ($broker->getDaemonState() == "nok") {
                try {
                    log::add('jMQTT', 'info', 'vérifie le démon ' . $broker->getName());
                    $broker->startDaemon();
                }
                catch (Exception $e) {}
            }
        }   
    }
       
    /**
     * Return daemon information
     * @return string[] daemon information array
     */
    public function getDaemonInfo() {
        $return = array('message' => '', 'launchable' => 'nok', 'state' => 'nok', 'log' => 'nok', 'auto' => 0);
        
        if ($this->getType() != self::TYP_BRK)
            return $return;
              
        // Verify if this broker is enable
        
        // Is the daemon launchable
        $return['launchable'] = 'ok';
        $dependancy_info = plugin::byId('jMQTT')->dependancy_info();
        if ($dependancy_info['state'] == 'ok') {
            if (!$this->getIsEnable()) {
                $return['launchable'] = 'nok';
                $return['message'] = __("L'équipement est désactivé", __FILE__);
            }
            if (config::byKey('enableCron', 'core', 1, true) == 0) {
                $return['launchable'] = 'nok';
                $return['message'] = __('Les crons et démons sont désactivés', __FILE__);
            }
            if (!jeedom::isStarted()) {
                $return['launchable'] = 'nok';
                $return['message'] = __('Jeedom n\'est pas encore démarré', __FILE__);
            }
        }
        else {
            $return['launchable'] = 'nok';
            if ($dependancy_info['state'] == 'in_progress') {
                $return['message'] = __('Dépendances en cours d\'installation', __FILE__);
            } else {
                $return['message'] = __('Dépendances non installées', __FILE__);
            }
        }

        $return['log'] = $this->getDaemonLogFile();
        $return['auto'] = $this->getDaemonAutoMode();
        $return['last_launch'] = $this->getLastDaemonLaunchTime();      
        $return['state'] = $this->getDaemonState();
        if ($dependancy_info['state'] == 'ok') {
            if ($return['state'] == 'nok')
                $return['message'] = __('Le démon est arrêté', __FILE__);
            elseif ($return['state'] == 'pok')
                $return['message'] = __('Le broker est OFFLINE', __FILE__);
        }

        return $return;
    }
    
    /**
     * Return daemon state
     *   - ok: daemon is running and mqtt broker is online
     *   - pok: daemon is running but mktt broker is offline
     *   - nok: no cron exists or cron is not running
     * @return string ok or nok
     */
    public function getDaemonState() {
        $cron = $this->getDaemonCron();
        if (is_object($cron) && $cron->running()) {
            $return = $this->getMqttClientStatusCmd()->execCmd() == self::ONLINE ? "ok" : "pok";
        }
        else
            $return = "nok";
        
        return $return;
    }
    
    /**
     * Start the daemon of this broker if it is launchable
     * @param bool $restart true to stop the daemon first
     * @param bool $force true to force the start (
     * @throws Exception if the daemon is not launchable
     */
    public function startDaemon($restart = false, $force = false) {
        $daemon_info = $this->getDaemonInfo();
        if ($daemon_info['launchable'] != 'ok') {
            throw new Exception(__('Le démon n\'est pas démarrable. Veuillez vérifier la configuration', __FILE__));
        }
        
        if ($restart) {
            $this->stopDaemon();
            sleep(1);
        }
        $cron = $this->getDaemonCron();
        if (!is_object($cron)) {
            $cron = new cron();
            $cron->setClass(__CLASS__);
            $cron->setFunction('daemon');
            $cron->setOption(array('id' => $this->getId()));
            $cron->setEnable(1);
            $cron->setDeamon(1);
            $cron->setSchedule('* * * * *');
            $cron->setTimeout('1440');
            $cron->save();
        }
        if ($daemon_info['auto'] || $force) {
            $this->log('info', 'démarre le démon');
            $this->setLastDaemonLaunchTime();
            $this->save(true);
            $cron->run();
        }
    }
    
    /**
     * Stop the daemon of this broker type object
     */
    public function stopDaemon() {
        $this->log('info', 'arrête le démon');
        $cron = $this->getDaemonCron();
        if (is_object($cron)) {
            $cron->halt();
        }
        $this->getMqttClientStatusCmd()->event(self::OFFLINE);
    }
       
//     /**
//      * Return daemon info of this broker type object
//      */
//     public static function deamon_info() {
//         $return = array();
//         $return['log'] = 'jMQTT';
//         $return['state'] = 'nok';
//         $cron = cron::byClassAndFunction('jMQTT', 'daemon');
//         if (is_object($cron) && $cron->running()) {
//             $return['state'] = 'ok';
//         }
//         $return['launchable'] = 'ok';
//         return $return;
//     }
    
    /**
     * Daemon method
     * @param string[] $option $option[id]=broker id
     * @throws Exception if $option[id] is not a valid broker id
     */
    public static function daemon($option) {
        $broker = self::getBroker($option['id']);
        $broker->log('debug', 'daemon starts, pid is ' . getmypid());

        // Create mosquitto client
        $broker->_client = $broker->getMosquittoClient($broker->getMqttId());
        
        // Set callbacks
        $broker->_client->onConnect(array($broker, 'brokerConnectCallback'));
        $broker->_client->onDisconnect(array($broker, 'brokerDisconnectCallback'));
        $broker->_client->onSubscribe(array($broker, 'brokerSubscribeCallback'));
        $broker->_client->onUnsubscribe(array($broker, 'brokerUnsubscribeCallback'));
        $broker->_client->onMessage(array($broker, 'brokerMessageCallback'));
        $broker->_client->onLog(array($broker, 'brokerLogCallback'));

        // Defines last will terminaison message
        $broker->_client->setWill($broker->getMqttClientStatusTopic(), self::OFFLINE, 1, 1);
        
        $statusCmd = $broker->getMqttClientStatusCmd();
        if ($statusCmd != null) {
            $broker->log('debug', 'status cmd: ' . $statusCmd->getId());
        }
            
        // Reset the last connection (to the broker) time. Will be set in the mosquittoConnect callback once connected
        $broker->setConfiguration(self::LAST_CONNECT_TIME, '');
        $broker->save(true);
       
        // Subscription and infinite loop
        try {
            $broker->connectSubscribeMqttBroker($broker->_client);
            $broker->_client->loopForever();
        }
        catch (Exception $e) {
            $broker->log('warning', 'exception thrown by MQTT client: ' . $e->getMessage());
        }
        
        $broker->getMqttClientStatusCmd()->event(self::OFFLINE);
        
        // Depending on the last connection time, reconnect immediately or wait 15s
        $time = $broker->getConfiguration(self::LAST_CONNECT_TIME, '');
        if ($time == '' || (strtotime('now') - $time) < 15) {
            $broker->log('info', 'relance le démon dans 15s');
            sleep(15);
        }
        else {
            $broker->log('info', 'relance le démon immédiatement');
        }
    }
       
    /**
     * Return the last deamon launch time
     * @return string date or unknown
     */
    public function getLastDaemonLaunchTime() {
        return $this->getCache('lastDaemonLaunchTime', __('Inconnue', __FILE__));
    }

    /**
     * Set the last deamon launch time to the current time
     */
    public function setLastDaemonLaunchTime() {
        return $this->setCache('lastDaemonLaunchTime', date('Y-m-d H:i:s'));
    }
    
    /**
     * Return the daemon auto mode status
     * @return int 0 or 1
     */
    public function getDaemonAutoMode() {
        return $this->getConfiguration('daemonAutoMode', 1);
    }
    
    /**
     * Set the daemon launch auto mode
     * @param int $mode 0 or 1
     */
    public function setDaemonAutoMode($mode) {
        $this->setConfiguration('daemonAutoMode', $mode);
        $this->save(true);
    }
    
    /**
     * Return the cron object related to this broker object 
     * @return cron|NULL
     */
    public function getDaemonCron() {
        return cron::byClassAndFunction('jMQTT', 'daemon', array('id' => $this->getId()));
    }
    
    ###################################################################################################################
    ##
    ##                   MQTT BROKER METHODS
    ##
    ###################################################################################################################
    
    /**
     * Create and return an MQTT client based on the plugin parameters (mqttUser and mqttPass) and the given ID
     *
     * @param string $id
     *            id of connexion of the client to the broker
     * @return MQTT client
     */
    private function getMosquittoClient($id = '') {
        $mosqUser = $this->getConfiguration('mqttUser', '');
        $mosqPass = $this->getConfiguration('mqttPass', '');
        
        // Création client mosquitto
        // Documentation passerelle php ici:
        // https://github.com/mqtt/mqtt.github.io/wiki/mosquitto-php
        $client = ($id == '') ? new Mosquitto\Client() : new Mosquitto\Client($id);
        
        // Credential configuration when needed
        if ($mosqUser != '') {
            $client->setCredentials($mosqUser, $mosqPass);
        }
        
        // Automatic reconnexion delay
        $client->setReconnectDelay(1, 16, true);
        
        return $client;
    }
    
    /**
     * Connect to this broker and suscribes topics
     * @param object client client to connect
     */
    private function connectSubscribeMqttBroker($client) {
        $mosqHost = $this->getConfiguration('mqttAddress', 'localhost');
        $mosqPort = $this->getConfiguration('mqttPort', '1883');
        $this->initPreviousConfiguration();
        
        $this->log('info',
            'Connect to mosquitto: Host=' . $mosqHost . ', Port=' . $mosqPort . ', Id=' . $this->getMqttId());
        $client->connect($mosqHost, $mosqPort, 60);
        
        if ($this->isIncludeMode()) { // auto inclusion mode
            $topic = $this->getConfiguration('mqttIncTopic', '#');
            // Subscribe to topic (root by default)
            $client->subscribe($topic, 1);
            $this->log('debug', 'Subscribe to topic "' . $topic . '" with Qos=1');
            
            if ($this->isApiEnable()) {
                if (! Mosquitto\Message::topicMatchesSub($this->getMqttApiTopic(), $topic)) {
                    $this->log('info', 'Subscribes to the API topic "' . $this->getMqttApiTopic() . '"');
                    $client->subscribe($this->getMqttApiTopic(), '1');
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
                $mqtt->initPreviousConfiguration();
                if ($mqtt->getIsEnable()) {
                    $topic = $mqtt->getTopic();
                    $qos = (int) $mqtt->getQos();
                    if (empty($topic)) {
                        $this->log('info', 'Equipment ' . $mqtt->getName() . ': no subscription (empty topic)');
                    }
                    else {
                        $this->log('info',
                            'Equipment ' . $mqtt->getName() . ': subscribes to "' . $topic . '" with Qos=' . $qos);
                        $client->subscribe($topic, $qos);
                    }
                }
            }
            
            if ($this->isApiEnable()) {
                $this->log('info', 'Subscribes to the API topic "' . $this->getMqttApiTopic() . '"');
                $client->subscribe($this->getMqttApiTopic(), '1');
            } else {
                $this->log('info', 'API is disable');
            }
        }
    }
    
    public function brokerConnectCallback($r, $message) {
        $this->log('debug', 'broker msg: connection response is ' . $message);
        $this->_client->publish($this->getMqttClientStatusTopic(), self::ONLINE, 1, 1);
        $this->setConfiguration(self::LAST_CONNECT_TIME, strtotime('now'));
        //$this->setConfiguration(self::CLIENT_STATUS, self::ONLINE);
        $this->save(true);
    }
    
    public function brokerDisconnectCallback($r) {
        $msg = ($r == 0) ? 'on client request' : 'unexpectedly';
        $this->log('debug', 'broker msg: disconnected ' . $msg);
        $this->_client->publish($this->getMqttClientStatusTopic(), self::OFFLINE, 1, 1);
        //$this->setConfiguration(self::CLIENT_STATUS, self::OFFLINE);
    }
    
    public function brokerSubscribeCallback($mid, $qosCount) {
        // Note: qosCount is not representative, do not display it (fix #31)
        $this->log('debug', 'broker msg: topic subscription accepted, mid=' . $mid);
    }
    
    public function brokerUnsubscribeCallback($mid) {
        $this->log('debug', 'broker msg: topic unsubscription accepted, mid=' . $mid);
    }
    
    public function brokerLogCallback($level, $str) {
        switch ($level) {
            case Mosquitto\Client::LOG_DEBUG:
                $logLevel = 'debug';
                break;
            case Mosquitto\Client::LOG_INFO:
            case Mosquitto\Client::LOG_NOTICE:
                $logLevel = 'info';
                break;
            case Mosquitto\Client::LOG_WARNING:
                $logLevel = 'warning';
                break;
            default:
                $logLevel = 'error';
                break;
        }
        
        $this->log($logLevel, 'broker msg: ' . $str);
    }
    
    /**
     * Callback called each time a subscribed topic is dispatched by the broker.
     *
     * @param $message string
     *            dispatched message
     */
    public function brokerMessageCallback($message) {
        $msgTopic = $message->topic;
        $msgValue = $message->payload;
        
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
        if (! ctype_print($msgTopic) || empty($topicContent)) {
            if (! jMQTTCmd::isConfigurationValid($msgTopic))
                $msgTopic = strtoupper(bin2hex($msgTopic));
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
        foreach (eqLogic::byType('jMQTT', false) as $eqpt) {
            if ($message->topicMatchesSub($msgTopic, $eqpt->getTopic())) {
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
                $sbscrbTopicArray = explode("/", $eqpt->getLogicalId());
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
                
                /** @var jMQTTCmd $cmdlogic command related to the current message */
                $cmdlogic = jMQTTCmd::byEqLogicIdAndLogicalId($eqpt->getId(), $msgTopic);
                
                // If no command has been found, try to create one
                if (! is_object($cmdlogic)) {
                    if ($eqpt->getAutoAddCmd()) {
                        $cmdlogic = jMQTTCmd::newCmd($eqpt, $cmdName, $msgTopic);
                    }
                    else {
                        $this->log('debug',
                            'Command ' . $eqpt->getName() . '|' . $cmdName .
                            ' not created as automatic command creation is disabled');
                    }
                }
                
                if (is_object($cmdlogic)) {
                    
                    // If the found command is an action command, skip
                    if ($cmdlogic->getType() == 'action') {
                        $this->log('debug',
                            $eqpt->getName() . '|' . $cmdlogic->getName() . ' is an action command: skip');
                        continue;
                    }
                    
                    // Update the command value
                    $cmdlogic->updateCmdValue($msgValue, jMQTTCmd::NOT_JSON_CHILD, jMQTTCmd::NOT_JSON_CHILD);
                    
                    // Decode the JSON payload if requested
                    if ($cmdlogic->getConfiguration('parseJson') == 1) {
                        jMQTTCmd::decodeJsonMessage($eqpt, $msgValue, $cmdName, $msgTopic, $cmdlogic->getId(), 0);
                    }
                }
            }
        }
    }
    
    /**
     * Publish a given message to the MQTT broker
     *
     * @param string $id
     *            id of the command
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
    public function publishMosquitto($id, $eqName, $topic, $payload, $qos, $retain) {
        $mosqHost = $this->getConfiguration('mqttAddress', 'localhost');
        $mosqPort = $this->getConfiguration('mqttPort', '1883');
        
        $payloadMsg = (($payload == '') ? '(null)' : $payload);
        $this->log('info', '<- ' . $eqName . '|' . $topic . ' ' . $payloadMsg);
        
        // To identify the sender (in case of debug need), bvuild the client id based on the jMQTT connexion id
        // and the command id.
        // Concatenates a random string to have a unique id (in case of burst of commands, see issue #23).
        $mosqId = $this->getMqttId() . '/' . $id . '/' . substr(md5(rand()), 0, 8);
        
        // The object variable $_client is not visible here as the current function
        // is not executed on the same thread as the deamon. So we do create a new client.
        $client = $this->getMosquittoClient($mosqId);
        
        $client->onConnect(
            function () use ($client, $topic, $payload, $qos, $retain) {
                $this->log('debug',
                    'Publication du message ' . $topic . ' ' . $payload . ' (pid=' . getmypid() . ', qos=' . $qos .
                    ', retain=' . $retain . ')');
                $client->publish($topic, $payload, $qos, (($retain) ? true : false));
                
                // exitLoop instead of disconnect:
                // . otherwise disconnect too early for Qos=2 see below (issue #25)
                // . to correct issue #30 (action commands not run immediately on scenarios)
                $client->exitLoop();
            });
        
        // Connect to the broker
        $client->connect($mosqHost, $mosqPort, 60);
        
        // Loop around to permit the library to do its work
        // This function will call the callback defined in `onConnect()` and exit properly
        // when the message is sent and the broker disconnected.
        $client->loopForever();
        
        // For Qos=2, it is nessary to loop around more to permit the library to do its work (see issue #25)
        if ($qos == 2) {
            for ($i = 0; $i < 30; $i ++) {
                $client->loop(1);
            }
        }
        
        $client->disconnect();
        
        $this->log('debug', 'Message publié');
    }
    
    /**
     * Return the MQTT topic name of this broker status command
     * @return string broker status topic name
     */
    public function getMqttClientStatusTopic()  {
        return $this->getMqttId() . '/' . jMQTT::CLIENT_STATUS;
    }
    
    /**
     * Return the MQTT status information command of this broker
     * @return cmd status information command.
     */
    public function getMqttClientStatusCmd() {
        return cmd::byEqLogicIdAndLogicalId($this->getId(), $this->getMqttClientStatusTopic());
    }
       
    public static function health() {
        $return = array();
        $mosqHost = config::byKey('mqttAdress', 'jMQTT', 'localhost');
        $mosqPort = config::byKey('mqttPort', 'jMQTT', '1883');
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $server = socket_connect ($socket , $mosqHost, $mosqPort);

        $return[] = array(
            'test' => __('Accès à Mosquitto', __FILE__),
            'result' => ($server) ? __('OK', __FILE__) : __('NOK', __FILE__),
            'advice' => __('Indique si le broker MQTT est visible sur le réseau', __FILE__),
            'state' => $server
        );
        return $return;
    }

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
     * Set $this->_log to the name of the log file attached to this jMQTT object and return it
     *
     * @return string daemon log filename.
     */
    public function getDaemonLogFile() {
        $broker = ($this->getType() == self::TYP_BRK) ? $this : self::getBroker($this->getBrkId());
        $this->_log = 'jMQTT_' . $broker->getMqttId();
        return $this->_log;
    }
    
    public function log($level, $msg) {
        if (!isset($this->_log)) {
            $this->getDaemonLogFile();
        }
        log::add($this->_log, $level, $msg);
    }
    
    /**
     * Return whether or not the MQTT API is enable
     * return boolean
     */
    public function isApiEnable() {
        return $this->getConfiguration('api', self::API_DISABLE) == self::API_ENABLE ? TRUE : FALSE;
    }

    /**
     * Get this jMQTT object type
     * @return string either jMQTT::TYPE_STD, jMQTT::TYP_BRK, or empty string if not defined
     */
    public function getType() {
        return $this->getConfiguration('type', '');
    }
    
    /**
     * Set this jMQTT object type
     * @param string $type either jMQTT::TYPE_STD or jMQTT::TYP_BRK
     */
    public function setType($type) {
        $this->setConfiguration('type', $type);
    }

    /**
     * Get this jMQTT object related broker eqLogic Id
     * @return int eqLogic Id or -1 if not defined
     */
    public function getBrkId() {
        return $this->getConfiguration('brkId', -1);
    }
    
    /**
     * Set this jMQTT object related broker eqLogic Id
     * @param int
     */
    public function setBrkId($id) {
        $this->setConfiguration('brkId', $id);
    }
    
    /**
     * Get the MQTT client id used by jMQTT to connect to the broker (default value = jeedom)
     * @return string MQTT id.
     */
    public function getMqttId() {
        return $this->getConfiguration('mqttId', 'jeedom');
    }
    
    /**
     * Get the previous MQTT client id
     * @return string
     */
    public function getPrevMqttId() {
        return $this->getCache('prevMqttId', 'jeedom');
    }
    
    /**
     * Set the previous MQTT client id
     * @return string
     */
    public function setPrevMqttId($prevMqttId) {
        return $this->setCache('prevMqttId', $prevMqttId);
    }
    
    /**
     * Get this jMQTT object topic
     * @return string
     */
    public function getTopic() {
        return $this->getConfiguration('topic', '');
    }
    
    /**
     * Set this jMQTT object topic
     * @var string $topic
     */
    public function setTopic($topic) {
        $this->setConfiguration('topic', $topic);
    }
    
    /**
     * Get this jMQTT object Qos
     * @return string
     */
    public function getQos() {
        return $this->getConfiguration('Qos', '1');
    }
    
    /**
     * Set this jMQTT object Qos
     * @var string $Qos
     */
    public function setQos($Qos) {
        $this->setConfiguration('Qos', $Qos);
    }
    
    /**
     * Get this jMQTT object previous Qos value
     * @return string
     */
    public function getPrevQos() {
        return $this->getCache('prevQos', '1');
    }
    
    /**
     * Set this jMQTT object previous Qos value
     * @var string $Qos
     */
    public function setPrevQos($PrevQos) {
        $this->setCache('prevQos', $PrevQos);
    }
    
    /**
     * Get this jMQTT object auto_add_cmd configuration parameter
     * @return string
     */
    public function getAutoAddCmd() {
        return $this->getConfiguration('auto_add_cmd', '1');
    }

    /**
     * Set this jMQTT object auto_add_cmd configuration parameter
     * @var string $auto_add_cmd
     */
    public function setAutoAddCmd($auto_add_cmd) {
        $this->setConfiguration('auto_add_cmd', $auto_add_cmd);
    }
    
    /**
     * Get this jMQTT object previous enable status
     * @return string
     */
    public function getPrevIsEnable() {
        return $this->getCache('prevIsEnable', '1');
    }
    
    /**
     * Set this jMQTT object previous enable status
     * @var string $prevIsEnable
     */
    public function setPrevIsEnable($prevIsEnable) {
        $this->setCache('prevIsEnable', $prevIsEnable);
    }
    
    /**
     * Get this jMQTT object broker topic
     * (built from the MQTT id used by the broker client to connect to the broker)
     * @return string
     */
    public function getBrokerTopic() {
        return $this->getMqttId() . '/#';
    }

    /**
     * Get this jMQTT broker object which eqLogic Id is given
     * @var int $id id of the broker
     * @return jMQTT
     * @throws Exception if $option[id] is not a valid broker id
     */
    public static function getBroker($id) {
        /** @var jMQTT $broker */
        $broker = jMQTT::byId($id);
        if (!is_object($broker)) {
            throw new Exception(__('Pas de broker avec l\'id fourni', __FILE__) . ' (id=' . 'id' . ')');
        }  
        return $broker;
    }
    
    /**
     * Return all jMQTT objects haveing the same broker id as this object
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
     * Provides dependancy information
     */
    public static function dependancy_info() {
        if (! isset(self::$_depLogFile))
            self::$_depLogFile = __CLASS__ . '_dep';

        if (! isset(self::$_depProgresFile))
            self::$_depProgressFile = jeedom::getTmpFolder(__CLASS__) . '/progress_dep.txt';

        $return = array();
        $return['log'] = log::getPathToLog(self::$_depLogFile);
        $return['progress_file'] = self::$_depProgressFile;

        // get number of mosquitto packages installed (should be 2 or 3 at least depending
        // on the installMosquitto config parameter)
        $mosq = exec(system::get('cmd_check') . 'mosquitto | wc -l');
        $minMosq = config::byKey('installMosquitto', 'jMQTT', 1) ? 3 : 2;

        // is lib PHP exists?
        $libphp = extension_loaded('mosquitto');

        // build the state status; if nok log debug information
        if ($mosq >= $minMosq && $libphp) {
            $return['state'] = 'ok';
        }
        else {
            $return['state'] = 'nok';
            log::add('jMQTT', 'debug', 'dependancy_info: NOK');
            log::add('jMQTT', 'debug',
                '   * Nb of mosquitto related packaged installed: ' . $mosq . ' (shall be greater equal than ' . $minMosq .
                ')');
            log::add('jMQTT', 'debug', '   * Mosquitto extension loaded: ' . $libphp);
        }

        return $return;
    }

    /**
     * Provides dependancy installation script
     */
    public static function dependancy_install() {
        log::add('jMQTT', 'info', 'Installation des dépendances, voir log dédié (' . self::$_depLogFile . ')');
        log::remove(self::$_depLogFile);
        return array(
            'script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . self::$_depProgressFile . ' ' .
            config::byKey('installMosquitto', 'jMQTT', 1),'log' => log::getPathToLog(self::$_depLogFile));
    }
    
    /**
     * Disable the equipment automatic inclusion mode and inform the desktop page
     * @param string[] $option $option[id]=broker id
     */
    public static function disableIncludeMode($option) {
        $broker = self::getBroker($option['id']);
        $broker->log('info', 'Disable equipment automatic inclusion mode');
        $broker->setIncludeMode(0);
        $broker->save(true);

        // Advise the desktop page (jMQTT.js) that the inclusion mode is disabled
        event::add('jMQTT::disableIncludeMode', array('brkId' => $broker->getId()));

        // Restart the daemon
        $this->startDaemon(true);
    }

    /**
     * Manage the include_mode of this broker object
     * Called by ajax when the button is pressed by the user
     * @param int $mode 0 or 1
     */
    public function changeIncludeMode($mode) {

        // Update the include_mode configuration parameter
        $this->setIncludeMode($mode);
        $this->save(true);
        
        // A cron process is used to reset the automatic mode after a delay

        // If the cron process is already defined, remove it
        $cron = cron::byClassAndFunction('jMQTT', 'disableIncludeMode', array('id' => $this->getId()));
        if (is_object($cron)) {
            $cron->remove();
        }

        // Create and configure the cron process when automatic mode is enabled
        if ($mode == 1) {
            $this->log('info', 'Enable equipment automatic inclusion mode');
            $cron = new cron();
            $cron->setClass('jMQTT');
            $cron->setOption(array('id' => $this->getId()));
            $cron->setFunction('disableIncludeMode');
            // Add 150s => actual delay between 2 and 3min
            $cron->setSchedule(cron::convertDateToCron(strtotime('now') + 150));
            $cron->setOnce(1);
            $cron->save();
        }
        else {
            $this->log('info', 'Disable equipment automatic inclusion mode');
        }

        // Restart the MQTT deamon to manage topic subscription
        $this->startDaemon(true);
    }
    
    /**
     * Set the include mode of this broker object
     * @param int $mode 0 or 1
     */
    private function setIncludeMode($mode) {
        $this->setConfiguration('include_mode', $mode);
    }
    
    /**
     * Return this broker object include mode parameter
     * @return int 0 or 1
     */
    public function getIncludeMode() {
        return $this->getConfiguration('include_mode', 0);
    }
    
    /**
     * Return whether or not the include mode of this broker object is active
     * @return bool
     */
    private function isIncludeMode() {
        return ($this->getIncludeMode() == 1);
    }
}
