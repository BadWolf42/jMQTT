<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Manage the jMQTT equipment
 */
class jMQTTEqpt {

    const CLIENT_STATUS = 'status';
    const OFFLINE = 'offline';
    const ONLINE = 'online';
    
    /**
     * Return the topic name of the jMQTT client status
     * @return string client status topic name
     */
    public static function getMqttClientStatusTopic() {
        return jMQTT::getMqttId() . '/' . self::CLIENT_STATUS;
    }

    /**
     * Return the jMQTT equipment (the one that contains the jMQTT status command)
     * @return cmd eqLogic jMQTT equipment
     */
    public static function getMqttClientEqLogic() {
        $cmd = self::getMqttClientStatusCmd();
        return $cmd == null ? null : $cmd->getEqLogic();
    }

        /**
     * Return the jMQTT status information command
     * @return cmd status information command. null if does not exist.
     */
    public static function getMqttClientStatusCmd() {
        $cmds = cmd::byLogicalId(self::getMqttClientStatusTopic(), 'info');
        switch (count($cmds)) {
            case 0:
                return null; break;
            case 1:
                return $cmds[0]; break;
            default:
                log::add('jMQTT', 'warning', 'Several commands having "' . self::getMqttClientStatusTopic() .
                                                                                 '" as topic exist. Consider the one with id=' . $cmds[0]->getId() . '.');
                return $cmds[0];
        }
    }
}
