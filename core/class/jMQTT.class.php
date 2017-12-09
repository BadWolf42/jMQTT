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

    public function preSave() {
        $this->setConfiguration('reload_d', '0');
        if (config::byKey('mqttAuto', 'jMQTT', 0) == 0) {  // manual mode
            //check if some change needs reloading daemon
            $_logicalId = $this->getLogicalId();
            $_topic     = $this->getConfiguration('topic');
            $_wcard     = $this->getConfiguration('wcard');
            $_qos       = $this->getConfiguration('Qos');

            if ($_logicalId != $_topic) {
                $this->setLogicalId($_topic);
                $this->setConfiguration('reload_d', '1');
            }

            if ($_wcard != $this->getConfiguration('prev_wcard')) {
                $this->setConfiguration('prev_wcard', $_wcard);
                $this->setConfiguration('reload_d', '1');
            }
                        
            if ($_qos != $this->getConfiguration('prev_Qos')) {
                $this->setConfiguration('prev_Qos', $_qos);
                $this->setConfiguration('reload_d', '1');
            }
            
        }
        log::add('jMQTT', 'debug', 'preSave: reload_d set to ' . $this->getConfiguration('reload_d'));
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
        message::add('jMQTT', 'chargement info démon');
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

    public static function daemon() {

    	$mosqHost = config::byKey('mqttAdress', 'jMQTT', '127.0.0.1');
    	$mosqPort = config::byKey('mqttPort', 'jMQTT', '1883');
    	$mosqId = config::byKey('mqttId', 'jMQTT', 'Jeedom');
        $mosqTopic = config::byKey('mqttTopic', 'jMQTT', '#');
        $mosqQos = config::byKey('mqttQos', 'jMQTT', 1);

        //$mosqAuth = config::byKey('mqttAuth', 'jMQTT', 0);
        $mosqUser = config::byKey('mqttUser', 'jMQTT', 0);
        $mosqPass = config::byKey('mqttPass', 'jMQTT', 0);
        //$mosqSecure = config::byKey('mqttSecure', 'jMQTT', 0);
        //$mosqCA = config::byKey('mqttCA', 'jMQTT', 0);
        //$mosqTree = config::byKey('mqttTree', 'jMQTT', 0);
        log::add('jMQTT', 'info', 'Paramètres utilisés, Host : ' . $mosqHost . ', Port : ' . $mosqPort . ', ID : ' . $mosqId);
        if (isset($mosqHost) && isset($mosqPort) && isset($mosqId)) {
            //https://github.com/mqtt/mqtt.github.io/wiki/mosquitto-php
            $client = new Mosquitto\Client($mosqId);
            //if ($mosqAuth) {
            //$client->setCredentials($mosqUser, $mosqPass);
            //}
            //if ($mosqSecure) {
            //$client->setTlsOptions($certReqs = Mosquitto\Client::SSL_VERIFY_PEER, $tlsVersion = 'tlsv1.2', $ciphers=NULL);
            //$client->setTlsCertificates($caPath = 'path/to/my/ca.crt');
            //}
            $client->onConnect('jMQTT::connect');
            $client->onDisconnect('jMQTT::disconnect');
            $client->onSubscribe('jMQTT::subscribe');
            $client->onMessage('jMQTT::message');
            $client->onLog('jMQTT::logmq');
            $client->setWill('/jeedom', "Client died :-(", 1, 0);

            try {
                if (isset($mosqUser)) {
                    $client->setCredentials($mosqUser, $mosqPass);
                }
                $client->connect($mosqHost, $mosqPort, 60);

                if (config::byKey('mqttAuto', 'jMQTT', 0) == 0) {  // manual mode
                    foreach (eqLogic::byType('jMQTT', true) as $mqtt) {
                        $devicetopic = $mqtt->getConfiguration('topic');
                        $wildcard    = $mqtt->getConfiguration('wcard');
                        $qos         = (int)$mqtt->getConfiguration('Qos');
                        if (!$qos) $qos = 1;
                        if($wildcard) {
                            $fulltopic = $devicetopic . "/" . $wildcard;
                        }
                        else $fulltopic = $devicetopic;
                        log::add('jMQTT', 'info', 'Subscribe to topic ' . $fulltopic);
                        $client->subscribe($fulltopic, $qos); // Subscribe to topic
                    }
                }
                else {
                    $client->subscribe($mosqTopic, $mosqQos); // !auto: Subscribe to root topic
                    log::add('jMQTT', 'debug', 'Subscribe to topic ' . $mosqtopic);
                }

                //$client->loopForever();
                while (true) { $client->loop(); }
            }
            catch (Exception $e){
                log::add('jMQTT', 'error', $e->getMessage());
            }
        } else {
            log::add('jMQTT', 'info', 'Tous les paramètres ne sont pas définis');
        }
    }

    public function stopDaemon() {
        $cron = cron::byClassAndFunction('jMQTT', 'daemon');
        $cron->stop();
    }

    public static function connect( $r, $message ) {
        log::add('jMQTT', 'info', 'Connexion à Mosquitto avec code ' . $r . ' ' . $message);
        config::save('status', '1',  'jMQTT');
    }

    public static function disconnect( $r ) {
        log::add('jMQTT', 'debug', 'Déconnexion de Mosquitto avec code ' . $r);
        config::save('status', '0',  'jMQTT');
    }

    public static function subscribe($mid, $qosCount) {
        log::add('jMQTT', 'debug', 'Subscribed');
    }

    public static function logmq( $code, $str ) {
        log::add('jMQTT', 'debug', $code . ' : ' . $str);
    }

    public static function message( $message ) {
        log::add('jMQTT', 'debug', 'Message ' . $message->payload . ' sur ' . $message->topic);
        $topic = $message->topic;

        if(!ctype_print($topic) || empty($topic)) {
            log::add('jMQTT', 'debug', 'Message skipped : "'.$message->topic.'" is not a valid topic');
            return;
        }

        $topicArray = explode("/", $topic);
        $cmdId = end($topicArray);
        $key = count($topicArray) - 1;
        unset($topicArray[$key]);
        $nodeid = (implode($topicArray,'/'));
        $value = $message->payload;

        log::add('jMQTT', 'debug', 'nodeid: ' . $nodeid);
        $elogic = self::byLogicalId($nodeid, 'jMQTT');
        log::add('jMQTT', 'debug', 'nodeid: ' . $nodeid);

        if (is_object($elogic)) {
            $elogic->setStatus('lastCommunication', date('Y-m-d H:i:s'));
            $elogic->save();
        }
        elseif (config::byKey('mqttAuto', 'jMQTT', 0) == 1) {
            $elogic = new jMQTT();
            $elogic->setEqType_name('jMQTT');
            $elogic->setLogicalId($nodeid);
            $elogic->setName($nodeid);
            $elogic->setIsEnable(true);
            $elogic->setStatus('lastCommunication', date('Y-m-d H:i:s'));
            $elogic->setConfiguration('topic', $nodeid);
            $elogic->setConfiguration('wcard', '+');
            $elogic->setConfiguration('prev_wcard', '+');
            $elogic->setConfiguration('Qos', '1');
            $elogic->setConfiguration('prev_Qos', '1');
            $elogic->setConfiguration('reload_d', '0');
            log::add('jMQTT', 'info', 'Saving device');
            $elogic->save();
        }
        else {
            log::add('jMQTT', 'warning', 'No equipment listening ' . $topic . ' found');
        }
            
        log::add('jMQTT', 'info', 'Message texte : ' . $value . ' pour information : ' . $cmdId . ' sur : ' . $nodeid);
        $cmdlogic = jMQTTCmd::byEqLogicIdAndLogicalId($elogic->getId(),$cmdId);
        if (!is_object($cmdlogic)) {
            log::add('jMQTT', 'info', 'Cmdlogic n existe pas, creation');
            $cmdlogic = new jMQTTCmd();
            $cmdlogic->setEqLogic_id($elogic->getId());
            $cmdlogic->setEqType('jMQTT');
            $cmdlogic->setIsVisible(1);
            $cmdlogic->setIsHistorized(0);
            $cmdlogic->setSubType('string');
            $cmdlogic->setLogicalId($cmdId);
            $cmdlogic->setType('info');
            $cmdlogic->setName( $cmdId );
            $cmdlogic->setConfiguration('topic', $topic);
            $cmdlogic->setConfiguration('parseJson', 0); //default don't parse json data
            $cmdlogic->save();
        }
        $cmdlogic->setConfiguration('value', $value);
        $cmdlogic->save();
        $cmdlogic->event($value);

        if ($value[0] == '{' && substr($value, -1) == '}' && $cmdlogic->getConfiguration('parseJson') == 1) {
            // payload is json
            $nodeid = $topic;
            $json = json_decode($value);
            foreach ($json as $cmdId => $value) {
                $topicjson = $topic . '{' . $cmdId . '}';
                log::add('jMQTT', 'info', 'Message json : ' . $value . ' pour information : ' . $cmdId . ' sur : ' . $nodeid);
                $cmdlogic = jMQTTCmd::byEqLogicIdAndLogicalId($elogic->getId(),$cmdId);
                if (!is_object($cmdlogic)) {
                    log::add('jMQTT', 'info', 'Cmdlogic n existe pas, creation');
                    $cmdlogic = new jMQTTCmd();
                    $cmdlogic->setEqLogic_id($elogic->getId());
                    $cmdlogic->setEqType('jMQTT');
                    $cmdlogic->setIsVisible(1);
                    $cmdlogic->setIsHistorized(0);
                    $cmdlogic->setSubType('string');
                    $cmdlogic->setLogicalId($cmdId);
                    $cmdlogic->setType('info');
                    $cmdlogic->setName( $cmdId );
                    $cmdlogic->setConfiguration('topic', $topicjson);
                    $cmdlogic->save();
                }
                $cmdlogic->setConfiguration('value', $value);
                $cmdlogic->save();
                $cmdlogic->event($value);
            }
        }
    }

    public static function publishMosquitto($subject, $message, $qos , $retain) {
        log::add('jMQTT', 'debug', 'Envoi du message ' . $message . ' vers ' . $subject);
        $mosqHost = config::byKey('mqttAdress', 'jMQTT', 0);
        $mosqPort = config::byKey('mqttPort', 'jMQTT', 0);
        $mosqId = config::byKey('mqttId', 'jMQTT', 0);
        if ($mosqHost == '') {
            $mosqHost = '127.0.0.1';
        }
        if ($mosqPort == '') {
            $mosqPort = '1883';
        }
        if ($mosqId == '') {
            $mosqId = 'Jeedom';
        }
        $mosqPub = $mosqId . '_pub';
        $mosqUser = config::byKey('mqttUser', 'jMQTT', 0);
        $mosqPass = config::byKey('mqttPass', 'jMQTT', 0);
        $publish = new Mosquitto\Client($mosqPub);
        if (isset($mosqUser)) {
            $publish->setCredentials($mosqUser, $mosqPass);
        }

        $publish->onConnect(function() use ($publish, $subject, $message, $qos, $retain) {
            $publish->publish($subject, $message, $qos, $retain);
            $publish->disconnect();
        });
        
        $publish->connect($mosqHost, $mosqPort, 60);

        // Loop around to permit the library to do its work
        // This function will call the callback defined in `onConnect()`
        // and disconnect cleanly when the message has been sent
        $publish->loopForever();
        log::add('jMQTT', 'debug', 'Message envoyé');
        unset($publish);
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
        passthru('sudo /bin/bash ' . $resource_path . '/install.sh ' . $resource_path . ' > ' . log::getPathToLog('jMQTT_dep') . ' 2>&1 &');
        return true;
    }

}

class jMQTTCmd extends cmd {
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
