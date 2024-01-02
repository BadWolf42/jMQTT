<?php

class jMQTTComToDaemon {

    /**
     * Send a request to jMQTT daemon REST API
     *
     * @param string $method Either GET, PUT, POST or DELETE
     * @param string $path Path part of the destination URL (with leading /)
     * @param string|null $payload Payload to send in the BODY (or null)
     * @param string $logfname Function name to put in debug logs (__METHOD__)
     * @param string $logparams Function parameters to put in debug logs
     * @param bool $result True if curl_exec should return the transfer
     * @return string|bool|void curl_exec return value
     */
    public static function doSend($method, $path, $payload, $logfname, $logparams, $result=false) {
        $logheader = $logfname . '(' . $logparams;
        if (!jMQTTDaemon::state()) {
            jMQTT::logger('debug', $logheader . '): Daemon not started');
            return;
        }

        $curl = curl_init('http://127.0.0.1:' . jMQTTDaemon::getPort() . $path);

        if ($method == 'GET' || empty($method)) {
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        } elseif ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
        } else {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (!is_null($payload)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));

        if ($result) {
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        }

        if ($res = curl_exec($curl)) {
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        }

        jMQTT::logger('debug', $logheader . '): ' . curl_errno($curl));
        curl_close($curl);

        return $res;
    }


    // ------------------------------------------------------------------------
    // Daemon related function
    public static function initDaemon($params) {
        $payload = json_encode($params, JSON_UNESCAPED_UNICODE);
        self::doSend('POST', '/daemon', $payload, __METHOD__, '...');
        // jMQTT::logger(
        //     'debug',
        //     'INIT res: ' .
        //     self::doSend('POST', '/daemon', $payload, __METHOD__, '...', true)
        // );
    }

    public static function hb() {
        if (!jMQTTDaemon::state()) {
            return;
        }
        self::doSend('PUT', '/daemon/hb', null, __METHOD__, '');
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

        $path = '/daemon/loglevel?name=jmqtt&level=' . $level;
        self::doSend('PUT', $path, null, __METHOD__, 'level=' . $level);
    }

    public static function changeApiKey($newApiKey) {
        if (
            is_null($newApiKey)
            || strlen(trim($newApiKey)) == 0
        ) {
            return;
        }

        $path = '/daemon/api?newapikey=' . trim($newApiKey);
        self::doSend('PUT', $path, null, __METHOD__, 'newapikey=' . trim($newApiKey));
    }

    // ------------------------------------------------------------------------
    // Broker related function
    public static function brokerSet($params) {
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
        self::doSend('POST', '/broker', $payload, __METHOD__,  $payload);
    }

    public static function brokerDel($id) {
        self::doSend('DELETE', '/broker/' . $id, null, __METHOD__, 'id=' . $id);
    }

    public static function brokerRestart($id) {
        $path = '/broker/' . $id . '/restart';
        self::doSend('GET', $path, null, __METHOD__, 'id=' . $id);
    }

    public static function brokerPublish($id, $topic, $payload, $qos=1, $retain=false) {
        if (empty($topic))
            return;
        $params = array(
            'topic' => strval($topic),
            'payload' => strval($payload),
            'qos' => intval($qos),
            'retain' => boolval($retain)
        );
        $data = json_encode($params, JSON_UNESCAPED_SLASHES);
        $path = '/broker/' . $id . '/publish';
        self::doSend('POST', $path, $data, __METHOD__, 'id=' . $id . ', data=' . $data);
    }

    // ------------------------------------------------------------------------
    // Real Time related function
    /**
     * Send a request to start Real Time Mode
     *
     * @param int $id Broker id on which start real time
     * @param string[] $subscribe Topics to subscribe in real time to
     * @param string[] $exclude Topics to exclude from real time
     * @param bool $retained Include retained messages
     * @param int $duration Duration of Real Time in seconds
     */
    public static function brokerRealTimeStart(
        $id,
        $subscribe,
        $exclude,
        $retained,
        $duration = 180
    ) {
        $params = array(
            'eqLogic' => intval($id),
            'subscribe' => $subscribe,
            'exclude' => $exclude,
            'retained' => boolval($retained),
            'duration' => intval($duration)
        );
        $data = json_encode($params, JSON_UNESCAPED_SLASHES);
        $path = '/broker/' . $id . '/realtime/start';
        self::doSend('PUT', $path, $data, __METHOD__, 'id=' . $id . ', data=' . $data);
    }

    /**
     * Send a request to get Real Time Mode status
     *
     * @param int $id Broker id
     * @return string // TODO =>json? =>obj?
     */
    public static function brokerRealTimeStatus($id) {
        $path = '/broker/' . $id . '/realtime/status';
        $res = self::doSend('GET', $path, null, __METHOD__, 'id=' . $id, true);
        return $res;
    }

    /**
     * Send a request to stop Real Time Mode status
     *
     * @param int $id Broker id
     */
    public static function brokerRealTimeStop($id) {
        $path = '/broker/' . $id . '/realtime/stop';
        self::doSend('GET', $path, null, __METHOD__, 'id=' . $id);
    }

    /**
     * Send a request to get Real Time Mode messages
     *
     * @param int $id Broker id
     * @param int $since Time of the last received message (default: 0)
     * @return string // TODO =>json? =>obj?
     */
    public static function brokerRealTimeGet($id, $since=0) {
        $path = '/broker/' . $id . '/realtime?since=' . intval($since);
        $res = self::doSend('GET', $path, null, __METHOD__, 'id=' . $id . ', since=' . $since, true);
        return $res;
    }

    /**
     * Send a request to clear Real Time Mode cache in daemon
     *
     * @param int $id Broker id
     */
    public static function brokerRealTimeClear($id) {
        $path = '/broker/' . $id . '/realtime/clear';
        self::doSend('GET', $path, null, __METHOD__, 'id=' . $id);
    }


    // ------------------------------------------------------------------------
    // Equipments related function
    public static function eqptSet($params) {
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
        self::doSend('POST', '/equipment', $payload, __METHOD__, $payload);
    }

    public static function eqptDel($id) {
        $path = '/equipment/' . $id;
        self::doSend('DELETE', $path, null, __METHOD__, 'id=' . $id);
    }


    // ------------------------------------------------------------------------
    // Commands related function
    public static function cmdSet($params) {
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES);
        self::doSend('POST', '/command', $payload, __METHOD__, $payload);
    }

    public static function cmdDel($id) {
        $path = '/command/' . $id;
        self::doSend('DELETE', $path, null, __METHOD__, 'id=' . $id);
    }
}
