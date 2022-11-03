<?php
///////////////////////////////////////////////////////////////////////////////////////////////////
// jMQTT_restore.php

//
//
// TODO (important) Still work in progress
// - All functions bellow are fonctional
// - Mutate into a class?
// - Warning: Shouldn't we call jMQTT_restore.php from the backup?
// - Rename --force -> --reconciliate?
// - Documentation
//
// Algorithm:
// - Extract archive
//
// - Check this hardwareId against the backup hardwareId
//   - If no match stop (FAIL)
//   - If --force then use old import_all method? -> TODO
//
// - Stop and Disabled Daemon
//
// - Check which eqLogic/cmd has been added/removed
//   -> Confirm all Id from backup ARE NOT used in Jeedom outside of jMQTT
//      If --force, then try to reconciliate, otherwise FAIL after having listed all issues
//   -> Match all eqLogic by Id first, continue to match with humainName if --force,
//   -> Match all cmd by Id first, continue to match with humainName if --force,
//   -> Display matching result if --verbose
//
// - If --apply & --do-cleanup, then simply remove newly added eqLogic/cmd (add to missing)
//   -> Display removed result if --verbose
// - Else If only --apply, then Forget about those eqLogic/cmd, they are OK now (considered new)
//   -> Display all new untouched if --verbose
//
// - If --apply, then add missing eqLogic/cmd + chache + their history from backup
//   -> Use $cmd->addHistoryValue($_value, $_datetime) for history
//      See if MySQL direct call could be more efficient? needed?
//   -> Forget about those eqLogic/cmd, they are OK now
//   -> Display all missing recreated if --verbose
//
// - If --apply, then overwrite all matched eqLogic/cmd with their backup + chache
//   -> Display all overwrote matched eqLogic/cmd if --verbose
//
// - If --apply & --do-history, then remove all matched cmd history and import it from the backup
//   -> Display all overwrote history cmd if --verbose
//
// - If --apply & --do-logs, then Remove all jMQTT* in log folder and move logs from backup to log folder
//
// - If --apply & --do-mosquitto, then Remove folder /etc/mosquitto and move mosquitto folder from backup
//
// - If --apply, then Restore plugin config (no backup of global cache for now. usefull/TODO?)
//
// - If --apply, then Delete jMQTT plugin folder
//     Move backup folder at its place
//     Delete backup.tgz backup.*.json mosquitto/ logs/ from jMQTT folder
//
// - Enable and Start Daemon
// - Move jMQTT_restore log file in log folder
// - Delete temp folder
//
//
//

require_once __DIR__ . '/../../../core/php/core.inc.php';
require_once __DIR__ . '/jMQTT_backup.php';

function restore_help() {
	echo "Usage: php " . basename(__FILE__) . " [OPTIONS]\n";
	echo "Restore a backup of jMQTT inside Jeedom.\n";
	echo "Defaults: `jMQTT/backup.tgz` file will be used ;\n";
	echo "          Newer jMQTT eqLogic or cmd than the backup, it will be keept ;\n";
	echo "          Newer cmd history than the backup, it will be keept ;\n";
	echo "          jMQTT log files will remain the same (untouched) ;\n";
	echo "          Mosquitto configuration will remain the same (untouched) ;\n\n";
	echo "  --apply         apply the backup, this flag is REQUIRED to acctually\n";
	echo "                  change any data on this Jeedom/jMQTT system\n";
	echo "  --file=<FILE>   restore a specific backup file\n";
	// echo "  --do-cleanup    delete all jMQTT eqLogic and cmd created since backup\n";
	// echo "  --do-history    restore previous history (do not preserve newer history)\n";
	// echo "  --do-logs       restore previous log files (do not preserve newer ones)\n";
	// echo "  --do-mosquitto  restore Mosquitto config folders/files\n";
	// echo "  --force         (not recommended) uses names to to map eqLogic and cmd,\n";
	// echo "                  DOES NOT CHECK if system hardwareKey match with backup\n";
	// echo "  --verbose       display more information about the restore process\n";
	echo "  -h, --help      display this help message\n";
}

function restore_options() {
	global $argv;
	
	$rest = null;
	$options = getopt("h", array('apply', 'file:', 'do-cleanup', 'do-history', 'do-logs', 'do-mosquitto', 'force', 'verbose', 'help'), $rest);

	$param = array();
	$param['file'] = (isset($options['file']) && $options['file'] != False) ? $options['file'] : __DIR__ . '/../backup.tgz';
	$param['help'] = isset($options['h']) || isset($options['help']);
	$param['apply']     = isset($options['apply']);
	// $param['clean']     = isset($options['do-cleanup']);
	// $param['hist']      = isset($options['do-history']);
	// $param['logs']      = isset($options['do-logs']);
	// $param['mosquitto'] = isset($options['do-mosquitto']);
	// $param['force ']    = isset($options['force']);
	// $param['verbose']   = isset($options['verbose']);
	$param['other'] = array_slice($argv, $rest);
	return $param;
}

function restore_getCurrentMI() {
	echo "Getting current install info...";
	$meta = export_metadata();
	$ids  = export_ids();
	echo "  [ OK ]\n";
	return array('meta' => $meta, 'ids' => $ids);
}

function restore_extactBackup($file) {
	echo "Extracting backup file...";
	$tmp_dir = shell_exec('mktemp -d');
	shell_exec('tar -zxpf ' . $file . ' --directory ' . $tmp_dir);
	echo "        [ OK ]\n";
	return $tmp_dir;
}

function restore_installBackupFolder($plugin_dir, $tmp_dir) {
	// Cleanup
	echo "Cleaning up backup folder...";
	shell_exec('rm -rf '.$tmp_dir.'/jMQTT/backup.*.json '.$tmp_dir.'/jMQTT/mosquitto '.$tmp_dir.'/jMQTT/logs');
	shell_exec('rm -rf '.$tmp_dir.'/jMQTT/jMQTT_backup_running.tgz '.$tmp_dir.'/jMQTT/backup.tgz');
	echo "     [ OK ]\n";

	echo "Installing backup folder...";
	$cwd = __DIR__; // We are working in a folder that is going to be removed
	chdir($tmp_dir); // Go to an existing folder
	// shell_exec('mv '.$plugin_dir.'/jMQTT '.$tmp_dir.'/jMQTT_old');
	shell_exec('rm -rf '.$plugin_dir.'/jMQTT');
	shell_exec('mv '.$tmp_dir.'/jMQTT '.$plugin_dir.'');
	chdir($cwd); // Switch back to jMQTT/resources/ folder
	echo "      [ OK ]\n";
}

function restore_removeTmpDir($tmp_dir) {
	echo "Removing backup directory...";
	if ($tmp_dir != '' && $tmp_dir != '/')
		shell_exec('rm -rf ' . $tmp_dir);
	echo "     [ OK ]\n";
}


function restore_getBackupM($tmp_dir) {
	echo "Getting backup jMQTT Metadata...";
	$meta = json_decode(file_get_contents($tmp_dir . '/jMQTT/backup.meta.json'), JSON_UNESCAPED_UNICODE);
	echo " [ OK ]\n";
	return $meta;
}

function restore_getBackupI($tmp_dir) {
	echo "Getting backup jMQTT Ids...";
	$ids  = json_decode(file_get_contents($tmp_dir . '/jMQTT/backup.ids.json'), JSON_UNESCAPED_UNICODE);
	echo "      [ OK ]\n";
	return $ids;
}

function restore_getBackupD($tmp_dir) {
	echo "Getting backup jMQTT Data...";
	$data = json_decode(file_get_contents($tmp_dir . '/jMQTT/backup.data.json'), JSON_UNESCAPED_UNICODE);
	echo "     [ OK ]\n";
	return $data;
}

function restore_getBackupH($tmp_dir) {
	echo "Getting backup jMQTT History...";
	$hist = json_decode(file_get_contents($tmp_dir . '/jMQTT/backup.hist.json'), JSON_UNESCAPED_UNICODE);
	echo "  [ OK ]\n";
	return $hist;
}


function restore_checkHwId($current, $old, $force) {
	echo "Verifing hardware keys...";
	if ($current['hardwareKey'] == $old['hardwareKey']) {
		echo "        [ OK ]\n";
	} elseif ($force) {
		echo "        [ FORCED ]\n";
		echo "FAILURE: Cannot restore a backup on a system with a different hardwareKey yet!\n"; // TODO
		exit(3);
	} else {
		echo "        [ FAIL ]\n";
		echo "FAILURE: Cannot restore this backup on a system with a different hardwareKey!\n";
		exit(3);
	}
}


// TODO (important) Rework : old function that imports as new all eq/cmd (unwanted in the restore version)
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
		if ($eq->getType() == jMQTT::TYP_BRK) {
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
				}
			}
		}
		
		foreach($link_infos as $cmd) {
			if (!isset($link_names[$cmd->getValue()]))
				continue;
			$id = $link_names[$cmd->getValue()];
	 		echo 'Replacing in cmd='.$cmd->getId().' value='.$cmd->getValue().' by '.$id."\n";
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
		echo 'Replacing in cmd='.$cmd->getId().' request='.$cmd->getConfiguration('request', '').' by '.$req."\n";
		$cmd->setConfiguration('request', $req);
		$cmd->save();
	}
}


function restore_main() {
	global $argv;
	$param = restore_options();
	echo json_encode($param);
	echo"\n";

	if (!is_readable($param['file'])) {
		echo "Could not open ".$param['file']." file for reading.\n";
		restore_help();
		exit(1);
	}

	if (count($argv) == 1 || $param['help']) {
		restore_help();
		exit(0);
	}


	// if (isset($options['all']))


	// if (isset($options['all']) || isset($options['I']))


	// if (isset($options['all']) || isset($options['D']))

	// if (isset($options['all']) || isset($options['H']))



	// Failsafe while devlopping
	// exit(128);
}

if (php_sapi_name() == 'cli')
	restore_main();
?>
