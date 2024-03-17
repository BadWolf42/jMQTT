<?php

require_once __DIR__ . '/../../../core/php/core.inc.php';
include_file('core', 'jMQTT', 'class', 'jMQTT');


function raiseForceDepInstallFlag() {
    config::save('forceDepInstall', 1, 'jMQTT');
}


function jMQTT_install() {
    jMQTT::logger('debug', 'install.php: jMQTT_install()');
    jMQTT_update(false);
}

function jMQTT_update($_direct=true) {
    if ($_direct)
        jMQTT::logger('debug', 'install.php: jMQTT_update()');

    // if version info is not in DB, it means it is a fresh install of jMQTT
    // and so we don't need to run these functions to adapt eqLogic structure/config
    // (even if plugin is disabled the config key stays)
    try {
        $content = file_get_contents(__DIR__ . '/info.json');
        $info = json_decode($content, true);
        $pluginVer = $info['pluginVersion'];
    } catch (Throwable $e) {
        log::add(
            'jMQTT',
            'warning',
            __("Impossible de récupérer le numéro de version dans le fichier info.json, ceci ce devrait pas arriver !", __FILE__)
        );
        $pluginVer = '0.0.0';
    }

    // Backup old version number
    $currentVer = config::byKey('version', 'jMQTT', $pluginVer);
    // @phpstan-ignore-next-line
    $currentVer = is_int($currentVer) ? strval($currentVer) . '.0.0' : $currentVer;
    config::save('previousVersion', $currentVer, 'jMQTT');

    // List all migration files
    $files = ls(__DIR__ . '/../resources/update/', '*.php', false, array('files'));
    $migrations = array();
    foreach ($files as $name) {
        // Use only matching files
        if (!preg_match_all("/^(\d+)(\.(\d+)(\.(\d+))?)?.php$/", $name, $m))
            continue;
        $fileVer = intval($m[1][0]).'.'.intval($m[3][0]).'.'.intval($m[5][0]);
        // Filter out migration files <= $currentVer
        if (version_compare($fileVer, $currentVer, '<='))
            continue;
        // Filter out migration files > $pluginVer
        if (version_compare($fileVer, $pluginVer, '>'))
            continue;
        $migrations[$fileVer] = $name;
    }

    // Sort files by key (version number)
    uksort($migrations, 'version_compare');

    // Apply migration files in the right order
    foreach ($migrations as $ver => $name) {
        try {
            $file = __DIR__ . '/../resources/update/' . $name;
            if (file_exists($file)) {
                log::add('jMQTT', 'debug', "Applying migration file to version " . $ver . "...");
                include $file;
                log::add('jMQTT', 'debug', "Migration to version " . $ver . " successful");
            }
        } catch (Throwable $e) {
            log::add('jMQTT', 'error', str_replace("\n", ' <br/> ', sprintf(
                __("Exception rencontrée lors de la migration vers la version %1\$d : %2\$s", __FILE__).
                ",<br/>@Stack: %3\$s.",
                $ver,
                $e->getMessage(),
                $e->getTraceAsString()
            )));
        }
    }

    config::save('version', $pluginVer, 'jMQTT');

    jMQTTPlugin::stats($_direct ? 'update' : 'install');
}

function jMQTT_remove() {
    jMQTT::logger('debug', 'install.php: jMQTT_remove()');
    jMQTTPlugin::stats('uninstall');
    jMQTTDaemon::delPort();
}

?>
