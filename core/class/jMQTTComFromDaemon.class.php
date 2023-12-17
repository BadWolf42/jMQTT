<?php

class jMQTTComFromDaemon {

    /**
     * Daemon callback to tell Jeedom it is started
     */
    public static function daemonUp($ruid) {
        // If we get here, apikey is OK!
        // jMQTT::logger('debug', 'daemonUp(ruid='.$ruid.')');
        // Verify that daemon RemoteUID contains ':' or die
        if (is_null($ruid) || !is_string($ruid) || (strpos($ruid, ':') === false)) {
            jMQTT::logger(
                'warning',
                sprintf(
                    __("Démon [%s] : Inconsistant", __FILE__),
                    $ruid
                )
            );
            return '';
        }
        // Verify that this daemon is not already initialized
        $cuid = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID)->getValue("0:0");
        if ($cuid == $ruid) {
            jMQTT::logger(
                'info',
                sprintf(
                    __("Démon [%s] : Déjà initialisé", __FILE__),
                    $ruid
                )
            );
            return '';
        }
        list($rpid, $rport) = array_map('intval', explode(":", $ruid));
        // Verify Remote UID coherence
        if ($rpid == 0) {
            // If Remote PID is NOT available
            jMQTT::logger(
                'warning',
                sprintf(
                    __("Démon [%s] : Pas d'identifiant d'exécution", __FILE__),
                    $ruid
                )
            );
            return '';
        }
        if (!@posix_getsid($rpid)) {
            // Remote PID is not running
            jMQTT::logger(
                'warning',
                sprintf(
                    __("Démon [%s] : Mauvais identifiant d'exécution", __FILE__),
                    $ruid
                )
            );
            return '';
        }
        // Searching a match for RemoteUID (PID and PORT) in listening ports
        $retval = 255;
        exec("ss -Htulpn 'sport = :" . $rport ."' 2> /dev/null | grep -E '[:]" . $rport . "[ \t]+.*[:][*][ \t]+.+pid=" . $rpid . "' 2> /dev/null", $output, $retval);
        // Execution issue with ss (too new)? Try (the good old) netstat!
        if ($retval != 0) {
            // Be sure to clear $output first
            unset($output);
            exec("netstat -lntp 2> /dev/null | grep -E '[:]" . $rport . "[ \t]+.*[:][*][ \t]+.+[ \t]+" . $rpid . "/python3' 2> /dev/null", $output, $retval);
        }
        // Execution issue with netstat? Try (the slow) lsof!
        if ($retval != 0) {
            // Be sure to clear $output first
            unset($output);
            exec("lsof -nP -iTCP -sTCP:LISTEN | grep -E 'python3[ \t]+" . $rpid . "[ \t]+.+[:]" . $rport ."[ \t]+' 2> /dev/null", $output, $retval);
        }
        if ($retval != 0 || count($output) == 0) {
            // Execution issue, could not get a match
            jMQTT::logger(
                'warning',
                sprintf(
                    __("Démon [%s] : N'a pas pu être authentifié", __FILE__),
                    $ruid
                )
            );
            return '';
        }
        // Verify if another daemon is not running
        list($cpid, $cport) = array_map('intval', explode(":", $cuid));
        if ($cpid != 0) { // Cached PID is available
            if (!@posix_getsid($cpid)) { // Cached PID is NOT running
                jMQTT::logger(
                    'warning',
                    sprintf(
                        __("Démon [%1\$s] va remplacer le Démon [%2\$s] !", __FILE__),
                        $ruid,
                        $cuid
                    )
                );
                // Must NOT `return ''` here, new daemon still needs to be accepted
                jMQTTDaemon::stop();
            } else { // Cached PID IS running
                jMQTT::logger(
                    'warning',
                    sprintf(
                        __("Démon [%1\$s] essaye de remplacer le Démon [%2\$s] !", __FILE__),
                        $ruid,
                        $cuid
                    )
                );
                exec(system::getCmdSudo() . 'fuser ' . $cport . '/tcp 2> /dev/null', $output, $retval);
                if ($retval != 0 || count($output) == 0) {
                    // Execution issue, could not get a match
                    jMQTT::logger(
                        'warning',
                        sprintf(
                            __("Démon [%s] : N'a pas pu être identifié", __FILE__),
                            $cuid
                        )
                    );
                    // Must NOT `return ''` here, new daemon still needs to be accepted
                    jMQTTDaemon::stop();
                } elseif (intval(trim($output[0])) != $cpid) {
                    // No match for old daemon
                    jMQTT::logger(
                        'warning',
                        sprintf(
                            __("Démon [%s] : Reprend la main", __FILE__),
                            $ruid
                        )
                    );
                    // Must NOT `return ''` here, new daemon still needs to be accepted
                    jMQTTDaemon::stop();
                } else {
                    // Old daemon is still alive. If Daemon is semi-dead, it may die by missing enough heartbeats
                    jMQTT::logger(
                        'warning',
                        sprintf(
                            __("Démon [%1\$s] va survivre au Démon [%2\$s] !", __FILE__),
                            $cuid,
                            $ruid
                        )
                    );
                    posix_kill($rpid, 15);
                    return '';
                }
            }
        }
        // VERY VERBOSE (1/5s to 1/m): Do not activate if not needed!
        //jMQTT::logger('debug', sprintf(__("Démon [%s] est vivant", __FILE__), $ruid));
        // Save in cache the daemon RemoteUID (as it is connected)
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID, $ruid);
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_PORT, $rport);
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        jMQTTDaemon::sendMqttDaemonStateEvent(true);
        // Launch MQTT Clients
        jMQTTDaemon::checkAllMqttClients();
        // Active listeners
        jMQTT::listenersAddAll();
        // Prepare and send initial data
        // TODO: Send all the eqLogics/cmds to the daemon
        //  labels: enhancement, php
        // $all_data = jMQTT::full_export();
        // return json_encode($all_data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Daemon callback to tell Jeedom it is OK
     */
    public static function hb($uid) {
        jMQTT::logger('debug', sprintf(__("Démon [%s] est en vie", __FILE__), $uid));
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
    }

    /**
     * Daemon callback to tell Jeedom it is stopped
     */
    public static function daemonDown($uid) {
        //jMQTT::logger('debug', 'daemonDown(uid='.$uid.')');
        // Remove PID file
        if (file_exists($pid_file = jeedom::getTmpFolder(jMQTT::class) . '/jmqttd.py.pid'))
            shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
        // Delete in cache the daemon uid (as it is disconnected)
        try {
            cache::delete('jMQTT::' . jMQTTConst::CACHE_DAEMON_UID);
        } catch (Exception $e) {
            // Cache file/key missed, nothing to do here
        }
        // Send state to WebUI
        jMQTTDaemon::sendMqttDaemonStateEvent(false);
        // Remove listeners
        jMQTT::listenersRemoveAll();
        // Get all brokers and set them as disconnected
        foreach(jMQTT::getBrokers() as $broker) {
            try {
                jMQTTComFromDaemon::brkDown($broker->getId());
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

    public static function brkUp($id) {
        try { // Catch if broker is unknown / deleted
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
            $broker->setStatus('warning', null);
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
            $broker->log('info', __('Client MQTT connecté au Broker', __FILE__));
            $broker->sendMqttClientStateEvent();
            // Subscribe to topics
            foreach (jMQTT::byBrkId($id) as $eq) {
                if ($eq->getIsEnable() && $eq->getId() != $broker->getId()) {
                    $eq->subscribeTopic($eq->getTopic(), $eq->getQos());
                }
            }

            // Enable Interactions
            if ($broker->getConf(jMQTTConst::CONF_KEY_MQTT_INT)) {
                $broker->log(
                    'info',
                    sprintf(
                        __("Souscription au topic d'Interaction '%s'", __FILE__),
                        $broker->getConf(jMQTTConst::CONF_KEY_MQTT_INT_TOPIC)
                    )
                );
                $broker->subscribeTopic($broker->getConf(jMQTTConst::CONF_KEY_MQTT_INT_TOPIC), '1');
                $broker->subscribeTopic($broker->getConf(jMQTTConst::CONF_KEY_MQTT_INT_TOPIC) . '/advanced', '1');
            } else
                $broker->log('debug', __("L'accès aux Interactions est désactivé", __FILE__));

            // Enable API
            if ($broker->getConf(jMQTTConst::CONF_KEY_MQTT_API)) {
                $broker->log(
                    'info',
                    sprintf(
                        __("Souscription au topic API '%s'", __FILE__),
                        $broker->getConf(jMQTTConst::CONF_KEY_MQTT_API_TOPIC)
                    )
                );
                $broker->subscribeTopic($broker->getConf(jMQTTConst::CONF_KEY_MQTT_API_TOPIC), '1');
            } else
                $broker->log('debug', __("L'accès à l'API est désactivé", __FILE__));

            // Active listeners
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

    public static function brkDown($id) {
        try { // Catch if broker is unknown / deleted
            /** @var jMQTT $broker */
            $broker = jMQTT::byId($id); // Don't use getBrokerFromId here!
            if (!is_object($broker)) {
                jMQTT::logger(
                    'debug',
                    sprintf(
                        __("Pas d'équipement avec l'id %s (il vient probablement d'être supprimé)", __FILE__),
                        $id
                    )
                );
                return;
            }
            if ($broker->getType() != jMQTTConst::TYP_BRK) {
                jMQTT::logger(
                    'error',
                    sprintf(
                        __("L'équipement %s n'est pas de type Broker", __FILE__),
                        $id
                    )
                );
                return;
            }
            // Save in cache that Mqtt Client is disconnected
            $broker->setCache(jMQTTConst::CACHE_MQTTCLIENT_CONNECTED, false);

            // If command exists update the status (used to get broker connection status inside Jeedom)
            // Need to check if statusCmd exists, because during Remove cmd are destroyed first by eqLogic::remove()
            $broker->checkAndUpdateCmd(
                $broker->getMqttClientStatusCmd(),
                jMQTTConst::CLIENT_STATUS_OFFLINE
            );
            // Need to check if connectedCmd exists, because during Remove cmd are destroyed first by eqLogic::remove()
            $broker->checkAndUpdateCmd(
                $broker->getMqttClientConnectedCmd(),
                0
            );
            // Also set a warning if eq is enabled (should be always true)
            $broker->setStatus('warning', $broker->getIsEnable() ? 1 : null);

            // Clear Real Time mode
            $broker->setCache(jMQTTConst::CACHE_REALTIME_MODE, 0);

            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
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

    public static function msgIn($id, $topic, $payload, $qos, $retain) {
        try {
            $broker = jMQTT::getBrokerFromId(intval($id));
            $broker->brokerMessageCallback($topic, $payload, $qos, $retain);
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
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
                            $id, $topic, $payload, $qos, $retain
                        )
                    )
                );
        }
    }

    public static function value($cmdId, $value) {
        try {
            /** @var jMQTTCmd $cmd */
            $cmd = jMQTTCmd::byId(intval($cmdId));
            if (!is_object($cmd)) {
                jMQTT::logger('debug', sprintf(
                    __("Pas de commande avec l'id %s", __FILE__),
                    $cmdId
                ));
                return;
            }
            $cmd->getEqLogic()->getBroker()->setStatus(array(
                'lastCommunication' => date('Y-m-d H:i:s'),
                'timeout' => 0
            ));
            $cmd->updateCmdValue($value);
            cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
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
                            $cmdId,
                            $value
                        )
                    )
                );
        }
    }

    public static function realTimeStarted($id) {
        $brk = jMQTT::getBrokerFromId(intval($id));
        // Update cache
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
        $brk->setCache(jMQTTConst::CACHE_REALTIME_MODE, 1);
        // Send event to WebUI
        $brk->log('info', __("Mode Temps Réel activé", __FILE__));
        $brk->sendMqttClientStateEvent();
    }

    public static function realTimeStopped($id, $nbMsgs) {
        $brk = jMQTT::getBrokerFromId(intval($id));
        // Update cache
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
        $brk->setCache(jMQTTConst::CACHE_REALTIME_MODE, 0);
        // Send event to WebUI
        $brk->log(
            'info',
            sprintf(
                __("Mode Temps Réel désactivé, %s messages disponibles", __FILE__),
                $nbMsgs
            )
        );
        $brk->sendMqttClientStateEvent();
    }

}
