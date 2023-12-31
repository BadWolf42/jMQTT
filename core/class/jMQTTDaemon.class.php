<?php

class jMQTTDaemon {

    private static $apikey = null;
    private static $pid = null;
    private static $port = null;

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
     * Neved uses cache
     */
    public static function check() {
        // VERY VERBOSE (1 log every 5s or 1m): Do not activate if not needed!
        // jMQTT::logger('debug', 'check() ['.getmypid().']: ref='.$_SERVER['HTTP_REFERER']);

        // Get expected daemon port (fast fail)
        $port = jMQTTDaemon::getPort(false);
        if ($port == 0) {
            // VERY VERBOSE (1 log every 5s or 1m): Do not activate if not needed!
            // jMQTT::logger('debug', 'Daemon PORT is absent or inactive.');
            return false;
        }
        // Get expected daemon PID (second fast fail)
        $pid = jMQTTDaemon::getPid(false);
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
            jMQTT::logger('debug', 'Daemon with a bad port.');
            // Cleanup and put jmqtt in a good state
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        // Checking last message FROM daemon
        $time = time();
        $last_rcv = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV)->getValue(0);
        if ($time - $last_rcv > 300) {
            jMQTT::logger(
                'debug',
                sprintf(
                    "No message or Heartbeat received for %ds, the Daemon is probably dead.",
                    $time - $last_rcv
                )
            );
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        // Checking last message TO daemon
        $last_snd = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND)->getValue(0);
        if ($time - $last_snd > 45) {
            jMQTT::logger(
                'debug',
                sprintf(
                    "Sending a Heartbeat to the Daemon (nothing has been sent for %ds).",
                    $time - $last_snd
                )
            );
            jMQTTComToDaemon::hb();
            return true;
        }
        // VERY VERBOSE (1 log every 5s or 1m): Do not activate if not needed!
        // jMQTT::logger('debug', "Daemon is OK");
        return true;
    }

    /**
     * Simple tests if a daemon is connected (do not validate it)
     * Use cached PID and PORT by defaut
     */
    public static function state($cache = true) {
        return jMQTTDaemon::getPort($cache) !== 0 && jMQTTDaemon::getPid($cache) !== 0;
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
        // Ensure Core hearbeat is disabled
        config::save('heartbeat::delay::jMQTT', '', 'core');
        config::save('heartbeat::restartDeamon::jMQTT', '0', 'core');
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
        $shellCmd .= ' APIKEY=' . jMQTTDaemon::getApiKey();
        $shellCmd .= ' PIDFILE=' . jeedom::getTmpFolder(jMQTT::class) . '/daemon.pid ';
        $shellCmd .= $path.'/venv/bin/python3 ' . $path . '/app/main.py';
        $shellCmd .= ' >> ' . log::getPathToLog(jMQTT::class.'d_trash') . ' 2>&1 &'; // TODO Remove LOG FILE <---------------------------------------
        if (log::getLogLevel(jMQTT::class) > 100) {
            jMQTT::logger('info', __('Lancement du démon jMQTT', __FILE__));
        } else {
            jMQTT::logger(
                'info',
                sprintf(
                    __("Lancement du démon jMQTT, commande shell: '%s'", __FILE__),
                    $shellCmd
                )
            );
        }
        exec($shellCmd);
        // Wait up to 10 seconds for daemon to start
        for ($i = 1; $i <= 40; $i++) {
            if (jMQTTDaemon::state(false)) { // Do not use cached state here
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

    /**
     * Cached function to get jMQTT internal API key
     * @return string jMQTT current internal API key
     */
    public static function getApiKey($cache = true) {
        if ($cache && !is_null(self::$apikey)) {
            return self::$apikey;
        }
        self::$apikey = jeedom::getApiKey(jMQTT::class);
        return self::$apikey;
    }

    /**
     * Cache function to get daemon current PID
     * @param bool $cache Use cache if True (default)
     * @return int Daemon PID if PID is alive, 0 otherwise
     */
    public static function getPid($cache = true) {
        if ($cache && !is_null(self::$pid)) {
            return self::$pid;
        }
        $pid_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.pid';
        if (!file_exists($pid_file)) {
            self::$pid = 0;
        } else {
            $pid = intval(trim(file_get_contents($pid_file)));
            // If PID is available and running
            if ($pid != 0 && @posix_getsid($pid)) {
                self::$pid = $pid;
            } else {
                self::$pid = 0;
            }
        }
        return self::$pid;
    }

    /**
     * Delete PID file and store 0 in PID cache
     * @return void
     */
    public static function delPid() {
        $pid_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.pid';
        if (file_exists($pid_file))
            unlink($pid_file);
        self::$pid = 0;
    }

    /**
     * Find an unused TCP port on the system
     * @return int Available PORT on the system
     */
    public static function newPort() {
        $sock = socket_create_listen(0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);
        return $port;
    }

    /**
     * Cache function to get daemon current PORT
     * @param bool $cache Use cache if True (default)
     * @return int Daemon PORT if file exists, 0 otherwise
     */
    public static function getPort($cache = true) {
        if ($cache && !is_null(self::$port)) {
            return self::$port;
        }
        $port_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.port';
        if (!file_exists($port_file)) {
            self::$port = 0;
        } else {
            self::$port = intval(trim(file_get_contents($port_file)));
        }
        return self::$port;
    }

    /**
     * Set PORT in PORT file and cache
     * @param int $port Port used by the daemon (must be > 1024)
     * @return void
     */
    public static function setPort($port) {
        if ($port <= 1024) {
            $f = 'Unusable new port provided (%d), this should not happend!';
            $msg = sprintf($f, $port);
            jMQTT::logger('error', $msg);
            throw new Exception($msg);
        }
        $port_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.port';
        file_put_contents($port_file, strval($port), LOCK_EX);
        self::$port = $port;
    }

    /**
     * Delete PORT file and store 0 in PORT cache
     * @return void
     */
    public static function delPort() {
        $port_file = jeedom::getTmpFolder(jMQTT::class) . '/daemon.port';
        if (file_exists($port_file))
            unlink($port_file);
        self::$port = 0;
    }

    /**
     * Check if PID has oppened PORT
     * @param int $pid The PID to test
     * @param int $port The PORT to test
     * @return bool True if PID has oppened PORT
     */
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
     * Callback to stop daemon
     */
    public static function stop() {
        // Get running PID attached to PID file
        $pid = jMQTTDaemon::getPid();
        // If PID is available and running
        if ($pid != 0) {
            jMQTT::logger('info', __("Arrêt du démon jMQTT", __FILE__));
            posix_kill($pid, 15);  // Signal SIGTERM
            jMQTT::logger('debug', "Sending SIGTERM signal to Daemon");
            for ($i = 1; $i <= 40; $i++) { //wait max 10 seconds for python daemon stop
                if (!jMQTTDaemon::state(false)) { // Do not use cached state here
                    jMQTT::logger('info', __("Démon jMQTT arrêté", __FILE__));
                    break;
                }
                usleep(250000);
            }
            if (jMQTTDaemon::state()) {
                // Signal SIGKILL
                posix_kill($pid, 9);
                jMQTT::logger('debug', "Sending SIGKILL signal to Daemon");
            }
        }
        // If something bad happened, clean anyway
        jMQTT::logger('debug', "Cleaning-up the daemon");
        // Kill existing jMQTT process by name
        system::kill('[/]jmqttd');
        // Kill existing jMQTT process by socket port
        $port = jMQTTDaemon::getPort();
        if ($port != 0) {
            // Kill daemon using stored port
            system::fuserk($port);
        }
        // Execute daemonDown callback anyway
        jMQTTCallbacks::onDaemonDown();
    }

    /**
     * Send a jMQTT::EventDaemonState event to the UI containing current daemon state
     * @param bool $_state true if Daemon is running and connected
     */
    public static function sendMqttDaemonStateEvent($_state) {
        event::add('jMQTT::EventDaemonState', $_state);
    }

}
