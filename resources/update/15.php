<?php

// Delete orphan cmd

$sql = "DELETE FROM `cmd` WHERE `cmd`.`id` = ANY(SELECT `cmd`.`id` FROM `cmd` LEFT JOIN `eqLogic` ON `eqLogic`.`id` = `cmd`.`eqLogic_id` WHERE `cmd`.`eqType` = 'jMQTT' AND `eqLogic`.`name` IS NULL)";
DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);

jMQTT::logger('info', "Orphan commands deleted");

raiseForceDepInstallFlag();

?>
