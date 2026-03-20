<?php

class jMQTTDaemon {

    /**
     * jMQTT static function returning an automatically detected callback url to Jeedom for the daemon
     */
    public static function get_callback_url() {
        // To fix let's encrypt issue like: https://community.jeedom.com/t/87060/26
        $proto = config::byKey('internalProtocol', 'core', 'http://');
        // To fix port issue like: https://community.jeedom.com/t/87060/30
        $port = config::byKey('internalPort', 'core', 80);
        // To fix path issue like: https://community.jeedom.com/t/87872/15
        $comp = trim(config::byKey('internalComplement', 'core', ''), '/');
        if ($comp !== '') $comp .= '/';
        return $proto.'127.0.0.1:'.$port.'/'.$comp.'plugins/jMQTT/core/php/callback.php';
    }

    public static function valid_uid($ruid) {
        $cuid = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID)->getValue("0:0");
        if ($cuid === "0:0")
             return null;
        return $cuid === $ruid;
    }

    /**
     * Validates that a daemon is connected, running and is communicating
     */
    public static function check() {
        // VERY VERBOSE (1/5s to 1/m): Do not activate if not needed!
        // jMQTT::logger('debug', 'check() ['.getmypid().']: ref='.$_SERVER['HTTP_REFERER']);

        // Get Cached PID and PORT
        $cuid = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID)->getValue("0:0");
        if ($cuid == "0:0") { // If UID nul -> not running
            // VERY VERBOSE (1/5s to 1/m): Do not activate if not needed!
            // jMQTT::logger('debug', 'Daemon with a null UID');
            return false;
        }
        list($cpid, $cport) = array_map('intval', explode(":", $cuid));
        if (!@posix_getsid($cpid)) { // PID IS NOT alive
            jMQTT::logger('debug', 'Daemon with a dead PID');
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        if ((@cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_PORT)->getValue(0)) != $cport) {
            jMQTT::logger('debug', 'Daemon with a bad port');
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        try {
            $deltaRx = time() - (cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV)->getValue(0));
            $deltaTx = time() - (cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND)->getValue(0));
        } catch (Throwable $e) {
            jMQTT::logger('error', str_replace("\n", ' <br/> ', sprintf(
                'Exception $s raised by $s(),<br/>@Stack: $s', $e->getMessage(), __METHOD__, $e->getTraceAsString()
            )));
            // Cache file/key missed or Exception, considering daemon OK
            return true;
        }
        if ($deltaRx > 300) {
            jMQTT::logger(
                'warning',
                'No message or Heartbeat received for >300s [deltaRx=' . strval($deltaRx)
                . '], killing the probably dead Daemon [deltaTx=' . strval($deltaTx) . ']'
            );
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        if ($deltaTx > 45) {
            jMQTT::logger(
                'debug',
                'Nothing has been sent for >45s [deltaTx=' . strval($deltaTx)
                . '], sending a Heartbeat to the Daemon [deltaRx=' . strval($deltaRx) . ']'
            );
            jMQTTComToDaemon::hb();
            return true;
        }
        // VERY VERBOSE (1 log every 5-60s): Do not activate if not needed!
        // jMQTT::logger('debug', 'Daemon OK');
        return true;
    }

    /**
     * Simple tests if a daemon is connected (do not validate it)
     */
    public static function state() {
        try {
            return cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID)->getValue("0:0") !== "0:0";
        } catch (Exception $e) {
            // Cache file/key missed
            return false;
        }
    }

    /**
     * Jeedom callback to get information on the daemon
     */
    public static function info() {
        $return = array('launchable' => jMQTTConst::CLIENT_OK, 'log' => jMQTT::class);
        $return['state'] = (jMQTTDaemon::check()) ? jMQTTConst::CLIENT_OK : jMQTTConst::CLIENT_NOK;
        return $return;
    }

    /**
     * Jeedom callback to start daemon
     */
    public static function start() {
        // if jMQTTConst::FORCE_DEPENDANCY_INSTALL flag is raised in plugin config
        if (config::byKey(jMQTTConst::FORCE_DEPENDANCY_INSTALL, jMQTT::class, 0) == 1) {
            jMQTT::logger(
                'info',
                'Forced dependencies install/check, jMQTT daemon should start on the next try'
            );
            $plugin = plugin::byId(jMQTT::class);
            //clean dependancy state cache
            $plugin->dependancy_info(true);
            //start dependancy install
            $plugin->dependancy_install();
            //remove flag
            config::remove(jMQTTConst::FORCE_DEPENDANCY_INSTALL, jMQTT::class);
            // Installation of the dependancies occures in another process, this one must end.
            return;
        }
        jMQTT::logger('info', 'Starting jMQTT daemon');
        // Always stop first.
        jMQTTDaemon::stop();
        // Ensure cron is enabled
        config::save('functionality::cron::enable', 1, jMQTT::class);
        // Check if daemon is launchable
        $dep_info = jMQTTPlugin::dependancy_info();
        if ($dep_info['state'] != jMQTTConst::CLIENT_OK) {
            throw new Exception(__('Veuillez vérifier la configuration et les dépendances', __FILE__));
        }
        // Reset timers to let Daemon start
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        // Start Python daemon
        $path = realpath(__DIR__ . '/../../resources/jmqttd');
        $callbackURL = jMQTTDaemon::get_callback_url();
        // To fix issue: https://community.jeedom.com/t/87727/39
        if ((file_exists('/.dockerenv')
             || config::byKey('forceDocker', jMQTT::class, '0'))
            && config::byKey('urlOverrideEnable', jMQTT::class, '0') == '1') {
            $callbackURL = config::byKey('urlOverrideValue', jMQTT::class, $callbackURL);
        }
        $shellCmd  = 'LOGLEVEL=' . log::convertLogLevel(log::getLogLevel(jMQTT::class));
        $shellCmd .= ' CALLBACK="'.$callbackURL.'"';
        $shellCmd .= ' APIKEY=' . jeedom::getApiKey(jMQTT::class);
        $shellCmd .= ' PIDFILE=' . jeedom::getTmpFolder(jMQTT::class) . '/jmqttd.py.pid ';
        $shellCmd .= $path.'/venv/bin/python3 ' . $path . '/jmqttd.py';
        $shellCmd .= ' >> ' . log::getPathToLog(jMQTT::class.'d') . ' 2>&1 &';
        if (log::getLogLevel(jMQTT::class) > 100)
            jMQTT::logger('info', 'Launching jMQTT daemon');
        else
            jMQTT::logger(
                'info',
                sprintf('Launching jMQTT daemon, shell command: %s', $shellCmd)
            );
        exec($shellCmd);
        // Wait up to 10 seconds for daemon to start
        for ($i = 1; $i <= 40; $i++) {
            if (jMQTTDaemon::state()) {
                jMQTT::logger('info', 'Daemon started');
                break;
            }
            usleep(250000);
        }
        // If daemon has not correctly started
        if (!jMQTTDaemon::state()) {
            jMQTTDaemon::stop();
            /* /!\ Use log::add() here to set 'unableStartDaemon' as logicalId /!\ */
            log::add(
                jMQTT::class,
                'error',
                __('Impossible de lancer le démon jMQTT, vérifiez les logs de jMQTT', __FILE__),
                'unableStartDaemon'
            );
            return;
        }
        // Else all good
        message::removeAll(jMQTT::class, 'unableStartDaemon');
    }

    /**
     * callback to stop daemon
     */
    public static function stop() {
        // Get cached PID and PORT
        $cuid = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID)->getValue("0:0");
        list($cpid, $cport) = array_map('intval', explode(":", $cuid));
        // If PID is available and running
        if ($cpid != 0 && @posix_getsid($cpid)) {
            jMQTT::logger('info', 'Stopping jMQTT daemon');
            posix_kill($cpid, 15);  // Signal SIGTERM
            jMQTT::logger('debug', 'Sending SIGTERM signal to jMQTT Daemon');
            for ($i = 1; $i <= 40; $i++) { //wait max 10 seconds for python daemon stop
                if (!jMQTTDaemon::state()) {
                    jMQTT::logger('info', 'Daemon jMQTT stopped');
                    break;
                }
                usleep(250000);
            }
            if (jMQTTDaemon::state()) {
                // Signal SIGKILL
                posix_kill($cpid, 9);
                jMQTT::logger('debug', 'Sending SIGKILL signal to jMQTT Daemon');
            }
        }
        // If something bad happened, clean anyway
        jMQTT::logger('debug', 'Cleaning up jMQTT Daemon');
        // TODO: Kill all jMQTT daemon(s) when daemon is stopped
        //  Use `realpath(__DIR__ . '/../../resources/jmqttd').'/venv/bin/python3'`
        //  labels: enhancement, php
        jMQTTComFromDaemon::daemonDown($cuid);
    }

    /**
     * Check all MQTT Clients (start them if needed)
     */
    public static function checkAllMqttClients() {
        if (jMQTTDaemon::check() != jMQTTConst::CLIENT_OK)
            return;
        foreach (jMQTT::getBrokers() as $broker) {
            if (!$broker->getIsEnable()
                || $broker->getMqttClientState() == jMQTTConst::CLIENT_OK) {
                continue;
            }
            try {
                $broker->startMqttClient();
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
                                $broker->getId()
                            )
                        )
                    );
            }
        }
    }

    /**
     * cron callback
     * check MQTT Clients are up and connected
     */
    public static function cron() {
        jMQTTDaemon::checkAllMqttClients();
        jMQTTPlugin::stats();
    }

    /**
     * Send a jMQTT::EventDaemonState event to the UI containing current daemon state
     * @param bool $_state true if Daemon is running and connected
     */
    public static function sendMqttDaemonStateEvent($_state) {
        event::add('jMQTT::EventDaemonState', array("state" => $_state));
    }

}
