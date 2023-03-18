<?php
/* 
 * version 4
 */
// for each brokers
foreach ((jMQTT::getBrokers()) as $broker) {
	// for each cmd of this broker
	foreach (jMQTTCmd::byEqLogicId($broker->getId()) as $cmd) {
		// if name is 'status'
		if ($cmd->getName() == jMQTT::CLIENT_STATUS) {
			//set logicalId to status (new method to manage broker status cmd)
			$cmd->setLogicalId(jMQTT::CLIENT_STATUS);
			$cmd->save();
		}
	}
}

jMQTT::logger('info', __("Ajout de tags sur le statut des Broker", __FILE__));

?>