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
            // jMQTT::logger('debug', __('Démon avec un UID nul.', __FILE__));
            return false;
        }
        list($cpid, $cport) = array_map('intval', explode(":", $cuid));
        if (!@posix_getsid($cpid)) { // PID IS NOT alive
            jMQTT::logger('debug', __('Démon avec un PID mort.', __FILE__));
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        if ((@cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_PORT)->getValue(0)) != $cport) {
            jMQTT::logger('debug', __('Démon avec un mauvais port.', __FILE__));
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        if (time() - (@cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_RCV)->getValue(0)) > 300) {
            jMQTT::logger(
                'debug',
                __('Pas de message ou de Heartbeat reçu depuis >300s, le Démon est probablement mort.', __FILE__)
            );
            jMQTTDaemon::stop(); // Cleanup and put jmqtt in a good state
            return false;
        }
        if (time() - (@cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_LAST_SND)->getValue(0)) > 45) {
            jMQTT::logger(
                'debug',
                __("Envoi d'un Heartbeat au Démon (rien n'a été envoyé depuis >45s).", __FILE__)
            );
            jMQTTComToDaemon::hb();
            return true;
        }
        // VERY VERBOSE (1/5s to 1/m): Do not activate if not needed!
        // jMQTT::logger('debug', __('Démon OK', __FILE__));
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
        jMQTT::logger('info', __('Démarrage du démon jMQTT', __FILE__));
        // Always stop first.
        jMQTTDaemon::stop();
        // Ensure cron is enabled (removing the key or setting it to 1 is equivalent to enabled)
        config::remove('functionality::cron::enable', jMQTT::class);
        // Check if daemon is launchable
        $dep_info = jMQTTPlugin::dependancy_info();
        if ($dep_info['state'] != jMQTTConst::CLIENT_OK) {
            throw new Exception(__('Veuillez vérifier la configuration et les dépendances', __FILE__));
        }
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
        $shellCmd .= ' CALLBACK="'.$callbackURL.'"';
        $shellCmd .= ' APIKEY=' . jeedom::getApiKey(jMQTT::class);
        $shellCmd .= ' PIDFILE=' . jeedom::getTmpFolder(jMQTT::class) . '/jmqttd.py.pid ';
        $shellCmd .= $path.'/venv/bin/python3 ' . $path . '/app/main.py';
        $shellCmd .= ' >> ' . log::getPathToLog(jMQTT::class.'d_trash') . ' 2>&1 &';
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

    /**
     * callback to stop daemon
     */
    public static function stop() {
        // Get cached PID and PORT
        $cuid = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID)->getValue("0:0");
        list($cpid, $cport) = array_map('intval', explode(":", $cuid));
        // If PID is available and running
        if ($cpid != 0 && @posix_getsid($cpid)) {
            jMQTT::logger('info', __("Arrêt du démon jMQTT", __FILE__));
            posix_kill($cpid, 15);  // Signal SIGTERM
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
                posix_kill($cpid, 9);
                jMQTT::logger('debug', __("Envoi du signal SIGKILL au Démon", __FILE__));
            }
        }
        // If something bad happened, clean anyway
        jMQTT::logger('debug', __("Nettoyage du Démon", __FILE__));
        // TODO: Kill all jMQTT daemon(s) when daemon is stopped
        //  Use `realpath(__DIR__ . '/../../resources/jmqttd_api').'/venv/bin/python3'`
        //  labels: enhancement, php
        jMQTTComFromDaemon::daemonDown($cuid);
    }

    /**
     * Send a jMQTT::EventDaemonState event to the UI containing current daemon state
     * @param bool $_state true if Daemon is running and connected
     */
    public static function sendMqttDaemonStateEvent($_state) {
        event::add('jMQTT::EventDaemonState', $_state);
    }

}
