<?php


foreach (jMQTT::byType('jMQTT') as $eqLogic) {
    /** @var jMQTT $eqLogic */
    // Protect already modified Eq
    $batId = $eqLogic->getBatteryCmd();
    $cmd = jMQTTCmd::byId($batId);
    if (is_object($cmd)){
        jMQTT::logger('info', sprintf("#%1\$s# ALREADY sets the battery of #%2\$s#.", $cmd->getHumanName(), $eqLogic->getHumanName()));
        continue;
    }
    // get info cmds of current eqLogic
    foreach (jMQTTCmd::byEqLogicId($eqLogic->getId(), 'info') as $cmd) {
        /** @var jMQTTCmd $cmd */
        // Old isBattery()
        if ($cmd->getType() == 'info' && ($cmd->getGeneric_type() == 'BATTERY' || preg_match('/(battery|batterie)$/i', $cmd->getName()))) {
            $eqLogic->setConfiguration(jMQTTConst::CONF_KEY_BATTERY_CMD, $cmd->getId());
            jMQTT::logger('info', sprintf("#%1\$s# sets the battery of #%2\$s#.", $cmd->getHumanName(), $eqLogic->getHumanName()));
            $eqLogic->save();
        }
    }
}

jMQTT::logger('info', "Battery commands defined directly on jMQTT equipment");

?>
