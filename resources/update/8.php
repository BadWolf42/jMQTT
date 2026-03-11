<?php


$eqLogics = jMQTT::byType('jMQTT');
/** @var jMQTT[] $eqLogics */
foreach ($eqLogics as $eqLogic) {
    $eqLogic->moveTopicToConfiguration();
}

jMQTT::logger('info', "Topics moved to configuration for all jMQTT equipment");


$templateFolderPath = __DIR__ . '/../../data/template';
foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
    jMQTT::moveTopicToConfigurationByFile($file);
}

jMQTT::logger('info', "Topics moved to configuration for all jMQTT templates");

?>
