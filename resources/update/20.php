<?php

// Delete orphan crons
// @phpstan-ignore-next-line
while ($cron = cron::byClassAndFunction('jMQTT', 'disableIncludeMode')) {
    /** @var cron $cron */
    $cron->remove(false);
}

// @phpstan-ignore-next-line
jMQTT::logger('info', "Orphaned crons deleted");

?>
