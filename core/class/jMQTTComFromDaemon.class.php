<?php

class jMQTTComFromDaemon {

    /**
     * Daemon callback to tell Jeedom it is started
     */
    public static function daemonUp($port) {
        // If we get here, apikey is OK!
        // jMQTT::logger('debug', 'daemonUp()');
        // Verify that this daemon is not already initialized

        // Get expected daemon port and PID
        $pid = jMQTTDaemon::getPid();
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
        jMQTT::logger('debug', __("Démon vivant", __FILE__));
        // Reset daemon timers
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        jMQTTDaemon::sendMqttDaemonStateEvent(true);
        // Launch MQTT Clients
        jMQTTPlugin::checkAllMqttClients();
        // Active listeners
        jMQTT::listenersAddAll();

        // Prepare and send all the eqLogics/cmds to the daemon

        // TODO: Send all the eqLogics/cmds to the daemon
        //  labels: enhancement, php
        // $all_data = jMQTT::full_export();
        // return json_encode($all_data, JSON_UNESCAPED_UNICODE);
        jMQTTComToDaemon::initDaemon(jMQTT::full_export());
    }

    /**
     * Daemon callback to tell Jeedom it is OK
     */
    public static function hb() {
        jMQTT::logger('debug', __("Démon est en vie", __FILE__));
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
    }

    /**
     * Daemon callback to tell Jeedom it is stopped
     */
    public static function daemonDown() {
        //jMQTT::logger('debug', 'daemonDown(uid='.$uid.')');
        // Delete daemon PORT file in temporary folder (as it is now disconnected)
        jMQTTDaemon::delPort();
        // Delete daemon PID file in temporary folder (as it is now disconnected)
        jMQTTDaemon::delPid();
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
