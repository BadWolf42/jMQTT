<?php

$eqLogics = jMQTT::byType('jMQTT');
foreach ($eqLogics as $eqLogic) {
	// get info cmds of current eqLogic
	$infoCmds = jMQTTCmd::byEqLogicId($eqLogic->getId(), 'info');
	foreach ($infoCmds as $cmd) {
		// split topic and jsonPath of cmd
		$cmd->splitTopicAndJsonPath();
	}
}

jMQTT::logger('info', __("JsonPath séparé du Topic pour tous les commandes info jMQTT", __FILE__));


$templateFolderPath = __DIR__ . '/../../data/template';
foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
	jMQTT::templateSplitJsonPathByFile($file);
}

jMQTT::logger('info', __("JsonPath séparé du Topic pour tous les Templates jMQTT", __FILE__));


raiseForceDepInstallFlag();

?>