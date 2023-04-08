<?php
///////////////////////////////////////////////////////////////////////////////////////////////////
// jMQTT_backup.php
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

$user = @posix_getpwuid(posix_geteuid())['name'];
if ($user != 'www-data' || !jeedom::isCapable('sudo')) {
	print("This script MUST run as www-data user with sudo privileges, aborting!\n");
	exit(2);
}

function export_writePidFile() {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Writing PID file...");
	file_put_contents(__DIR__.'/../data/backup/backup.pid', posix_getpid());
	print("                                  [ OK ]\n");
}

function export_deletePidFile() {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Removing PID file...");
	shell_exec(system::getCmdSudo().'rm -rf '.__DIR__.'/../data/backup/backup.pid 2>&1 > /dev/null');
	print("                                 [ OK ]\n");
}

// Check if another backup is already running
function export_isRunning() {
	if (file_exists(__DIR__.'/../data/backup/backup.pid')) {
		$runing_pid = file_get_contents(__DIR__.'/../data/backup/backup.pid');
		if (@posix_getsid($runing_pid) !== false)
			return true; // PID is running
		export_deletePidFile();
	}
	return false;
}

// [-cC] clean old backups and leftovers
function export_cleanup($limit = 5) { // 5 backups max
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Cleaning up... ");
	// Remove leftovers of a previously running backup
	shell_exec(system::getCmdSudo() . 'rm -rf '.__DIR__.'/../data/backup/jMQTT_backingup.tgz '.__DIR__.'/../data/backup/backup 2>&1 > /dev/null');
	// Search for existing backups
	$backup_files = glob(__DIR__.'/../data/backup/jMQTT_*_*.tgz');
	// Only if more backups than the limit
	if (count($backup_files) <= $limit) {
		print("                                      [ OK ]\n");
		return;
	}
	print((count($backup_files) - $limit) . " backup(s) to remove:\n");
	// Reverse sort the backup files by name
	rsort($backup_files);
	// Remove oldest backups until limit is OK
	while (count($backup_files) > $limit) {
		$del = array_pop($backup_files);
		unlink($del);
		print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "    -> ".basename($del)." removed  [ OK ]\n");
	}
}

function export_prepare() {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Preparing to backup...");
	shell_exec('mkdir -p '.__DIR__.'/../data/backup/backup 2>&1 > /dev/null');
	print("                               [ OK ]\n");
}

// [-I] all used id for eqBroker, eqLogic, cmd
function export_index() {
	$error = array();
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Generating index file...");
	$res = array('eqLogic' => array(), 'cmd' => array());

	$allEqLogics = eqLogic::byType('jMQTT');
	// Preprend brokers
	foreach ($allEqLogics as $o) {
		if ($o->getType() == jMQTT::TYP_BRK)
			$res['eqLogic'][$o->getId()] = $o->getHumanName();
	}

	foreach ($allEqLogics as $o) {
		if ($o->getType() != jMQTT::TYP_BRK) {
			if (!isset($res['eqLogic'][$o->getBrkId()]))
				$error[] = date('[Y-m-d H:i:s][\W\A\R\N\I\N\G] : ') . '    -> eqLogic:' . $o->getId() . ' (' . $o->getHumanName() . ") is orphan.\n";
			$res['eqLogic'][$o->getId()] = $o->getHumanName();
		}
	}

	foreach (cmd::searchConfiguration('', 'jMQTT') as $o) {
		if (!isset($res['eqLogic'][$o->getEqLogic_id()]))
			$error[] = date('[Y-m-d H:i:s][\W\A\R\N\I\N\G] : ') . '    -> cmd:' . $o->getId() . ' (' . $o->getHumanName() . ") is orphan.\n";
		$res['cmd'][$o->getId()] = $o->getHumanName();
	}
	// sort($res['cmd']);

	if($error) {
		print("                        [ WARNING ]\n");
		foreach($error as $e)
			print($e);
	} else {
		print("                             [ OK ]\n");
	}
	return $res;
}

// [-D] backup eqLogic, cmd, cache, plugin config
function export_data() {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Generating Data file...");
	$res = array();

	// Plugin config
	$conf = array();
	foreach (config::searchKey('', "jMQTT") as $o) {
		$conf[$o['key']] = $o['value'];
	}
	$res['conf'] = $conf;

	// Plugin cache
	$cache = array();
	$cacheKeys = array(
		'dependancyjMQTT',
		'jMQTT::daemonLastRcv',
		'jMQTT::daemonLastSnd',
		'jMQTT::daemonPort',
		'jMQTT::daemonUid',
		'jMQTT::nextStats'
	);
	foreach ($cacheKeys as $k) {
		$cache[$k] = cache::byKey($k)->getValue(null);
	}
	$res['cache'] = $cache;

	// EqLogics
	$eqLogics = array();
	foreach (eqLogic::byType('jMQTT') as $o) {
		$eq = utils::o2a($o);
		$eq['cache'] = $o->getCache();
		$eqLogics[] = $eq;
	}
	$res['eqLogic'] = $eqLogics;

	// Cmds
	$cmds = array();
	foreach (cmd::searchConfiguration('', 'jMQTT') as $o) {
		$cmd = utils::o2a($o);
		$cmd['cache'] = $o->getCache();
		$cmds[] = $cmd;
	}
	$res['cmd'] = $cmds;

	print("                              [ OK ]\n");
	return $res;
}

// [-H] backup history, historyArch (directly from SQL ?)
function export_history() {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Generating History file...");
	$res = array();
	foreach (cmd::searchConfiguration('', 'jMQTT') as $o) {
		if ($o->getIsHistorized()) {
			$histo = array();
			foreach (utils::o2a($o->getHistory()) as $h) {
				unset($h['cmd_id']);
				$histo[] = $h;
			}
			$res[$o->getId()] = $histo;
		}
	}
	print("                           [ OK ]\n");
	return $res;
}

// [-P] backup jMQTT plugin dir
function export_folder() {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Backing up jMQTT folder...");
	shell_exec('mkdir -p '.__DIR__.'/../data/backup/backup/jMQTT 2>&1 > /dev/null');
	shell_exec("tar -cf - -C ".__DIR__."/.. --exclude './data/backup/*' --exclude './.git' . | tar -xC ".__DIR__."/../data/backup/backup/jMQTT 2>&1 > /dev/null");
	print("                           [ OK ]\n");
}

// [-L] backup jMQTT logs
function export_logs() {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Backing up jMQTT log files...");
	shell_exec('mkdir -p '.__DIR__.'/../data/backup/backup/logs 2>&1 > /dev/null');
	shell_exec('cp -a '.__DIR__.'/../../../log/jMQTT* '.__DIR__.'/../data/backup/backup/logs 2>&1 > /dev/null');
	print("                        [ OK ]\n");
}

// [-Q] backup Mosquitto config
function export_mosquitto() {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Backing up Mosquitto config...");
	// do not put trailing '/' after mosquitto
	shell_exec(system::getCmdSudo().'cp -a /etc/mosquitto '.__DIR__.'/../data/backup/backup 2>&1 > /dev/null');
	shell_exec(system::getCmdSudo().'chown -R www-data:www-data '.__DIR__.'/../data/backup/backup/mosquitto');
	print("                       [ OK ]\n");
}

// system metadata (jeedom id, date, ...)
function export_metadata($packages = []) {
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Generating Metadata file...");
	$res = array();
	$res['date'] = date("Y-m-d H:i:s");
	$res['hardwareKey'] = jeedom::getHardwareKey();
	$res['hardwareName'] = jeedom::getHardwareName();
	$res['distrib'] = system::getDistrib();
	$res['phpVersion'] = phpversion();
	$res['jeedom'] = jeedom::version();
	$res['lang'] = config::byKey('language', 'core', 'fr_FR');
	$jplugin = update::byLogicalId("jMQTT");
	$res['source'] = $jplugin->getSource();
	$res['branch'] = $jplugin->getConfiguration('version', 'unknown');
	$res['localVersion'] = $jplugin->getLocalVersion();
	$res['remoteVersion'] = $jplugin->getRemoteVersion();
	try {
		$info = file_get_contents(__DIR__ . '/../plugin_info/info.json');
		$info_json = json_decode($info, true);
		$res['pluginVersion'] = $info_json['pluginVersion'];
	} catch (Throwable $e) {
		print("\n" . date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Warning: Could not get version number from info.json, this should not be possible!\n");
		$res['pluginVersion'] = -1;
	}
	$res['configVersion'] = intval(config::byKey('version', 'jMQTT', -1));
	$res['packages'] = $packages;
	print("                          [ OK ]\n");
	return $res;
}

// [-A] make an archive of the backed up data
function export_archive() {
	$date = date("Ymd_His");
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Creating archive jMQTT_".$date.".tgz...");
	shell_exec(system::getCmdSudo().'tar -zcf '.__DIR__.'/../data/backup/jMQTT_backingup.tgz -C '.__DIR__.'/../data/backup backup/');
	shell_exec(system::getCmdSudo().'chown www-data:www-data '.__DIR__.'/../data/backup/jMQTT_backingup.tgz');
	shell_exec('mv '.__DIR__.'/../data/backup/jMQTT_backingup.tgz '.__DIR__.'/../data/backup/jMQTT_'.$date.'.tgz');
	print("        [ OK ]\n");
}

// [-h --help] display help
function export_help() {
	print("Usage: php " . basename(__FILE__) . " <OPTION>\n");
	print("Backup various data of jMQTT, sequentially, in this order\n\n");
	print("  --all       equivalent to -cIDHFLQAC\n");
	print("  -c          clean backup up leftovers before\n");
	print("  -I          backup all used id for eqBroker, eqLogic, cmd\n");
	print("  -D          backup all eqLogic, cmd, cache, plugin config\n");
	print("  -H          backup all cmd history, historyArch\n");
	print("  -F          backup jMQTT plugin folder\n");
	print("  -L          backup jMQTT logs\n");
	print("  -Q          backup Mosquitto config\n");
	print("  -A          make an archive of the backed up data\n");
	print("  -C          clean backup up leftovers after\n");
	print("  -h, --help  display this help message\n");
}


function backup_main() {
	$options = getopt("cIDHFLQACh", array('all', 'help'));

	if (isset($options['h']) || isset($options['help']) || count($options) == 0) {
		export_help();
		exit(0);
	}

	if (export_isRunning()) {
		print("Backup is already running, please wait until it ends, aborting!\n");
		exit(1);
	}
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "###########################################################\n");
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Starting jMQTT backup...\n");

	export_writePidFile();

	if (isset($options['all']) || isset($options['c']))
		export_cleanup();

	export_prepare();
	$packages = array();

	if (isset($options['all']) || isset($options['I'])) {
		file_put_contents(__DIR__.'/../data/backup/backup/index.json', json_encode(export_index(), JSON_UNESCAPED_UNICODE));
		$packages[] = 'index';
	}

	if (isset($options['all']) || isset($options['D'])) {
		file_put_contents(__DIR__.'/../data/backup/backup/data.json', json_encode(export_data(), JSON_UNESCAPED_UNICODE));
		$packages[] = 'data';
	}

	if (isset($options['all']) || isset($options['H'])) {
		file_put_contents(__DIR__.'/../data/backup/backup/history.json', json_encode(export_history(), JSON_UNESCAPED_UNICODE));
		$packages[] = 'history';
	}

	if (isset($options['all']) || isset($options['F'])) {
		export_folder();
		$packages[] = 'folder';
	}

	if (isset($options['all']) || isset($options['L'])) {
		export_logs();
		$packages[] = 'logs';
	}

	if (isset($options['all']) || isset($options['Q'])) {
		if (file_exists('/etc/mosquitto')) {
			export_mosquitto();
			$packages[] = 'mosquitto';
		} else {
			print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "Mosquitto folder in /etc is missing             [ SKIPPED ]\n");
		}
	}

	file_put_contents(__DIR__.'/../data/backup/backup/metadata.json', json_encode(export_metadata($packages), JSON_UNESCAPED_UNICODE));

	if (isset($options['all']) || isset($options['A']))
		export_archive();

	if (isset($options['all']) || isset($options['C']))
		export_cleanup();

	export_deletePidFile();

	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "End of jMQTT backup.\n");
	print(date('[Y-m-d H:i:s][\I\N\F\O] : ') . "###########################################################\n");

	exit(0);
}
/*
	// All plugin configuration
	foreach ($allConfig as $val)
		$key_plugin['configuration'][] = $val['key'];
	$res['plugin'] = $key_plugin

	$key_brk    = array('cache' => array(), 'configuration' => array());
	$key_eq     = array('cache' => array(), 'configuration' => array());
	$key_cmd    = array('cache' => array(), 'configuration' => array());


	$id_eqs = [];
	$id_cmds = [];

	$cacheBrkKeys[] = 'eqLogicCacheAttr'.$brk->getId();
	$cacheBrkKeys[] = 'eqLogicStatusAttr'.$brk->getId();

	$cacheEqptKeys[] = 'jMQTT::' . $eqpt->getId() . '::' . jMQTT::CACHE_IGNORE_TOPIC_MISMATCH;
	// $cacheEqptKeys[] = 'jMQTT::' . $eqpt->getId() . '::' . jMQTT::CACHE_MQTTCLIENT_CONNECTED;
	$cacheEqptKeys[] = 'eqLogicCacheAttr'.$eqpt->getId();
	$cacheEqptKeys[] = 'eqLogicStatusAttr'.$eqpt->getId();

	$cacheCmdKeys[] = 'cmdCacheAttr'.$cmd->getId();
	$cacheCmdKeys[] = 'cmd'.$cmd->getId();

	config::searchKey('', "jMQTT")
	utils::o2a($eqBroker)['configuration']
	utils::o2a($eqLogic)['configuration']
	utils::o2a($cmd)['configuration']

	$eqLogics = array();
	foreach (eqLogic::byType('jMQTT') as $eq) {
		$exp = $eq->toArray();
		$eqLogics[] = $exp;
	}
	function fsort($a, $b) {
		$x = ((isset($a['configuration']) && isset($a['configuration']['type'])) ?
				$a['configuration']['type'] : "z").$a['id'];
		$y = ((isset($b['configuration']) && isset($b['configuration']['type'])) ?
				$b['configuration']['type'] : "z").$b['id'];
		return strcmp($x, $y);
	}
	usort($returns, 'fsort'); // Put the Broker first (needed)
	return $returns;
*/

if ((php_sapi_name() == 'cli') && (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]))) {
	backup_main();
}
?>
