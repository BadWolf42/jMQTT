<?php

class jMQTTComToDaemon {

    public static function send($params, $except = true) {
        if (!jMQTTDaemon::state()) {
            throw new Exception(__("Le démon n'est pas démarré", __FILE__));
        }

        // $params['id'] = $id;
        $payload = json_encode($params);
        // $payload = http_build_query($params);

        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/URL');
        curl_setopt($curl, CURLOPT_POST, true);
        // curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jeedom::getApiKey(jMQTT::class)
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        } else {
            // jMQTT::logger(
            //     'debug',
            //     sprintf(
            //         __("Impossible de se connecter du Démon sur le port %1\$s, erreur %2\$s", __FILE__),
            //         $port,
            //         socket_strerror(socket_last_error($socket))
            //     )
            // );
            // jMQTT::logger(
            //     'debug',
            //     sprintf(
            //         __("Impossible d'envoyer un message au Démon sur le port %1\$s, erreur %2\$s", __FILE__),
            //         $port,
            //         socket_strerror(socket_last_error($socket))
            //     )
            // );
        }
        curl_close($curl);
        // jMQTT::logger('debug', sprintf("send: port=%1\$s, payload=%2\$s", $port, json_encode($params)));
    }


    public static function hb() {
        if (!jMQTTDaemon::state()) {
            return;
        }

        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/daemon/hb');
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jeedom::getApiKey(jMQTT::class)
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(array()));
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        curl_close($curl);
    }

    public static function setLogLevel($_level=null) {
        if (!jMQTTDaemon::state()) {
            throw new Exception(__("Le démon n'est pas démarré", __FILE__));
        }

        $params['level'] = is_null($_level) ? log::getLogLevel(__class__) : $_level;
        if ($params['level'] == 'default') // Replace 'default' log level
            $params['level'] = log::getConfig('log::level');
        if (is_numeric($params['level'])) // Replace numeric log level par text level
            $params['level'] = log::convertLogLevel($params['level']);
        $payload = http_build_query($params);

        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/daemon/loglevel');
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jeedom::getApiKey(jMQTT::class)
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        curl_close($curl);
    }

    public static function changeApiKey($newApiKey) {
        if (!jMQTTDaemon::state()) {
            return;
        }

        $params['option'] = $newApiKey;
        $payload = http_build_query($params);

        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/daemon/api');
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jeedom::getApiKey(jMQTT::class)
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        curl_close($curl);
    }

    public static function initDaemon($params) {
        if (!jMQTTDaemon::state()) {
            throw new Exception(__("Le démon n'est pas démarré", __FILE__));
        }

        $payload = json_encode($params, JSON_UNESCAPED_UNICODE);

        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/daemon');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jeedom::getApiKey(jMQTT::class)
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        curl_close($curl);
    }

    public static function newClient($id, $params = array()) {
        jMQTT::logger('debug', "jMQTTComToDaemon::newClient(): <<UNUSED>>");
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
