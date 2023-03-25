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

//
//
// TODO (important) Still work in progress
// - Warning: Shouldn't we call jMQTT_restore.php from the backup?
// - Rename --force -> --reconciliate?
// - Documentation
//
// Algorithm:
/*
// - Extract archive
//
// - Check this hardwareKey against the backup hardwareKey
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
*/

require_once __DIR__ . '/../../../core/php/core.inc.php';
require_once __DIR__ . '/jMQTT_backup.php';

function restore_help() {
	print("Usage: php " . basename(__FILE__) . " [OPTIONS]\n");
	print("Restore a backup of jMQTT inside Jeedom.\n");
	print("Defaults: `jMQTT/backup.tgz` file will be used ;\n");
	print("          Newer jMQTT eqLogic or cmd than the backup, it will be keept ;\n");
	print("          Newer cmd history than the backup, it will be keept ;\n");
	print("          jMQTT log files will remain the same (untouched) ;\n");
	print("          Mosquitto configuration will remain the same (untouched) ;\n\n");
	print("  --apply         apply the backup, this flag is REQUIRED to acctually\n");
	print("                  change any data on this Jeedom/jMQTT system\n");
	print("  --file=<FILE>   restore a specific backup file\n");
	// print("  --do-cleanup    delete all jMQTT eqLogic and cmd created since backup\n");
	// print("  --do-history    restore previous history (do not preserve newer history)\n");
	// print("  --do-logs       restore previous log files (do not preserve newer ones)\n");
	// print("  --do-mosquitto  restore Mosquitto config folders/files\n");
	// print("  --force         (not recommended) uses names to to map eqLogic and cmd,\n");
	// print("                  DOES NOT CHECK if system hardwareKey match with backup\n");
	// print("  --verbose       display more information about the restore process\n");
	print("  -h, --help      display this help message\n");
}

function restore_options() {
	global $argv;

	$rest = null;
	$options = getopt("h", array('apply', 'file:', 'do-cleanup', 'do-history', 'do-logs', 'do-mosquitto', 'force', 'verbose', 'help'), $rest);

	$param = array();
	$param['file'] = (isset($options['file']) && $options['file'] != false) ? $options['file'] : __DIR__ . '/../backup.tgz';
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

// Pass by value current metadata, ids and a new temporary folder to extact the backup
function restore_prepare(&$initial_metadata, &$initial_indexes, &$tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Preparing to restore...");
	$initial_metadata = export_metadata();
	$initial_indexes  = export_index();
	$tmp_dir = shell_exec('mktemp -d');
	print("                               [ OK ]\n");
}

// Extract the backup file (with root privileges) into temporary folder
function restore_extactBackup($file, $tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Extracting archive ".$file."...");
	shell_exec('tar -zxpf ' . $file . ' --directory ' . $tmp_dir);
	print("      [ OK ]\n");
}

// Return Metadata from the extacted backup temporary folder
function restore_getBackupM($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT Metadata...");
	$metadata = json_decode(file_get_contents($tmp_dir . '/backup/metadata.json'), JSON_UNESCAPED_UNICODE);
	print(" [ OK ]\n");
	return $metadata;
}

// Return Indexes from the extacted backup temporary folder
function restore_getBackupI($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT Index...");
	$index  = json_decode(file_get_contents($tmp_dir . '/backup/index.json'), JSON_UNESCAPED_UNICODE);
	print("    [ OK ]\n");
	return $index;
}

// Return Data from the extacted backup temporary folder
function restore_getBackupD($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT Data...");
	$data = json_decode(file_get_contents($tmp_dir . '/backup/data.json'), JSON_UNESCAPED_UNICODE);
	print("     [ OK ]\n");
	return $data;
}

// Return History from the extacted backup temporary folder
function restore_getBackupH($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT History...");
	$history = json_decode(file_get_contents($tmp_dir . '/backup/history.json'), JSON_UNESCAPED_UNICODE);
	print("  [ OK ]\n");
	return $history;
}

// Restore jMQTT plugin files, keeping existing jMQTT backups and .git folder
function restore_plugin($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Saving existing jMQTT backups...");
	exec('cp -a '.__DIR__.'/../data/backup/* '.$tmp_dir.'/backup/jMQTT/data/backup 2>&1 > /dev/null');
	if (file_exists(__DIR__.'/../.git'))
		exec('cp -a '.__DIR__.'/../.git '.$tmp_dir.'/backup/jMQTT 2>&1 > /dev/null');
	print("                     [ OK ]\n");

	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Restoring jMQTT plugin files...");
	$plugins_dir = realpath(__DIR__.'/../..');
	$cwd = getcwd(); // We may be working in a folder that is going to be removed
	chdir($tmp_dir); // Go to an existing folder

	// exec('mv '.$plugins_dir.'/jMQTT '.$tmp_dir.'/jMQTT_old');
	exec('rm -rf '.$plugins_dir.'/jMQTT 2>&1 > /dev/null');
	exec('mv '.$tmp_dir.'/backup/jMQTT '.$plugins_dir.' 2>&1 > /dev/null');

	chdir($cwd); // Switch back to previous cwd folder
	print("                       [ OK ]\n");
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

// Remove the temporary folder where backup has been extracted
function restore_removeTmpDir($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Removing backup directory...");
	if ($tmp_dir != '' && $tmp_dir != '/')
		shell_exec('rm -rf ' . $tmp_dir);
	print("     [ OK ]\n");
}

// Compare hardware keys
function restore_checkHwKey($current, $old, $force) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Verifing hardware keys...");
	if ($current['hardwareKey'] == $old['hardwareKey']) {
		print("        [ OK ]\n");
	} elseif ($force) {
		print("    [ FORCED ]\n");
		print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') .print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Cannot restore a backup on a system with a different hardwareKey yet!\n"); // TODO
		exit(3);
	} else {
		print("      [ FAIL ]\n");
		print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Cannot restore this backup on a system with a different hardwareKey!\n");
		exit(3);
	}
}


// Old export functions
function full_export_old() {
	$returns = array();
	foreach (eqLogic::byType('jMQTT') as $eq) {
		$exp = $eq->toArray();
		$exp['commands'] = array();
		foreach ($eq->getCmd() as $cmd)
			$exp['commands'][] = $cmd->full_export();
		$returns[] = $exp;
	}
	function fsort($a, $b) {
		$x = ((array_key_exists('configuration', $a) && array_key_exists('type', $a['configuration'])) ?
				$a['configuration']['type'] : "z").$a['id'];
		$y = ((array_key_exists('configuration', $b) && array_key_exists('type', $b['configuration'])) ?
				$b['configuration']['type'] : "z").$b['id'];
		return strcmp($x, $y);
	}
	usort($returns, 'fsort'); // Put the Broker first (needed)
	return $returns;
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


function restore_main() {
	global $argv;
	$param = restore_options();
	print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . "Options: " . json_encode($param) . "\n"); // TODO Remove debug

	if (count($argv) == 1 || $param['help']) {
		restore_help();
		exit(0);
	}

	if (!is_readable($param['file'])) {
		print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Could not open ".$param['file']." file for reading.\n");
		restore_help();
		exit(1);
	}

	if (export_isRunning()) {
		print(date('[Y-m-d H:i:s][[\E\R\R\O\R] : ') ."Backup is already running, please wait until it ends, aborting!\n");
		exit(1);
	}

	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') ."###########################################################\n");
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') ."Starting to restore jMQTT...\n");

	export_writePidFile();

	// TODO DEBUG remove when restore ready
	sleep(3);

	// if (isset($options['all']))


	// if (isset($options['all']) || isset($options['I']))


	// if (isset($options['all']) || isset($options['D']))

	// if (isset($options['all']) || isset($options['H']))


	export_deletePidFile();

	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "End of jMQTT restore.\n");
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "###########################################################\n");

	exit(0);
}

if (php_sapi_name() == 'cli')
	restore_main();
?>
