<?php

class jMQTTComToDaemon {

    public static function send($params, $except = true) {
        if (!jMQTTDaemon::state()) {
            if ($except)
                throw new Exception(__("Le démon n'est pas démarré", __FILE__));
            else
                return;
        }
        $params['apikey'] = jeedom::getApiKey(jMQTT::class);
        $payload = json_encode($params);
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
        $port = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_PORT)->getValue(0);
        if (!socket_connect($socket, '127.0.0.1', $port)) {
            jMQTT::logger(
                'debug',
                sprintf(
                    __("Impossible de se connecter du Démon sur le port %1\$s, erreur %2\$s", __FILE__),
                    $port,
                    socket_strerror(socket_last_error($socket))
                )
            );
            return;
        }
        if (socket_write($socket, $payload, strlen($payload)) === false) {
            jMQTT::logger(
                'debug',
                sprintf(
                    __("Impossible d'envoyer un message au Démon sur le port %1\$s, erreur %2\$s", __FILE__),
                    $port,
                    socket_strerror(socket_last_error($socket))
                )
            );
            return;
        }
        socket_close($socket);
        // jMQTT::logger('debug', sprintf("sendToDaemon: port=%1\$s, payload=%2\$s", $port, $payload));
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
    }


    public static function hb() {
        $params['cmd']      = 'hb';
        jMQTTComToDaemon::send($params, false);
    }

    public static function setLogLevel($_level=null) {
        $params['cmd']      = 'loglevel';
        $params['id']       = 0;
        $params['level']    = is_null($_level) ? log::getLogLevel(__class__) : $_level;
        if ($params['level'] == 'default') // Replace 'default' log level
            $params['level'] = log::getConfig('log::level');
        if (is_numeric($params['level'])) // Replace numeric log level par text level
            $params['level'] = log::convertLogLevel($params['level']);
        jMQTTComToDaemon::send($params);
    }

    public static function changeApiKey($newApiKey) {
        $params['cmd']       = 'changeApiKey';
        $params['id']        = 0;
        $params['newApiKey'] = $newApiKey;
        // Inform Daemon without generating exception if not running
        jMQTTComToDaemon::send($params, false);
    }

    public static function newClient($id, $params = array()) {
        $params['cmd']      = 'newMqttClient';
        $params['id']       = $id;
        jMQTTComToDaemon::send($params);
    }

    public static function removeClient($id) {
        $params['cmd']      = 'removeMqttClient';
        $params['id']       = $id;
        jMQTTComToDaemon::send($params);
    }


    public static function subscribe($id, $topic, $qos = 1) {
        if (empty($topic)) return;
        $params['cmd']   = 'subscribeTopic';
        $params['id']    = $id;
        $params['topic'] = $topic;
        $params['qos']   = $qos;
        jMQTTComToDaemon::send($params);
    }

    public static function unsubscribe($id, $topic) {
        if (empty($topic)) return;
        $params['cmd']   = 'unsubscribeTopic';
        $params['id']    = $id;
        $params['topic'] = $topic;
        jMQTTComToDaemon::send($params);
    }


    public static function publish($id, $topic, $payload, $qos = 1, $retain = false) {
        if (empty($topic)) return;
        $params['cmd']     = 'messageOut';
        $params['id']      = $id;
        $params['topic']   = $topic;
        $params['payload'] = $payload;
        $params['qos']     = $qos;
        $params['retain']  = $retain;
        jMQTTComToDaemon::send($params);
    }


    public static function realTimeStart(
        $id,
        $subscribe,
        $exclude,
        $retained,
        $duration = 180
    ) {
        $params['cmd']       = 'realTimeStart';
        $params['id']        = $id;
        $params['file']      = jeedom::getTmpFolder(jMQTT::class).'/rt' . $id . '.json';
        $params['subscribe'] = $subscribe;
        $params['exclude']   = $exclude;
        $params['retained']  = $retained;
        $params['duration']  = $duration;
        jMQTTComToDaemon::send($params);
    }

    public static function realTimeStop($id) {
        $params['cmd'] = 'realTimeStop';
        $params['id']  = $id;
        jMQTTComToDaemon::send($params);
    }

    public static function realTimeClear($id) {
        $params['cmd']  = 'realTimeClear';
        $params['id']   = $id;
        $params['file'] = jeedom::getTmpFolder(jMQTT::class).'/rt' . $id . '.json';
        jMQTTComToDaemon::send($params);
    }


}
