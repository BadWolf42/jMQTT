<?php

try {
    require_once __DIR__ . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception('401 - Unauthorized access');
    }

    require_once __DIR__ . '/../../core/class/jMQTT.class.php';
    ajax::init();

// -------------------- Config Daemon --------------------
    if (init('action') == 'configGetInternal') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        $res[] = array('header' => '', 'data' => config::searchKey('', "jMQTT"));
        ajax::success($res);
    }
    if (init('action') == 'configSetInternal') {
        jMQTT::logger(
            'debug',
            'debug.ajax.php: ' . init('action').': key='.init('key').' value='.init('val')
        );
        config::save(init('key'), json_decode(init('val')), 'jMQTT');
        ajax::success();
    }
    if (init('action') == 'configDelInternal') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action').': key='.init('key'));
        config::remove(init('key'), 'jMQTT');
        ajax::success();
    }

// -------------------- Config eqBroker / eqLogic --------------------
    if (init('action') == 'configGetBrokers') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        foreach (jMQTT::getBrokers() as $eqBroker) {
            $data = array();
            foreach (utils::o2a($eqBroker)['configuration'] as $k => $val)
                $data[] = array('key' => $k, 'value' => $val);
            $header = $eqBroker->getHumanName().' (id: '.$eqBroker->getId().')';
            $res[] = array('header' => $header, 'id' => $eqBroker->getId(), 'data' => $data);
        }
        ajax::success($res);
    }
    if (init('action') == 'configGetEquipments') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        foreach (jMQTT::getNonBrokers() as $brk)
            foreach ($brk as $eqLogic) {
                $data = array();
                foreach (utils::o2a($eqLogic)['configuration'] as $k => $val)
                    $data[] = array('key' => $k, 'value' => $val);
                $header = $eqLogic->getHumanName().' (id: '.$eqLogic->getId().')';
                $res[] = array('header' => $header, 'id' => $eqLogic->getId(), 'data' => $data);
            }
        ajax::success($res);
    }
    if (init('action') == 'configSetBrkAndEqpt') {
        jMQTT::logger(
            'debug',
            'debug.ajax.php: ' . init('action').': id='.init('id')
            .' key='.init('key').' value='.init('val')
        );
        jMQTT::byId(init('id'))->setConfiguration(init('key'), json_decode(init('val')))->save();
        ajax::success();
    }
    if (init('action') == 'configDelBrkAndEqpt') {
        jMQTT::logger(
            'debug',
            'debug.ajax.php: ' . init('action').': id='.init('id').'key='.init('key')
        );
        jMQTT::byId(init('id'))->setConfiguration(init('key'), null)->save();
        ajax::success();
    }

// -------------------- Config cmd --------------------
    if (init('action') == 'configGetCommandsInfo') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
            if ($cmd->getType() != 'info')
                continue;
            $data = array();
            foreach (utils::o2a($cmd)['configuration'] as $k => $val)
                $data[] = array('key' => $k, 'value' => $val);
            $header = $cmd->getHumanName().' (id: '.$cmd->getId().')';
            $res[] = array('header' => $header, 'id' => $cmd->getId(), 'data' => $data);
        }
        ajax::success($res);
    }
    if (init('action') == 'configGetCommandsAction') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
            if ($cmd->getType() != 'action')
                continue;
            $data = array();
            foreach (utils::o2a($cmd)['configuration'] as $k => $val)
                $data[] = array('key' => $k, 'value' => $val);
            $header = $cmd->getHumanName().' (id: '.$cmd->getId().')';
            $res[] = array('header' => $header, 'id' => $cmd->getId(), 'data' => $data);
        }
        ajax::success($res);
    }
    if (init('action') == 'configSetCommands') {
        jMQTT::logger(
            'debug',
            'debug.ajax.php: ' . init('action')
            .': id='.init('id').' key='.init('key').' value='.init('val')
        );
        jMQTTCmd::byId(init('id'))->setConfiguration(
            init('key'),
            json_decode(init('val'))
        )->save();
        ajax::success();
    }
    if (init('action') == 'configDelCommands') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action').': id='.init('id').'key='.init('key'));
        jMQTTCmd::byId(init('id'))->setConfiguration(init('key'), null)->save();
        ajax::success();
    }

// -------------------- Cache Daemon --------------------
    if (init('action') == 'cacheGetInternal') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $cacheKeys = array();
        $cacheKeys[] = 'deamonStartjMQTTinprogress';
        $cacheKeys[] = 'dependancyjMQTT';
        $cacheKeys[] = 'jMQTT::' . jMQTTConst::CACHE_DAEMON_LAST_RCV;
        $cacheKeys[] = 'jMQTT::' . jMQTTConst::CACHE_DAEMON_LAST_SND;
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
    if (init('action') == 'cacheGetBrokers') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        foreach (jMQTT::getBrokers() as $brk) {
            $cacheBrkKeys = array();
            $cacheBrkKeys[] = 'eqLogicCacheAttr'.$brk->getId();
            $cacheBrkKeys[] = 'eqLogicStatusAttr'.$brk->getId();
            $data = array();
            foreach ($cacheBrkKeys as $k) {
                $val = cache::byKey($k)->getValue(null);
                if (!is_null($val))
                    $data[] = array('key' => $k, 'value' => $val);
            }
            if ($data !== array()) {
                $header = $brk->getHumanName().' (id: '.$brk->getId().')';
                $res[] = array('header' => $header, 'data' => $data);
            }
        }
        ajax::success($res);
    }
// -------------------- Cache eqLogic --------------------
    if (init('action') == 'cacheGetEquipments') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        foreach (jMQTT::getNonBrokers() as $brk) {
            foreach ($brk as $eqpt) {
                $cacheEqptKeys = array();
                $cacheEqptKeys[] = 'jMQTT::' . $eqpt->getId() . '::' . jMQTTConst::CACHE_IGNORE_TOPIC_MISMATCH;
                // $cacheEqptKeys[] = 'jMQTT::' . $eqpt->getId() . '::' . jMQTTConst::CACHE_MQTTCLIENT_CONNECTED;
                $cacheEqptKeys[] = 'eqLogicCacheAttr'.$eqpt->getId();
                $cacheEqptKeys[] = 'eqLogicStatusAttr'.$eqpt->getId();
                $data = array();
                foreach ($cacheEqptKeys as $k) {
                    $val = cache::byKey($k)->getValue(null);
                    if (!is_null($val))
                        $data[] = array('key' => $k, 'value' => $val);
                }
                if ($data !== array()) {
                    $header = $eqpt->getHumanName().' (id: '.$eqpt->getId().')';
                    $res[] = array('header' => $header, 'data' => $data);
                }
            }
        }
        ajax::success($res);
    }
// -------------------- Cache cmd --------------------
    if (init('action') == 'cacheGetCommandsInfo') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
            if ($cmd->getType() != 'info')
                continue;
            $cacheCmdKeys = array();
            $cacheCmdKeys[] = 'cmdCacheAttr'.$cmd->getId();
            $cacheCmdKeys[] = 'cmd'.$cmd->getId();
            $data = array();
            foreach ($cacheCmdKeys as $k) {
                $val = cache::byKey($k)->getValue(null);
                if (!is_null($val))
                    $data[] = array('key' => $k, 'value' => $val);
            }
            if ($data !== array()) {
                $header = $cmd->getHumanName().' (id:'.$cmd->getId().')';
                $res[] = array('header' => $header, 'data' => $data);
            }
        }
        ajax::success($res);
    }
    if (init('action') == 'cacheGetCommandsAction') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        $res = array();
        foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
            if ($cmd->getType() != 'action')
                continue;
            $cacheCmdKeys = array();
            $cacheCmdKeys[] = 'cmdCacheAttr'.$cmd->getId();
            $cacheCmdKeys[] = 'cmd'.$cmd->getId();
            $data = array();
            foreach ($cacheCmdKeys as $k) {
                $val = cache::byKey($k)->getValue(null);
                if (!is_null($val))
                    $data[] = array('key' => $k, 'value' => $val);
            }
            if ($data !== array()) {
                $header = $cmd->getHumanName().' (id:'.$cmd->getId().')';
                $res[] = array('header' => $header, 'data' => $data);
            }
        }
        ajax::success($res);
    }

// -------------------- Cache set / delete --------------------
    if (init('action') == 'cacheSet') {
        jMQTT::logger(
            'debug',
            'debug.ajax.php: ' . init('action').': key='.init('key').' value='.init('val')
        );
        cache::set(init('key'), json_decode(init('val'), true));
        ajax::success();
    }
    if (init('action') == 'cacheDel') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action').': key='.init('key'));
        cache::delete(init('key'));
        ajax::success();
    }

// -------------------- Send raw data to Daemon --------------------
    if (init('action') == 'sendToDaemon') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action').': data='.init('data'));
        $data = init('data');
        $decoded = json_decode($data, true);
        if (is_null($decoded) || !is_array($decoded)) {
            ajax::error('Invalid format');
        }
        // Send to Daemon
        if (!jMQTTDaemon::state()) {
            ajax::error('Daemon is not started');
        }
        $port = jMQTTDaemon::getPort();
        // TODO: Add Method+URL+key as parameters and return the error code+result?
        $curl = curl_init('http://127.0.0.1:' . $port . '/URL');
        curl_setopt($curl, CURLOPT_POST, true);
        // curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey()
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_exec($curl);
        curl_close($curl);
        ajax::success();
    }
    if (init('action') == 'sendToJeedom') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action').': data='.init('data'));
        $data = json_decode(init('data'), true);
        if (is_null($data) || !is_array($data)) {
            ajax::error('Invalid format');
        }
        // Prepare url
        $callbackURL = jMQTTDaemon::get_callback_url();
        // TODO: Remove forceDocker, urlOverrideEnable & urlOverrideValue
        //  This should be automatically detected and handled accordingly

        // To fix issue: https://community.jeedom.com/t/87727/39
        if (
            (
                file_exists('/.dockerenv')
                || config::byKey('forceDocker', 'jMQTT', '0')
            )
            && config::byKey('urlOverrideEnable', 'jMQTT', '0') == '1'
        ) {
            $callbackURL = config::byKey('urlOverrideValue', 'jMQTT', $callbackURL);
        }

        // Send to Jeedom
        $curl = curl_init($callbackURL);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . jMQTTDaemon::getApiKey(),
            'PID: ' . jMQTTDaemon::getPid()
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, init('data'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        curl_close($curl);

        ajax::success($response);
    }

// -------------------- Simulate dangerous actions --------------------
    // Installation and files
    if (init('action') == 'depCheck') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        plugin::byId('jMQTT')->dependancy_info(true);
        ajax::success();
    }
    if (init('action') == 'reInstall') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        log::clear('update');
        $update = update::byId('jMQTT');
        if (!is_object($update))
            throw new Exception('jMQTT is not installed?!');
        try {
            log::add('update', 'alert', "[START UPDATE]");
            $update->doUpdate();
            log::add('update', 'alert', "Launch cron dependancy plugins");
            try {
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
    if (init('action') == 'depDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        jMQTTDaemon::stop();
        exec(system::getCmdSudo() . 'rm -rf '.__DIR__.'/../../resources/JsonPath-PHP/composer.lock');
        exec(system::getCmdSudo() . 'rm -rf '.__DIR__.'/../../resources/JsonPath-PHP/vendor');
        ajax::success();
    }
    if (init('action') == 'venvDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        jMQTTDaemon::stop();
        exec(system::getCmdSudo() . 'rm -rf '.__DIR__.'/../../resources/jmqttd_api/venv');
        ajax::success();
    }
    if (init('action') == 'dynContentDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        jMQTTDaemon::stop();
        exec(system::getCmdSudo() . 'rm -rf '.__DIR__.'/../../resources/jmqttd_api/__pycache__');
        exec(system::getCmdSudo() . 'rm -rf '.jeedom::getTmpFolder('jMQTT').'/rt*.json');
        ajax::success();
    }
    if (init('action') == 'listenersRemove') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        jMQTT::listenersRemoveAll();
        ajax::success();
    }
    if (init('action') == 'listenersCreate') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        jMQTT::listenersAddAll();
        ajax::success();
    }

    // Running contents
    if (init('action') == 'pidFileDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        jMQTTDaemon::delPid();
        ajax::success();
    }
    if (init('action') == 'portFileDelete') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        jMQTTDaemon::delPort();
        ajax::success();
    }
    if (init('action') == 'killAllSIGTERM') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        system::kill('[/]jmqttd', false);
        ajax::success();
    }
    if (init('action') == 'killAllSIGKILL') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        system::kill('[/]jmqttd', true);
        ajax::success();
    }

    // Troubleshooting
    if (init('action') == 'hbStop') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        config::save('functionality::cron::enable', 1, jMQTT::class);
        ajax::success();
    }
    if (init('action') == 'threadDump') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        // Get cached PID and PORT
        $pid = jMQTTDaemon::getPid();
        // If PID is unavailable or not running
        if ($pid == 0)
            throw new Exception('Daemon PID not found. Is it running?');
        // Else send signal SIGUSR1
        posix_kill($pid, 10);
        ajax::success();
    }
    if (init('action') == 'logVerbose') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        jMQTTComToDaemon::setLogLevel('trace');
        ajax::success();
    }
    if (init('action') == 'statsSend') {
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action'));
        cache::set('jMQTT::nextStats', time() - 300);
        jMQTTPlugin::stats();
        ajax::success();
    }

// -------------------- Reapply upgrade script --------------------
    if (init('action') == 'reapplyUpdate') {
        $name = init('name', 'NONE');
        jMQTT::logger('debug', 'debug.ajax.php: ' . init('action') . ' (' . $name . ')');
        $ver = str_replace('.php', '', $name);
        $file = __DIR__ . '/../../resources/update/' . $name;
        try {
            if (!file_exists($file))
                ajax::error("Not such a file: $name", -1);

            log::add('jMQTT', 'debug', "Applying migration file to version $ver...");
            include $file;
            log::add('jMQTT', 'debug', "Migration to version $ver successfully completed");
        } catch (Throwable $e) {
            log::add('jMQTT', 'error', str_replace("\n", ' <br/> ', sprintf(
                "Exception encountered during migration to version %s: %s,<br/>@Stack: %3\$s.",
                $ver,
                $e->getMessage(),
                $e->getTraceAsString()
            )));
        }
        ajax::success();
    }

    throw new Exception('No corresponding Ajax method: ' . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
?>
