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

class DiffType {
	const Created = 'Created';
	const Unchanged = 'Unchanged';
	const Exists = 'Exists';
	const Deleted = 'Deleted';
	const Invalid = 'Invalid';
	const CollisionOnName = 'CollisionOnName';
	const CollisionOnId = 'CollisionOnId';
}


//
// TODO (important) Still work in progress
// - Warning: Shouldn't we call jMQTT_restore.php from the backup?
// - Documentation
//

// Pass by value current metadata, ids and a new temporary folder to extact the backup
function restore_prepare(&$initial_metadata, &$initial_indexes, &$tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Preparing to restore...\n");
	$initial_metadata = export_metadata();
	$initial_indexes  = export_index();
	$tmp_dir = trim(shell_exec('mktemp -dt jMQTT.XXXXXX'));
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Preparing to restore...                              [ OK ]\n");
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
		$metadata = json_decode(file_get_contents($tmp_dir . '/backup/metadata.json'), JSON_UNESCAPED_UNICODE);
		print("                     [ OK ]\n");
	} else {
		print("                  [ ERROR ]\n");
		$metadata = null;
	}
	return $metadata;
}

// Compare hardware keys
function restore_checkHwKey($current, $old, $no_hw_check) {
	// - If no match stop (FAIL)
	// - If --no-hw-check then use old full_import method? -> TODO
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Verifing hardware keys...");
	if ($current['hardwareKey'] == $old['hardwareKey']) {
		print("                            [ OK ]\n");
		return true;
	} elseif ($no_hw_check) {
		print("                   [ NO HW CHECK ]\n");
		print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Cannot restore a backup on a system with a different hardwareKey yet!\n"); // TODO
		return false;
	} else {
		print("                        [ FAILED ]\n");
		print(date('[Y-m-d H:i:s][\E\R\R\O\R] : ') . "Cannot restore this backup on a system with a different hardwareKey!\n");
		return false;
	}
}

// Return Indexes from the extacted backup temporary folder
function restore_getBackupI($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT Index...");
	$index  = json_decode(file_get_contents($tmp_dir . '/backup/index.json'), JSON_UNESCAPED_UNICODE);
	print("                        [ OK ]\n");
	return $index;
}


function restore_diffIndexes(&$options, &$backup_indexes, &$current_indexes, &$diff_indexes) {
	//   -> Confirm all Id from backup ARE NOT used in Jeedom outside of jMQTT
	//      If --by-name, then try to reconciliate, otherwise FAIL after having listed all issues // TODO ?
	//   -> Match all eqLogic by Id first, continue to match with humainName if --by-name,
	//   -> Match all cmd by Id first, continue to match with humainName if --by-name,
	//   -> Display matching result if --verbose
	//
	// if ($options['by-name']) // TODO

	$sucess = true;
	$current_indexes = array('eqLogic' => array(), 'cmd' => array());
	$diff_indexes = array('eqLogic' => array(), 'cmd' => array());

	function abstract_diffIndexes($type, &$options, &$backup_indexes, &$current_indexes, &$diff_indexes, &$sucess) {
		// For all existing jMQTT eqLogic/cmd
		$all = ($type == 'eqLogic') ? eqLogic::byType('jMQTT') : cmd::searchConfiguration('', 'jMQTT');
		foreach ($all as $o) {
			$current_indexes[$type][$o->getId()] = $o->getHumanName();
			if (array_key_exists($o->getId(), $backup_indexes[$type])) {
				if ($current_indexes[$type][$o->getId()] == $backup_indexes[$type][$o->getId()]) {
					$diff_indexes[$type][$o->getId()] = DiffType::Unchanged;
					if ($options['verbose'])
						print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . $type . ':' . $o->getId() . " (" . $current_indexes[$type][$o->getId()] . ") still exists with the same name\n");
				} else {
					$diff_indexes[$type][$o->getId()] = DiffType::Exists;
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
			if (array_key_exists($id, $diff_indexes[$type]))
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
					print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . $type . ':' . $id . " no longer exists\n");
			}
		}
	}
	abstract_diffIndexes('eqLogic', $options, $backup_indexes, $current_indexes, $diff_indexes, $sucess);
	abstract_diffIndexes('cmd',     $options, $backup_indexes, $current_indexes, $diff_indexes, $sucess);

	return $sucess;
}

// Return Data from the extacted backup temporary folder
function restore_getBackupD($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT Data...");
	$data = json_decode(file_get_contents($tmp_dir . '/backup/data.json'), JSON_UNESCAPED_UNICODE);
	print("                         [ OK ]\n");
	return $data;
}

// Return History from the extacted backup temporary folder
function restore_getBackupH($tmp_dir) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Getting backup jMQTT History...");
	$history = json_decode(file_get_contents($tmp_dir . '/backup/history.json'), JSON_UNESCAPED_UNICODE);
	print("                      [ OK ]\n");
	return $history;
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
	print("                         [ OK ]\n");
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


function restore_mainlogic(&$options, &$tmp_dir) {
	$error_code = 0;

	// Get info on current install
	$initial_metadata = null;
	$initial_indexes = null;
	restore_prepare($initial_metadata, $initial_indexes, $tmp_dir);

	// TODO Remove debug
/*
	if ($options['verbose'])
		print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . "Metadata of jMQTT: \n" . json_encode($initial_metadata, JSON_PRETTY_PRINT) . "\n");
	if ($options['verbose'])
		print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . "Indexes of jMQTT: \n" . json_encode($initial_indexes, JSON_PRETTY_PRINT) . "\n");
	if ($options['verbose'])
		print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . "Temp dir: '" . $tmp_dir . "'\n");
*/

	// Extact the archive
	restore_extactBackup($options['file'], $tmp_dir);
	$backup_dir = $tmp_dir . '/backup';
	$metadata = restore_getBackupM($tmp_dir);
	if (is_null($metadata))
		return 3;

	// TODO Remove debug
	// if ($options['verbose'])
		// print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . "Metadata of the backup: \n" . json_encode($metadata, JSON_PRETTY_PRINT) . "\n");

	// Check if indexes are included in archive
	if (!in_array('index', $metadata['packages']))
		return 4;

	// Check this Jeedom hardwareKey against the backup hardwareKey
	if (!restore_checkHwKey($initial_metadata, $metadata, $options['no-hw-check']))
		return 5;

	// Get indexes from backup
	$backup_indexes = restore_getBackupI($tmp_dir);
	$current_indexes = array();
	$diff_indexes = array();

	// Check which eqLogic/cmd has been added/removed
	if (!restore_diffIndexes($options, $backup_indexes, $current_indexes, $diff_indexes))
		return 5;


	// If --apply, then stop and disabled Daemon
	if ($options['apply']) {
		print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Stopping jMQTT daemon...");
		$old_autoMode = config::byKey('deamonAutoMode', 'jMQTT', 1);
		config::save('deamonAutoMode', 0, 'jMQTT');
		$old_daemonState = jMQTT::daemon_state();
		jMQTT::deamon_stop();
		print("                             [ OK ]\n");
	}

	//
	// - If --apply & --do-plugin, then Restore jMQTT plugin folder
	//
	// if ($options['apply'] && $options['do-plugin'])
	// if (in_array('plugin', $metadata['packages']))
	// if ($options['verbose'])


	//
	// - If --apply & --do-delete, then simply remove newly added eqLogic/cmd (add to missing)
	//   -> Display removed result if --verbose
	// - Else If only --apply, then Forget about those eqLogic/cmd, they are OK now (considered new)
	//   -> Display all new untouched if --verbose
	//
	// if ($options['apply'] && $options['do-delete'])
	// elseif ($options['apply'])
	// if ($options['verbose'])


	// Get data from backup
	$data = restore_getBackupD($tmp_dir);
	// $conf = array();
	// foreach (config::searchKey('', "jMQTT") as $o)
		// $conf[$o['key']] = $o['value'];
	// $data['conf']    = $conf;
	// $eqLogics = array();
	// foreach (eqLogic::byType('jMQTT') as $o) {
		// $eq = utils::o2a($o);
		// $eq['cache'] = $o->getCache();
		// $eqLogics[] = $eq;
	// }
	// $data['eqLogic'] = $eqLogics;
	// $cmds = array();
	// foreach (cmd::searchConfiguration('', 'jMQTT') as $o) {
		// $cmd = utils::o2a($o);
		// $cmd['cache'] = $o->getCache();
		// $cmds[] = $cmd;
	// }
	// $data['cmd']     = $cmds;


	//
	// - If --apply, then add missing eqLogic/cmd + chache + their history from backup
	//   -> Use $cmd->addHistoryValue($_value, $_datetime) for history
	//      See if MySQL direct call could be more efficient? needed?
	//   -> Forget about those eqLogic/cmd, they are OK now
	//   -> Display all missing recreated if --verbose
	//
	// if ($options['apply'])
	// if ($options['verbose'])


	//
	// - If --apply, then overwrite all matched eqLogic/cmd with their backup + chache
	//   -> Display all overwrote matched eqLogic/cmd if --verbose
	//
	// if ($options['apply'])
	// if ($options['verbose'])


	// Get history from backup
	$history = restore_getBackupH($tmp_dir);
	// $history = array();
	// foreach (cmd::searchConfiguration('', 'jMQTT') as $o) {
		// if ($o->getIsHistorized()) {
			// $histo = array();
			// foreach (utils::o2a($o->getHistory()) as $h) {
				// unset($h['cmd_id']);
				// $histo[] = $h;
			// }
			// $history[$o->getId()] = $histo;
		// }
	// }


	//
	// - If --apply & --do-history, then remove all matched cmd history and import it from the backup
	//   -> Display all overwrote history cmd if --verbose
	//
	// if ($options['apply'] && $options['do-history'])
	// if (in_array('hist', $metadata['packages']))
	// if ($options['verbose'])


	//
	// - If --apply & --do-logs, then Remove all jMQTT* in log folder and move logs from backup to log folder
	//
	// if ($options['apply'] && $options['do-logs'])
	// if (in_array('logs', $metadata['packages']))


	//
	// - If --apply & --do-mosquitto, then Remove folder /etc/mosquitto and move mosquitto folder from backup
	//
	// if ($options['apply'] && $options['do-mosquitto'])
	// if (in_array('mosquitto', $metadata['packages']))


	//
	// - If --apply, then Restore plugin config (no backup of global cache for now. usefull/TODO?)
	//
	// if ($options['apply'])



	// If --apply, then retore Daemon previous state
	if ($options['apply']) {
		print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Starting jMQTT daemon...");
		config::save('deamonAutoMode', $old_autoMode, 'jMQTT');
		if ($old_daemonState)
			jMQTT::deamon_start();
		print("                             [ OK ]\n");
	}

	return $error_code;
}

function restore_help() {
	print("Usage: php " . basename(__FILE__) . " [OPTIONS]\n");
	print("Restore a backup of jMQTT inside Jeedom.\n");
	print("  --apply             apply the backup, this flag is REQUIRED to acctually\n");
	print("                      change any data on this Jeedom/jMQTT system\n");
	print("  --file=<FILE>       backup file to restore\n");
	print("  -P, --do-plugin     restore previous jMQTT folder (keeping existing backups)\n");
	print("  -D, --do-delete     delete all jMQTT eqLogic and cmd created since backup\n");
	print("  -C, --do-cache      restore previous cached values\n");
	print("  -H, --do-history    restore previous history (do not preserve newer history)\n");
	print("  -L, --do-logs       restore previous log files (do not preserve newer ones)\n");
	print("  -M, --do-mosquitto  restore Mosquitto config folders/files\n");
	print("  --all               equivalent to -PCH\n");
	print("  --by-name           (not recommended) match eqLogic and cmd by name,\n");
	print("  --no-hw-check       DOES NOT CHECK if system hardwareKey match with backup\n");
	print("  -v, --verbose       display more information about the restore process\n");
	print("  -h, --help          display this help message\n");
	print("Defaults:  eqLogic and cmd are matched by id ;\n");
	print("           a backup can only be restored on the same system ;\n");
	print("           eqLogic or cmd newer than the backup will be keept ;\n");
	print("           cmd history newer than the backup will be keept ;\n");
	print("           log files will NOT be restored ;\n");
	print("           Mosquitto configuration will NOT be restored (untouched) ;\n");
}

function restore_main() {
	global $argv;

	$rest = null;
	$getopt_res = getopt(
		"hPDCHLMv",
		array(
			'apply',
			'file:',
			'do-plugin',
			'do-delete',
			'do-cache',
			'do-history',
			'do-logs',
			'do-mosquitto',
			'all',
			'by-name',
			'no-hw-check',
			'verbose',
			'help'
		),
		$rest
	);

	$options = array();
	$options['file'] = (isset($getopt_res['file']) && $getopt_res['file'] != false) ? $getopt_res['file'] : null;
	$options['help'] = isset($getopt_res['h']) || isset($getopt_res['help']);
	$options['apply'] = isset($getopt_res['apply']);
	$options['do-plugin'] = isset($getopt_res['P']) || isset($getopt_res['do-plugin']) || isset($getopt_res['all']);
	$options['do-delete'] = isset($getopt_res['D']) || isset($getopt_res['do-delete']);
	$options['do-cache'] = isset($getopt_res['C']) || isset($getopt_res['do-cache']) || isset($getopt_res['all']);
	$options['do-history'] = isset($getopt_res['H']) || isset($getopt_res['do-history']) || isset($getopt_res['all']);
	$options['do-logs'] = isset($getopt_res['L']) || isset($getopt_res['do-logs']);
	$options['do-mosquitto'] = isset($getopt_res['M']) || isset($getopt_res['do-mosquitto']);
	$options['by-name'] = isset($getopt_res['by-name']);
	$options['no-hw-check'] = isset($getopt_res['no-hw-check']);
	$options['verbose'] = isset($getopt_res['v']) || isset($getopt_res['verbose']);
	$options['other'] = array_slice($argv, $rest);

	print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . "Getopt:  " . json_encode($getopt_res) . "\n"); // TODO Remove debug
	print(date('[Y-m-d H:i:s][\D\E\B\U\G] : ') . "Options: " . json_encode($options) . "\n"); // TODO Remove debug

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
