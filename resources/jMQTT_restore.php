<?php
///////////////////////////////////////////////////////////////////////////////////////////////////
// jMQTT_restore.php
/*
Backup tar.gz structure:
 - backup/metadata.json     <- backup descriptor json file
 - backup/index.json        <- id to name of eqLogic and cmd index json file
 - backup/data.json         <- eqLogic and cmd backup json file
 - backup/history.json      <- cmd history backup json file
 - backup/jMQTT/            <- jMQTT files full backup folder
 - backup/logs/             <- jMQTT logs full backup folder
 - backup/mosquitto/        <- mosquitto config full folder backup
*/

require_once __DIR__ . '/../../../core/php/core.inc.php';
require_once __DIR__ . '/jMQTT_backup.php';

// TODO: Documentation of jMQTT_backup.php and jMQTT_restore.php
//  labels: documentation, quality


class DiffType {
    const Created = 'created';
    const Existing = 'existing';
    const Deleted = 'deleted';
    const Invalid = 'invalid';
    const CollisionOnName = 'CollisionOnName';
    const CollisionOnId = 'CollisionOnId';
}


// Pass by value current metadata, ids and a new temporary folder to extact the backup
function restore_prepare(&$initial_metadata, &$initial_indexes, &$tmp_dir) {
    $initial_metadata = export_metadata();
    $initial_indexes  = export_index();
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Creating temporary directory...");
    $tmp_dir = trim(shell_exec('mktemp -dt jMQTT.XXXXXX'));
    print("                      [ OK ]\n");
}

// Extract the backup file (with root privileges) into temporary folder
function restore_extactBackup($file, $tmp_dir) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Extracting archive ".basename($file)."...");
    shell_exec('tar -zxpf ' . $file . ' --directory ' . $tmp_dir);
    print("      [ OK ]\n");
}

// Return Metadata from the extacted backup temporary folder
function restore_getBackupM($tmp_dir) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT Metadata...");
    if (file_exists($tmp_dir . '/backup/metadata.json')) {
        $metadata = json_decode(file_get_contents($tmp_dir . '/backup/metadata.json'), true);
        print("                     [ OK ]\n");
    } else {
        print("                  [ ERROR ]\n");
        $metadata = null;
    }
    return $metadata;
}

// Compare hardware keys
function restore_checkHwKey($current, $old, $no_hw_check) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Verifing hardware keys...");
    if ($current['hardwareKey'] == $old['hardwareKey']) {
        print("                            [ OK ]\n");
        return true;
    } elseif ($no_hw_check) {
        print("                       [ IGNORED ]\n");
        print(date('[Y-m-d H:i:s][\W\A\R\N\I\N\G] : ') . "THIS IS UNSAFE AND NOT RECOMMANDED: hardwareKey are different between the system and the backup, but you decided to ignore it!\n");
        return true;
    } else {
        print("                        [ FAILED ]\n");
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Cannot restore this backup on a system with a different hardwareKey!\n");
        return false;
    }
}

// Return Indexes from the extacted backup temporary folder
function restore_getBackupI($tmp_dir) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT Index...");
    $index = json_decode(file_get_contents($tmp_dir . '/backup/index.json'), true);
    print("                        [ OK ]\n");
    return $index;
}


function restore_diffIndexes(&$options, &$backup_indexes, &$current_indexes, &$diff_indexes) {
    //   -> Confirm all Id from backup ARE NOT used in Jeedom outside of jMQTT
    //      If --by-name, then try to reconciliate, otherwise FAIL after having listed all issues
    //   -> Match all eqLogic by Id first, continue to match with humainName if --by-name,
    //   -> Match all cmd by Id first, continue to match with humainName if --by-name,
    //   -> Display matching result if --verbose
    //
    // if ($options['by-name']) // TODO

    $sucess = true;
    $current_indexes = array('eqLogic' => array(), 'cmd' => array());
    $diff_indexes = array('eqLogic' => array(), 'cmd' => array());

    // @phpstan-ignore-next-line
    function abstract_diffIndexes($type, &$options, &$backup_indexes, &$current_indexes, &$diff_indexes, &$sucess) {
        // For all existing jMQTT eqLogic/cmd
        $all = ($type == 'eqLogic') ? eqLogic::byType('jMQTT') : cmd::searchConfiguration('', 'jMQTT');
        foreach ($all as $o) {
            $current_indexes[$type][$o->getId()] = $o->getHumanName();
            if (isset($backup_indexes[$type][$o->getId()])) {
                $diff_indexes[$type][$o->getId()] = DiffType::Existing;
                if ($current_indexes[$type][$o->getId()] == $backup_indexes[$type][$o->getId()]) {
                    if ($options['verbose'])
                        print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . $type . ':' . $o->getId() . " (" . $current_indexes[$type][$o->getId()] . ") still exists with the same name\n");
                } else {
                    if ($options['verbose'])
                        print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . $type . ':' . $o->getId() . " (" . $backup_indexes[$type][$o->getId()] . ' -> ' . $current_indexes[$type][$o->getId()] . ") still exists with a different name\n");
                }
            } else {
                $diff_indexes[$type][$o->getId()] = DiffType::Created;
                if ($options['verbose'])
                    print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . $type . ':' . $o->getId() . " (" . $current_indexes[$type][$o->getId()] . ") is new\n");
            }
        }
        // For all old jMQTT eqLogic/cmd id
        foreach ($backup_indexes[$type] as $id=>$name) {
            if ($type == 'eqLogic')
                $res = preg_match("/\[(.+)\]\[(.+)\]/", $name, $match);
            else
                $res = preg_match("/\[(.+)\]\[(.+)\]\[(.+)\]/", $name, $match);
            if (!$res) {
                print(date('[Y-m-d H:i:s][\W\A\R\N\I\N\G] : ') . $type . ':' . $id . " has an invalid humainName (" . $name . "), it won't be restored\n");
                $diff_indexes[$type][$id] = DiffType::Invalid;
                continue;
            }
            if ($type == 'eqLogic')
                $o = eqLogic::byObjectNameEqLogicName($match[1], $match[2]);
            else
                $o = cmd::byObjectNameEqLogicNameCmdName($match[1], $match[2], $match[3]);
            if (is_object($o) && $o->getEqType_name() != 'jMQTT') {
                $diff_indexes[$type][$id] = DiffType::CollisionOnName;
                print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . $type . ':' . $o->getHumanName() . ' (' . $id . ") already exists for plugin " . $o->getEqType_name() . ", aborting!\n");
                $sucess = false;
            }

            // Found previously
            if (isset($diff_indexes[$type][$id]))
                continue;

            // Check if another eqLogic/cmd with the same id exists outside of jMQTT
            $o = $type::byId($id);
            if (is_object($o)) {
                $diff_indexes[$type][$id] = DiffType::CollisionOnId;
                print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . $type . ':' . $id . ' (' . $o->getHumanName() . ") already exists for plugin " . $o->getEqType_name() . ", aborting!\n");
                $sucess = false;
            } else {
                $diff_indexes[$type][$id] = DiffType::Deleted;
                if ($options['verbose'])
                    print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . $type . ':' . $id . ' (' . $name . ") no longer exists\n");
            }
        }
    }
    abstract_diffIndexes('eqLogic', $options, $backup_indexes, $current_indexes, $diff_indexes, $sucess); // @phpstan-ignore-line
    abstract_diffIndexes('cmd',     $options, $backup_indexes, $current_indexes, $diff_indexes, $sucess); // @phpstan-ignore-line

    return $sucess;
}

// Return Data from the extacted backup temporary folder
function restore_getBackupD($tmp_dir) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT Data...");
    $data = json_decode(file_get_contents($tmp_dir . '/backup/data.json'), true);
    print("                         [ OK ]\n");
    return $data;
}

// Return History from the extacted backup temporary folder
function restore_getBackupH($tmp_dir) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT History...");
    $history = json_decode(file_get_contents($tmp_dir . '/backup/history.json'), true);
    print("                      [ OK ]\n");
    return $history;
}


// Create a new jMQTT eqLogic with a specific id
function createEqWithId($_id) {
    try {
        $sql = 'INSERT INTO `eqLogic` (`id`,`name`,`eqType_name`,`isVisible`,`isEnable`) VALUES (' . $_id . ',"","jMQTT",0,0)';
        $res = DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
        return true;
    } catch (Exception $exc) {
        return false;
    }
}

// Create a new jMQTT cmd with a specific id
function createCmdWithId($_id) {
    try {
        $sql = 'INSERT INTO `cmd` (`id`,`eqLogic_id`,`eqType`,`isHistorized`,`isVisible`) VALUES (' . $_id . ',0,"jMQTT",0,0)';
        $res = DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
        return true;
    } catch (Exception $exc) {
        return false;
    }
}


// Restore jMQTT plugin files, keeping existing jMQTT backups and .git folder
function restore_folder($tmp_dir) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Saving existing jMQTT backups...");
    exec('cp -a ' . __DIR__ . '/../' . jMQTTConst::PATH_BACKUP . '* ' . $tmp_dir . '/backup/jMQTT/' . jMQTTConst::PATH_BACKUP . ' 2>&1 > /dev/null');
    if (file_exists(__DIR__ . '/../.git'))
        exec('cp -a ' . __DIR__ . '/../.git ' . $tmp_dir . '/backup/jMQTT 2>&1 > /dev/null');
    print("                     [ OK ]\n");

    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring jMQTT plugin files...");
    $plugins_dir = realpath(__DIR__ . '/../..');
    $cwd = getcwd(); // We may be working in a folder that is going to be removed
    chdir($tmp_dir); // Go to an existing folder

    // exec('mv '.$plugins_dir.'/jMQTT '.$tmp_dir.'/jMQTT_old');
    exec('rm -rf ' . $plugins_dir . '/jMQTT 2>&1 > /dev/null');
    exec('mv ' . $tmp_dir . '/backup/jMQTT ' . $plugins_dir . ' 2>&1 > /dev/null');

    chdir($cwd); // Switch back to previous cwd folder
    print("                      [ OK ]\n");
}

// Remove some eqLogic and cmd
function restore_purgeEqAndCmd(&$diff_indexes, $type, $verbose = false) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Removing some eqLogics and cmds...");
    $logs = array();
    // Remove newly created eqLogics
    foreach ($diff_indexes['eqLogic'] as $id=>$state) {
        if ($state != $type)
            continue;
        $o = eqLogic::byId($id);
        if (is_object($o))
            $o->remove();
        if ($verbose)
            $logs[] = date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> eqLogic:' . $id . ' (type: ' . $state . ") removed\n";
    }
    // Remove newly created cmds
    foreach ($diff_indexes['cmd'] as $id=>$state) {
        if ($state != $type)
            continue;
        $o = cmd::byId($id);
        if (is_object($o))
            $o->remove();
        if ($verbose)
            $logs[] = date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> cmd:' . $id . ' (type: ' . $state . ") removed\n";
    }
    print("                   [ OK ]\n");
    foreach($logs as $l)
        print($l);
}

// Created missing eqLogic and cmd
function restore_createMissingEqAndCmd(&$diff_indexes, $verbose = false) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Creating missing eqLogics and cmds...");
    $logs = array();
    // Create missing eqLogics
    foreach ($diff_indexes['eqLogic'] as $id=>&$state) {
        if ($state != DiffType::Deleted)
            continue;
        if(!createEqWithId($id)) {
            $state = DiffType::Invalid;
            $logs[] = date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . '    -> eqLogic:' . $id . " could NOT be created!\n";
        } elseif ($verbose) {
            $logs[] = date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> eqLogic:' . $id . " created\n";
        }
    }
    // Create missing cmds
    foreach ($diff_indexes['cmd'] as $id=>&$state) {
        if ($state != DiffType::Deleted)
            continue;
        if(!createCmdWithId($id)) {
            $state = DiffType::Invalid;
            $logs[] = date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . '    -> cmd:' . $id . " could NOT be created!\n";
        } elseif ($verbose) {
            $logs[] = date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> cmd:' . $id . " created\n";
        }
    }
    print("                [ OK ]\n");
    foreach($logs as $l)
        print($l);
}

// Replace eqLogics and cmds of type $type with their backups
function restore_replaceEqAndCmd(&$diff_indexes, &$data, $type, $verbose = false, $cache = true) {
    $logs = array();

    // Retore eqLogics
    $errorE = false;
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring the previously " . $type . " eqLogics...     ");
    if ($type == DiffType::Deleted) print(' ');
    foreach ($data['eqLogic'] as $eq) {
        $id = $eq['id'];
        if(!isset($diff_indexes['eqLogic'][$id])) {
            $logs[] = "\n" . date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . '    -> eqLogic:' . $id . " could NOT be found in diff!\n";
            $errorE = true;
            continue;
        }
        if ($diff_indexes['eqLogic'][$id] != $type)
            continue;
        $o = jMQTT::byId($id);
        utils::a2o($o, $eq);
        $o->save(true);
        if ($verbose)
            $logs[] = date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> eqLogic:' . $id . ' (type: ' . $type . ") restored\n";
    }
    print($errorE ? "[ ERROR ]\n" : "   [ OK ]\n");

    // Retore eqLogics
    $errorC = false;
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring the previously " . $type . " cmds...         ");
    if ($type == DiffType::Deleted) print(' ');
    foreach ($data['cmd'] as $eq) {
        $id = $eq['id'];
        if(!isset($diff_indexes['cmd'][$id])) {
            $logs[] = date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . '    -> cmd:' . $id . " could NOT be found in diff!\n";
            $errorC = true;
            continue;
        }
        if ($diff_indexes['cmd'][$id] != $type)
            continue;
        $o = jMQTTCmd::byId($id);
        utils::a2o($o, $eq);
        $o->save(true);
        if ($verbose)
            $logs[] = date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> cmd:' . $id . ' (type: ' . $type . ") restored\n";
    }
    print($errorC ? "[ ERROR ]\n" : "   [ OK ]\n");

    foreach($logs as $l)
        print($l);

    return !$errorE && !$errorC;
}

// Purge existing cmds histories
function restore_purgeHistories(&$diff_indexes, $type, $_date = '', $verbose = false) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Purging the previously " . $type . " cmds history...");
    $logs = array();
    foreach ($diff_indexes['cmd'] as $id=>&$state) {
        if ($state != $type)
            continue;
        history::emptyHistory($id, $_date);
        if ($verbose)
            $logs[] = date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> cmd:' . $id . ' history' . (($_date == '') ? '' : (' <= ' . $_date)) . " purged\n";
    }
    print("      [ OK ]\n");
    foreach($logs as $l)
        print($l);
}

// Replace existing cmds histories with their backups
function restore_restoreHistories(&$diff_indexes, &$history, $type, $verbose) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring the previously " . $type . " cmds history...");
    foreach ($diff_indexes['cmd'] as $id=>&$state) {
        if ($state != $type)
            continue;
        if (isset($history[$id])) {
            if ($verbose)
                print("\n" . date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> cmd:' . $id . " history ");
            $cpt = 0;
            foreach ($history[$id] as $h) {
                $h['cmd_id'] = $id;
// (new history())->setCmd_id($cmd->getId())->setValue($_value)->setDatetime($_datetime)->save($cmd, true);
// See if MySQL direct call could be more efficient? needed?
                $sql = 'REPLACE INTO history SET cmd_id=:cmd_id, `datetime`=:datetime, value=:value';
                DB::Prepare($sql, $h, DB::FETCH_TYPE_ROW);
                if ($cpt++ % 10 == 0) print('.'); // 1 dot every 10 history point
            }
            if ($verbose)
                print(" restored");

        } elseif ($verbose) {
            print("\n" . date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . '    -> cmd:' . $id . " no history");
        }
    }
    print("\n                                                                                   [ OK ]\n");
}

// Restore jMQTT log files
function restore_logs($tmp_dir) {
    $logs_dir = dirname(realpath(log::getPathToLog('jMQTT')));

    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Deleting jMQTT log files...");
    exec('rm -rf '.$logs_dir.'/jMQTT* 2>&1 > /dev/null');
    print("                                   [ OK ]\n");

    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring jMQTT log files...");
    exec('cp -a '.$tmp_dir.'/backup/logs/jMQTT* '.$logs_dir.' 2>&1 > /dev/null');
    print("                         [ OK ]\n");
}

// Restore Mosquitto service files
function restore_mosquitto($tmp_dir) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Stopping Mosquitto Service...");
    exec(system::getCmdSudo() . ' systemctl stop mosquitto');
    print("                        [ OK ]\n");

    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring Mosquitto config...");
    // do not put trailing '/' after mosquitto
    exec(system::getCmdSudo().'rm -rf /etc/mosquitto 2>&1 > /dev/null');
    exec(system::getCmdSudo().'cp -a '.$tmp_dir.'/backup/mosquitto /etc/mosquitto 2>&1 > /dev/null');
    exec(system::getCmdSudo().'chown -R root:root /etc/mosquitto 2>&1 > /dev/null');
    print("                        [ OK ]\n");

    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Starting Mosquitto Service...");
    exec(system::getCmdSudo() . ' systemctl start mosquitto');
    print("                        [ OK ]\n");
}

// Restore jMQTT configuration and cache
function restore_confAndCache(&$data, $cache = true) {
    // Restore plugin config
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring plugin config...");
    foreach ($data['conf'] as $k=>$value)
        config::save($k, $value, 'jMQTT');
    print("                           [ OK ]\n");

    if ($cache) {
        // Restore plugin cache
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring plugin cache...");
        foreach ($data['cache'] as $k=>$value)
            cache::set($k, $value);
        print("                            [ OK ]\n");
    }
}

// Remove the temporary folder where backup has been extracted
function restore_removeTmpDir($tmp_dir) {
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Removing backup directory...");
    if ($tmp_dir != '' && $tmp_dir != '/')
        shell_exec('rm -rf ' . $tmp_dir);
    print("                         [ OK ]\n");
}


// Old export functions
function full_export_old() {
    $returns = array();
    foreach (eqLogic::byType('jMQTT') as $eq) {
        $exp = $eq->toArray();
        $exp['commands'] = array();
        /** @var jMQTTCmd $cmd */
        foreach ($eq->getCmd() as $cmd)
            $exp['commands'][] = $cmd->full_export();
        $returns[] = $exp;
    }
    function fullsort($a, $b) { // @phpstan-ignore-line
        $x = ((isset($a['configuration']) && isset($a['configuration']['type'])) ?
                $a['configuration']['type'] : "z").$a['id'];
        $y = ((isset($b['configuration']) && isset($b['configuration']['type'])) ?
                $b['configuration']['type'] : "z").$b['id'];
        return strcmp($x, $y);
    }
    usort($returns, 'fullsort'); // @phpstan-ignore-line // Put the Broker first (needed)
    return $returns;
}

// TODO: Remove unused full_import function?
//  It imports as new all eq/cmd (unwanted in the restore version)
//  labels: quality, php

function full_import($data) {
    $eq_names = array();
    foreach (eqLogic::byType('jMQTT') as $eq) {
        $eq_names[] = $eq->getName();
    }
    $old_eqs = array();
    $old_cmds = array();
    $link_actions = array();
    foreach ($data as $data_eq) {
        // Handle eqLogic
        $link_infos = array();
        $link_names = array();
        $eq = new jMQTT();
        utils::a2o($eq, $data_eq);
        $eq->setId('');
        if ($eq->getType() == jMQTTConst::TYP_BRK) {
            $eq->setIsEnable('0');
        } else {
            $eq->setBrkId($old_eqs[$eq->getBrkId()]->getId());
        }
        $eq->setObject_id('');
        if (in_array($eq->getName(), $eq_names)) {
            $i = 2;
            while (in_array($data_eq['name'].'_'.$i, $eq_names)) {
                $i++;
            }
            $eq->setName($data_eq['name'].'_'.$i);
        }
        $old_eqs[$data_eq['id']] = $eq;
        $eq->save();

        // Handle cmd
        if (isset($data_eq['commands'])) {
            $cmd_order = 0;
            foreach ($data_eq['commands'] as $data_cmd) {
                try {
                    $cmd = new jMQTTCmd();
                    utils::a2o($cmd, $data_cmd);
                    $cmd->setId('');
                    $cmd->setOrder($cmd_order);
                    $cmd->setEqLogic_id($eq->getId());
                    //$cmd->setConfiguration('logicalId', $cmd->getLogicalId());
                    $cmd->setConfiguration('autoPub', 0);
                    $cmd->save();
                    $old_cmds[$data_cmd['id']] = $cmd;

                    $link_names[$cmd->getName()] = $cmd->getId();
                    if (isset($data_cmd['value']) && $data_cmd['value'] != '') {
                        $link_infos[] = $cmd;
                    }
                    if (isset($data_cmd['configuration']['request']) && $data_cmd['configuration']['request'] != '') {
                        $link_actions[] = $cmd;
                    }
                    $cmd_order++;
                } catch (Exception $exc) {
                    // TODO
                }
            }
        }

        foreach($link_infos as $cmd) {
            if (!isset($link_names[$cmd->getValue()]))
                continue;
            $id = $link_names[$cmd->getValue()];
             print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . 'Replacing in cmd='.$cmd->getId().' value='.$cmd->getValue().' by '.$id."\n");
            $cmd->setValue($id);
            $cmd->save();
        }
    }
    foreach($link_actions as $cmd) {
        $req = $cmd->getConfiguration('request', '');
        preg_match_all("/#([0-9]*)#/", $req, $matches);
        $req_cmds = array_unique($matches[1]);
        if (count($req_cmds) == 0)
            continue;
        foreach ($req_cmds as $req_cmd) {
            if (isset($old_cmds[$req_cmd])) {
                $req = str_replace('#'.$req_cmd.'#', '#'.(($old_cmds[$req_cmd])->getId()).'#', $req);
             }
        }
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . 'Replacing in cmd='.$cmd->getId().' request='.$cmd->getConfiguration('request', '').' by '.$req."\n");
        $cmd->setConfiguration('request', $req);
        $cmd->save();
    }
}


function restore_mainlogic(&$options, &$tmp_dir) {
    $error_code = 0;
    $old_autoMode = config::byKey('deamonAutoMode', 'jMQTT', 1);
    $old_daemonState = jMQTTDaemon::state();

    // Get info on current install
    $initial_metadata = null;
    $initial_indexes = null;
    restore_prepare($initial_metadata, $initial_indexes, $tmp_dir);

    // Extact the archive
    restore_extactBackup($options['file'], $tmp_dir);
    $backup_dir = $tmp_dir . '/backup';
    $metadata = restore_getBackupM($tmp_dir);
    if (is_null($metadata))
        return 20;

    // if ($options['verbose'])
        // print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . "Metadata of the backup: \n" . json_encode($metadata, JSON_PRETTY_PRINT) . "\n");

    // If not --not-eq-cmd, then Check if indexes are included in archive
    if (!$options['not-eq-cmd'] && !in_array('index', $metadata['packages']))
        return 21;

    // Check this Jeedom hardwareKey against the backup hardwareKey
    if (!restore_checkHwKey($initial_metadata, $metadata, $options['no-hw-check']))
        return 22;

    // Get indexes from backup
    $backup_indexes = restore_getBackupI($tmp_dir);
    $current_indexes = array();
    $diff_indexes = array();

    // If not --not-eq-cmd, then Check which eqLogic/cmd has been added/removed
    if (!$options['not-eq-cmd'] && !restore_diffIndexes($options, $backup_indexes, $current_indexes, $diff_indexes))
        return 23;

    // If not --not-folder or not --not-eq-cmd AND --apply, then Stop and disabled Daemon
    if ((!$options['not-folder'] || !$options['not-eq-cmd']) && $options['apply']) {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Stopping jMQTT daemon...");
        config::save('deamonAutoMode', 0, 'jMQTT');
        jMQTTDaemon::stop();
        print("                             [ OK ]\n");
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Stopping jMQTT daemon...                        [ SKIPPED ]\n");
    }

    // If --not-folder, then Do not restore jMQTT plugin folder
    if (!$options['not-folder'] && !in_array('folder', $metadata['packages'])) {
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Restoring jMQTT plugin folder... (not in backup) [ FAILED ]\n");
        $error_code = 24;
    } elseif (!$options['not-folder'] && $options['apply']) {
        restore_folder($tmp_dir);
        include __DIR__ . '/../core/class/jMQTT.class.php';
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Reloading jMQTT classes...                           [ OK ]\n");
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring jMQTT plugin folder...                [ SKIPPED ]\n");
    }

    // If not --not-eq-cmd and --do-delete, then Simply remove newly added eqLogic/cmd
    if (!$options['not-eq-cmd'] && $options['do-delete'] && !in_array('data', $metadata['packages'])) {
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Deleting new eq/cmd...       (no data in backup) [ FAILED ]\n");
        $error_code = 25;
    } elseif (!$options['not-eq-cmd'] && $options['do-delete'] && $options['apply']) {
        restore_purgeEqAndCmd($diff_indexes, DiffType::Created, $options['verbose']);
        restore_purgeEqAndCmd($diff_indexes, DiffType::Invalid, $options['verbose']);
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Deleting new eq/cmd...                          [ SKIPPED ]\n");
    }
    // Forget about the Created/Invalid eqLogic/cmd, they are OK now

    // If not --not-eq-cmd and --apply, then Add missing eqLogic/cmd and restore all eqLogic/cmd from backup
    if (!$options['not-eq-cmd'] && $options['apply']) {

        // Create missing eqLogics and cmds
        restore_createMissingEqAndCmd($diff_indexes, $options['verbose']);

        // Get data from backup
        $data = restore_getBackupD($tmp_dir);

        // Replace eqLogics and cmds
        restore_replaceEqAndCmd($diff_indexes, $data, DiffType::Existing, $options['verbose'], !$options['not-cache']);
        restore_replaceEqAndCmd($diff_indexes, $data, DiffType::Deleted, $options['verbose'], !$options['not-cache']);
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Creating missing eqLogics and cmds...           [ SKIPPED ]\n");
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring the previously " . DiffType::Existing . " eqLogics...   [ SKIPPED ]\n");
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring the previously " . DiffType::Deleted . " eqLogics...    [ SKIPPED ]\n");
    }

    // If not --not-eq-cmd, then Restore history from backup
    if (!$options['not-eq-cmd'] && !in_array('history', $metadata['packages'])) {
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Restoring jMQTT cmds history...  (not in backup) [ FAILED ]\n");
        $error_code = 26;
    } elseif (!$options['not-eq-cmd'] && $options['apply']) {
        // Get history from backup
        $history = restore_getBackupH($tmp_dir);

        // If --not-history, then Remove all history (including recent) and import it all from the backup
        $date = ($options['not-history'] ? '' : $metadata['date']);

        // Purge all cmds histories before $date
        restore_purgeHistories($diff_indexes, DiffType::Existing, $date, $options['verbose']);

        // Restore all cmds histories
        restore_restoreHistories($diff_indexes, $history, DiffType::Existing, $options['verbose']);
        restore_restoreHistories($diff_indexes, $history, DiffType::Deleted, $options['verbose']);
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Purging the previously " . DiffType::Existing . " cmds history... [ SKIPPED ]\n");
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring the previously " . DiffType::Existing . " cmds history..[ SKIPPED ]\n");
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring the previously " . DiffType::Deleted . " cmds history...[ SKIPPED ]\n");
    }

    // If --do-logs AND data in packages, then Remove all jMQTT* in log folder and move logs from backup to log folder
    if ($options['do-logs'] && !in_array('logs', $metadata['packages'])) {
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Restoring jMQTT logs...          (not in backup) [ FAILED ]\n");
        $error_code = 27;
    } elseif ($options['do-logs'] && $options['apply']) {
        restore_logs($tmp_dir);
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring jMQTT logs...                         [ SKIPPED ]\n");
    }

    // If --do-mosquitto AND data in packages, then Remove folder /etc/mosquitto and move mosquitto folder from backup
    if ($options['do-mosquitto'] && !in_array('mosquitto', $metadata['packages'])) {
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Restoring Mosquitto config...    (not in backup) [ FAILED ]\n");
        $error_code = 28;
    } elseif ($options['do-mosquitto'] && $options['apply']) {
        restore_mosquitto($tmp_dir);
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring Mosquitto config...                   [ SKIPPED ]\n");
    }

    // If not --not-folder or not --not-eq-cmd AND --apply, then Restore old previous deamonAutoMode (before restoring config from backup)
    if ((!$options['not-folder'] || !$options['not-eq-cmd']) && $options['apply']) {
        config::save('deamonAutoMode', $old_autoMode, 'jMQTT');
    }

    // If not --not-folder or not --not-eq-cmd AND --apply AND data in packages, then Restore plugin config and cache
    if ((!$options['not-folder'] || !$options['not-eq-cmd']) && !in_array('data', $metadata['packages'])) {
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Restoring plugin conf & cache... (not in backup) [ FAILED ]\n");
        $error_code = 29;
    } elseif ((!$options['not-folder'] || !$options['not-eq-cmd']) && $options['apply']) {
        restore_confAndCache($data, !$options['not-cache']);
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring plugin conf & cache...                [ SKIPPED ]\n");
    }

    // If not --not-folder or not --not-eq-cmd AND --apply AND Daemon was running before, then Start it
    if ((!$options['not-folder'] || !$options['not-eq-cmd']) && $options['apply']) {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Killall jMQTT daemon just in case...");
        exec("ps -ef | grep jmqttd.py | grep -v grep | awk '{print $2}' | xargs -r kill -9");
        print("                 [ OK ]\n");

        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Starting jMQTT daemon...");
        if ($old_daemonState) {
            jMQTTDaemon::start();
        }
        print("                             [ OK ]\n");
    } else {
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Starting jMQTT daemon...                        [ SKIPPED ]\n");
    }

    return $error_code;
}

function restore_help() {
    print("Usage: php " . basename(__FILE__) . " [OPTIONS]\n");
    print("Restore a backup of jMQTT inside Jeedom.\n");
    print("  --apply           apply the backup, this flag is REQUIRED to actually\n");
    print("                    change any data on this Jeedom/jMQTT system\n");
    print("  --file=<FILE>     backup file to restore\n");
    print("  --no-hw-check     DOES NOT CHECK if system hardwareKey match with backup\n");
    print("  --not-folder      do NOT restore previous jMQTT folder\n");
    print("  --not-eq-cmd      do NOT restore eqLogics or cmds\n");
    print("  --by-name         match eqLogic and cmd by name (NOT recommended)\n");
    print("  --do-delete       remove jMQTT eqLogic and cmd created since backup\n");
    print("  --not-cache       do NOT restore previous cached values (preserve cache)\n");
    print("  --not-history     remove recent history (keep only history from backup)\n");
    print("  --do-logs         restore previous logs (do NOT preserve newer logs)\n");
    print("  --do-mosquitto    restore Mosquitto config folders/files\n");
    print("  -v, --verbose     display more information about the restore process\n");
    print("  -h, --help        display this help message\n");
    print("Defaults:   a backup can only be restored on the same system ;\n");
    print("            jMQTT folder will be restored from backup ;\n");
    print("            eqLogic and cmd are matched by id (not by name) ;\n");
    print("            eqLogic or cmd newer than the backup will be keept ;\n");
    print("            plugin, eqLogic and cmd cache will be restored ;\n");
    print("            cmd history newer than the backup will be keept ;\n");
    print("            log files will NOT be restored ;\n");
    print("            Mosquitto configuration will NOT be restored (untouched) ;\n");
}

function restore_main() {
    global $argv;

    $rest = null;
    $getopt_res = getopt(
        "hPDCHLMv",
        array(
            'apply',
            'file:',
            'no-hw-check',
            'not-folder',
            'not-eq-cmd',
            'by-name',
            'do-delete',
            'not-cache',
            'not-history',
            'do-logs',
            'do-mosquitto',
            'verbose',
            'help'
        ),
        $rest
    );

    $options = array();
    $options['apply'] = isset($getopt_res['apply']);
    $options['file'] = (isset($getopt_res['file']) && $getopt_res['file'] != false) ? $getopt_res['file'] : null;
    $options['no-hw-check'] = isset($getopt_res['no-hw-check']);
    $options['not-folder'] = isset($getopt_res['not-folder']);
    $options['not-eq-cmd'] = isset($getopt_res['not-eq-cmd']);
    $options['by-name'] = isset($getopt_res['by-name']);
    $options['do-delete'] = isset($getopt_res['do-delete']);
    $options['not-cache'] = isset($getopt_res['not-cache']);
    $options['not-history'] = isset($getopt_res['not-history']);
    $options['do-logs'] = isset($getopt_res['do-logs']);
    $options['do-mosquitto'] = isset($getopt_res['do-mosquitto']);
    $options['verbose'] = isset($getopt_res['v']) || isset($getopt_res['verbose']);
    $options['help'] = isset($getopt_res['h']) || isset($getopt_res['help']);
    $options['other'] = array_slice($argv, $rest);

    if (count($argv) == 1 || $options['help']) {
        restore_help();
        exit(0);
    }

    if (is_null($options['file'])) {
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Please provide a file to restore using --file option.\n");
        restore_help();
        exit(1);
    }

    if (!is_readable($options['file'])) {
        print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Could not open ".$options['file']." file for reading.\n");
        restore_help();
        exit(1);
    }

    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') ."###########################################################\n");
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') ."Starting to restore jMQTT...\n");

    $error_code = 0;
    if (export_isRunning()) {
        print(date('[Y-m-d H:i:s][[\E\R\R\O\R] : ') ."Backup is already running, please wait until it ends, aborting!\n");
        $error_code = 1;
    } else {
        // If not --apply, then explain it is a dry run
        if (!$options['apply']) {
            print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "/!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\\n");
            print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "/!\\                                                     /!\\\n");
            print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "/!\\                    DRY RUN MODE                     /!\\\n");
            print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "/!\\     Use --apply to actually make some changes!      /!\\\n");
            print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "/!\\                                                     /!\\\n");
            print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "/!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\ /!\\\n");
        }

        export_writePidFile();

        $tmp_dir = null;
        $error_code = restore_mainlogic($options, $tmp_dir);

        // Delete temp folder
        restore_removeTmpDir($tmp_dir);

        // Remove PID file
        export_deletePidFile();
    }

    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "End of jMQTT restore.\n");
    if ($error_code)
        print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Error code: ".$error_code."\n");
    print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "###########################################################\n");

    exit($error_code);
}

if ((php_sapi_name() == 'cli') && (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]))) {
    restore_main();
}
?>
