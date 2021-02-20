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

class jMQTT extends eqLogic {

    const API_TOPIC = 'api';
    const API_ENABLE = 'enable';
    const API_DISABLE = 'disable';
    
    const CLIENT_STATUS = 'status';
    const OFFLINE = 'offline';
    const ONLINE = 'online';

    const DAEMON_OK = 'ok';
    const DAEMON_POK = 'pok';
    const DAEMON_NOK = 'nok';
    
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
    const CONF_KEY_LAST_CONNECT_TIME = 'lastClientConnectTime';
    
    const CONF_KEY_OLD = 'old';
    const CONF_KEY_NEW = 'new';
    
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
     * Possible value of $_post_data; to restart the daemon.
     * @var integer
     */
    const POST_ACTION_RESTART_DAEMON = 1;
    
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
        $this->setAutoAddCmd('1');
        $this->setQos('1');
        
        if ($this->getType() == self::TYP_BRK) {
            config::save('log::level::' . $this->getDaemonLogFile(), '{"100":"0","200":"0","300":"0","400":"0","1000":"0","default":"1"}', 'jMQTT');
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
     * Overload preSave to manage changes in subscription parameters to the MQTT broker
     */
    public function preSave() {

        // Initialise the equipment at creation if not already initialized (this is the case 
        // when equipment is created manually from the user interface)
        // FIXME: what is the reason for the logicalId test?
        if (!isset($this->id) && $this->logicalId == '') {
            $topic = '';
            // Two brokers cannot have the same name => raise an exception if this is the case
            if ($this->getType() == self::TYP_BRK) {
                foreach(self::getBrokers() as $broker) {
                    if ($broker->getName() == $this->getName()) {
                        throw new Exception(__('Un broker portant le même nom existe déjà : ', __FILE__) . $this->getName());
                    }
                }
                $topic = $this->getBrokerTopic();
            }
            $this->initEquipment($this->getName(), $topic);
        }

        if (isset($this->_post_data['action']) && $this->getBrkId() > 0) {
            
            $restart_daemon = false;
            if ($this->getType() == self::TYP_BRK) {
                
                // If broker has been disabled, stop it
                if (! $this->getIsEnable() && $this->getDaemonState() != self::DAEMON_NOK) {
                    $this->stopDaemon();
                }
                
                if ($this->_post_data['action'] &  self::POST_ACTION_BROKER_CLIENT_ID_CHANGED) {
                    $this->setTopic($this->getBrokerTopic($this->_post_data[self::CONF_KEY_NEW]));
                }
                
                if ($this->isDaemonToBeRestarted()) {
                    $restart_daemon = true;
                }
            }
            
            if ($this->getType() == self::TYP_EQPT) {
                if ($this->getBroker()->isDaemonToBeRestarted(true)) {
                    $restart_daemon = true;     
                }
            }
            
            foreach ($this->_post_data['msg'] as $msg) {
                $this->log('info', $msg);
            }
            
            if ($restart_daemon) {
                $this->log('info', 'relance du démon nécessaire');
                $this->getBroker()->stopDaemon();
                if ($this->getType() == self::TYP_BRK) {
                    $this->setIncludeMode(0);
                }
                $this->addPostAction(self::POST_ACTION_RESTART_DAEMON, '', '');
            }
            else {
                $this->removePostAction(self::POST_ACTION_RESTART_DAEMON);
            }
        }
    }

    /**
     * postSave callback:
     *   - On broker name change, rename the the log file
     *   - Start daemon (when stopped in preSave)
     */
    public function postSave() {
        // Check $this->getBrkId() to avoid restarting daemon at broker creation
        if (isset($this->_post_data['action']) && $this->getBrkId() > 0) {
            
            if ($this->_post_data['action'] & self::POST_ACTION_BROKER_NAME_CHANGED) {
                $old_log = $this->getDaemonLogFile();
                $new_log = $this->getDaemonLogFile(true);
                rename(log::getPathToLog($old_log), log::getPathToLog($new_log));
                config::save('log::level::' . $new_log, config::byKey('log::level::' . $old_log, 'jMQTT'), 'jMQTT');
                config::remove('log::level::' . $old_log, 'jMQTT');
            }
            
            // In case of broker id change, 
            if (! ($this->_post_data['action'] & self::POST_ACTION_BROKER_CLIENT_ID_CHANGED)) {            
                if ($this->_post_data['action'] & self::POST_ACTION_RESTART_DAEMON) {
                    $this->getBroker()->startDaemon(false);
                }
                $this->_post_data['action'] = null;
            }
        }      
    }
    
    /**
     * postAjax callback:
     *   - On broker MQTT client id modification:
     *     . Update command topics
     *     . Start daemon (when stopped in preSave)

     *   - At broker equipment creation:
     *     . create the status command of a broker
     *     . define brkId of a broker
     */
    public function postAjax() {
        
        if (isset($this->_post_data['action'])) {
            // Done first (before creation of MQTT client status cmd below)
            // Done in postAjax (not in postSave) as commands coming from the UI are saved between postSave and postAjax.
            if ($this->_post_data['action'] & self::POST_ACTION_BROKER_CLIENT_ID_CHANGED) {
                /** @var jMQTTCmd[] $cmds */
                $cmds = cmd::byEqLogicId($this->getId());
                foreach ($cmds as $cmd) {
                    if (strpos($this->_post_data[self::CONF_KEY_OLD], $cmd->getTopic()) == 0) {
                        $cmd->setTopic(str_replace($this->_post_data[self::CONF_KEY_OLD], $this->_post_data[self::CONF_KEY_NEW], $cmd->getTopic()));
                        $cmd->save();
                    }
                }
                // Send a null command to the previous status command topic to suppress the retained message
                $this->publishMosquitto($this->getMqttClientStatusCmd()->getId(), $this->getName(),
                    self::_getMqttClientStatusTopic($this->_post_data[self::CONF_KEY_OLD]), '', 1, 1);
            }
                
                
            if ($this->_post_data['action'] & self::POST_ACTION_RESTART_DAEMON) {
                $this->startDaemon(false);
            }
            $this->_post_data['action'] = null;
        }
        
        if ($this->getType() == self::TYP_BRK) {
            $this->createMqttClientStatusCmd();
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
        $this->_post_data = null;
        if ($this->getType() == self::TYP_BRK) {
            $this->log('info', 'removing broker ' . $this->getName());
            
            // Disable first the broker to avoid during removal of the broker to restart the daemon
            $this->setIsEnable(0);
            $this->save(true);

            $cron = $this->getDaemonCron();
            if (is_object($cron)) {
                $cron->remove(true);
            }
            // suppress the log file
            if (file_exists(log::getPathToLog($this->getDaemonLogFile()))) {
                unlink(log::getPathToLog($this->getDaemonLogFile()));
            }
            config::remove('log::level::' . $this->getDaemonLogFile(), 'jMQTT');
            // remove all equipments attached to the broker
            foreach ($this->byBrkId() as $eqpt) {
                if ($this->getId() != $eqpt->getId())
                    $eqpt->remove();
            }
        }
        else {
            $this->log('info', 'removing equipment ' . $this->getName());
            $broker = $this->getBroker();
            if ($this->getIsEnable() && $broker->getIsEnable() && ! $broker->isIncludeMode()) {
                $this->log('info', 'relance le démon');
                $broker->stopDaemon();
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
            $this->_post_data->startDaemon();
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
        
        // Disable first the broker to avoid during removal of the equipments to restart the daemon
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
                $info = $broker->getDaemonInfo();
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
     * check daemons are started
     */
    public function cron() {
        //log::add('jMQTT', 'info', 'démarre le plugin');
        self::checkAllDaemons();
    }
    
    /**
     * callback to start this plugin
     */
    public static function start() {
        log::add('jMQTT', 'info', 'démarre le plugin');
        self::checkAllDaemons();
    }
    
    /**
     * callback to stop this plugin
     */
    public static function stop() {
        log::add('jMQTT', 'info', 'arrête le plugin');
        foreach(self::getBrokers() as $broker) {
            $broker->stopDaemon();
        }
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
        $return = array('message' => '', 'launchable' => 'nok', 'state' => 'nok', 'log' => 'nok');
        
        if ($this->getType() != self::TYP_BRK)
            return $return;
              
        $return['brkId'] = $this->getId();
        
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
        $return['last_launch'] = $this->getLastDaemonLaunchTime();      
        $return['state'] = $this->getDaemonState();
        if ($dependancy_info['state'] == 'ok') {
            if ($return['state'] == self::DAEMON_NOK && $return['message'] == '')
                $return['message'] = __('Le démon est arrêté', __FILE__);
            elseif ($return['state'] == self::DAEMON_POK)
                $return['message'] = __('Le broker est OFFLINE', __FILE__);
        }

        return $return;
    }
    
    /**
     * Return whether or not the daemon shall be restarted after a configuration change that impacts its processing.
     * If $isIncludeMode is true and the broker is in automatic inclusion mode returns false. Otherwise output depends
     * on the launchable status of the broker.
     * 
     * Shall be called for a broker only.
     * 
     * @param bool $isIncludeMode whether or not the automatic inclusion mode of the broker shall be taken into
     *              account in the assessement.
     * @return boolean
     */
    public function isDaemonToBeRestarted($isIncludeMode=false) {
        if ($isIncludeMode && $this->getBroker()->isIncludeMode()) {
            return false;
        }
        else {
            $info = $this->getDaemonInfo();
            return $info['launchable'] == 'ok' ? true : false;
        }
    }
    
    /**
     * Return daemon state
     *   - self::DAEMON_OK: daemon is running and mqtt broker is online
     *   - self::DAEMON_POK: daemon is running but mktt broker is offline
     *   - self::DAEMON_NOK: no cron exists or cron is not running
     * @return string ok or nok
     */
    public function getDaemonState() {
        $cron = $this->getDaemonCron();
        if (is_object($cron) && $cron->running()) {
            $cmd = $this->getMqttClientStatusCmd();
            if (is_object($cmd)) {
                $return = $cmd->execCmd() == self::ONLINE ? self::DAEMON_OK : self::DAEMON_POK;
            }
            else {
                $return  = self::DAEMON_POK;
            }
        }
        else
            $return = self::DAEMON_NOK;
        
        return $return;
    }
    
    /**
     * Start the daemon of this broker if it is launchable
     * @param bool $restart true to stop the daemon first
     * @throws Exception if the daemon is not launchable
     */
    public function startDaemon($restart = false) {
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
        $this->log('info', 'démarre le démon');
        $this->setLastDaemonLaunchTime();
        $this->sendDaemonStateEvent();
        $cron->run();
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
        
        $cmd = $this->getMqttClientStatusCmd();
        // Status cmd may not exist on object removal for instance
        if (is_object($cmd)) {
            $cmd->event(self::OFFLINE);
        }
        
        $this->sendDaemonStateEvent();
    }
   
    /**
     * Daemon method
     * @param string[] $option $option[id]=broker id
     * @throws Exception if $option[id] is not a valid broker id
     */
    public static function daemon($option) {
        $broker = self::getBrokerFromId($option['id']);
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
        $broker->log('debug', 'status cmd id: ' . $statusCmd->getId() . ', topic: ' . $statusCmd->getLogicalId());
            
        // Reset the last connection (to the broker) time. Will be set in the mosquittoConnect callback once connected
        $broker->setConfiguration(self::CONF_KEY_LAST_CONNECT_TIME, '');
        $broker->save(true);
       
        // Subscription and infinite loop
        try {
            $broker->connectSubscribeMqttBroker($broker->_client);
            $broker->_client->loopForever();
        }
        catch (Exception $e) {
            $broker->log('warning', 'exception thrown by MQTT client: ' . $e->getMessage());
        }
        
        $statusCmd->event(self::OFFLINE);
        $broker->sendDaemonStateEvent();
        
        // Depending on the last connection time, reconnect immediately or wait 15s
        $time = $broker->getConf(self::CONF_KEY_LAST_CONNECT_TIME);
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
     * Return the cron object related to this broker object 
     * @return cron|NULL
     */
    public function getDaemonCron() {
        return cron::byClassAndFunction('jMQTT', 'daemon', array('id' => $this->getId()));
    }
    
    /**
     * Send a jMQTT::EventState event to the UI containing daemon info
     * The method shall be called on a broker equipment eqLogic
     */
    private function sendDaemonStateEvent() {
        event::add('jMQTT::EventState', $this->getDaemonInfo());
    }
    
    ###################################################################################################################
    ##
    ##                   MQTT BROKER METHODS
    ##
    ###################################################################################################################
    
    /**
     * Create and return an MQTT client based on the plugin parameters (mqttUser and mqttPass) and the given ID
     * This is the responsability of the caller to insure that this object is of type broker
     *
     * @param string $id
     *            id of connexion of the client to the broker
     * @return MQTT client
     */
    private function getMosquittoClient($id = '') {
        $mosqUser = $this->getConf(self::CONF_KEY_MQTT_USER);
        $mosqPass = $this->getConf(self::CONF_KEY_MQTT_PASS);
        
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
        $mosqHost = $this->getMqttAddress();
        $mosqPort = $this->getMqttPort();
        
        $this->log('info',
            'Connect to mosquitto: Host=' . $mosqHost . ', Port=' . $mosqPort . ', Id=' . $this->getMqttId());
        $client->connect($mosqHost, $mosqPort, 60);
        
        if ($this->isIncludeMode()) { // auto inclusion mode
            $topic = $this->getConf(self::CONF_KEY_MQTT_INC_TOPIC);
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
        $this->setConfiguration(self::CONF_KEY_LAST_CONNECT_TIME, strtotime('now'));
        $this->getMqttClientStatusCmd()->event(self::ONLINE);
        $this->sendDaemonStateEvent();
        $this->save(true);
    }
    
    public function brokerDisconnectCallback($r) {
        $msg = ($r == 0) ? 'on client request' : 'unexpectedly';
        $this->log('debug', 'broker msg: disconnected ' . $msg);
        $this->_client->publish($this->getMqttClientStatusTopic(), self::OFFLINE, 1, 1);
        $this->getMqttClientStatusCmd()->event(self::OFFLINE);
        $this->sendDaemonStateEvent();
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
        
        $this->setStatus(array('lastCommunication' => date('Y-m-d H:i:s'), 'timeout' => 0));
        
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
                        $this->sendDaemonStateEvent();
                    }
                }
            }
        }
    }
    
    /**
     * Publish a given message to the MQTT broker attached to this object
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
        $mosqHost = $this->getBroker()->getMqttAddress();
        $mosqPort = $this->getBroker()->getMqttPort();
        
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
        
        // To identify the sender (in case of debug need), build the client id based on the jMQTT connexion id
        // and the command id.
        // Concatenates a random string to have a unique id (in case of burst of commands, see issue #23).
        $mosqId = $this->getBroker()->getMqttId() . '/' . $id . '/' . substr(md5(rand()), 0, 8);
        
        // The object variable $_client is not visible here as the current function
        // is not executed on the same thread as the deamon. So we do create a new client.
        $client = $this->getBroker()->getMosquittoClient($mosqId);
        
        $messageId = 0;

        $client->onConnect(
            function () use ($client, $topic, $payload, $qos, $retain, &$messageId) {
                $this->log('debug',
                    'Publication du message ' . $topic . ' ' . $payload . ' (pid=' . getmypid() . ', qos=' . $qos .
                    ', retain=' . $retain . ')');
                $messageId = $client->publish($topic, $payload, $qos, (($retain) ? true : false));
            });

        $client->onPublish(
            function ($publishedId) use ($client, &$messageId) {
                if($publishedId == $messageId) {
                    $client->disconnect();
                }
            });
        
        // Connect to the broker
        $client->connect($mosqHost, $mosqPort, 60);
        
        // Loop around to permit the library to do its work
        // This function will call the callback defined in `onConnect()` and publish the message
        // once the message is published, `onPublish()` is called to disconnect properly the client
        // then this loopForever ends by itself once disconnected
        $client->loopForever();
        
        $d = date('Y-m-d H:i:s');
        $this->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
        if ($this->getType() == self::TYP_EQPT) {
            $this->getBroker()->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
        }
        
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
            $this->_statusCmd = jMQTTCmd::byEqLogicIdAndTopic($this->getId(), $this->getMqttClientStatusTopic());
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
     * @return string daemon log filename.
     */
    public function getDaemonLogFile($force=false) {
        if (!isset($this->_log) || $force) {
            $this->_log = 'jMQTT_' . str_replace(' ', '_', $this->getBroker()->getName());
        }
        return $this->_log;
    }
      
    /**
     * Log messages
     * @param string $level
     * @param string $msg
     */
    public function log($level, $msg) {
        log::add($this->getDaemonLogFile(), $level, $msg);
    }
    
    /**
     * Return whether or not the MQTT API is enable
     * return boolean
     */
    public function isApiEnable() {
        return $this->getConf(self::CONF_KEY_API) == self::API_ENABLE ? TRUE : FALSE;
    }

    private function addPostAction($action, $key, $newVal, $oldVal='') {
        if (isset($this->_post_data['action'])) {
            $this->_post_data['action'] = $this->_post_data['action'] | $action;
        }
        else {
            $this->_post_data['action'] = $action;
        }
        
        if ($key != '') {
            if ($oldVal == '') {
                $this->_post_data['msg'][] = $this->getName() . ': '. $key . ' modifié à ' . $newVal;
            }
            else {
                $this->_post_data['msg'][] = $this->getName() . ': '. $key . ' modifié de ' . $oldVal . ' à ' . $newVal;
            }
        }
    }
    
    private function removePostAction($action) {
        if (isset($this->_post_data['action'])) {
            $this->_post_data['action'] = $this->_post_data['action'] & ~$action;
        }
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
     * If log level is changed, save the new value and restart the daemon
     * @param string $level
     */
    public function setLogLevel($log_level) {
        $this->_log = $this->getDaemonLogFile();
        $new_level = json_decode($log_level, true);
        $old_level = config::byKey('log::level::' . $this->_log, 'jMQTT');
        if (reset($new_level) != $old_level) {
            config::save('log::level::' . $this->_log, json_encode(reset($new_level)), 'jMQTT');
            $this->addPostAction(self::POST_ACTION_RESTART_DAEMON, 'niveau de log',
                log::convertLogLevel(log::getLogLevel($this->getDaemonLogFile())));
        }
    }

    /**
     * Override setConfiguration to manage daemon stop/start when deemed necessary
     * {@inheritDoc}
     * @see eqLogic::setConfiguration()
     */
    public function setConfiguration($_key, $_value) {
        $default_value = self::getDefaultConfiguration($_key);
        $old_value = $this->getConfiguration($_key, $default_value);
        if ($_value == '') {
            $value = self::getDefaultConfiguration($_key);
        }
        else {
            $value = $_value;
        }
        
        // General case
        $keys = array('Qos');
        if ($this->getType() == self::TYP_BRK) {
            $keys = array_merge($keys, array('mqttAddress', 'mqttPort', 'mqttUser', 'mqttPass', 'mqttIncTopic', 'api'));
        }
        if (in_array($_key, $keys)) {
            if ($value != $old_value) {
                $this->addPostAction(self::POST_ACTION_RESTART_DAEMON, $_key, $value, $old_value);
            }
        }
        
        // Specific case: MQTT id change
        if ($this->getType() == self::TYP_BRK && $_key == self::CONF_KEY_MQTT_ID) {
            if ($value != $old_value) {
                // Note: topic (i.e. logicalId) is modified in preSave
                $this->addPostAction(self::POST_ACTION_BROKER_CLIENT_ID_CHANGED, 'MQTT id', $value, $old_value);
                $this->_post_data[self::CONF_KEY_OLD] = $old_value;
                $this->_post_data[self::CONF_KEY_NEW] = $value;
            }
        }
        return parent::setConfiguration($_key, $_value);
    }
        
    /**
     * Overide setName to manage log file renaming (for broker)
     * {@inheritDoc}
     * @see eqLogic::setName()
     */
    public function setName($_name) {
        if ($this->getType() == self::TYP_BRK) {
            if ($_name != $this->name) {
                $this->_log = $this->getDaemonLogFile(); // To write in the correct log until log is renamed
                $this->addPostAction(self::POST_ACTION_BROKER_NAME_CHANGED, 'nom du broker', $_name, $this->name);
            }
        }
        
        return parent::setName($_name);
    }
    
    /**
     * Override setIsEnable to manage daemon stop/start 
     * {@inheritDoc}
     * @see eqLogic::setIsEnable()
     */
    public function setIsEnable($_isEnable) {
        if ($_isEnable != $this->isEnable) {
            $newVal = $_isEnable ? 'activée' : 'désactivée';
            $oldVal = $this->isEnable ? 'activée' : 'désactivée';
            $this->addPostAction(self::POST_ACTION_RESTART_DAEMON, 'activation', $newVal, $oldVal);
        }

        parent::setIsEnable($_isEnable);
               
        return $this;
    }
    
    /**
     * Override setLogicalId (which store the equipment registration topic) to manage daemon stop/start 
     * {@inheritDoc}
     * @see eqLogic::setLogicalId()
     */
    public function setLogicalId($_logicalId) {
        if ($_logicalId != $this->logicalId) {
            $this->addPostAction(self::POST_ACTION_RESTART_DAEMON, 'topic', $_logicalId, $this->logicalId);
        }
        parent::setLogicalId($_logicalId);
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
     * @param string $type either jMQTT::TYPE_STD or jMQTT::TYP_BRK
     */
    public function setType($type) {
        $this->setConfiguration(self::CONF_KEY_TYPE, $type);
    }

    /**
     * Get this jMQTT object related broker eqLogic Id
     * @return int eqLogic Id or -1 if not defined
     */
    public function getBrkId() {
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

        // Restart the daemon
        if ($broker->isDaemonToBeRestarted()) {
            $broker->startDaemon(true);
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
        $cron = cron::byClassAndFunction('jMQTT', 'disableIncludeMode', array('id' => $this->getId()));
        if (is_object($cron)) {
            $cron->remove();
        }

        // Create and configure the cron process when automatic mode is enabled
        if ($mode == 1) {
            $cron = new cron();
            $cron->setClass('jMQTT');
            $cron->setOption(array('id' => $this->getId()));
            $cron->setFunction('disableIncludeMode');
            // Add 150s => actual delay between 2 and 3min
            $cron->setSchedule(cron::convertDateToCron(strtotime('now') + 150));
            $cron->setOnce(1);
            $cron->save();
        }

        // Restart the MQTT deamon to manage topic subscription
        $this->startDaemon(true);
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
        log::add('jMQTT', 'debug', $s);
    }
}
