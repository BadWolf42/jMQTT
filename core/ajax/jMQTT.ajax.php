<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }


    require_once __DIR__ . '/../../core/class/jMQTT.class.php';
    ajax::init(array('fileupload'));
    $action = init('action');

    ###################################################################################################################
    # File upload
    if ($action == 'fileupload') {
        if (!isset($_FILES['file'])) {
            throw new Exception(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
        }
        if (init('dir') == 'template') {
            $uploaddir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO);
            $allowed_ext = '.json';
            $max_size = 500*1024; // 500KB
        } elseif (init('dir') == 'backup') {
            $uploaddir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_BACKUP);
            $allowed_ext = '.tgz';
            $max_size = 100*1024*1024; // 100MB
        } else {
            throw new Exception(__('Téléversement non valide', __FILE__));
        }
        if (filesize($_FILES['file']['tmp_name']) > $max_size) {
            throw new Exception(sprintf(__('Le fichier est trop gros (maximum %s)', __FILE__), sizeFormat($max_size)));
        }
        $extension = strtolower(strrchr($_FILES['file']['name'], '.'));
        if ($extension != $allowed_ext)
            throw new Exception(sprintf(__("L'extension de fichier '%s' n'est pas autorisée", __FILE__), secureXSS($extension)));
        if (!file_exists($uploaddir)) {
            mkdir($uploaddir);
        }
        if (!file_exists($uploaddir)) {
            throw new Exception(__('Répertoire de téléversement non trouvé :', __FILE__) . ' ' . $uploaddir);
        }
        $fname = $_FILES['file']['name'];
        if (file_exists($uploaddir . '/' . $fname)) {
            throw new Exception(__('Impossible de téléverser le fichier car il existe déjà. Par sécurité, il faut supprimer le fichier existant avant de le remplacer.', __FILE__));
        }
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploaddir . '/' . secureXSS($fname))) {
            throw new Exception(__('Impossible de déplacer le fichier temporaire', __FILE__));
        }
        if (!file_exists($uploaddir . '/' . $fname)) {
            throw new Exception(__('Impossible de téléverser le fichier (limite du serveur web ?)', __FILE__));
        }
        // After template file imported
        if (init('dir') == 'template') {
            // Adapt template for the topic in configuration
            jMQTT::moveTopicToConfigurationByFile($fname);
            jMQTT::logger('info', sprintf(__("Template %s correctement téléversée", __FILE__), $fname));
            ajax::success($fname);
        }
        elseif (init('dir') == 'backup') {
            $backup_dir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_BACKUP);
            $files = ls($backup_dir, '*.tgz', false, array('files', 'quiet'));
            sort($files);
            $backups = array();
            foreach ($files as $backup)
                $backups[] = array('name' => $backup, 'size' => sizeFormat(filesize($backup_dir.'/'.$backup)));
            jMQTT::logger('info', sprintf(__("Sauvegarde %s correctement téléversée", __FILE__), $fname));
            ajax::success($backups);
        }
    }

    ###################################################################################################################
    # Add a new command on an existing jMQTT equipment
    if ($action == 'newCmd') {
        /** @var void|jMQTT $eqpt */
        $eqpt = jMQTT::byId(init('id'));
        if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
            throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s", __FILE__), init('id')));
        }
        $new_cmd = jMQTTCmd::newCmd($eqpt, init('name'), init('topic'), init('jsonPath'));
        $new_cmd->save();
        ajax::success(array('id' => $new_cmd->getId(), 'human' => $new_cmd->getHumanName()));
    }

    ###################################################################################################################
    # Test jsonPath
    if ($action == 'testJsonPath') {
        $payload = init('payload');
        if ($payload == '') {
            ajax::success(array('success' => false, 'message' => __('Pas de payload', __FILE__), 'value' => ''));
        }

        $jsonArray = json_decode($payload, true);
        if (!is_array($jsonArray) || json_last_error() != JSON_ERROR_NONE) {
            if (json_last_error() == JSON_ERROR_NONE)
                ajax::success(array('success' => false, 'message' => __("Problème de format JSON: Le message reçu n'est pas au format JSON.", __FILE__), 'value' => ''));
            else
                ajax::success(array('success' => false, 'message' => sprintf(__("Problème de format JSON: %1\$s (%2\$d)", __FILE__), json_last_error_msg(), json_last_error()), 'value' => ''));
        }

        if (file_exists(__DIR__ . '/../../resources/JsonPath-PHP/vendor/autoload.php'))
            require_once __DIR__ . '/../../resources/JsonPath-PHP/vendor/autoload.php';
        if (!class_exists('JsonPath\JsonObject'))
            throw new Exception(__("La bibliothèque JsonPath-PHP n'a pas été trouvée, relancez l'installation des dépendances", __FILE__));

        $jsonPath = trim(init('jsonPath'));
        if (strlen($jsonPath) == 0 || $jsonPath[0] != '$')
            $jsonPath = '$' . $jsonPath;

        // Create JsonObject for JsonPath
        try {
            $jsonobject = new JsonPath\JsonObject($jsonArray);
            $value = $jsonobject->get($jsonPath);
            if ($value !== false && $value !== array()) {
                ajax::success(array(
                    'success' => true,
                    'message' => 'OK',
                    'value' => json_encode(
                        (count($value) > 1) ? $value : $value[0],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    )
                ));
            } else {
                ajax::success(array(
                    'success' => false,
                    'message' => __("Le Chemin JSON n'a pas retourné de résultat sur ce payload json", __FILE__),
                    'value' => ''
                ));
            }
        } catch (Throwable $e) {
            if (log::getLogLevel('jMQTT') <= 100) {
                jMQTT::logger(
                    'warning',
                    str_replace(
                        "\n",
                        ' <br/> ',
                        sprintf(
                            __("Chemin JSON '%1\$s' dans le testeur de JsonPath a levé l'Exception: %2\$s", __FILE__).
                            ",<br/>@Stack: %4\$s.",
                            $jsonPath,
                            $e->getMessage(),
                            $e->getTraceAsString()
                        )
                    )
                );
            }
            ajax::success(array(
                'success' => false,
                'message' => __("Exception : ", __FILE__) . $e->getMessage(),
                'stack' => $e->getTraceAsString(),
                'value' => ''
            ));
        }
    }

    ###################################################################################################################
    # Template
    if ($action == 'getTemplateList') {
        ajax::success(jMQTT::templateList());
    }

    if ($action == 'getTemplateByFile') {
        ajax::success(jMQTT::templateByFile(init('file')));
    }

    if ($action == 'deleteTemplateByFile') {
        if (!jMQTT::deleteTemplateByFile(init('file')))
            throw new Exception(__('Impossible de supprimer le fichier', __FILE__));
        ajax::success(true);
    }

    if ($action == 'applyTemplate') {
        /** @var void|jMQTT $eqpt */
        $eqpt = jMQTT::byId(init('id'));
        if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
            throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s", __FILE__), init('id')));
        }
        $template = jMQTT::templateByName(init('name'));
        $eqpt->applyATemplate($template, init('topic'), init('keepCmd'));
        ajax::success();
    }

    if ($action == 'createTemplate') {
        /** @var void|jMQTT $eqpt */
        $eqpt = jMQTT::byId(init('id'));
        if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
            throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s", __FILE__), init('id')));
        }
        $eqpt->createTemplate(init('name'));
        ajax::success();
    }

    ###################################################################################################################
    # Configuration page
    if ($action == 'startMqttClient') {
        $broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($broker->startMqttClient());
    }

    if ($action == 'sendLoglevel') {
        jMQTTComToDaemon::setLogLevel(init('level'));
        ajax::success();
    }

    if ($action == 'updateUrlOverride') {
        config::save('urlOverrideEnable', init('valEn'), 'jMQTT');
        config::save('urlOverrideValue', init('valUrl'), 'jMQTT');
        ajax::success();
    }

    ###################################################################################################################
    # Real Time mode
    if ($action == 'changeRealTimeMode') {
        $id = init('id');
        $mode = init('mode');
        $subscribe = init('subscribe', '#');
        $exclude = init('exclude', 'homeassistant/#');
        $retained = init('retained', false);
        $duration = init('duration', 180);

        $broker = jMQTT::getBrokerFromId($id);
        $broker->log(
            'info',
            $mode ? __("Lancement du Mode Temps Réel...", __FILE__) : __("Arrêt du Mode Temps Réel...", __FILE__)
        );

        // $broker->log('debug', sprintf("changeRealTimeMode(id=%1\$s, mode=%2\$s, subscribe=%3\$s, exclude=%4\$s, retained=%5\$s, duration=%6)",
        //                               $id, $mode, $subscribe, $exclude, $retained, $duration));

        // If Real Time mode needs to be enabled
        if ($mode) {
            // Check if a subscription topic is provided
            if (trim($subscribe) == '') {
                throw new Exception(
                    __("Impossible d'activer le mode Temps Réel avec un topic de souscription vide", __FILE__)
                );
            }
            // Cleanup subscriptions ($subscribe is never empty here)
            $subscriptions = [];
            foreach (explode('|', $subscribe) as $t) {
                $t = trim($t);
                if ($t == '')
                    continue;
                $subscriptions[] = $t;
            }
            if (empty($subscriptions)) {
                throw new Exception(
                    __("Impossible d'activer le mode Temps Réel sans topic de souscription", __FILE__)
                );
            }
            // Cleanup exclusions
            $exclude = (trim($exclude) == '') ? [] : explode('|', $exclude);
            $exclusions = [];
            foreach ($exclude as $t) {
                $t = trim($t);
                if ($t == '')
                    continue;
                $exclusions[] = $t;
            }
            // Cleanup retained
            $retained = is_bool($retained) ? $retained : ($retained == '1' || $retained == 'true');
            // Cleanup duration
            $duration = min(max(1, intval($duration)), 3600);
            // Start Real Time Mode (must be started before subscribe)
            jMQTTComToDaemon::realTimeStart($id, $subscriptions, $exclusions, $retained, $duration);
            // Update cache
            $broker->setCache(jMQTTConst::CACHE_REALTIME_INC_TOPICS, implode('|', $subscriptions));
            $broker->setCache(jMQTTConst::CACHE_REALTIME_EXC_TOPICS, implode('|', $exclusions));
            $broker->setCache(jMQTTConst::CACHE_REALTIME_RET_TOPICS, $retained ? 1 : 0);
            $broker->setCache(jMQTTConst::CACHE_REALTIME_DURATION, $duration);
        } else { // Real Time mode needs to be disabled
            // Stop Real Time mode
            jMQTTComToDaemon::realTimeStop($id);
        }
        ajax::success();
    }

    if ($action == 'realTimeGet') {
        $_file = jeedom::getTmpFolder('jMQTT').'/rt' . trim(init('id')) . '.json';
        if (!file_exists($_file))
            ajax::success([]);
        // Read content from file without error handeling!
        $content = file_get_contents($_file);
        // Decode template file content to json (or raise)
        $json = json_decode($content, true);
        if (is_null($json))
            ajax::success([]);
        // Get filtering data
        $since = init('since', '');
        // Search for compatible eqLogic on this Broker
        $eqpts = jMQTT::byBrkId(init('id'));
        $res = [];
        // Function to filter array on date
        function since_filter($val) { global $since; return $val['date'] > $since; }
        // Filter array and search for matching eqLogic on remainings
        foreach (array_filter($json, 'since_filter') as $msg) {
            $eqNames = '';
            foreach ($eqpts as $eqpt) {
                if (mosquitto_topic_matches_sub($eqpt->getTopic(), $msg['topic']))
                    $eqNames .= '<br/>#'.$eqpt->getHumanName().'#';
            }
            $msg['existing'] = $eqNames;
            $res[] = $msg;
        }
        // Return result
        ajax::success($res);
    }

    if ($action == 'realTimeClear') {
        jMQTTComToDaemon::realTimeClear(init('id'));
        ajax::success();
    }

    ###################################################################################################################
    # Mosquitto
    if ($action == 'mosquittoInstall') {
        jMQTTPlugin::mosquittoInstall();
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if ($action == 'mosquittoRepare') {
        jMQTTPlugin::mosquittoRepare();
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if ($action == 'mosquittoRemove') {
        jMQTTPlugin::mosquittoRemove();
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if ($action == 'mosquittoReStart') {
        exec('sudo systemctl restart mosquitto');
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if ($action == 'mosquittoStop') {
        exec('sudo systemctl stop mosquitto');
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if ($action == 'mosquittoConf') {
        $cfg = file_get_contents('/etc/mosquitto/conf.d/jMQTT.conf');
        ajax::success($cfg);
    }

    if ($action == 'mosquittoEdit') {
        if (init('config') == '')
            throw new Exception(__('Configuration manquante', __FILE__));
        shell_exec('sudo tee /etc/mosquitto/conf.d/jMQTT.conf > /dev/null <<jmqttEOF' . "\n" . init('config') . 'jmqttEOF');
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if ($action == 'debugModaleToggle') {
        if (init('status') == '')
            throw new Exception(__('Valeur manquante', __FILE__));
        config::save('debugMode', init('status'), 'jMQTT');
        ajax::success();
    }

    // ########################################################################
    // Community Post
    if ($action == 'jMQTTCommunityPost') {
        $infoPost = '< Ajoutez un titre puis rédigez votre question/problème ici, sans effacer les infos de config indiquées ci-dessous >';
        $infoPost .= '<br/><br/><br/><br/>--- <br/>[details="Mes infos de config"]<br/>```json';

        // OS, hardware, PHP, Python
        $hw = jeedom::getHardwareName();
        if ($hw == 'diy')
            $hw = trim(shell_exec('systemd-detect-virt'));
        if ($hw == 'none')
            $hw = 'diy';
        $distrib = trim(shell_exec('. /etc/*-release && echo $ID $VERSION_ID'));
        $infoPost .= '<br/>OS version: ' . $distrib . ' on ' . $hw;
        $infoPost .= '<br/>PHP version: ' . phpversion();
        $infoPost .= '<br/>Python version: ';
        $infoPost .= trim(shell_exec("python3 -V | cut -d' ' -f2"));
        $m = jMQTTPlugin::mosquittoCheck();
        $infoPost .= '<br/>Mosquitto: ' . ($m['installed'] ? ('Installed by ' . $m['by']) : 'Not installed');

        // Jeedom core version and branch
        $infoPost .= '<br/>Core version: ';
        $infoPost .= config::byKey('version', 'core', 'N/A');
        $infoPost .= ' (' . config::byKey('core::branch') . ')';
        $infoPost .= '<br/>Nb lines in http.error: ';
        $infoPost .= trim(shell_exec("wc -l /var/www/html/log/http.error | cut -d' ' -f1"));

        // All plugins
        $infoPost .= '<br/>Plugins:';
        // activateOnly, !orderByCategoryuse, !translate, nameOnly
        foreach (plugin::listPlugin(true, false, true, true) as $p) {
            $infoPost .= ' '.$p;
        }
        $infoPost .= '<br/>';

        // jMQTT version
        /** @var update $update */
        $update = update::byTypeAndLogicalId('plugin', 'jMQTT');
        $infoPost .= '<br/>jMQTT: ';
        $infoPost .= config::byKey('version', 'jMQTT', 'unknown', true);
        $infoPost .= ' (' . $update->getLocalVersion() . ') branch: ';
        $infoPost .= $update->getConfiguration('version');

        // jMQTT logs stats
        $infoPost .= '<br/>Nb Errors or Warnings in jMQTT logs: ';
        $infoPost .= trim(shell_exec("cat /var/www/html/log/jMQTT* 2> /dev/null | grep -c 'ERROR\|WARNING'"));
        $infoPost .= ' (level is ' . log::convertLogLevel(log::getLogLevel(jMQTT::class)) . ')';

        // Daemon status
        $daemon_info = jMQTT::deamon_info();
        $infoPost .= '<br/>Daemon Status: ';
        $infoPost .= (jMQTTDaemon::check() ? 'Started' :  'Stopped') . ' (';
        $infoPost .= config::byKey('lastDeamonLaunchTime', 'jMQTT', 'Unknown') . ')';

        // Brk, eq, cmds
        $nbEqAll = DB::Prepare('SELECT COUNT(*) as nb FROM `eqLogic` WHERE `eqType_name` = "jMQTT"', array());
        $nbBrks = DB::Prepare('SELECT COUNT(*) as nb FROM `eqLogic` WHERE `eqType_name` = "jMQTT" AND `configuration` LIKE "%broker%"', array());
        $infoPost .= '<br/>Nb eqBrokers: ' . $nbBrks['nb'];
        $infoPost .= ' / eqLogics: ' . ($nbEqAll['nb'] - $nbBrks['nb']);
        $nbCmds = DB::Prepare('SELECT COUNT(*) as nb FROM `cmd` WHERE `eqType` = "jMQTT"', array());
        $infoPost .= ' / cmds: ' . $nbCmds['nb'];

        $infoPost .= '<br/>```<br/>[/details]';

        $query = http_build_query(
                array(
                'category' => 'plugins/protocole-domotique',
                'tags' => 'plugin-jmqtt',
                'body' => br2nl($infoPost)
            )
        );
        $url = 'https://community.jeedom.com/new-topic?' . $query;
        ajax::success(array('url' => $url, 'plugin' => 'jMQTT'));
    }

    throw new Exception(__('Aucune méthode Ajax ne correspond à :', __FILE__) . ' ' . $action);
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
?>
