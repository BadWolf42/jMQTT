<?php
///////////////////////////////////////////////////////////////////////////////////////////////////
// jMQTT_backup.php

require_once __DIR__ . '/../../../core/php/core.inc.php';

// [-M] system metadata (jeedom id, date, ...) -> backup.meta.json
function export_metadata() {
	echo "Exporting Metadata file...";
	$res = array();
	$res['version'] = 1;
	$res['date'] = date("Y-m-d H:i:s");
	$res['jeedom'] = jeedom::version();
	$res['hardwareKey'] = jeedom::getHardwareKey();
	$res['hardwareName'] = jeedom::getHardwareName();
	$res['distrib'] = system::getDistrib();
	$jplugin = update::byLogicalId("jMQTT");
	$res['source'] = $jplugin->getSource();
	$res['localVersion'] = $jplugin->getLocalVersion();
	$res['remoteVersion'] = $jplugin->getRemoteVersion();
	echo "       [ OK ]\n";
	return $res;
}

// [-I] all used id for eqBroker, eqLogic, cmd -> backup.ids.json
function export_ids() {
	echo "Exporting Id file...";
	$res = array('eqLogic' => array(), 'cmd' => array());
	foreach (eqLogic::byType('jMQTT') as $o)
		$res['eqLogic'][$o->getId()] = $o->getHumanName();
	// sort($res['eqLogic']);
	foreach (cmd::searchConfiguration('', 'jMQTT') as $o)
		$res['cmd'][$o->getId()] = $o->getHumanName();
	// sort($res['cmd']);
	echo "             [ OK ]\n";
	return $res;
}

// [-D] backup eqLogic, cmd, cache, plugin config -> backup.data.json
function export_data() {
	echo "Exporting Data file...";
	$res = array();

	$conf = array();
	foreach (config::searchKey('', "jMQTT") as $o) {
		$conf[$o['key']] = $o['value'];
	}
	$res['conf']    = $conf;

	$eqLogics = array();
	foreach (eqLogic::byType('jMQTT') as $o) {
		$eq = utils::o2a($o);
		$eq['cache'] = $o->getCache();
		$eqLogics[] = $eq;
	}
	$res['eqLogic'] = $eqLogics;

	$cmds = array();
	foreach (cmd::searchConfiguration('', 'jMQTT') as $o) {
		$cmd = utils::o2a($o);
		$cmd['cache'] = $o->getCache();
		$cmds[] = $cmd;
	}
	$res['cmd']     = $cmds;

	echo "           [ OK ]\n";
	return $res;
}

// [-H] backup history, historyArch (directly from SQL ?) -> backup.hist.json
function export_history() {
	echo "Exporting History file...";
	$res = array();

	$cmds = array();
	foreach (cmd::searchConfiguration('', 'jMQTT') as $o) {
		$res[$o->getId()] = utils::o2a($o->getHistory());
	}
	// TODO (important) remove all redondant "cmd_id" from history
	echo "        [ OK ]\n";
	return $res;
}

// [-h --help] display help
function export_help() {
	echo "Usage: php " . basename(__FILE__) . " <OPTION>\n";
	echo "Backup various data of jMQTT from data from inside Jeedom\n\n";
	echo "  --all       equivalent to -MIDH\n";
	echo "  -M          export system metadata (jeedom id, date ...)   into jMQTT/backup.meta.json\n";
	echo "  -I          export all used id for eqBroker, eqLogic, cmd  into jMQTT/backup.ids.json\n";
	echo "  -D          export all eqLogic, cmd, cache, plugin config  into jMQTT/backup.data.json\n";
	echo "  -H          export all cmd history, historyArch            into jMQTT/backup.hist.json\n";
	echo "  -h, --help  display this help message\n";
}


function backup_main() {
	global $argv;
	$options = getopt("MKIDHh", array('all', 'help'));

	if (isset($options['h']) || isset($options['help']) || count($argv) == 1) {
		export_help();
		exit(0);
	}

	if (isset($options['all']) || isset($options['M']))
		file_put_contents(__DIR__ . '/../backup.meta.json', json_encode(export_metadata(), JSON_UNESCAPED_UNICODE));

	if (isset($options['all']) || isset($options['I']))
		file_put_contents(__DIR__ . '/../backup.ids.json',  json_encode(export_ids(), JSON_UNESCAPED_UNICODE));

	if (isset($options['all']) || isset($options['D']))
		file_put_contents(__DIR__ . '/../backup.data.json', json_encode(export_data(), JSON_UNESCAPED_UNICODE));

	if (isset($options['all']) || isset($options['H']))
		file_put_contents(__DIR__ . '/../backup.hist.json', json_encode(export_history(), JSON_UNESCAPED_UNICODE));

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
		$x = ((array_key_exists('configuration', $a) && array_key_exists('type', $a['configuration'])) ?
				$a['configuration']['type'] : "z").$a['id'];
		$y = ((array_key_exists('configuration', $b) && array_key_exists('type', $b['configuration'])) ?
				$b['configuration']['type'] : "z").$b['id'];
		return strcmp($x, $y);
	}
	usort($returns, 'fsort'); // Put the Broker first (needed)
	return $returns;
*/

if (php_sapi_name() == 'cli')
	backup_main();
?>
