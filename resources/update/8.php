<?php


$eqLogics = jMQTT::byType('jMQTT');
/** @var jMQTT[] $eqLogics */
foreach ($eqLogics as $eqLogic) {
    $eqLogic->moveTopicToConfiguration();
}

jMQTT::logger('info', __("Topics déplacé vers la configuration pour tous les équipements jMQTT", __FILE__));


$templateFolderPath = __DIR__ . '/../../data/template';
foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
    jMQTT::moveTopicToConfigurationByFile($file);
}

jMQTT::logger('info', __("Topics déplacé vers la configuration pour tous les Templates jMQTT", __FILE__));

?>
