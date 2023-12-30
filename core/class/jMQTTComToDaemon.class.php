<?php

class jMQTTComToDaemon {

    public static function hb() {
        if (!jMQTTDaemon::state()) {
            return;
        }
        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/daemon/hb');
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        } else {
            jMQTT::logger(
                'debug',
                sprintf(
                    __("Impossible d'envoyer un message au Démon sur le port %1\$s, erreur %2\$s", __FILE__),
                    $port,
                    curl_error($curl)
                )
            );
        }
        jMQTT::logger('debug', __METHOD__ . '(): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function setLogLevel($_level=null) {
        $level = is_null($_level) ? log::getLogLevel(__class__) : $_level;
        if ($level == 'default') // Replace 'default' log level
            $level = log::getConfig('log::level');
        if (is_numeric($level)) // Replace numeric log level par text level
            $level = log::convertLogLevel($level);

        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(level=' . $level . '): Daemon not started');
            throw new Exception(__("Le démon n'est pas démarré", __FILE__));
        }

        $url = 'http://127.0.0.1:' . jMQTTDaemon::getPort();
        $url .= '/daemon/loglevel?name=jmqtt&level=' . $level;
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(level=' . $level . '): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function changeApiKey($newApiKey) {
        if (
            !jMQTTDaemon::state()
            || is_null($newApiKey)
            || strlen(trim($newApiKey)) == 0
        ) {
            return;
        }
        $url = 'http://127.0.0.1:' . jMQTTDaemon::getPort();
        $url .= '/daemon/loglevel?newapikey=' . trim($newApiKey);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(newApiKey=' . trim($newApiKey) . '): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function initDaemon($params) {
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(...): Daemon not started');
            return;
        }
        $payload = json_encode($params, JSON_UNESCAPED_UNICODE);
        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/daemon');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(...): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function brokerSet($params) {
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(' . $payload . '): Daemon not started');
            return;
        }
        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/broker');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(' . $payload . '): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function brokerDel($id) {
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): Daemon not started');
            return;
        }
        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/broker/' . $id);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function brokerRestart($id) {
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): Daemon not started');
            return;
        }
        $url = 'http://127.0.0.1:' . jMQTTDaemon::getPort();
        $url .= '/broker/' . $id . '/restart';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function brokerPublish($id, $topic, $payload, $qos = 1, $retain = false) {
        if (empty($topic))
            return;
        $params = array(
            'topic' => $topic,
            'payload' => $payload,
            'qos' => $qos,
            'retain' => $retain
        );
        $data = json_encode($params, JSON_UNESCAPED_SLASHES);
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(id=' . $id . ', data=' . $data . '): Daemon not started');
            return;
        }
        $url = 'http://127.0.0.1:' . jMQTTDaemon::getPort();
        $url .= '/broker/' . $id . '/publish';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . ', data=' . $data . '): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function brokerRealTimeStart(
        $id,
        $subscribe,
        $exclude,
        $retained,
        $duration = 180
    ) {
        $params['cmd'] = 'realTimeStart';
        $params['id'] = $id;
        $params['file'] = jeedom::getTmpFolder(jMQTT::class).'/rt' . $id . '.json';
        $params['subscribe'] = $subscribe;
        $params['exclude'] = $exclude;
        $params['retained'] = $retained;
        $params['duration'] = $duration;
        // TODO: Implement this method
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . ', data=' . $payload . '): NOT IMPLEMENTED' /* . curl_errno($curl) */);
    }

    public static function brokerRealTimeStop($id) {
        // TODO: Implement this method
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): ' /* . curl_errno($curl) */);
    }

    public static function brokerRealTimeGet($id, $since) {
        // TODO: Implement this method
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . ', since=' . $since . '): NOT IMPLEMENTED' /* . curl_errno($curl) */);
    }

    public static function brokerRealTimeClear($id) {
        // TODO: Implement this method
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): NOT IMPLEMENTED' /* . curl_errno($curl) */);
    }


    public static function eqptSet($params) {
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(' . $payload . '): Daemon not started');
            return;
        }
        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/equipment');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(' . $payload . '): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function eqptDel($id) {
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): Daemon not started');
            return;
        }
        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/equipment/' . $id);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): ' . curl_errno($curl));
        curl_close($curl);
    }


    public static function cmdSet($params) {
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(' . $payload . '): Daemon not started');
            return;
        }
        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/command');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(' . $payload . '): ' . curl_errno($curl));
        curl_close($curl);
    }

    public static function cmdDel($id) {
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): Daemon not started');
            return;
        }
        $port = jMQTTDaemon::getPort();
        $curl = curl_init('http://127.0.0.1:' . $port . '/command/' . $id);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        if (curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }
        jMQTT::logger('debug', __METHOD__ . '(id=' . $id . '): ' . curl_errno($curl));
        curl_close($curl);
    }

}
