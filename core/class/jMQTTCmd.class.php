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
 * Extends the cmd core class
 */
class jMQTTCmd extends cmd {

    // Constant value to be affected to Parent and Order configuration parameters, for commands
    // that do not derive from a JSON structure
    const NOT_JSON_CHILD = -1;
    
    /**
     * @var int maximum length of command name supported by the database scheme
     */
    private static $_cmdNameMaxLength;

    /**
     * Create a new command. Command IS NOT saved.
     * @param jMQTT $eqLogic jMQTT equipment the command belongs to
     * @param string $name command name
     * @param string $topic command mqtt topic
     * @return jMQTTCmd new command (NULL if not created)
     */
    public static function newCmd($eqLogic, $name, $topic) {

        $cmd = new jMQTTCmd();
        $cmd->setEqLogic_id($eqLogic->getId());
        $cmd->setEqType('jMQTT');
        $cmd->setIsVisible(1);
        $cmd->setIsHistorized(0);
        $cmd->setSubType('string');
        $cmd->setLogicalId($topic);
        $cmd->setType('info');
        $cmd->setTopic($topic);
        $cmd->setConfiguration('parseJson', '0');
        $cmd->setConfiguration('prevParseJson', 0);

        // Check cmd name does not exceed the max lenght of the database scheme (fix issue #58)
        if (($newName = self::checkCmdName($eqLogic, $name)) !== true) {
            $name = $newName;
        }
        $cmd->setName($name);
        
        $eqLogic->log('info', 'Creating command of type info ' . $eqLogic->getName() . '|' . $name);

        // Advise the desktop page (jMQTT.js) that a new command has been added
        event::add('jMQTT::cmdAdded',
                   array('eqlogic_id' => $eqLogic->getId(), 'eqlogic_name' => $eqLogic->getName(),
                         'cmd_name' => $name));

        return $cmd;
    }

    /**
     * preRemove method to log that a command is removed
     */
    public function preRemove() {
        $this->getEqLogic()->log('info', 'Removing command ' . $this->getEqLogic()->getName() . '|' . $this->getName());
    }

    /**
     * Update this command value, inform all stakeholders, and save when necessary
     * @param string $value new command value
     * @param int $jParent cmd id of the parent. Set NOT_JSON_CHILD if not a JSON structure.
     * @param int $jOrder order of the command. Set NOT_JSON_CHILD if not a JSON structure.
     */
    public function updateCmdValue($value, $jParent, $jOrder) {

        $to_save = false;
        if ($this->getId() == '') {
            $to_save = true;
        }
        if ($this->getConfiguration('jParent', self::NOT_JSON_CHILD) != $jParent || $this->getConfiguration('jOrder', self::NOT_JSON_CHILD) != $jOrder) {
            $this->setConfiguration('jParent', $jParent);
            $this->setConfiguration('jOrder', $jOrder);
            $to_save = true;
        }
        if ($to_save) {
            $this->save(); 
        }
        
        // Update the command value
        $this->event($value);

        $this->getEqLogic()->log('info', '-> ' . $this->getEqLogic()->getName() . '|' . $this->getName() . ' ' . $value);
    }

    /**
     * Returns weather or not a given parameter is valid and can be processed by the setConfiguration method
     * @param string $value given configuration parameter value
     * @return boolean TRUE of the parameter is valid, FALSE if not
     */
    public static function isConfigurationValid($value) {
        return (json_encode(array('v' => $value), JSON_UNESCAPED_UNICODE) !== FALSE);
    }

    /**
     * Decode the given message as a JSON structure and update command values.
     * If the given message is not a JSON valid structure, nothing is done.
     * Commands are created when they do not exist.
     * If the given JSON message contains other JSON structure, routine is called recursively.
     * @param eqLogic $_eqLogic current equipment
     * @param string $_msgValue message value
     * @param string $_cmdName command name prefix
     * @param string $_topic mqtt topic prefix
     * @param int $_jParent cmd id of the parent (in case of JSON payload)
     */
    public static function decodeJsonMessage($_eqLogic, $_msgValue, $_cmdName, $_topic, $_jParent) {
        $jsonArray = json_decode($_msgValue, true);
        if (is_array($jsonArray) && json_last_error() == JSON_ERROR_NONE)
            self::decodeJsonArray($_eqLogic, $jsonArray, $_cmdName, $_topic, $_jParent);
    }

    /**
     * Decode the given JSON array and update command values.
     * Commands are created when they do not exist.
     * If the given JSON structure contains other JSON structure, call this routine recursively.
     * @param eqLogic $_eqLogic current equipment
     * @param array $_jsonArray JSON decoded array to parse
     * @param string $_cmdName command name prefix
     * @param string $_topic mqtt topic prefix
     * @param int $_jParent cmd id of the parent (in case of JSON payload)
     */
    public static function decodeJsonArray($_eqLogic, $_jsonArray, $_cmdName, $_topic, $_jParent) {

        // Current index in the JSON structure: starts from 0
        $jOrder = 0;

        foreach ($_jsonArray as $id => $value) {
            $jsonTopic = $_topic    . '{' . $id . '}';
            $jsonName  = $_cmdName  . '{' . $id . '}';
            $cmd = jMQTTCmd::byEqLogicIdAndLogicalId($_eqLogic->getId(), $jsonTopic);

            // If no command has been found, create one
            if (!is_object($cmd)) {
                $cmd = jMQTTCmd::newCmd($_eqLogic, $jsonName, $jsonTopic);
            }

            if (is_object($cmd)) {
                // json_encode is used as it works whatever the type of $value (array, boolean, ...)
                $cmd->updateCmdValue(json_encode($value), $_jParent, $jOrder);

                // If the current command is a JSON structure that shall be decoded, call this routine recursively
                if ($cmd->getConfiguration('parseJson') == '1' && is_array($value))
                    self::decodeJsonArray($_eqLogic, $value, $jsonName, $jsonTopic, $cmd->getId());
            }
            $jOrder++;
        }
    }

    /**
     * This method is called when a command is executed
     */
    public function execute($_options = null) {

        if ($this->getType() != 'action')
            return;
        
        $request = $this->getConfiguration('request', "");
        $topic = $this->getTopic();
        $qos = $this->getConfiguration('Qos', 1);
        $retain = $this->getConfiguration('retain', 0);

        switch ($this->getSubType()) {
            case 'slider':
                $request = str_replace('#slider#', $_options['slider'], $request);
                break;
            case 'color':
                $request = str_replace('#color#', $_options['color'], $request);
                break;
            case 'message':
                if ($_options != null)  {
                    $replace = array('#title#', '#message#');
                    $replaceBy = array($_options['title'], $_options['message']);
                    if ( $_options['title'] == '') {
                        throw new Exception(__('Le sujet du message ne peut pas être vide', __FILE__));
                    }
                    $request = str_replace($replace, $replaceBy, $request);
                }
                break;
        }

        $request = jeedom::evaluateExpression($request);
        $this->getEqLogic()->getBroker()->publishMosquitto(
            $this->getId(), $this->getEqLogic()->getName(), $topic, $request, $qos, $retain);

        return $request;
    }

    /*
     * Override preSave:
     *    . To detect changes on the retain flag: when retain mode is exited, send a null
     *      payload to the broker to erase the retained topic (implementation of Issue #1).
     *    . To update the Logical Id: usefull for action commands (fix issue #18).
     */
    public function preSave() {

        /** @var jMQTT $eqLogic */
        $eqLogic       = $this->getEqLogic();
        $eqName        = $eqLogic->getName();
        $cmdLogName    = $eqName  . '|' . $this->getName();
        $prevRetain    = $this->getConfiguration('prev_retain', 0);
        $retain        = $this->getConfiguration('retain', 0);
        $parseJson     = $this->getConfiguration('parseJson', '0');
        $prevParseJson = $this->getConfiguration('prevParseJson', 1);
        
        // If request are JSON parameters, re-encode them (as Jeedom core decode them when saving through
        // the desktop interface - fix issue #28)
        foreach(array('request') as $key) {
            $conf = $this->getConfiguration($key);
            if (is_array($conf) && (($conf = json_encode($conf, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK)) !== FALSE))
                $this->setConfiguration($key, $conf);
        }

        // If an action command is being created, initialize correctly the prev_retain flag (fix issue #13)
        if ($this->getId() == '' && $this->getType() == 'action') {
            $eqLogic->log('info', 'Creating action command ' . $cmdLogName);
            $prevRetain = $retain;
            $this->setConfiguration('prev_retain', $retain);
        }

        if ($retain != $prevRetain) {
            // Acknowledge the retain mode change
            $this->setConfiguration('prev_retain', $retain);

            if ($prevRetain) {
                // A null payload shall be sent to the broker to erase the last retained value
                // Otherwise, this last value remains retained at broker level
                $eqLogic->log('info',
                         $cmdLogName . ': mode retain désactivé, efface la dernière valeur mémorisée sur le broker');
                jMQTT::publishMosquitto($this->getId(), $eqName, $this->getTopic(), '', 1, 1);
            }
            else
                $eqLogic->log('info', $cmdLogName . ': mode retain activé');
        }

        if ($parseJson != $prevParseJson && $this->getType() == 'info') {
            // Acknowledge parseJson change
            $this->setConfiguration('prevParseJson', $parseJson);

            if ($parseJson) {
                $eqLogic->log('info', $cmdLogName . ': parseJson is enabled');
                jMQTTCmd::decodeJsonMessage($this->getEqLogic(), $this->execCmd(), $this->getName(),
                    $this->getTopic(), $this->getId());
            }
            else
                $eqLogic->log('info', $cmdLogName . ': parseJson is disabled');
        }

        // Insure Logical ID is always equal to the topic (fix issue #18)
        $this->setLogicalId($this->getTopic());
    }

    /**
     * Set this command as irremovable
     */
    public function setIrremovable() {
        $this->setConfiguration('irremovable', 1);
    }
    
    public function setTopic($topic) {
        $this->setConfiguration('topic', $topic);
    }
    
    public function getTopic() {
        return $this->getConfiguration('topic');
    }
    
    /**
     * @param jMQTT jMQTT broker to log to
     * @param string $name
     * @return boolean|string true if the command name is valid, corrected cmd name otherwise
     */
    private static function checkCmdName($eqLogic, $name) {
        if (! isset(self::$_cmdNameMaxLength)) {
            $field = 'character_maximum_length';
            $sql = "SELECT " . $field . " FROM information_schema.columns WHERE table_name='cmd' AND column_name='name'";
            $res = DB::Prepare($sql, array());
            self::$_cmdNameMaxLength = $res[$field];
            $eqLogic->log('debug', 'Cmd name max length retrieved from the DB: ' . strval(self::$_cmdNameMaxLength));
        }
        
        if (strlen($name) > self::$_cmdNameMaxLength)
            return hash("md4", $name);
        else
            return true;
    }
}
