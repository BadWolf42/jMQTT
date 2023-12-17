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

    public static function pluginStats($_reason = 'cron') {
        // Check last reporting (or if forced)
        $nextStats = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_JMQTT_NEXT_STATS)->getValue(0);
        if ($_reason === 'cron' && (time() < $nextStats)) { // No reason to force send stats
            // jMQTT::logger('debug', sprintf(
            //  __("Aucune raison d'envoyer des données statistiques avant le %s", __FILE__),
            //  date('Y-m-d H:i:s', $nextStats)
            // ));
            return;
        }
        // Ensure between 5 and 10 minutes before next attempt
        cache::set('jMQTT::'.jMQTTConst::CACHE_JMQTT_NEXT_STATS, time() + 300 + rand(0, 300));
        // Avoid getting all stats exactly at the same time
        sleep(rand(0, 10));

        $url = 'https://stats.bad.wf/v1/query';
        $data = array();
        $data['plugin'] = 'jmqtt';
        $data['hardwareKey'] = jeedom::getHardwareKey();
        // Ensure system unicity using a rotating UUID
        $data['lastUUID'] = config::byKey(jMQTTConst::CONF_KEY_JMQTT_UUID, jMQTT::class, $data['hardwareKey']);
        $data['UUID'] = base64_encode(hash('sha384', microtime() . random_bytes(107), true));
        $data['hardwareName'] = jeedom::getHardwareName();
        if ($data['hardwareName'] == 'diy')
            $data['hardwareName'] = trim(shell_exec('systemd-detect-virt'));
        if ($data['hardwareName'] == 'none')
            $data['hardwareName'] = 'diy';
        $data['distrib'] = trim(shell_exec('. /etc/*-release && echo $ID $VERSION_ID'));
        $data['phpVersion'] = phpversion();
        $data['pythonVersion'] = trim(shell_exec("python3 -V | cut -d ' ' -f 2"));
        $data['jeedom'] = jeedom::version();
        $data['lang'] = config::byKey('language', 'core', 'fr_FR');
        $data['lang'] = ($data['lang'] != '') ? $data['lang'] : 'fr_FR';
        $jplugin = update::byLogicalId(jMQTT::class);
        $data['source'] = $jplugin->getSource();
        $data['branch'] = $jplugin->getConfiguration('version', 'unknown');
        $data['configVersion'] = config::byKey('version', jMQTT::class, -1);
        $data['reason'] = $_reason;
        if ($_reason == 'uninstall' || $_reason == 'noStats')
            $data['next'] = 0;
        else
            $data['next'] = time() + 432000 + rand(0, 172800); // Next stats in 5-7 days
        $encoded = json_encode($data);
        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $encoded
            )
        );
        jMQTT::logger(
            'debug',
            sprintf(
                __('Transmission des données statistiques suivantes : %s', __FILE__),
                $encoded
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            // Could not send or invalid data
            jMQTT::logger(
                'debug',
                sprintf(
                    __('Impossible de communiquer avec le serveur de statistiques (Réponse : %s)', __FILE__),
                    'false'
                )
            );
            return;
        }
        $response = @json_decode($result, true);
        if (!isset($response['status']) || $response['status'] != 'success') {
            // Could not send or invalid data
            jMQTT::logger(
                'debug',
                sprintf(
                    __('Impossible de communiquer avec le serveur de statistiques (Réponse : %s)', __FILE__),
                    $result
                )
            );
        } else {
            config::save(jMQTTConst::CONF_KEY_JMQTT_UUID, $data['UUID'], jMQTT::class);
            if ($data['next'] == 0) {
                jMQTT::logger('info', __('Données statistiques supprimées', __FILE__));
                cache::set('jMQTT::'.jMQTTConst::CACHE_JMQTT_NEXT_STATS, PHP_INT_MAX);
            } else {
                jMQTT::logger(
                    'debug',
                    sprintf(
                        __('Données statistiques envoyées (Réponse : %s)', __FILE__),
                        $result
                    )
                );
                // Set last sent datetime
                cache::set('jMQTT::'.jMQTTConst::CACHE_JMQTT_NEXT_STATS, $data['next']);
            }
        }
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
    private static function check() {
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
        foreach(jMQTT::getBrokers() as $broker) {
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
        jMQTTDaemon::pluginStats();
    }

    /**
     * Send a jMQTT::EventDaemonState event to the UI containing current daemon state
     * @param bool $_state true if Daemon is running and connected
     */
    public static function sendMqttDaemonStateEvent($_state) {
        event::add('jMQTT::EventDaemonState', $_state);
    }

}
