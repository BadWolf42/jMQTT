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

    /**
     * Create a new equipment that will subscribe to $topic0/#
     * The equipment is not saved
     * @param string $topic0 first topic level
     * return new jMQTT object
     */
    private static function newEquipment($topic0) {
        log::add('jMQTT', 'info', 'Creation device ' . $topic0);
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

    public function preSave() {
        $this->setConfiguration('reload_d', '0');
        if (config::byKey('mqttAuto', 'jMQTT', 0) == 0) {  // manual mode
            //check if some change needs reloading daemon
            $_logicalId = $this->getLogicalId();
            $_topic     = $this->getConfiguration('topic');
            $_qos       = $this->getConfiguration('Qos', 1);
            $_isActive  = $this->getIsEnable();
            
            log::add('jMQTT', 'debug', 'preSave: ' . $_logicalId . ', ' . $_topic . ', ' . $_qos . ', ' . $_isActive);

            if ($_logicalId != $_topic) {
                $this->setLogicalId($_topic);
                $this->setConfiguration('reload_d', '1');
            }

            if ($_qos != $this->getConfiguration('prev_Qos')) {
                $this->setConfiguration('prev_Qos', $_qos);
                $this->setConfiguration('reload_d', '1');
            }

            if ($_isActive != $this->getConfiguration('prev_isActive')) {
                $this->setConfiguration('prev_isActive', $_isActive);
                $this->setConfiguration('reload_d', '1');
            }            
        }
        log::add('jMQTT', 'debug', 'preSave: reload_d set to ' . $this->getConfiguration('reload_d') .
                 ' on equipment ' .
        $this->getName());
    }

    public function postSave() {
        if ($this->getConfiguration('reload_d') == "1") {
            log::add('jMQTT', 'debug', 'postSave: restart daemon');
            $cron = cron::byClassAndFunction('jMQTT', 'daemon');
            //Restarting mqtt daemon
            if (is_object($cron) && $cron->running()) {
                $cron->halt();
                $cron->run();
            }
        }
    }
  
    public function postRemove() {
        if (config::byKey('mqttAuto', 'jMQTT', 0) == 0) {  // manual mode
            $cron = cron::byClassAndFunction('jMQTT', 'daemon');
            //Restarting mqtt daemon
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
        log::add('jMQTT', 'debug', 'daemon_start');
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
        log::add('jMQTT', 'debug', 'daemon_stop');
        $cron = cron::byClassAndFunction('jMQTT', 'daemon');
        if (!is_object($cron)) {
            throw new Exception(__('Tache cron introuvable', __FILE__));
        }
        $cron->halt();
    }

    /**
     * Daemon method called by cron
     */
    public static function daemon() {

        // Create mosquitto client
        $client = self::newMosquittoClient('');
        
        // Set callbacks
        $client->onConnect(function($r, $message) use ($client) {
            log::add('jMQTT', 'info', 'mosquitto: connection response is ' . $message);
            $client->publish(jMQTT::getMqttId() . '/status', 'online', 1, 1);
            config::save('status', '1',  'jMQTT');
        });

        $client->onDisconnect(function($r) use ($client) {
            $msg = ($r == 0) ? 'on client request' : 'unexpectedly';
            log::add('jMQTT', 'debug', 'mosquitto: disconnected from broker' . $msg);
            $client->publish(jMQTT::getMqttId() . '/status', 'offline', 1, 1);
            config::save('status', '0',  'jMQTT');
        });

        //$client->onDisconnect('jMQTT::mosquittoDisconnect');
        $client->onSubscribe('jMQTT::mosquittoSubscribe');
        $client->onMessage('jMQTT::mosquittoMessage');
        $client->onLog('jMQTT::mosquittoLog');

        // Defines last will terminaison message
        $client->setWill(self::getMqttId() . '/status', 'offline', 1, 1);

        try {
            $mosqHost = config::byKey('mqttAdress', 'jMQTT', '127.0.0.1');
            $mosqPort = config::byKey('mqttPort', 'jMQTT', '1883');

            log::add('jMQTT', 'info', 'Connect to mosquitto broker: Host=' . $mosqHost . ', Port=' . $mosqPort .
                     ', Id=' . self::getMqttId());
            $client->connect($mosqHost, $mosqPort, 60);

            if (config::byKey('mqttAuto', 'jMQTT', 0) == 0) {  // manual mode
                foreach (eqLogic::byType('jMQTT', true) as $mqtt) {
                    $topic = $mqtt->getConfiguration('topic');
                    $qos   = (int) $mqtt->getConfiguration('Qos', '1');
                    log::add('jMQTT', 'info', 'Equipment ' . $mqtt->getName() . ' subscribes to topic ' .
                              $topic . ' with Qos=' . $qos);
                    $client->subscribe($topic, $qos); // Subscribe to topic
                }
            }
            else { // auto mode
                $mosqTopic = config::byKey('mqttTopic', 'jMQTT', '#');
                $mosqQos   = config::byKey('mqttQos', 'jMQTT', 1);
                // Subscribe to topic (root by default)
                $client->subscribe($mosqTopic, $mosqQos);
                log::add('jMQTT', 'debug', 'Subscribe to topic ' . $mosqTopic);
            }

            $client->loopForever();
            //while (true) { $client->loop(); }
        }
        catch (Exception $e){
            log::add('jMQTT', 'error', $e->getMessage());
        }

        log::add('jMQTT', 'error', 'Terminaison démon');
    }

    public function stopDaemon() {
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

    public static function mosquittoSubscribe($mid, $qosCount) {
        log::add('jMQTT', 'debug', 'mosquitto: topic subscription accepted, mid=' . $mid . ' ,qosCount=' . $qosCount);
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
        log::add('jMQTT', 'debug', 'Message ' . $message->payload . ' sur ' . $message->topic);

        $msgTopic = $message->topic;
        $msgValue = $message->payload;

        $msgTopicArray = explode("/", $msgTopic);

        if(!ctype_print($msgTopic) || empty($msgTopic)) {
            log::add('jMQTT', 'debug', 'Message skipped : "' . $message->topic . '" is not a valid topic');
            return;
        }

        // Loop on enabled jMQTT equipment and get ones that listen
        // the current message
        $elogics = array();
        foreach (eqLogic::byType('jMQTT', true) as $eqpt) {
            if ($message->topicMatchesSub($msgTopic,
                $eqpt->getConfiguration('topic'))) {
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
        // Loop on equipments listening to the current message
        //
        foreach($elogics as $eqpt) {

            $eqpt->setStatus('lastCommunication', date('Y-m-d H:i:s'));
            $eqpt->save();

            // Determine the name of the command
            // Suppress starting topic levels that are common with the equipment
            // suscribing topic
            $sbscrbTopicArray = explode("/", $eqpt->getLogicalId());
            reset($msgTopicArray);
            foreach($sbscrbTopicArray as $s) {
                if ($s == '#' || $s == '+')
                    break;
                else
                    next($msgTopicArray);
            }
            $cmdName = current($msgTopicArray) === false ?
                       end($msgTopicArray) : current($msgTopicArray);
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
            log::add('jMQTT', 'debug', 'Envoi du message ' . $payload . ' vers ' . $topic);
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
