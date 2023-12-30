<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';


class JmqttdCallbacks {
    private static $allowedActions = array(
        'brokerDown',
        'brokerUp',
        'daemonDown',
        'daemonHB',
        'daemonUp',
        'message',
        'test',
        'values'
    );

    public static function checkAuthorization() {
        $headers = getallheaders();
        if (
            !array_key_exists('Authorization', $headers)
            || 'Bearer' !== trim(substr($headers['Authorization'], 0, 6))
        ) {
            http_response_code(401);
            echo 'Unauthorized access.';
            log::add(
                'jMQTT',
                'error',
                sprintf(
                    __("Accès non autorisé depuis %1\$s (pas de clé API)", __FILE__),
                    $_SERVER['REMOTE_ADDR']
                )
            );
            die();
        }

        if (!jeedom::apiAccess(trim(substr($headers['Authorization'], 6)), 'jMQTT')) {
            http_response_code(401);
            echo 'Unauthorized access.';
            log::add(
                'jMQTT',
                'error',
                sprintf(
                    __("Accès non autorisé depuis %1\$s, avec la clé API commençant par %2\$.8s...", __FILE__),
                    $_SERVER['REMOTE_ADDR'],
                    trim(substr($headers['Authorization'], 6))
                )
            );
        }
    }

    public static function getPayload() {
        try {
            $received = file_get_contents("php://input"); // Get page full content
            jMQTT::logger('debug', sprintf("Payload='%1\$s'", $received));
        } catch (Throwable $e) {
            http_response_code(400);
            echo 'Bad Request.';
            die();
        }
        if (empty($received)) {
            http_response_code(406);
            echo 'Not Acceptable Content.';
            die();
        }
        try {
            $message = json_decode($received, true, 128, JSON_THROW_ON_ERROR ); // Try to decode json
        } catch (Throwable $e) {
            jMQTT::logger(
                'error',
                sprintf(
                    __("Format JSON erroné: '%1\$s'", __FILE__),
                    $received
                )
            );
            http_response_code(406);
            echo 'Not Acceptable Value.';
            die();
        }
        return $message;
    }

    public static function getAction() {
        $action = init('a'); // Collect Requested action
        if (!in_array($action, self::$allowedActions, true)) {
            http_response_code(406);
            echo 'Not Acceptable Action.';
            die();
        }
        if (!is_callable(array(self::class, 'on'.ucfirst($action)))) {
            http_response_code(501);
            echo 'Not Implemented.';
            die();
        }
        return $action;
    }

    public static function processRequest() {
        // Check if API key and method are correct (or die)
        self::checkAuthorization();

        // NOT POST, we just close the connection
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed.';
            die();
        }

        // Get action and remote uid (or die)
        $action = self::getAction();

        // Update timer
        if ($action != 'test')
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());

        // Execute static method corresponding to the action
        $action = 'on'.ucfirst($action);
        self::$action();

        // Finally return 200
        http_response_code(200);
        die();
    }


    /**
     * Daemon callback to test Jeedom connectivity
     */
    public static function onTest() {
        jMQTT::logger('debug', __METHOD__);
    }

    /**
     * Daemon callback to tell Jeedom it is started
     */
    public static function onDaemonUp() {
        $message = self::getPayload();
        if (!isset($message['port'])) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        jMQTT::logger('debug', __METHOD__);

        // Get expected daemon port and PID
        $pid = jMQTTDaemon::getPid();
        $port = $message['port'];
        // Searching a match for PID and PORT in listening ports
        if (
            jMQTTDaemon::getPort() == 0
            && !jMQTTDaemon::checkPidPortMatch($pid, $port)
        ) {
            // Execution issue, could not get a match
            jMQTT::logger(
                'warning',
                __("Démon : N'a pas pu être authentifié", __FILE__)
            );
            // TODO: Daemon should be informed back that it is NOT accepted
            return;
        }
        // Daemon is UP, registering PORT
        jMQTTDaemon::setPort(intval($port));
        jMQTT::logger('debug', __("Démon a bien démarré", __FILE__));
        // Reset send daemon timer
        // cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        // Send state to WebUI
        jMQTTDaemon::sendMqttDaemonStateEvent(true);
        // Active listeners
        jMQTT::listenersAddAll();
        // Send all the eqLogics/cmds to the daemon
        jMQTTComToDaemon::initDaemon(jMQTT::full_export());
    }

    /**
     * Daemon callback to tell Jeedom it is OK
     */
    public static function onDaemonHB() {
        // jMQTT::logger('debug', __METHOD__);
        jMQTT::logger('debug', __("Démon est en vie", __FILE__));

    }

    /**
     * Daemon callback to tell Jeedom it is stopped
     */
    public static function onDaemonDown() {
        jMQTT::logger('debug', __METHOD__);
        // Delete daemon PORT file in temporary folder (as it is now disconnected)
        jMQTTDaemon::delPort();
        // Delete daemon PID file in temporary folder (as it is now disconnected)
        jMQTTDaemon::delPid();
        // Send state to WebUI
        jMQTTDaemon::sendMqttDaemonStateEvent(false);
        // Remove listeners
        jMQTT::listenersRemoveAll();
        // Get all brokers and set them as disconnected
        foreach (jMQTT::getBrokers() as $broker) {
            try {
                self::onBrokerDown($broker->getId());
            } catch (Throwable $e) {
                if (log::getLogLevel(jMQTT::class) > 100) {
                    jMQTT::logger(
                        'error',
                        sprintf(
                            __("%1\$s() a levé l'Exception: %2\$s", __FILE__),
                            __METHOD__,
                            $e->getMessage()
                        )
                    );
                } else {
                    jMQTT::logger(
                        'error',
                        str_replace(
                            "\n",
                            ' <br/> ',
                            sprintf(
                                __("%1\$s() a levé l'Exception: %2\$s", __FILE__).
                                ",<br/>@Stack: %3\$s,<br/>@BrkId: %4\$s.",
                                __METHOD__,
                                $e->getMessage(),
                                $e->getTraceAsString(),
                                $broker->getId()
                            )
                        )
                    );
                }
            }
        }
    }

    /**
     * Daemon callback to tell Jeedom a broker is connected
     */
    public static function onBrokerUp() {
        $message = self::getPayload();
        if (!isset($message['id'])) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        $id = $message['id'];
        jMQTT::logger('debug', sprintf("%1\$s: %2\$s", __METHOD__, $id));

        // Catch if thing do bad
        try {
            // Throws if broker is unknown
            $broker = jMQTT::getBrokerFromId(intval($id));
            // Save in cache that Mqtt Client is connected
            $broker->setCache(jMQTTConst::CACHE_MQTTCLIENT_CONNECTED, true);
            // If not existing at brkUp, create it
            $broker->checkAndUpdateCmd(
                $broker->getMqttClientStatusCmd(true),
                jMQTTConst::CLIENT_STATUS_ONLINE
            );
            // If not existing at brkUp, create it
            $broker->checkAndUpdateCmd(
                $broker->getMqttClientConnectedCmd(true),
                1
            );
            // Remove warning status
            $broker->setStatus('warning', null);
            $broker->log('info', __('Client MQTT connecté au Broker', __FILE__));
            $broker->setCache(jMQTTConst::CACHE_LAST_LAUNCH_TIME, date('Y-m-d H:i:s'));
            $broker->sendMqttClientStateEvent();

            // $params = array();
            // $params['hostname'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_ADDRESS);
            // $params['proto'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_PROTO);
            // $params['port'] = intval($this->getConf(jMQTTConst::CONF_KEY_MQTT_PORT));
            // $params['wsUrl'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_WS_URL);
            // $params['mqttId'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_ID) == "1";
            // $params['mqttIdValue'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_ID_VALUE);
            // $params['lwt'] = ($this->getConf(jMQTTConst::CONF_KEY_MQTT_LWT) == '1');
            // $params['lwtTopic'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_LWT_TOPIC);
            // $params['lwtOnline'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_LWT_ONLINE);
            // $params['lwtOffline'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_LWT_OFFLINE);
            // $params['username'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_USER);
            // $params['password'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_PASS);
            // $params['tlscheck'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_TLS_CHECK);
            // switch ($this->getConf(jMQTTConst::CONF_KEY_MQTT_TLS_CHECK)) {
            //     case 'disabled':
            //         $params['tlsinsecure'] = true;
            //         break;
            //     case 'public':
            //         $params['tlsinsecure'] = false;
            //         break;
            //     case 'private':
            //         $params['tlsinsecure'] = false;
            //         $params['tlsca'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_TLS_CA);
            //         break;
            // }
            // $params['tlscli'] = ($this->getConf(jMQTTConst::CONF_KEY_MQTT_TLS_CLI) == '1');
            // if ($params['tlscli']) {
            //     $params['tlsclicert'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_TLS_CLI_CERT);
            //     $params['tlsclikey'] = $this->getConf(jMQTTConst::CONF_KEY_MQTT_TLS_CLI_KEY);
            //     if ($params['tlsclicert'] == '' || $params['tlsclikey'] == '') {
            //         $params['tlscli']    = false;
            //         unset($params['tlsclicert']);
            //         unset($params['tlsclikey']);
            //     }
            // }

            // Activate listeners
            jMQTT::listenersAddAll();
        } catch (Throwable $e) {
            if (log::getLogLevel(jMQTT::class) > 100)
                jMQTT::logger(
                    'error',
                    sprintf(
                        __("%1\$s() a levé l'Exception: %2\$s", __FILE__),
                        __METHOD__,
                        $e->getMessage()
                    )
                );
            else
                jMQTT::logger(
                    'error',
                    str_replace(
                        "\n",
                        ' <br/> ',
                        sprintf(
                            __("%1\$s() a levé l'Exception: %2\$s", __FILE__).
                            ",<br/>@Stack: %3\$s,<br/>@BrkId: %4\$s.",
                            __METHOD__,
                            $e->getMessage(),
                            $e->getTraceAsString(),
                            $id
                        )
                    )
                );
        }
    }

    /**
     * Daemon callback to tell Jeedom a broker is disconnected
     */
    public static function onBrokerDown() {
        $message = self::getPayload();
        if (!isset($message['id'])) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        $id = $message['id'];
        jMQTT::logger('debug', sprintf("%1\$s: %2\$s", __METHOD__, $id));

        // Catch if thing do bad
        try {
            // Catch if broker is unknown / deleted
            try {
                $broker = jMQTT::getBrokerFromId(intval($id));
            } catch (Throwable $e) {
                jMQTT::logger('debug', $e->getMessage());
                $e->getMessage();
                return;
            }
            // Save in cache that Mqtt Client is disconnected
            $broker->setCache(jMQTTConst::CACHE_MQTTCLIENT_CONNECTED, false);

            // If command exists update the status
            // Check if statusCmd exists, because cmd
            // are destroyed first by eqLogic::remove()
            $broker->checkAndUpdateCmd(
                $broker->getMqttClientStatusCmd(),
                jMQTTConst::CLIENT_STATUS_OFFLINE
            );
            // Check if connectedCmd exists, because cmd
            // are destroyed first by eqLogic::remove()
            $broker->checkAndUpdateCmd(
                $broker->getMqttClientConnectedCmd(),
                0
            );
            // Also set a warning if eq is enabled (should be always true)
            $broker->setStatus('warning', $broker->getIsEnable() ? 1 : null);

            // Clear Real Time mode
            $broker->setCache(jMQTTConst::CACHE_REALTIME_MODE, 0);

            $broker->log('info', __('Client MQTT déconnecté du Broker', __FILE__));
            $broker->sendMqttClientStateEvent();
        } catch (Throwable $e) {
            if (log::getLogLevel(jMQTT::class) > 100)
                jMQTT::logger(
                    'error',
                    sprintf(
                        __("%1\$s() a levé l'Exception: %2\$s", __FILE__),
                        __METHOD__,
                        $e->getMessage()
                    )
                );
            else
                jMQTT::logger(
                    'error',
                    str_replace(
                        "\n",
                        ' <br/> ',
                        sprintf(
                            __("%1\$s() a levé l'Exception: %2\$s", __FILE__).
                            ",<br/>@Stack: %3\$s,<br/>@BrkId: %4\$s.",
                            __METHOD__,
                            $e->getMessage(),
                            $e->getTraceAsString(),
                            $id
                        )
                    )
                );
        }
    }

    /**
     * Daemon callback to send a MQTT payload to Jeedom
     */
    public static function onMessage() {
        $message = self::getPayload();
        if (
            !isset($message['id'])
            || !isset($message['topic'])
            || !isset($message['payload'])
        ) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        jMQTT::logger(
            'debug',
            sprintf(
                "onMessage: id='%1\$s', topic='%2\$s', payload='%3\$s', qos=%4\$s, retain=%5\$s",
                $message['id'],
                $message['topic'],
                $message['payload'],
                $message['qos'],
                $message['retain'] ? 'True' : 'False'
            )
        );

        try {
            $broker = jMQTT::getBrokerFromId(intval($message['id']));
            $broker->brokerMessageCallback(
                $message['topic'],
                $message['payload'],
                $message['qos'],
                $message['retain']
            );
        } catch (Throwable $e) {
            if (log::getLogLevel(jMQTT::class) > 100)
                jMQTT::logger(
                    'error',
                    sprintf(
                        __("%1\$s() a levé l'Exception: %2\$s", __FILE__),
                        __METHOD__,
                        $e->getMessage()
                    )
                );
            else
                jMQTT::logger(
                    'error',
                    str_replace(
                        "\n",
                        ' <br/> ',
                        sprintf(
                            __("%1\$s() a levé l'Exception: %2\$s", __FILE__).
                            ",<br/>@Stack: %3\$s,<br/>@BrkId: %4\$s,".
                            "<br/>@Topic: %5\$s,<br/>@Payload: %6\$s,".
                            "<br/>@Qos: %7\$s,<br/>@Retain: %8\$s.",
                            __METHOD__,
                            $e->getMessage(),
                            $e->getTraceAsString(),
                            $message['id'],
                            $message['topic'],
                            $message['payload'],
                            $message['qos'],
                            $message['retain'] ? 'True' : 'False'
                        )
                    )
                );
        }
    }

    /**
     * Daemon callback to send values to update in Jeedom
     */
    public static function onValues() {
        $message = self::getPayload();
        if (!is_array($message)) {
            echo 'Bad Request.';
            die();
        }
        foreach ($message as $val) {
            jMQTT::logger(
                'debug',
                sprintf(
                    "onValues: id='%1\$s', value='%2\$s'",
                    $val['id'],
                    $val['value']
                )
            );
            try {
                /** @var jMQTTCmd $cmd */
                $cmd = jMQTTCmd::byId(intval($val['id']));
                if (!is_object($cmd)) {
                    jMQTT::logger('debug', sprintf(
                        __("Pas de commande avec l'id %s", __FILE__),
                        $val['id']
                    ));
                    return;
                }
                $cmd->getEqLogic()->getBroker()->setStatus(array(
                    'lastCommunication' => date('Y-m-d H:i:s'),
                    'timeout' => 0
                ));
                $cmd->updateCmdValue($val['value']);
            } catch (Throwable $e) {
                if (log::getLogLevel(jMQTT::class) > 100)
                    jMQTT::logger(
                        'error',
                        sprintf(
                            __("%1\$s() a levé l'Exception: %2\$s", __FILE__),
                            __METHOD__,
                            $e->getMessage()
                        )
                    );
                else
                    jMQTT::logger(
                        'error',
                        str_replace(
                            "\n",
                            ' <br/> ',
                            sprintf(
                                __("%1\$s() a levé l'Exception: %2\$s", __FILE__).
                                ",<br/>@Stack: %3\$s,<br/>@cmdId: %4\$s,".
                                "<br/>@value: %5\$s.",
                                __METHOD__,
                                $e->getMessage(),
                                $e->getTraceAsString(),
                                $val['id'],
                                $val['value']
                            )
                        )
                    );
            }
        }
    }

    /**
     * Daemon callback to inform Jeedom Real Time mode is started
     */
    public static function onRealTimeStarted() {
        $message = self::getPayload();
        if (!isset($message['id'])) {
            echo 'Bad Request.';
            die();
        }
        jMQTT::logger(
            'debug',
            sprintf("%1\$s: %2\$s", __METHOD__, $message['id'])
        );

        $broker = jMQTT::getBrokerFromId(intval($message['id']));
        // Update cache
        $broker->setCache(jMQTTConst::CACHE_REALTIME_MODE, 1);
        // Send event to WebUI
        $broker->log('info', __("Mode Temps Réel activé", __FILE__));
        $broker->sendMqttClientStateEvent();
    }

    /**
     * Daemon callback to inform Jeedom Real Time mode has stopped
     */
    public static function onRealTimeStopped() {
        $message = self::getPayload();
        if (!isset($message['id']) || !isset($message['nbMsgs'])) {
            echo 'Bad Request.';
            die();
        }
        jMQTT::logger(
            'debug',
            sprintf(
                "%1\$s: %2\$s / %3\$s",
                __METHOD__,
                $message['id'],
                $message['nbMsgs']
            )
        );

        $broker = jMQTT::getBrokerFromId(intval($message['id']));
        // Update cache
        $broker->setCache(jMQTTConst::CACHE_REALTIME_MODE, 0);
        // Send event to WebUI
        $broker->log(
            'info',
            sprintf(
                __("Mode Temps Réel désactivé, %s messages disponibles", __FILE__),
                $message['nbMsgs']
            )
        );
        $broker->sendMqttClientStateEvent();
    }
}

// Call the request processor
JmqttdCallbacks::processRequest();
