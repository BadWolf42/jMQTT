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

    /**
     * Validates that a daemon is connected, running and is communicating
     */
    public static function check() {
        // VERY VERBOSE (1 log every 5s or 1m): Do not activate if not needed!
        // jMQTT::logger('debug', 'check() ['.getmypid().']: ref='.$_SERVER['HTTP_REFERER']);

        // Get expected daemon port (fast fail)
        $port = jMQTTDaemon::getPort();
        if ($port == 0) {
            // VERY VERBOSE (1 log every 5s or 1m): Do not activate if not needed!
            // jMQTT::logger('debug', 'Daemon PORT is absent or inactive.');
            return false;
        }
        // Get expected daemon PID (second fast fail)
        $pid = jMQTTDaemon::getPid();
        // If PID nul OR no PORT -> not running
        if ($pid == 0) {
            // VERY VERBOSE (1 log every 5s or 1m): Do not activate if not needed!
            // jMQTT::logger('debug', 'Daemon PID is absent or inactive.');
            // Delete port to trigger first fast fail next time
            jMQTTDaemon::delPort();
            return false;
        }
        // If PID and PORT does not match
        if (!jMQTTDaemon::checkPidPortMatch($pid, $port)) {
            jMQTT::logger('debug', __('Démon avec un mauvais port.', __FILE__));
            // Cleanup and put jmqtt in a good state
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        // Checking last message FROM daemon
        if (time() - (@cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV)->getValue(0)) > 300) {
            jMQTT::logger(
                'debug',
                __('Pas de message ou de Heartbeat reçu depuis >300s, le Démon est probablement mort.', __FILE__)
            );
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        // Checking last message TO daemon
        if (time() - (@cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND)->getValue(0)) > 45) {
            jMQTT::logger(
                'debug',
                __("Envoi d'un Heartbeat au Démon (rien n'a été envoyé depuis >45s).", __FILE__)
            );
            jMQTTComToDaemon::hb();
            return true;
        }
        // VERY VERBOSE (1 log every 5s or 1m): Do not activate if not needed!
        // jMQTT::logger('debug', __('Démon OK', __FILE__));
        return true;
    }

    /**
     * Simple tests if a daemon is connected (do not validate it)
     */
    public static function state() {
        return jMQTTDaemon::getPort() !== 0 && jMQTTDaemon::getPid() !== 0;
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
                __("Installation/Vérification forcée des dépendances, le démon jMQTT démarrera au prochain essai", __FILE__)
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
        // Always stop first.
        jMQTTDaemon::stop();
        jMQTT::logger('info', __('Démarrage du démon jMQTT', __FILE__));
        // Ensure 1 minute cron is enabled (removing the key or setting it to 1 is equivalent)
        config::remove('functionality::cron::enable', jMQTT::class);
        // Check if daemon is launchable
        $dep_info = jMQTTPlugin::dependancy_info();
        if ($dep_info['state'] != jMQTTConst::CLIENT_OK) {
            throw new Exception(
                __('Veuillez vérifier la configuration et les dépendances', __FILE__)
            );
        }
        // Get a free port on the system
        // $port = jMQTTDaemon::newPort();
        // Reset timers to let Daemon start
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV, time());
        cache::set('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND, time());
        // Start Python daemon
        $path = realpath(__DIR__ . '/../../resources/jmqttd_api');
        $callbackURL = jMQTTDaemon::get_callback_url();
        // To fix issue: https://community.jeedom.com/t/87727/39
        if ((file_exists('/.dockerenv')
             || config::byKey('forceDocker', jMQTT::class, '0'))
            && config::byKey('urlOverrideEnable', jMQTT::class, '0') == '1') {
            $callbackURL = config::byKey('urlOverrideValue', jMQTT::class, $callbackURL);
        }
        $shellCmd  = 'LOGLEVEL=' . log::convertLogLevel(log::getLogLevel(jMQTT::class));
        $shellCmd .= ' LOGFILE=' . log::getPathToLog(jMQTT::class.'d');
        // TODO: Remove LOCALONLY debug parameter
        $shellCmd .= ' LOCALONLY=False';
        $shellCmd .= ' CALLBACK="'.$callbackURL.'"';
        // $shellCmd .= ' SOCKETPORT=' . $port;
        $shellCmd .= ' SOCKETPORT=18883'; // TODO Remove me <----------------------------------------------------------------------------------------
        $shellCmd .= ' APIKEY=' . jeedom::getApiKey(jMQTT::class);
        $shellCmd .= ' PIDFILE=' . jeedom::getTmpFolder(jMQTT::class) . '/daemon.pid ';
        $shellCmd .= $path.'/venv/bin/python3 ' . $path . '/app/main.py';
        $shellCmd .= ' >> ' . log::getPathToLog(jMQTT::class.'d_trash') . ' 2>&1 &'; // TODO Remove LOG FILE <---------------------------------------
        if (log::getLogLevel(jMQTT::class) > 100)
            jMQTT::logger('info', __('Lancement du démon jMQTT', __FILE__));
        else
            jMQTT::logger(
                'info',
                sprintf(
                    __("Lancement du démon jMQTT, commande shell: '%s'", __FILE__),
                    $shellCmd
                )
            );
        exec($shellCmd);
        // Wait up to 10 seconds for daemon to start
        for ($i = 1; $i <= 40; $i++) {
            if (jMQTTDaemon::state()) {
                jMQTT::logger('info', __('Démon démarré', __FILE__));
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

    public static function getPid() {
        $pid_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.pid';
        if (!file_exists($pid_file))
            return 0;
        $pid = intval(trim(file_get_contents($pid_file)));
        // If PID is available and running
        if ($pid != 0 && @posix_getsid($pid))
            return $pid;
        return 0;
    }

    public static function delPid() {
        $pid_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.pid';
        if (file_exists($pid_file))
            unlink($pid_file);
    }

    public static function newPort() {
        $sock = socket_create_listen(0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);
        return $port;
    }

    public static function getPort() {
        $port_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.port';
        if (!file_exists($port_file))
            return 0;
        return intval(trim(file_get_contents($port_file)));
    }

    public static function setPort($port) {
        if ($port <= 1024) {
            $f = 'Unusable new port provided (%d), this should not happend!';
            $msg = sprintf($f, $port);
            jMQTT::logger('error', $msg);
            throw new Exception($msg);
        }
        $port_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.port';
        file_put_contents($port_file, strval($port), LOCK_EX);
    }

    public static function delPort() {
        $port_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.port';
        if (file_exists($port_file))
            unlink($port_file);
    }

    public static function checkPidPortMatch($pid, $port) {
        // Searching a match for PID and PORT in listening ports
        $retval = 255;
        exec("ss -Htulpn 'sport = :" . $port . "' 2> /dev/null | grep -E '[:]" . $port . "[ \t]+.*[:][*][ \t]+.+pid=" . $pid . "' 2> /dev/null", $output, $retval);
        if ($retval == 0)
            return true;

        // Be sure to clear $output first
        unset($output);
        // Execution issue with ss (too new)? Try (the good old) netstat!
        exec("netstat -lntp 2> /dev/null | grep -E '[:]" . $port . "[ \t]+.*[:][*][ \t]+.+[ \t]+" . $pid . "/python3' 2> /dev/null", $output, $retval);
        if ($retval == 0)
            return true;

        // Be sure to clear $output first
        unset($output);
        // Execution issue with netstat? Try (the slow) lsof!
        exec("lsof -nP -iTCP -sTCP:LISTEN | grep -E 'python3[ \t]+" . $pid . "[ \t]+.+[:]" . $port ."[ \t]+' 2> /dev/null", $output, $retval);
        if ($retval != 0 || count($output) == 0) {
            // Execution issue, could not get a match
            return false;
        }
        return true;
    }


    /**
     * callback to stop daemon
     */
    public static function stop() {
        // Get running PID attached to PID file
        $pid = jMQTTDaemon::getPid();
        // If PID is available and running
        if ($pid != 0) {
            jMQTT::logger('info', __("Arrêt du démon jMQTT", __FILE__));
            posix_kill($pid, 15);  // Signal SIGTERM
            jMQTT::logger('debug', __("Envoi du signal SIGTERM au Démon", __FILE__));
            for ($i = 1; $i <= 40; $i++) { //wait max 10 seconds for python daemon stop
                if (!jMQTTDaemon::state()) {
                    jMQTT::logger('info', __("Démon jMQTT arrêté", __FILE__));
                    break;
                }
                usleep(250000);
            }
            if (jMQTTDaemon::state()) {
                // Signal SIGKILL
                posix_kill($pid, 9);
                jMQTT::logger('debug', __("Envoi du signal SIGKILL au Démon", __FILE__));
            }
        }
        // If something bad happened, clean anyway
        jMQTT::logger('debug', __("Nettoyage du Démon", __FILE__));
        // Kill existing jMQTT process by name
        system::kill('[/]jmqttd');
        // Kill existing jMQTT process by socket port
        $port = jMQTTDaemon::getPort();
        if ($port != 0) {
            // Kill daemon using stored port
            system::fuserk($port);
        }
        // Execute daemonDown callback anyway
        jMQTTComFromDaemon::daemonDown();
    }

    /**
     * Send a jMQTT::EventDaemonState event to the UI containing current daemon state
     * @param bool $_state true if Daemon is running and connected
     */
    public static function sendMqttDaemonStateEvent($_state) {
        event::add('jMQTT::EventDaemonState', $_state);
    }

}
