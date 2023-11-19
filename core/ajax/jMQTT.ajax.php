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

    ###################################################################################################################
    # File upload
    if (init('action') == 'fileupload') {
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
            throw new Exception(__('Téléversement invalide', __FILE__));
        }
        if (filesize($_FILES['file']['tmp_name']) > $max_size) {
            throw new Exception(sprintf(__('Le fichier est trop gros (maximum %s)', __FILE__), sizeFormat($max_size)));
        }
        $extension = strtolower(strrchr($_FILES['file']['name'], '.'));
        if ($extension != $allowed_ext)
            throw new Exception(sprintf(__("L'extension de fichier '%s' n'est pas autorisée", __FILE__), $extension));
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
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploaddir . '/' . $fname)) {
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
    if (init('action') == 'newCmd') {
        /** @var jMQTT $eqpt */
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
    if (init('action') == 'testJsonPath') {
        $payload = init('payload');
        if ($payload == '') {
            ajax::success(array('success' => false, 'message' => __('Pas de payload', __FILE__), 'value' => ''));
            return;
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
            throw new Exception(__("La bibliothèque JsonPath-PHP n'a pas été trouvée, relancez les dépendances", __FILE__));

        $jsonPath = trim(init('jsonPath'));
        if (strlen($jsonPath) == 0 || $jsonPath[0] != '$')
            $jsonPath = '$' . $jsonPath;

        // Create JsonObject for JsonPath
        try {
            $jsonobject = new JsonPath\JsonObject($jsonArray);
            $value = $jsonobject->get($jsonPath);
                if ($value !== false && $value !== array())
                ajax::success(array(
                    'success' => true,
                    'message' => 'OK',
                    'value' => json_encode(
                        (count($value) > 1) ? $value : $value[0],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    )
                ));
            else
                ajax::success(array(
                    'success' => false,
                    'message' => __("Le Chemin JSON n'a pas retourné de résultat sur ce message json", __FILE__),
                    'value' => ''
                ));
        } catch (Throwable $e) {
            ajax::success(array(
                'success' => false,
                'message' => __("Exception: ", __FILE__) . $e->getMessage(),
                'stack' => $e->getTraceAsString(),
                'value' => ''
            ));
            if (log::getLogLevel('jMQTT') <= 100)
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
    }

    ###################################################################################################################
    # Template
    if (init('action') == 'getTemplateList') {
        ajax::success(jMQTT::templateList());
    }

    if (init('action') == 'getTemplateByFile') {
        ajax::success(jMQTT::templateByFile(init('file')));
    }

    if (init('action') == 'deleteTemplateByFile') {
        if (!jMQTT::deleteTemplateByFile(init('file')))
            throw new Exception(__('Impossible de supprimer le fichier', __FILE__));
        ajax::success(true);
    }

    if (init('action') == 'applyTemplate') {
        /** @var jMQTT $eqpt */
        $eqpt = jMQTT::byId(init('id'));
        if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
            throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s", __FILE__), init('id')));
        }
        $template = jMQTT::templateByName(init('name'));
        $eqpt->applyATemplate($template, init('topic'), init('keepCmd'));
        ajax::success();
    }

    if (init('action') == 'createTemplate') {
        /** @var jMQTT $eqpt */
        $eqpt = jMQTT::byId(init('id'));
        if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
            throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s", __FILE__), init('id')));
        }
        $eqpt->createTemplate(init('name'));
        ajax::success();
    }

    ###################################################################################################################
    # Configuration page
    if (init('action') == 'startMqttClient') {
        $broker = jMQTT::getBrokerFromId(init('id'));
        ajax::success($broker->startMqttClient());
    }

    if (init('action') == 'sendLoglevel') {
        jMQTTComToDaemon::setLogLevel(init('level'));
        ajax::success();
    }

    if (init('action') == 'updateUrlOverride') {
        config::save('urlOverrideEnable', init('valEn'), 'jMQTT');
        config::save('urlOverrideValue', init('valUrl'), 'jMQTT');
        ajax::success();
    }

    ###################################################################################################################
    # Real Time mode
    if (init('action') == 'changeRealTimeMode') {
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
        if($mode) {
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
            if (count($subscriptions) == 0) {
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

    if (init('action') == 'realTimeGet') {
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

    if (init('action') == 'realTimeClear') {
        jMQTTComToDaemon::realTimeClear(init('id'));
        ajax::success();
    }

    ###################################################################################################################
    # Mosquitto
    if (init('action') == 'mosquittoInstall') {
        jMQTTPlugin::mosquittoInstall();
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if (init('action') == 'mosquittoRepare') {
        jMQTTPlugin::mosquittoRepare();
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if (init('action') == 'mosquittoRemove') {
        jMQTTPlugin::mosquittoRemove();
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if (init('action') == 'mosquittoReStart') {
        exec(system::getCmdSudo() . ' systemctl restart mosquitto');
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if (init('action') == 'mosquittoStop') {
        exec(system::getCmdSudo() . ' systemctl stop mosquitto');
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    if (init('action') == 'mosquittoConf') {
        $cfg = file_get_contents('/etc/mosquitto/conf.d/jMQTT.conf');
        ajax::success($cfg);
    }

    if (init('action') == 'mosquittoEdit') {
        if (init('config') == '')
            throw new Exception(__('Configuration manquante', __FILE__));
        shell_exec(system::getCmdSudo() . ' tee /etc/mosquitto/conf.d/jMQTT.conf > /dev/null <<jmqttEOF' . "\n" . init('config') . 'jmqttEOF');
        ajax::success(jMQTTPlugin::mosquittoCheck());
    }

    ###################################################################################################################
    # Backup / Restore
    if (init('action') == 'backupCreate') {
        jMQTT::logger('info', sprintf(__("Sauvegarde de jMQTT lancée...", __FILE__)));
        $out = null;
        $code = null;
        exec('php ' . __DIR__ . '/../../resources/jMQTT_backup.php --all >> ' . log::getPathToLog('jMQTT') . ' 2>&1', $out, $code);
        if ($code)
            throw new Exception(__("Échec de la sauvegarde de jMQTT, consultez le log jMQTT", __FILE__));

        $backup_dir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_BACKUP);
        $files = ls($backup_dir, '*.tgz', false, array('files', 'quiet'));
        sort($files);
        $backups = array();
        foreach ($files as $backup)
            $backups[] = array(
                'name' => $backup,
                'size' => sizeFormat(filesize($backup_dir.'/'.$backup))
            );
        jMQTT::logger('info', __("Sauvegarde de jMQTT effectuée", __FILE__));
        ajax::success($backups);
    }

    if (init('action') == 'backupRemove') {
        /** @var null|string $_backup */
        $_backup = init('file');
        if (
            !isset($_backup)
            || is_null($_backup) // @phpstan-ignore-line
            || $_backup == ''
        ) {
            throw new Exception(__('Merci de fournir le fichier à supprimer', __FILE__));
        }

        $backup_dir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_BACKUP);
        if (in_array($_backup, ls($backup_dir, '*.tgz', false, array('files', 'quiet'))) && file_exists($backup_dir.'/'.$_backup))
            unlink($backup_dir.'/'.$_backup);
        else
            throw new Exception(__('Impossible de supprimer le fichier', __FILE__));
        ajax::success();
    }

    if (init('action') == 'backupRestore') {
        /** @var null|string $_backup */
        $_backup = init('file');
        if (
            !isset($_backup)
            || is_null($_backup) // @phpstan-ignore-line
            || $_backup == ''
        ){
            throw new Exception(__('Merci de fournir le fichier à restaurer', __FILE__));
        }

        $backup_dir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_BACKUP);
        if (!in_array($_backup, ls($backup_dir, '*.tgz', false, array('files', 'quiet'))))
            throw new Exception(__('Impossible de restaurer le fichier fourni', __FILE__));

        $msg = sprintf(__("Restauration de la sauvegarde %s...", __FILE__), $_backup);
        @message::removeAll('jMQTT', 'backupRestoreEnded');
        @message::add('jMQTT', $msg, '', 'backupRestoreStarted');
        jMQTT::logger('warning', $msg);
        // Use a temporary log file for restoration
        file_put_contents(log::getPathToLog('tmp_jMQTT'), date('[Y-m-d H:i:s][\I\N\F\O] : ') . $msg . "\n");
        // exec("echo '" . date('[Y-m-d H:i:s][\I\N\F\O] : ') . $msg . "' >> " . );

        // Flags
        $flags = ' ';
        if (init('nohwcheck') == '1') $flags .= '--no-hw-check ';
        if (init('notfolder') == '1') $flags .= '--not-folder ';
        if (init('noteqcmd') == '1') $flags .= '--not-eq-cmd ';
        if (init('byname') == '1') $flags .= '--by-name ';
        if (init('dodelete') == '1') $flags .= '--do-delete ';
        if (init('notcache') == '1') $flags .= '--not-cache ';
        if (init('nothistory') == '1') $flags .= '--not-history ';
        if (init('dologs') == '1') $flags .= '--do-logs ';
        if (init('domosquitto') == '1') $flags .= '--do-mosquitto ';
        if (init('verbose') == '1') $flags .= '--verbose ';
        if (init('apply') == '1') $flags .= '--apply ';

        // Launch restoration
        $out = null;
        $res = null;
        exec('php ' . __DIR__ . '/../../resources/jMQTT_restore.php ' . $flags . '--file ' . $backup_dir.'/'.$_backup . ' >> ' . log::getPathToLog('tmp_jMQTT') . ' 2>&1', $out, $res);

        // Append temporary log to jMQTT log
        file_put_contents(log::getPathToLog('jMQTT'), file_get_contents(log::getPathToLog('tmp_jMQTT')), FILE_APPEND);
        // exec('cat ' . log::getPathToLog('tmp_jMQTT') . ' >> ' . log::getPathToLog('jMQTT'));
        unlink(log::getPathToLog('tmp_jMQTT'));
        // exec('rm ' . log::getPathToLog('tmp_jMQTT'));
        @message::removeAll('jMQTT', 'backupRestoreStarted');
        if (!$res) {
            $msg = sprintf(__("Restauration de la sauvegarde %s effectuée avec succès", __FILE__), $_backup);
            file_put_contents(log::getPathToLog('jMQTT'), date('[Y-m-d H:i:s][\I\N\F\O] : ') . $msg . "\n", FILE_APPEND);
            // exec("echo '" . date('[Y-m-d H:i:s][\I\N\F\O] : ') . $msg . "' >> " . log::getPathToLog('jMQTT'));
            ajax::success();
        } else {
            $msg = sprintf(__("Échec de la restauration de %s, consultez le log jMQTT", __FILE__), $_backup);
            file_put_contents(log::getPathToLog('jMQTT'), date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . $msg . "\n", FILE_APPEND);
            // exec("echo '" . date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . $msg . "' >> " . log::getPathToLog('jMQTT'));
            @message::add('jMQTT', $msg, '', 'backupRestoreEnded');
            throw new Exception($msg);
        }
    }

    throw new Exception(__('Aucune méthode Ajax ne correspond à :', __FILE__) . ' ' . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
?>
