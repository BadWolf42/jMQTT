<?php

class jMQTTPlugin {

    /**
     * cron callback
     * check MQTT Clients are up and connected
     */
    public static function cron() {
        jMQTTPlugin::stats();
        jMQTTDaemon::check();
    }

    /**
     * Provides dependancy information
     *
     * @return string[]
     */
    public static function dependancy_info() {
        $depLogFile = 'jMQTT_dep';
        $depProgressFile = jeedom::getTmpFolder('jMQTT') . '/dependancy';

        $return = array();
        $return['log'] = log::getPathToLog($depLogFile);
        $return['progress_file'] = $depProgressFile;
        $return['state'] = jMQTTConst::CLIENT_OK;

        if (file_exists($depProgressFile)) {
            jMQTT::logger(
                'debug',
                sprintf(
                    "Dependencies are being installed... (%s%%)",
                    trim(file_get_contents($depProgressFile))
                )
            );
            $return['state'] = jMQTTConst::CLIENT_NOK;
            return $return;
        }

        $composerCheckCmd = 'cat ' . __DIR__ . '/../../resources';
        $composerCheckCmd .= '/JsonPath-PHP/vendor/composer/installed.json ';
        $composerCheckCmd .= '2>/dev/null | grep galbar/jsonpath | wc -l';
        if (exec(system::getCmdSudo() . $composerCheckCmd) < 1) {
            jMQTT::logger(
                'debug',
                "Relaunch dependencies, PHP package 'JsonPath' is missing",
            );
            $return['state'] = jMQTTConst::CLIENT_NOK;
        }

        $daemonDir = __DIR__ . '/../../resources/jmqttd_api';
        if (
            !file_exists($daemonDir . '/venv/bin/pip3')
            || !file_exists($daemonDir . '/venv/bin/python3')
        ) {
            jMQTT::logger(
                'debug',
                "Relaunch dependencies, the Python venv has not been created yet",
            );
            $return['state'] = jMQTTConst::CLIENT_NOK;
        } else {
            $depCheckCmd = $daemonDir . '/venv/bin/pip3 freeze --no-cache-dir -r ';
            $depCheckCmd .= $daemonDir . '/requirements.txt 2>&1 >/dev/null';
            exec($depCheckCmd, $output);
            if (count($output) > 0) {
                $error_msg = __('Relancez les dépendances, au moins une bibliothèque Python requise est manquante dans le venv :', __FILE__);
                $error_msg .= ' <br/>'.implode('<br/>', $output);
                jMQTT::logger('error', $error_msg);
                $return['state'] = jMQTTConst::CLIENT_NOK;
            }
        }

        if ($return['state'] == jMQTTConst::CLIENT_OK)
            jMQTT::logger('debug', "Dependencies seem correctly installed.");
        return $return;
    }

    /**
     * Provides dependancy installation script
     *
     * @return string[]
     */
    public static function dependancy_install() {
        $depLogFile = 'jMQTT_dep';
        $depProgressFile = jeedom::getTmpFolder('jMQTT') . '/dependancy';

        jMQTT::logger('info', sprintf(__('Installation des dépendances, voir log dédié (%s)', __FILE__), $depLogFile));

        $update = update::byLogicalId('jMQTT');
        shell_exec(
            'echo "\n\n================================================================================\n'.
            '== Jeedom '.jeedom::version().' '.jeedom::getHardwareName().
            ' in $(lsb_release -d -s | xargs echo -n) on $(arch | xargs echo -n)/'.
            '$(dpkg --print-architecture | xargs echo -n)/$(getconf LONG_BIT | xargs echo -n)bits\n'.
            '== $(python3 -VV | xargs echo -n)\n'.
            '== jMQTT v'.config::byKey('version', 'jMQTT', 'unknown', true).
            ' ('.$update->getLocalVersion().') branch:'.$update->getConfiguration()['version'].
            ' previously:v'.config::byKey('previousVersion', 'jMQTT', 'unknown', true).
            '" >> '.log::getPathToLog($depLogFile)
        );

        return array(
            'script' => __DIR__ . '/../../resources/install_#stype#.sh ' . $depProgressFile,
            'log' => log::getPathToLog($depLogFile)
        );
    }

    public static function stats($_reason = 'cron') {
        // Check last reporting (or if forced)
        $nextStats = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_JMQTT_NEXT_STATS)->getValue(0);
        if ($_reason === 'cron' && (time() < $nextStats)) { // No reason to force send stats
            // jMQTT::logger('debug', sprintf(
            //     "No reason to send statistical data before %s",
            //     date('Y-m-d H:i:s', $nextStats)
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
                "Anonymous statistical data have been sent: %s",
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
                    "Unable to communicate with the statistics server (Response: %s)",
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
                    "Unable to communicate with the statistics server (Response: %s)",
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
                    sprintf("Statistical data sent (Response: %s)", $result)
                );
                // Set last sent datetime
                cache::set('jMQTT::'.jMQTTConst::CACHE_JMQTT_NEXT_STATS, $data['next']);
            }
        }
    }

    // Check install status of Mosquitto service
    public static function mosquittoCheck() {
        $res = array('installed' => false, 'by' => '', 'message' => '', 'service' => '', 'config' => '');
        $retval = 255;
        $output = null;
        // Check if Mosquitto package is installed
        exec('dpkg -s mosquitto 2> /dev/null 1> /dev/null', $output, $retval); // retval = 1 not installed ; 0 installed

        // Not installed return default values
        if ($retval != 0) {
            $res['message'] = __("Mosquitto n'est pas installé en tant que service.", __FILE__);
            try {
                // Checking for Mosquitto installed in Docker by MQTT Manager
                if (is_object(update::byLogicalId('mqtt2'))
                    && plugin::byId('mqtt2')->isActive()
                    && config::byKey('mode', 'mqtt2', 'NotThere') == 'docker') {
                    // Plugin Active and mqtt2 mode is docker
                    $res['by'] = 'MQTT Manager ' . __('(en docker)', __FILE__);
                    $res['message'] = __('Mosquitto est installé <b>en docker</b> par', __FILE__);
                    $res['message'] .= ' <a class="control-label danger" href="index.php?v=d&p=plugin&id=mqtt2">';
                    $res['message'] .= 'MQTT Manager</a> (mqtt2).';
                }
            } catch (Throwable $e) {
                if (log::getLogLevel('jMQTT') > 100) {
                    jMQTT::logger('error', sprintf(
                        __("%1\$s() a levé l'Exception: %2\$s", __FILE__),
                        __METHOD__,
                        $e->getMessage()
                    ));
                } else {
                    jMQTT::logger('error', sprintf(
                        __("%1\$s() a levé l'Exception: %2\$s", __FILE__) . "\n@Stack: %3\$s",
                        __METHOD__,
                        $e->getMessage(),
                        $e->getTraceAsString()
                    ));
                }
            }
            return $res;
        }

        // Otherwise, it is Installed
        $res['installed'] = true;
        // Get service active status
        $res['service'] = ucfirst(shell_exec('systemctl status mosquitto.service | grep "Active:" | sed -r "s/^[^:]*: (.*)$/\1/"'));
        // Get service config file
        // $res['config'] = shell_exec('systemctl show mosquitto.service -p ExecStart | sed -r "s/^.* -c ([^ ]*) .*$/\1/"');

        // Check if mosquitto.service has been changed by mqtt2
        if (file_exists('/lib/systemd/system/mosquitto.service')
                && strpos(file_get_contents('/lib/systemd/system/mosquitto.service'), 'mqtt2') !== false) {
            $res['by'] = 'MQTT Manager ' . __('(en local)', __FILE__);
            $res['message'] = __('Mosquitto est installé par', __FILE__);
            $res['message'] .= ' <a class="control-label danger" target="_blank" href="index.php?v=d&p=plugin&id=mqtt2">';
            $res['message'] .= 'MQTT Manager</a> (mqtt2).';
        } elseif (
            file_exists('/etc/mosquitto/mosquitto.conf')
            && preg_match(
                '#^include_dir.*zigbee2mqtt/data/mosquitto/include#m',
                file_get_contents('/etc/mosquitto/mosquitto.conf')
            )
        ) {
            // Check if ZigbeeLinker has modified Mosquitto config
            $res['by'] = 'ZigbeeLinker';
            $res['message'] = __('Mosquitto est installé par', __FILE__);
            $res['message'] .= ' <a class="control-label danger" target="_blank" href="index.php?v=d&p=plugin&id=zigbee2mqtt">';
            $res['message'] .= 'ZigbeeLinker</a> (zigbee2mqtt).';
        } elseif (file_exists('/etc/mosquitto/conf.d/jMQTT.conf')) {
            // Check if jMQTT config file is in place
            $res['by'] = 'jMQTT';
            $res['message'] = __('Mosquitto est installé par', __FILE__);
            $res['message'] .= ' <a class="control-label success disabled">jMQTT</a>.';
        } else {
            // Otherwise its considered to be a custom install
            $res['by'] = __("Inconnu", __FILE__);
            $res['message'] = __("Mosquitto n'a pas été installé par un plugin connu.", __FILE__);
        }
        return $res;
    }

    // Install Mosquitto service and create first broker eqpt
    public static function mosquittoInstall() {
        $retval = 255;
        $output = null;
        // Check if Mosquitto package is installed
        exec('dpkg -s mosquitto 2> /dev/null 1> /dev/null', $output, $retval);
        // retval = 1 not installed ; 0 installed
        if ($retval == 0) {
            jMQTT::logger('warning', __("Mosquitto est déjà installé sur ce système !", __FILE__));
            return;
        }

        // Apt-get mosquitto
        jMQTT::logger(
            'info',
            __("Mosquitto : Installation en cours, merci de patienter...", __FILE__)
        );
        shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive apt-get install -y -o Dpkg::Options::="--force-confask,confnew,confmiss" mosquitto');

        $retval = 255;
        $output = null;
        // Check if mosquitto has already been configured (/etc/mosquitto/conf.d/jMQTT.conf is present)
        exec('ls /etc/mosquitto/conf.d/jMQTT.conf 2>/dev/null | wc -w', $output, $retval); // retval = 1 conf ok ; 0 no conf
        if ($retval == 0) {
            shell_exec(system::getCmdSudo() . ' cp ' . __DIR__ . '/../../resources/mosquitto_jMQTT.conf /etc/mosquitto/conf.d/jMQTT.conf');
        }

        // Cleanup, just in case
        shell_exec(system::getCmdSudo() . ' systemctl daemon-reload');
        shell_exec(system::getCmdSudo() . ' systemctl enable mosquitto');
        shell_exec(system::getCmdSudo() . ' systemctl stop mosquitto');
        shell_exec(system::getCmdSudo() . ' systemctl start mosquitto');
        jMQTT::logger('info', __("Mosquitto : Fin de l'installation.", __FILE__));

        // Looking for eqBroker pointing to local mosquitto
        $brokerexists = false;
        foreach (jMQTT::getBrokers() as $broker) {
            $hn = $broker->getConf(jMQTTConst::CONF_KEY_MQTT_ADDRESS);
            $ip = gethostbyname($hn);
            $localips = explode(' ', exec(system::getCmdSudo() . 'hostname -I'));
            if ($hn == '' || substr($ip, 0, 4) == '127.' || in_array($ip, $localips)) {
                $brokerexists = true;
                jMQTT::logger(
                    'info',
                    sprintf(
                        __("L'équipement Broker local #%s# existe déjà, pas besoin d'en créer un.", __FILE__),
                        $broker->getHumanName()
                    )
                );
                break;
            }
        }

        // Could not find a local eqBroker
        if (!$brokerexists) {
            jMQTT::logger(
                'info',
                __("Aucun équipement Broker local n'a été trouvé, création en cours...", __FILE__)
            );
            $brokername = 'local';

            // Looking for a conflict with eqBroker name
            $brokernameconflict = false;
            foreach (jMQTT::getBrokers() as $broker) {
                if ($broker->getName() == $brokername) {
                    $brokernameconflict = true;
                    break;
                }
            }
            if ($brokernameconflict) {
                $i = 0;
                do {
                    $i++;
                    $brokernameconflict = false;
                    $brokername = 'local'.$i;
                    foreach (jMQTT::getBrokers() as $broker) {
                        if ($broker->getName() == $brokername) {
                            $brokernameconflict = true;
                            break;
                        }
                    }
                } while ($brokernameconflict);
            }

            // Creating a new eqBroker to communicate with local Mosquitto
            $broker = new jMQTT();
            $broker->setType(jMQTTConst::TYP_BRK);
            $broker->setName($brokername);
            $broker->setIsEnable(1);
            $broker->save();
            jMQTT::logger(
                'info',
                sprintf(
                    __("L'équipement Broker #%s# a été créé.", __FILE__),
                    $broker->getHumanName()
                )
            );
        }
    }

    // Reinstall Mosquitto service over previous install
    public static function mosquittoRepare() {
        jMQTT::logger(
            'info',
            __("Mosquitto : Réparation en cours, merci de patienter...", __FILE__)
        );
        // Stop service
        shell_exec(system::getCmdSudo() . ' systemctl stop mosquitto');
        // Ensure no config is remaining
        shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive rm -rf /etc/mosquitto');
        // Reinstall and force reapply service default config
        shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive apt-get install --reinstall -y -o Dpkg::Options::="--force-confask,confnew,confmiss" mosquitto');
        // Apply jMQTT config
        shell_exec(system::getCmdSudo() . ' cp ' . __DIR__ . '/../../resources/mosquitto_jMQTT.conf /etc/mosquitto/conf.d/jMQTT.conf');
        // Cleanup, just in case
        shell_exec(system::getCmdSudo() . ' systemctl daemon-reload');
        shell_exec(system::getCmdSudo() . ' systemctl stop mosquitto');
        shell_exec(system::getCmdSudo() . ' systemctl enable mosquitto');
        shell_exec(system::getCmdSudo() . ' systemctl start mosquitto');
        jMQTT::logger('info', __("Mosquitto : Fin de la réparation.", __FILE__));
    }

    // Purge Mosquitto service and all related config files
    public static function mosquittoRemove() {
        $retval = 255;
        $output = null;
        // Check if Mosquitto package is installed
        exec('dpkg -s mosquitto 2> /dev/null 1> /dev/null', $output, $retval); // retval = 1 not installed ; 0 installed
        if ($retval == 1) {
            event::add(
                'jeedom::alert',
                array(
                    'level' => 'danger',
                    'page' => 'plugin',
                    'message' => __("Mosquitto n'est pas installé sur ce système !", __FILE__)
                )
            );
            return;
        }
        jMQTT::logger(
            'info',
            __("Mosquitto : Désinstallation en cours, merci de patienter...", __FILE__)
        );
        // Remove package and /etc folder
        shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive apt-get purge -y mosquitto');
        shell_exec(system::getCmdSudo() . ' DEBIAN_FRONTEND=noninteractive rm -rf /etc/mosquitto');
        jMQTT::logger('info', __("Mosquitto : Fin de la désinstallation.", __FILE__));
    }

}
