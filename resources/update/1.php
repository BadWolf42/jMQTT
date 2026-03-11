<?php
/**
 * version 1
 * Migrate the plugin to the new JSON version (implementing #76)
 * Return without doing anything if the new JSON version is already installed
 */
/** @var cmd $cmd */
foreach (cmd::searchConfiguration('', 'jMQTT') as $cmd) {
    jMQTT::logger('debug', 'Migration of info command: ' . $cmd->getHumanName());
    $cmd->setConfiguration('parseJson', null);
    $cmd->setConfiguration('prevParseJson', null);
    $cmd->setConfiguration('jParent', null);
    $cmd->setConfiguration('jOrder', null);
    $cmd->save();
}

jMQTT::logger('info', "Migration to Json version");

?>
