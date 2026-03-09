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
        throw new Exception('401 - Unauthorized access');
    }

    require_once __DIR__ . '/../../core/class/jMQTT.class.php';
    ajax::init();
    $action = init('action');

// -------------------- Config Daemon --------------------
    if ($action == 'configGetInternal') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        $res[] = array('header' => '', 'data' => config::searchKey('', "jMQTT"));
        ajax::success($res);
    }
    if ($action == 'configSetInternal') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': key=' . init('key') . ' value=' . init('val'));
        config::save(init('key'), json_decode(init('val')), 'jMQTT');
        ajax::success();
    }
    if ($action == 'configDelInternal') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': key=' . init('key'));
        config::remove(init('key'), 'jMQTT');
        ajax::success();
    }

// -------------------- Config eqBroker / eqLogic --------------------
    if ($action == 'configGetBrokers') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        foreach (jMQTT::getBrokers() as $eqBroker) {
            $data = array();
            foreach (utils::o2a($eqBroker)['configuration'] as $k => $val)
                $data[] = array('key' => $k, 'value' => $val);
            $header = $eqBroker->getHumanName() . ' (id: ' . $eqBroker->getId() . ')';
            $res[] = array('header' => $header, 'id' => $eqBroker->getId(), 'data' => $data);
        }
        ajax::success($res);
    }
    if ($action == 'configGetEquipments') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        foreach (jMQTT::getNonBrokers() as $brk)
            foreach ($brk as $eqLogic) {
                $data = array();
                foreach (utils::o2a($eqLogic)['configuration'] as $k => $val)
                    $data[] = array('key' => $k, 'value' => $val);
                $header = $eqLogic->getHumanName() . ' (id: ' . $eqLogic->getId() . ')';
                $res[] = array('header' => $header, 'id' => $eqLogic->getId(), 'data' => $data);
            }
        ajax::success($res);
    }
    if ($action == 'configSetBrkAndEqpt') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': id=' . init('id') .' key=' . init('key') . ' value=' . init('val'));
        jMQTT::byId(init('id'))->setConfiguration(init('key'), json_decode(init('val')))->save();
        ajax::success();
    }
    if ($action == 'configDelBrkAndEqpt') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': id=' . init('id') . ' key=' . init('key'));
        jMQTT::byId(init('id'))->setConfiguration(init('key'), null)->save();
        ajax::success();
    }

// -------------------- Config cmd --------------------
    if ($action == 'configGetCommandsInfo') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
            if ($cmd->getType() != 'info')
                continue;
            $data = array();
            foreach (utils::o2a($cmd)['configuration'] as $k => $val)
                $data[] = array('key' => $k, 'value' => $val);
            $header = $cmd->getHumanName() . ' (id: ' . $cmd->getId() . ')';
            $res[] = array('header' => $header, 'id' => $cmd->getId(), 'data' => $data);
        }
        ajax::success($res);
    }
    if ($action == 'configGetCommandsAction') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
            if ($cmd->getType() != 'action')
                continue;
            $data = array();
            foreach (utils::o2a($cmd)['configuration'] as $k => $val)
                $data[] = array('key' => $k, 'value' => $val);
            $header = $cmd->getHumanName() . ' (id: ' . $cmd->getId() . ')';
            $res[] = array('header' => $header, 'id' => $cmd->getId(), 'data' => $data);
        }
        ajax::success($res);
    }
    if ($action == 'configSetCommands') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': id=' . init('id') . ' key=' . init('key') . ' value=' . init('val'));
        jMQTTCmd::byId(init('id'))->setConfiguration(
            init('key'),
            json_decode(init('val'))
        )->save();
        ajax::success();
    }
    if ($action == 'configDelCommands') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': id=' . init('id') . ' key=' . init('key'));
        jMQTTCmd::byId(init('id'))->setConfiguration(init('key'), null)->save();
        ajax::success();
    }

// -------------------- Cache Daemon --------------------
    if ($action == 'cacheGetInternal') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $cacheKeys = array();
        // $cacheKeys[] = 'deamonStartjMQTTinprogress';
        $cacheKeys[] = 'dependancyjMQTT';
        $cacheKeys[] = 'jMQTT::' . jMQTTConst::CACHE_DAEMON_LAST_RCV;
        $cacheKeys[] = 'jMQTT::' . jMQTTConst::CACHE_DAEMON_LAST_SND;
        $cacheKeys[] = 'jMQTT::' . jMQTTConst::CACHE_DAEMON_PORT;
        $cacheKeys[] = 'jMQTT::' . jMQTTConst::CACHE_DAEMON_UID;
        $cacheKeys[] = 'jMQTT::' . jMQTTConst::CACHE_JMQTT_NEXT_STATS;
        // $cacheKeys[] = 'jMQTT::dummy';
        // $cacheKeys[] = ;
        $data = array();
        foreach ($cacheKeys as $k)
            $data[] = array('key' => $k, 'value' => cache::byKey($k)->getValue(null));
        $res = array();
        $res[] = array('header' => '', 'data' => $data);
        ajax::success($res);
    }
// -------------------- Cache eqBroker --------------------
    if ($action == 'cacheGetBrokers') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        foreach (jMQTT::getBrokers() as $brk) {
            $cacheBrkKeys = array();
            $cacheBrkKeys[] = 'eqLogicCacheAttr' . $brk->getId();
            $cacheBrkKeys[] = 'eqLogicStatusAttr' . $brk->getId();
            $data = array();
            foreach ($cacheBrkKeys as $k) {
                $val = cache::byKey($k)->getValue(null);
                if (!is_null($val))
                    $data[] = array('key' => $k, 'value' => $val);
            }
            if ($data !== array()) {
                $header = $brk->getHumanName() . ' (id: ' . $brk->getId() . ')';
                $res[] = array('header' => $header, 'data' => $data);
            }
        }
        ajax::success($res);
    }
// -------------------- Cache eqLogic --------------------
    if ($action == 'cacheGetEquipments') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        foreach (jMQTT::getNonBrokers() as $brk) {
            foreach ($brk as $eqpt) {
                $cacheEqptKeys = array();
                $cacheEqptKeys[] = 'jMQTT::' . $eqpt->getId() . '::' . jMQTTConst::CACHE_IGNORE_TOPIC_MISMATCH;
                $cacheEqptKeys[] = 'jMQTT::' . $eqpt->getId() . '::' . jMQTTConst::CACHE_MQTTCLIENT_CONNECTED;
                $cacheEqptKeys[] = 'eqLogicCacheAttr' . $eqpt->getId();
                $cacheEqptKeys[] = 'eqLogicStatusAttr' . $eqpt->getId();
                $data = array();
                foreach ($cacheEqptKeys as $k) {
                    $val = cache::byKey($k)->getValue(null);
                    if (!is_null($val))
                        $data[] = array('key' => $k, 'value' => $val);
                }
                if ($data !== array()) {
                    $header = $eqpt->getHumanName() . ' (id: ' . $eqpt->getId() . ')';
                    $res[] = array('header' => $header, 'data' => $data);
                }
            }
        }
        ajax::success($res);
    }
// -------------------- Cache cmd --------------------
    if ($action == 'cacheGetCommandsInfo') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
            if ($cmd->getType() != 'info')
                continue;
            $cacheCmdKeys = array();
            $cacheCmdKeys[] = 'cmdCacheAttr' . $cmd->getId();
            $cacheCmdKeys[] = 'cmd' . $cmd->getId();
            $data = array();
            foreach ($cacheCmdKeys as $k) {
                $val = cache::byKey($k)->getValue(null);
                if (!is_null($val))
                    $data[] = array('key' => $k, 'value' => $val);
            }
            if ($data !== array()) {
                $header = $cmd->getHumanName() . ' (id:' . $cmd->getId() . ')';
                $res[] = array('header' => $header, 'data' => $data);
            }
        }
        ajax::success($res);
    }
    if ($action == 'cacheGetCommandsAction') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        $res = array();
        foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
            if ($cmd->getType() != 'action')
                continue;
            $cacheCmdKeys = array();
            $cacheCmdKeys[] = 'cmdCacheAttr' . $cmd->getId();
            $cacheCmdKeys[] = 'cmd' . $cmd->getId();
            $data = array();
            foreach ($cacheCmdKeys as $k) {
                $val = cache::byKey($k)->getValue(null);
                if (!is_null($val))
                    $data[] = array('key' => $k, 'value' => $val);
            }
            if ($data !== array()) {
                $header = $cmd->getHumanName() . ' (id:' . $cmd->getId() . ')';
                $res[] = array('header' => $header, 'data' => $data);
            }
        }
        ajax::success($res);
    }

// -------------------- Cache set / delete --------------------
    if ($action == 'cacheSet') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': key=' . init('key') . ' value=' . init('val'));
        cache::set(init('key'), json_decode(init('val'), true));
        ajax::success();
    }
    if ($action == 'cacheDel') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': key=' . init('key'));
        cache::delete(init('key'));
        ajax::success();
    }

// -------------------- Send raw data to Daemon --------------------
    if ($action == 'sendToDaemon') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': data=' . init('data'));
        $data = json_decode(init('data'), true);
        if (is_null($data) || !is_array($data)) {
            ajax::error('Invalid format');
        }
        // Send to Daemon
        jMQTTComToDaemon::send($data);
        ajax::success();
    }
    if ($action == 'sendToJeedom') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': data=' . init('data'));
        $data = json_decode(init('data'), true);
        if (is_null($data) || !is_array($data)) {
            ajax::error('Invalid format');
        }
        // Prepare url
        $callbackURL = jMQTTDaemon::get_callback_url();
        // To fix issue: https://community.jeedom.com/t/87727/39
        if (
            (file_exists('/.dockerenv') || config::byKey('forceDocker', 'jMQTT', '0'))
            && config::byKey('urlOverrideEnable', 'jMQTT', '0') == '1'
        ) {
            $callbackURL = config::byKey('urlOverrideValue', 'jMQTT', $callbackURL);
        }
        $url = $callbackURL . '?apikey=' . jeedom::getApiKey('jMQTT') . '&uid=';
        $url .= (@cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID)->getValue("0:0"));
        // Send to Jeedom
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_POSTFIELDS, init('data'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl); // TODO: curl_close() is deprecated in PHP8.0

        ajax::success($response);
    }

// ----------------------- Backup / Restore ------------------------
    function listBackup() {
        $backup_dir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_BACKUP);
        $files = ls($backup_dir, '*.tgz', false, array('files', 'quiet'));
        sort($files);
        $backups = array();
        foreach ($files as $backup)
            $backups[] = array(
                'name' => $backup,
                'size' => sizeFormat(filesize($backup_dir.'/'.$backup))
            );
        // jMQTT::logger('debug', 'listBackup: ' . json_encode($backups, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $backups;
    }
    if ($action == 'backupList') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        ajax::success(listBackup());
    }
    if ($action == 'backupCreate') {
        jMQTT::logger('info', "JMQTT backup launched...");
        $out = null;
        $code = null;
        exec('php ' . __DIR__ . '/../../resources/jMQTT_backup.php --all >> ' . log::getPathToLog('jMQTT') . ' 2>&1', $out, $code);
        if ($code)
            throw new Exception("JMQTT backup failed, see jMQTT log");
        jMQTT::logger('info', "JMQTT backup successful");
        ajax::success(listBackup());
    }
    if ($action == 'backupRemove') {
        /** @var string $_backup */
        $_backup = init('file');
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': file=' . $_backup);
        if ($_backup == '') {
            throw new Exception("Please provide the file to delete");
        }

        $backup_dir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_BACKUP);
        if (in_array($_backup, ls($backup_dir, '*.tgz', false, array('files', 'quiet'))) && file_exists($backup_dir.'/'.$_backup))
            unlink($backup_dir . '/' . $_backup);
        else
            throw new Exception("Unable to delete this file");
        ajax::success();
    }
    if ($action == 'backupRestore') {
        /** @var string $_backup */
        $_backup = init('file');
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ': file=' . $_backup);
        if ($_backup == ''){
            throw new Exception("Please provide the file to restore");
        }

        $backup_dir = realpath(__DIR__ . '/../../' . jMQTTConst::PATH_BACKUP);
        if (!in_array($_backup, ls($backup_dir, '*.tgz', false, array('files', 'quiet'))))
            throw new Exception("Unable to restore the supplied file");

        $msg = sprintf("Restoring backup %s...", $_backup);
        @message::removeAll('jMQTT', 'backupRestoreEnded');
        @message::add('jMQTT', $msg, '', 'backupRestoreStarted');
        jMQTT::logger('warning', $msg);
        // Use a temporary log file for restoration
        file_put_contents(log::getPathToLog('tmp_jMQTT'), date('[Y-m-d H:i:s][\I\N\F\O] : ') . $msg . "\n");
        // exec("echo '" . date('[Y-m-d H:i:s][\I\N\F\O] : ') . $msg . "' >> " . );

        // Flags
        $flags = '';
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
            $msg = sprintf("Backup %s restored successfully", $_backup);
            file_put_contents(log::getPathToLog('jMQTT'), date('[Y-m-d H:i:s][\I\N\F\O] : ') . $msg . "\n", FILE_APPEND);
            // exec("echo '" . date('[Y-m-d H:i:s][\I\N\F\O] : ') . $msg . "' >> " . log::getPathToLog('jMQTT'));
            ajax::success();
        } else {
            $msg = sprintf("Backup %s restoration failed, see jMQTT log", $_backup);
            file_put_contents(log::getPathToLog('jMQTT'), date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . $msg . "\n", FILE_APPEND);
            // exec("echo '" . date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . $msg . "' >> " . log::getPathToLog('jMQTT'));
            @message::add('jMQTT', $msg, '', 'backupRestoreEnded');
            throw new Exception($msg);
        }
    }

// -------------------- Simulate dangerous actions --------------------
    // Installation and files
    if ($action == 'depCheck') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        plugin::byId('jMQTT')->dependancy_info(true);
        ajax::success();
    }
    if ($action == 'reInstall') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        log::clear('update');
        $update = update::byId('jMQTT');
        if (!is_object($update))
            throw new Exception('jMQTT is not installed?!');
        try {
            log::add('update', 'alert', "[START UPDATE]");
            $update->doUpdate();
            log::add('update', 'alert', "Launch cron dependancy plugins");
            try {
                /** @var null|cron $cron */
                $cron = cron::byClassAndFunction('plugin', 'checkDeamon');
                if (is_object($cron)) {
                    $cron->start();
                }
            } catch (Exception $e) {
                log::add('update', 'alert', "jMQTT update exception:\n" . $e->getMessage());
            }
            log::add('update', 'alert', "[END UPDATE SUCCESS]");
        } catch (Exception $e) {
            log::add('update', 'alert', $e->getMessage());
            log::add('update', 'alert', "[END UPDATE ERROR]");
        }
        ajax::success();
    }
    if ($action == 'depDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        jMQTTDaemon::stop();
        exec('sudo rm -rf ' . __DIR__ . '/../../resources/JsonPath-PHP/composer.lock');
        exec('sudo rm -rf ' . __DIR__ . '/../../resources/JsonPath-PHP/vendor');
        ajax::success();
    }
    if ($action == 'venvDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        jMQTTDaemon::stop();
        exec('sudo rm -rf ' . __DIR__ . '/../../resources/jmqttd/venv');
        ajax::success();
    }
    if ($action == 'dynContentDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        jMQTTDaemon::stop();
        exec('sudo rm -rf ' . __DIR__ . '/../../resources/jmqttd/__pycache__');
        exec('sudo rm -rf ' . jeedom::getTmpFolder('jMQTT') . '/rt*.json');
        ajax::success();
    }
    if ($action == 'listenersRemove') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        jMQTT::listenersRemoveAll();
        ajax::success();
    }
    if ($action == 'listenersCreate') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        jMQTT::listenersAddAll();
        ajax::success();
    }

    // Running contents
    // TODO: Debug modal: implement missing actions
    //  pidFileDelete, portFileDelete
    //  labels: enhancement, php
    if ($action == 'pidFileDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        throw new Exception('TODO, not implemented');
        // jMQTTDaemon::delPid();
        // ajax::success();
    }
    if ($action == 'portFileDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        throw new Exception('TODO, not implemented');
        // jMQTTDaemon::delPort();
        // ajax::success();
    }
    if ($action == 'killAllSIGTERM') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        system::kill('[/]jmqttd', false);
        ajax::success();
    }
    if ($action == 'killAllSIGKILL') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        system::kill('[/]jmqttd', true);
        ajax::success();
    }

    // Troubleshooting
    if ($action == 'hbStart') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        config::save('functionality::cron::enable', 1, jMQTT::class);
        ajax::success();
    }
    if ($action == 'hbStop') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        config::save('functionality::cron::enable', 0, jMQTT::class);
        ajax::success();
    }
    if ($action == 'printTree') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        throw new Exception('TODO, not implemented');
        // jMQTTComToDaemon::printTree();
        // ajax::success();
    }
    if ($action == 'threadDump') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        // Get cached PID and PORT
        $cuid = @cache::byKey('jMQTT::'.jMQTTConst::CACHE_DAEMON_UID)->getValue("0:0");
        list($cpid, $cport) = array_map('intval', explode(":", $cuid));
        // If PID is unavailable or not running
        if ($cpid == 0 || !@posix_getsid($cpid))
            throw new Exception("Daemon PID not found, is it running?");

        // Else send signal SIGUSR1
        posix_kill($cpid, 10);
        ajax::success();
    }
    if ($action == 'logVerbose') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        jMQTTComToDaemon::setLogLevel('verbose');
        ajax::success();
    }
    if ($action == 'statsSend') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action);
        cache::set('jMQTT::nextStats', time() - 300);
        jMQTTPlugin::stats();
        ajax::success();
    }

// -------------------- Reapply upgrade script --------------------
    if ($action == 'reapplyUpdate') {
        $name = init('name', 'NONE');
        jMQTT::logger('debug', 'debug.ajax.php: ' . $action . ' (' . $name . ')');
        $ver = str_replace('.php', '', $name);
        $file = __DIR__ . '/../../resources/update/' . $name;
        try {
            if (!file_exists($file))
                ajax::error("Not such a file: $name", -1);

            log::add('jMQTT', 'debug', "Applying migration file to version $ver...");
            include $file;
            log::add('jMQTT', 'debug', "Migration to version $ver successfully completed");
        } catch (Throwable $e) {
            log::add('jMQTT', 'error', sprintf(
                "Exception encountered during migration to version %s: %s\n@Stack: %3\$s",
                $ver,
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
        ajax::success();
    }

    throw new Exception('No corresponding Ajax method: ' . secureXSS($action));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
?>
