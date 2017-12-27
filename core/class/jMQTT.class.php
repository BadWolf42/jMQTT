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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class jMQTT extends eqLogic {

    // MQTT client is defined as a static variable.
    // IMPORTANT: This variable is set in the deamon method; it is only visible from functions
    // that are executed on the same thread as the deamon method.
    private static $_client;
    
    /**
     * Create a new equipment that will subscribe to $topic0/#
     * The equipment is not saved
     * @param string $topic0 first topic level
     * return new jMQTT object
     */
    private static function newEquipment($topic0) {
        log::add('jMQTT', 'info', 'Create device ' . $topic0);
        $topic = $topic0 . '/#';
        $eqpt = new jMQTT();
        $eqpt->setEqType_name('jMQTT');
        $eqpt->setLogicalId($topic);
        $eqpt->setName($topic0);
        $eqpt->setIsEnable(1);
        $eqpt->setStatus('lastCommunication', date('Y-m-d H:i:s'));
        $eqpt->setConfiguration('topic', $topic);
        $eqpt->setConfiguration('Qos', '1');
        $eqpt->setConfiguration('prev_Qos', '1');
        $eqpt->setConfiguration('reload_d', '0');
        return $eqpt;
    }

    /**
     * Overload preSave to manage changes in subscription parameters to the MQTT broker
     */
    public function preSave() {

        /*
        // Local method to log messages
        private function log($_msg) {
        */    

        $topic = $this->getConfiguration('topic');
        if ($topic == '') {
            throw new Exception(__("Le topic ne peut pas être vide", __FILE__));
        }
        
        // Check if MQTT subscription parameters have changed for this equipment
        // Applies to the manual mode only as in automatic mode, # (i.e. all topics) is subscribed
        $reload_d = 0;
        if (config::byKey('mqttAuto', 'jMQTT', 0) == 0) {  // manual mode

            $prevTopic    = $this->getLogicalId();
            $prevQos      = $this->getConfiguration('prev_Qos');
            $qos          = $this->getConfiguration('Qos', 1);
            $prevIsActive = $this->getConfiguration('prev_isActive');
            $isActive     = $this->getIsEnable();

            // Subscription topic
            if ($prevTopic != $topic) {
                log::add('jMQTT', 'debug', $this->getName() . '.preSave: prevTopic=' . $prevTopic .
                         ', topic=' . $topic);
                $this->setLogicalId($topic);
                $reload_d = 1;
            }

            // Quality of service
            if ($qos != $prevQos) {
                log::add('jMQTT', 'debug', $this->getName() . '.preSave: prevQos=' . $prevQos . ', qos=' . $qos);
                $this->setConfiguration('prev_Qos', $qos);
                $reload_d = 1;
            }

            // Equipment enable status
            if ($isActive != $prevIsActive) {
                log::add('jMQTT', 'debug', $this->getName() . '.preSave: prevIsActive=' . $prevIsActive .
                         ', isActive=' . $isActive);
                $this->setConfiguration('prev_isActive', $isActive);
                $reload_d = 1;
            }

            // If this method is executed on the thread that has access the $_client variable, we can manage directly
            // the unsubscription/subscription here. Othervise will be done in postSave.
            if ($reload_d == 1 and isset(self::$_client)) {
                if ($prevIsActive == 1) {
                    log::add('jMQTT', 'debug', $this->getName() . '.preSave: unsubscribe "' . $prevTopic . '"');
                    self::$_client->unsubscribe($prevTopic, $prevQos);
                }
                if ($isActive == 1) {
                    log::add('jMQTT', 'debug', $this->getName() . '.preSave: subscribe "' . $topic . '"');
                    self::$_client->subscribe($topic, $qos);
                }
                $reload_d = 0;
           }
        }
        $this->setConfiguration('reload_d', $reload_d);
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
     * Overload postSave to restart the deamon when deemed necessary (see preSave)
     */
    public function postSave() {
        if ($this->getConfiguration('reload_d') == "1") {
            log::add('jMQTT', 'debug', 'postSave: restart deamon, current pid is ' . getmypid());

            /*if (isset(self::$_client)) {
                log::add('jMQTT', 'debug', 'postSave: variable client is visible');
                self::$_client->disconnect();
                self::mqtt_connect_subscribe(self::$_client);
            }
            else {*/
            $cron = cron::byClassAndFunction('jMQTT', 'daemon');
            if (is_object($cron) && $cron->running()) {
                log::add('jMQTT', 'debug', 'postSave: restart the deamon, pid is ' . $cron->getPID());
                $cron->halt();
                $cron->run();
            }
            //}
        }
    }

    public function postRemove() {
        if (config::byKey('mqttAuto', 'jMQTT', 0) == 0) {  // manual mode
            log::add('jMQTT', 'debug', 'postRemove: restart deamon');
            $cron = cron::byClassAndFunction('jMQTT', 'daemon');
            if (is_object($cron) && $cron->running()) {
                $cron->halt();
                $cron->run();
            }
        }
    }

    public static function health() {
        $return = array();
        $mosqHost = config::byKey('mqttAdress', 'jMQTT', 0);
        if ($mosqHost == '') {
            $mosqHost = '127.0.0.1';
        }
        $mosqPort = config::byKey('mqttPort', 'jMQTT', 0);
        if ($mosqPort == '') {
            $mosqPort = '1883';
        }
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $server = socket_connect ($socket , $mosqHost, $mosqPort);

        $return[] = array(
            'test' => __('Mosquitto', __FILE__),
            'result' => ($server) ? __('OK', __FILE__) : __('NOK', __FILE__),
            'advice' => ($server) ? '' : __('Indique si Mosquitto est disponible', __FILE__),
            'state' => $server,
        );
        return $return;
    }

    public static function deamon_info() {
        //       message::add('jMQTT', 'chargement info démon');
        //log::add('jMQTT', 'debug', 'load deamon info');
        $return = array();
        $return['log'] = '';
        $return['state'] = 'nok';
        $cron = cron::byClassAndFunction('jMQTT', 'daemon');
        if (is_object($cron) && $cron->running()) {
            $return['state'] = 'ok';
        }
        $return['launchable'] = 'ok';
        return $return;
    }

    public static function deamon_start($_debug = false) {
        self::deamon_stop();
        log::add('jMQTT', 'debug', 'deamon_start');
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        $cron = cron::byClassAndFunction('jMQTT', 'daemon');
        if (!is_object($cron)) {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->run();
    }
    
    public static function deamon_stop() {
        log::add('jMQTT', 'debug', 'deamon_stop');
        if (isset(self::$_client)) {
            log::add('jMQTT', 'debug', 'disconnect MQTT client');
            self::$_client->disconnect();
        }
        $cron = cron::byClassAndFunction('jMQTT', 'daemon');
        if (!is_object($cron)) {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->halt();

        // Unset the variable after calling halt as the deamon uses the client variable
        self::$_client = NULL;
    }

    private static function mqtt_connect_subscribe($client) {
        $mosqHost = config::byKey('mqttAdress', 'jMQTT', '127.0.0.1');
        $mosqPort = config::byKey('mqttPort', 'jMQTT', '1883');

        log::add('jMQTT', 'info', 'Connect to mosquitto broker: Host=' . $mosqHost . ', Port=' . $mosqPort .
                 ', Id=' . self::getMqttId());
        $client->connect($mosqHost, $mosqPort, 60);

        if (config::byKey('mqttAuto', 'jMQTT', 0) == 0) {  // manual mode
            foreach (eqLogic::byType('jMQTT', true) as $mqtt) {
                $topic = $mqtt->getConfiguration('topic');
                $qos   = (int) $mqtt->getConfiguration('Qos', '1');
                log::add('jMQTT', 'info', 'Equipment ' . $mqtt->getName() . ' subscribes to "' . $topic .
                         '" with Qos=' . $qos);
                $client->subscribe($topic, $qos);
            }
        }
        else { // auto mode
            $topic = config::byKey('mqttTopic', 'jMQTT', '#');
            $qos   = config::byKey('mqttQos', 'jMQTT', 1);
            // Subscribe to topic (root by default)
            $client->subscribe($topic, $qos);
            log::add('jMQTT', 'debug', 'Subscribe to topic ' . $topic . '" with Qos=' . $qos);
        }
    }
    
    /**
     * Daemon method called by cron
     */
    public static function daemon() {

        log::add('jMQTT', 'info', 'daemon pid is ' . getmypid());

        // Create mosquitto client
        self::$_client = self::newMosquittoClient('');
        
        // Set callbacks
        self::$_client->onConnect(function($r, $message) {
            log::add('jMQTT', 'info', 'mosquitto: connection response is ' . $message);
            self::$_client->publish(jMQTT::getMqttId() . '/status', 'online', 1, 1);
            config::save('status', '1',  'jMQTT');
        });

        self::$_client->onDisconnect(function($r) {
            $msg = ($r == 0) ? 'on client request' : 'unexpectedly';
            log::add('jMQTT', 'debug', 'mosquitto: disconnected from broker' . $msg);
            self::$_client->publish(jMQTT::getMqttId() . '/status', 'offline', 1, 1);
            config::save('status', '0',  'jMQTT');
        });

        //self::$_client->onDisconnect('jMQTT::mosquittoDisconnect');
        self::$_client->onSubscribe('jMQTT::mosquittoSubscribe');
        self::$_client->onUnsubscribe('jMQTT::mosquittoUnsubscribe');
        self::$_client->onMessage('jMQTT::mosquittoMessage');
        self::$_client->onLog('jMQTT::mosquittoLog');

        // Defines last will terminaison message
        self::$_client->setWill(self::getMqttId() . '/status', 'offline', 1, 1);

        try {
            self::mqtt_connect_subscribe(self::$_client);

            //self::$_client->loopForever();
            while (true) { self::$_client->loop(); }
        }
        catch (Exception $e){
            log::add('jMQTT', 'error', $e->getMessage());
        }

        log::add('jMQTT', 'error', 'deamon method exists');
    }

    public function stopDaemon() {
        log::add('jMQTT', 'debug', 'stopDaemon');
        $cron = cron::byClassAndFunction('jMQTT', 'daemon');
        $cron->stop();
    }

    /*public static function mosquittoConnect($r, $message) {
        log::add('jMQTT', 'info', 'mosquitto: connection response is ' . $message);
        config::save('status', '1',  'jMQTT');
    }*/
 
    /*public static function mosquittoDisconnect($r) {
        $msg = ($r == 0) ? 'on client request' : 'unexpectedly';
        log::add('jMQTT', 'debug', 'mosquitto: disconnected from broker' . $msg);
            
        config::save('status', '0',  'jMQTT');
    }*/

    public static function mosquittoSubscribe($mid, $qos) {
        log::add('jMQTT', 'debug', 'mosquitto: topic subscription accepted, mid=' . $mid . ' ,qos=' . $qos);
    }

    public static function mosquittoUnsubscribe($mid) {
        log::add('jMQTT', 'debug', 'mosquitto: topic unsubscription accepted, mid=' . $mid);
    }
    
    public static function mosquittoLog($level, $str) {
        switch ($level) {
        case Mosquitto\Client::LOG_DEBUG:
            $logLevel = 'debug'; break;
        case Mosquitto\Client::LOG_INFO:
        case Mosquitto\Client::LOG_NOTICE:
            $logLevel = 'info'; break;
        case Mosquitto\Client::LOG_WARNING:
            $logLevel = 'warning'; break;
        default:
            $logLevel = 'error'; break;
        }
               
        log::add('jMQTT', $logLevel, 'mosquitto: ' . $str);
    }

    public static function mosquittoMessage($message) {

        $msgTopic = $message->topic;
        $msgValue = $message->payload;
        log::add('jMQTT', 'debug', 'Message ' . $msgValue . ' sur ' . $msgTopic);

        $msgTopicArray = explode("/", $msgTopic);

        if(!ctype_print($msgTopic) || empty($msgTopic)) {
            log::add('jMQTT', 'debug', 'Message skipped : "' . $message->topic . '" is not a valid topic');
            return;
        }

        // Loop on jMQTT equipments and get ones that subscribed to the current message
        $elogics = array();
        foreach (eqLogic::byType('jMQTT', false) as $eqpt) {
            if ($message->topicMatchesSub($msgTopic, $eqpt->getConfiguration('topic'))) {
                $elogics[] = $eqpt;
            }
        }

        // If no equipment listening to the current message is found and the
        // automatic discovering mode is active => create a new equipment
        // subscribing to all topics starting with the first topic of the
        // current message
        if (empty($elogics) && config::byKey('mqttAuto', 'jMQTT', 0) == 1) {
            $elogics[] = jMQTT::newEquipment($msgTopicArray[0]);
        }

        // No equipment listening to the current message is found
        // Should not occur: log a warning
        if (empty($elogics)) {
            log::add('jMQTT', 'warning', 'No equipment listening to topic ' . $msgTopic);
            return;
        }
            
        //
        // Loop on enabled equipments listening to the current message
        //
        foreach($elogics as $eqpt) {

            if ($eqpt->getIsEnable()) {
                
                $eqpt->setStatus('lastCommunication', date('Y-m-d H:i:s'));
                $eqpt->save();

                // Determine the name of the command.
                // Suppress starting topic levels that are common with the equipment suscribing topic
                $sbscrbTopicArray = explode("/", $eqpt->getLogicalId());
                reset($msgTopicArray);
                foreach($sbscrbTopicArray as $s) {
                    if ($s == '#' || $s == '+')
                    break;
                else
                    next($msgTopicArray);
                }
                $cmdName = current($msgTopicArray) === false ? end($msgTopicArray) : current($msgTopicArray);
                while(next($msgTopicArray) !== false) {
                    $cmdName = $cmdName . '/' . current($msgTopicArray);
                }
            
                $cmdlogic = jMQTTCmd::byEqLogicIdAndLogicalId($eqpt->getId(), $msgTopic);
                if (!is_object($cmdlogic)) {
                    // parseJson=0 by default
                    $cmdlogic = jMQTTCmd::newCmd($eqpt->getId(), $cmdName, $msgTopic, 0);
                }

                // Update the command value
                $cmdlogic->updateCmdValue($msgValue);

                if ($cmdlogic->getConfiguration('parseJson') == 1) {
                    $jsonArray = json_decode($msgValue, true);
                    if (is_array($jsonArray) && json_last_error() == JSON_ERROR_NONE)
                        jMQTTCmd::decodeJsonMessage($eqpt, $jsonArray, $cmdName, $msgTopic);
                }
            }
        }
    }

    /**
     * Return the MQTT id (default value = jeedom)
     * @return MQTT id.
     */
    public static function getMqttId() {
        return config::byKey('mqttId', 'jMQTT', 'jeedom');
    }
    
    /**
     * Create a mosquitto client based on the plugin parameters (mqttAdress, mqttPort,
     * mqttId, mqttUser and mqttPass).
     * @param string $_mosqIdSuffix suffix to concatenate to mqttId if the later is not empty
     */
    private static function newMosquittoClient($_mosqIdSuffix) {
        $mosqId   = self::getMqttId();
        $mosqUser = config::byKey('mqttUser', 'jMQTT', '');
        $mosqPass = config::byKey('mqttPass', 'jMQTT', '');

        // Création client mosquitto
        // Documentation passerelle php ici:
        //    https://github.com/mqtt/mqtt.github.io/wiki/mosquitto-php
        if ($mosqId == '')
            $client = new Mosquitto\Client();
        else {
            $mosqId = $mosqId . $_mosqIdSuffix;
            $client = new Mosquitto\Client($mosqId);
        }

        if ($mosqUser != '') {
            $publish->setCredentials($mosqUser, $mosqPass);
        }

        return $client;
    }
        
    /** Publish a given message to the mosquitto broker
     * @param string $topic topic
     * @param string $message payload
     * @param string $qos quality of service used to send the message  ('0', '1' or '2')
     * @param string $retain whether or not the message is a retained message ('0' or '1')
     */
    public static function publishMosquitto($topic, $payload, $qos , $retain) {

        $mosqHost = config::byKey('mqttAdress', 'jMQTT', '127.0.0.1');
        $mosqPort = config::byKey('mqttPort', 'jMQTT', '1883');

        $client = self::newMosquittoClient('_pub', 'debug');

        $client->onConnect(function() use ($client, $topic, $payload, $qos, $retain) {
            log::add('jMQTT', 'debug', 'Envoi du message ' . $payload . ' vers ' . $topic . ' (pid=' .
                     getmypid() . ')');
            $client->publish($topic, $payload, $qos, $retain);
            $client->disconnect();
        });
        
        $client->connect($mosqHost, $mosqPort, 60);

        // Loop around to permit the library to do its work
        // This function will call the callback defined in `onConnect()`
        // and disconnect properly when the message has been sent
        $client->loopForever();
        log::add('jMQTT', 'debug', 'Message envoyé');
    }

    public static function dependancy_info() {
        $return = array();
        $return['log'] = 'jMQTT_dep';
        $return['state'] = 'nok';

        $cmd = "dpkg -l | grep mosquitto";
        exec($cmd, $output, $return_var);
        //lib PHP exist
        $libphp = extension_loaded('mosquitto');

        if ($output[0] != "" && $libphp) {
            $return['state'] = 'ok';
        }
        log::add('jMQTT', 'debug', 'Lib : ' . print_r(get_loaded_extensions(),true));

        return $return;
    }

    public static function dependancy_install() {
        log::add('jMQTT','info','Installation des dépéndances');
        $resource_path = realpath(dirname(__FILE__) . '/../../resources');
        passthru('sudo /bin/bash ' . $resource_path . '/install.sh ' . $resource_path . ' > ' .
                 log::getPathToLog('jMQTT_dep') . ' 2>&1 &');
        return true;
    }
}

class jMQTTCmd extends cmd {

    /**
     * Create a new command. Command is not saved.
     * @param integer $_eqptId equipment id the command belongs to
     * @param string $_name command name
     * @param string $_topic command mqtt topic
     * @param integer $_parseJson whether or not the payload shall be decoded as Json (0 or 1)
     * @return new command
     */
    public static function newCmd($_eqptId, $_name, $_topic, $_parseJson) {
        $cmd = new jMQTTCmd();
        $cmd->setEqLogic_id($_eqptId);
        $cmd->setEqType('jMQTT');
        $cmd->setIsVisible(1);
        $cmd->setIsHistorized(0);
        $cmd->setSubType('string');
        $cmd->setLogicalId($_topic);
        $cmd->setType('info');
        $cmd->setName($_name);
        $cmd->setConfiguration('topic', $_topic);
        $cmd->setConfiguration('parseJson', $_parseJson);
        log::add('jMQTT', 'info', 'Creating command ' . $_name . ' for topic ' . $_topic);
        return $cmd;
    }

    /**
     * Update this command value, save and inform all stakeholders
     * @param string $value new command value
     */
    public function updateCmdValue($value) {
        // Update the configuration value that is displayed inside the equipment command tab
        $this->setConfiguration('value', $value);
        $this->save();

        // Update the command value
        $eqLogic = $this->getEqLogic();
        $eqLogic->checkAndUpdateCmd($this, $value);
            
        log::add('jMQTT', 'info', $eqLogic->getName() . '->' . $this->getName() . ' = ' . $value);
    }
    
    /**
     * Decode the given JSON decode array and update command values.
     * Commands are created when they do not exist.
     * If the given JSON structure contains other JSON structure, call this routine
     * recursively.
     * @param eqLogic $_eqLogic current equipment
     * @param array $jsonArray JSON decoded array to parse
     * @param string $_cmdName command name prefix
     * @param string $_topic mqtt topic prefix
     */
    public static function decodeJsonMessage($_eqLogic, $_jsonArray, $_cmdName, $_topic) {
        foreach ($_jsonArray as $id => $value) {
            $jsonTopic = $_topic    . '{' . $id . '}';
            $jsonName  = $_cmdName  . '{' . $id . '}';
            $cmd = jMQTTCmd::byEqLogicIdAndLogicalId($_eqLogic->getId(), $jsonTopic);
            if (!is_object($cmd)) {
                // parseJson=0 by default
                $cmd = jMQTTCmd::newCmd($_eqLogic->getId(), $jsonName, $jsonTopic, 0);
            }

            // json_encode is used as it works whatever the type of $value
            // (array, boolean, ...)
            $cmd->updateCmdValue(json_encode($value));
            
            // If the current command is a JSON structure that shall be decoded, call
            // this routine recursively
            if ($cmd->getConfiguration('parseJson') == 1 && is_array($value))
                jMQTTCmd::decodeJsonMessage($_eqLogic, $value, $jsonName, $jsonTopic);
        }
    }
    
    public function execute($_options = null) {
        switch ($this->getType()) {
        case 'info' :
            return $this->getConfiguration('value');
            break;

        case 'action' :
            $request = $this->getConfiguration('request');
            $topic = $this->getConfiguration('topic');
            $qos = $this->getConfiguration('Qos');

            if ($this->getConfiguration('retain') == 0) $retain = false;
            else $retain = true;
	  
            if ($qos == NULL) $qos = 1; //default to 1

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
                    if ( $_options['title'] == '') {
                        throw new Exception(__('Le sujet ne peuvent être vide', __FILE__));
                    }
                    $request = str_replace($replace, $replaceBy, $request);

                }
                else
                    $request = 1;

                break;
            default : $request == null ?  1 : $request;

            }
            $request = jeedom::evaluateExpression($request);
            $eqLogic = $this->getEqLogic();

            jMQTT::publishMosquitto($topic, $request, $qos, $retain);

            $result = $request;
            return $result;
        }
        return true;
    }
}
