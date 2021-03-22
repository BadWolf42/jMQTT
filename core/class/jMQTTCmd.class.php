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

    /**
     * Possible value of $_post_action.
     * @var integer
     */
    const POST_ACTION_INIT_JSON_VALUE = 1;
    
    /**
     * @var int maximum length of command name supported by the database scheme
     */
    private static $_cmdNameMaxLength;
    
    /**
     * Data shared between preSave and postSave
     * @var int $_post_action value among constants POST_ACTION* 
     */
    private $_post_action;
    
    /**
     * Create a new command. Command IS NOT saved.
     * @param jMQTT $eqLogic jMQTT equipment the command belongs to
     * @param string $name command name
     * @param string $topic command mqtt topic
     * @return jMQTTCmd new command (NULL if not created)
     */
    public static function newCmd($eqLogic, $name, $topic) {

        $cmd = new jMQTTCmd();
        $cmd->setEqLogic($eqLogic);
        $cmd->setEqLogic_id($eqLogic->getId());
        $cmd->setEqType('jMQTT');
        $cmd->setIsVisible(1);
        $cmd->setIsHistorized(0);
        $cmd->setSubType('string');
        $cmd->setType('info');
        $cmd->setTopic($topic);
        
        // Check cmd name does not exceed the max lenght of the database scheme (fix issue #58)
        $cmd->setName(self::checkCmdName($eqLogic, $name));
        
        $cmd->eventNewCmd(true);
        
        return $cmd;
    }
    
    /**
     * Inform that a command has been created (in the log and to the ui though an event)
     * @param bool $reload indicate if the desktop page shall be reloaded
     */
    private function eventNewCmd($reload=false) {
        $eqLogic = $this->getEqLogic();
        $eqLogic->log('info', 'Création commande ' . $this->getType() . ' ' . $this->getLogName());
        
        // Advise the desktop page (jMQTT.js) that a new command has been added
        event::add('jMQTT::cmdAdded',
            array('eqlogic_id' => $eqLogic->getId(), 'eqlogic_name' => $eqLogic->getName(),
                'cmd_name' => $this->getName(), 'reload' => $reload));        
    }
    
    private function eventTopicMismatch() {
        $eqLogic = $this->getEqLogic();
        $eqLogic->log('warning', 'Le topic de la commande ' . $this->getLogName() .
            " est incompatible du topic de l'équipement associé");
        
        // Advise the desktop page (jMQTT.js) of the topic mismatch
        event::add('jMQTT::cmdTopicMismatch',
            array('eqlogic_name' => $eqLogic->getName(), 'cmd_name' => $this->getName()));
    }
    
    /**
     * Return a full export of this command as an array.
     * @return array
     */
    public function full_export() {
        $cmd = clone $this;
        $cmdValue = $cmd->getCmdValue();
        if (is_object($cmdValue)) {
            $cmd->setValue($cmdValue->getName());
        } else {
            $cmd->setValue('');
        }
        $return = utils::o2a($cmd);
        return $return;
    }

    /**
     * preRemove method to log that a command is removed
     */
    public function preRemove() {
        $this->getEqLogic()->log('info', 'Removing command ' . $this->getLogName());
    }

    /**
     * Update this command value, and inform all stakeholders about the new value
     * @param string $value new command value
     */
    public function updateCmdValue($value) {
        if(in_array(strtolower($this->getName()), ["color","colour","couleur","rgb"]) || $this->getGeneric_type() == "LIGHT_COLOR") {
		if(is_numeric($value)) {
			$value=jMQTT::DECtoHEX($value);
		} else {
			$json=json_decode($value);
			if($json != null){
				if(isset($json->x) && isset($json->y)){
					$value=jMQTT::XYtoHTML($json->x,$json->y);
				} elseif(isset($json->r) && isset($json->g) && isset($json->b)) {
					$value=jMQTT::RGBtoHTML($json->r,$json->g,$json->b);
				}
			}
		}
	}
        $this->event($value);
        $this->getEqLogic()->log('info', '-> ' . $this->getLogName() . ' ' . $value);
        if (in_array(strtolower($this->getName()), ['battery', 'batterie']) || $this->getGeneric_type() == 'BATTERY') {
            if ($this->getSubType() == 'binary') {
                $this->getEqLogic()->batteryStatus($value ? 100 : 10);
            } else {
                $this->getEqLogic()->batteryStatus($value);
            }
            $this->getEqLogic()->log('info', '-> Update battery status');
        }
    }
    
    /**
     * Update this command value knowing that this command is derived from a JSON payload
     * Inform all stakeholders about the new value
     * @param array $jsonArray associative array
     */
    public function updateJsonCmdValue($jsonArray) {
        $topic = $this->getTopic();
        $indexes = substr($topic, strpos($topic, '{'));
        $indexes = str_replace(array('}{', '{', '}'), array('|', '', ''), $indexes);
        $indexes = explode('|', $indexes);
        try {
            $value = self::get_array_value($jsonArray, $indexes);
            $this->updateCmdValue(json_encode($value));
        }
        catch (Exception $e) {
            // Should never occur
            $this->getEqLogic()->log('info', 'valeur de la commande ' . $this->getLogName() . ' non trouvée');
        }
    }
    
    /**
     * Decode and return the JSON payload being received by this command
     * @param string $payload JSON payload being received
     * @return null|array|object null if the JSON payload cannot de decoded
     */
    public function decodeJsonMsg($payload) {
        $jsonArray = json_decode($payload, true);
        if (is_array($jsonArray) && json_last_error() == JSON_ERROR_NONE)
            return $jsonArray;
        else {
            $this->getEqLogic()->log('warning', 'Erreur de format JSON sur la commande ' . $this->getLogName() .
                ': ' . json_last_error_msg());
            return null;
        }
    }
    
    /**
     * Returns whether or not a given parameter is valid and can be processed by the setConfiguration method
     * @param string $value given configuration parameter value
     * @return boolean TRUE of the parameter is valid, FALSE if not
     */
    public static function isConfigurationValid($value) {
        return (json_encode(array('v' => $value), JSON_UNESCAPED_UNICODE) !== FALSE);
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
                    $request = str_replace($replace, $replaceBy, $request);
                }
                break;
            case 'select':
                $request = str_replace('#select#', $_options['select'], $request);
                break;
        }

        $request = jeedom::evaluateExpression($request);
        $this->getEqLogic()->publishMosquitto(
            $this->getId(), $this->getEqLogic()->getName(), $topic, $request, $qos, $retain);

        return $request;
    }

    /**
     * preSave callback called by the core before saving this command in the DB
     */
    public function preSave() {

        /** @var jMQTT $eqLogic */
        $eqLogic       = $this->getEqLogic();
        $cmdLogName    = $this->getLogName();
        $prevRetain    = $this->getConfiguration('prev_retain', 0);
        $retain        = $this->getConfiguration('retain', 0);
        
        // If request are JSON parameters, re-encode them (as Jeedom core decode them when saving through
        // the desktop interface - fix issue #28)
        foreach(array('request') as $key) {
            $conf = $this->getConfiguration($key);
            if (is_array($conf) && (($conf = json_encode($conf, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK)) !== FALSE))
                $this->setConfiguration($key, $conf);
        }

        // Creation of a command
        if ($this->getId() == '') {
            
            // Action command: initialize correctly the prev_retain flag (fix issue #13)
            if ($this->getType() == 'action') {
                $prevRetain = $retain;
                $this->setConfiguration('prev_retain', $retain);
                $this->eventNewCmd(false);
            }
            
            // Information command: if deriving from a JSON payload, set a flag to initiliaze its value in postSave
            if ($this->getType() == 'info' && $this->isJson()) {
                $this->addPostAction(self::POST_ACTION_INIT_JSON_VALUE);
                $this->eventNewCmd(false);
            }
        }

        if ($retain != $prevRetain) {
            // Acknowledge the retain mode change
            $this->setConfiguration('prev_retain', $retain);

            if ($prevRetain) {
                // A null payload shall be sent to the broker to erase the last retained value
                // Otherwise, this last value remains retained at broker level
                $eqLogic->log('info',
                         $cmdLogName . ': mode retain désactivé, efface la dernière valeur mémorisée sur le broker');
                $eqLogic->publishMosquitto($this->getId(), $eqLogic->getName(), $this->getTopic(), '', 1, 1);
            }
            else
                $eqLogic->log('info', $cmdLogName . ': mode retain activé');
        }
    }
    
    /**
     * Callback called by the core after having saved this command in the DB
     */
    public function postSave() {
        // When requested, initialize the value of a new command deriving from a JSON payload 
        if (isset($this->_post_action)) {
            
            if ($this->_post_action & self::POST_ACTION_INIT_JSON_VALUE) {
                $root_topic = substr($this->getTopic(), 0, strpos($this->getTopic(), '{'));
                
                /** @var jMQTTCmd $root_cmd root JSON command */
                $root_cmd = jMQTTCmd::byEqLogicIdAndTopic($this->getEqLogic_id(), $root_topic, false);
                
                if (isset($root_cmd)) {
                    $value = $root_cmd->execCmd();
                    if (! empty($value)) {
                        $jsonArray = $root_cmd->decodeJsonMsg($value);
                        if (isset($jsonArray)) {
                            $this->updateJsonCmdValue($jsonArray);
                        }
                    }
                }
            }
        }
        
        // For info commands, check that the topic is compatible with the subscription command
        // of the related equipment
        if ($this->getType() == 'info') {
            if (! $this->topicMatchesSubscription($this->getEqLogic()->getTopic())) {
                $this->eventTopicMismatch();
            }
        }
    }

    public function setName($name) {
        // Since 3.3.22, the core removes / from command names
        $name = str_replace("/", ":", $name);
        parent::setName($name);
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
     * Return the list of commands of the given equipment which topic is related to the given one
     * (i.e. equal to the given one if multiple is false, or having the given topic as mother JSON related
     * topic if multiple is true)
     * For JSON related topic, mother command is always returned first if existing.
     * 
     * @param int $eqLogic_id of the eqLogic
     * @param string $topic topic to search
     * @param boolean $multiple true if the cmd related topic and associated JSON derived commands are requested
     * @return NULL|jMQTTCmd|array(jMQTTCmd)
     */
    public static function byEqLogicIdAndTopic($eqLogic_id, $topic, $multiple=false) {
        // JSON_UNESCAPED_UNICODE used to correct #92
        $conf = substr(json_encode(array('topic' => $topic), JSON_UNESCAPED_UNICODE), 1, -2);
        $conf = str_replace('\\', '\\\\', $conf);
        
        $values = array(
            'topic' => '%' . $conf . '"%',
            'eqLogic_id' => $eqLogic_id,
        );
        $sql = 'SELECT ' . DB::buildField(__CLASS__) . 'FROM cmd WHERE eqLogic_id=:eqLogic_id AND ';
            
        if ($multiple) {
            $values['topic_json'] = '%' . $conf . '{%';
            // Union is used to have the mother command returned first
            $sql .= 'configuration LIKE :topic UNION ' . $sql . 'configuration LIKE :topic_json';
        }
        else {
            $sql .= 'configuration LIKE :topic';
        }
        $cmds = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
        
        if (count($cmds) == 0)
            return null;
        else
            return $multiple ? $cmds : $cmds[0];
    }
    
    /**
     * Return the $array element designated by $indexes.
     * Example: to retrieve $array['1']['name'], call get_array_value($array, array('1', 'name'))
     * @param array $array
     * @param array $indexes
     * @return mixed the requested element
     * @throws Exception if index is not found in $array
     */
    private static function get_array_value($array, $indexes) {
        if (count($array) == 0 || count($indexes) == 0) {
            throw new Exception();
        }
        
        $index = array_shift($indexes);
        if(!array_key_exists($index, $array)){
            throw new Exception();
        }
        
        $value = $array[$index];
        if (count($indexes) == 0) {
            return $value;
        }
        else {
            return self::get_array_value($value, $indexes);
        }
    }
    
    /**
     * Return whether or not this command is derived from a Json payload
     * @return boolean
     */
    private function isJson() {
        return strpos($this->getTopic(), '{') !== false;
    }
    
    /**
     * Return the name of this command for logging purpose
     * (basically, return concatenation of the eqLogic name and the cmd name)
     * @return string
     */
    private function getLogName() {
        return $this->getEqLogic()->getName()  . '|' . $this->getName();
    }
    
    private function addPostAction($action) {
        if (isset($this->_post_action)) {
            $this->_post_action = $this->_post_action | $action;
        }
        else {
            $this->_post_action = $action;
        }
    }
    
    /**
     * Returns true if the topic of this command matches the given subscription description
     * @param string $subscription subscription to match
     * @return boolean
     */
    public function topicMatchesSubscription($subscription) {
        $topic = $this->getTopic();
        $i = strpos($topic, '{');
        return Mosquitto\Message::topicMatchesSub($i === false ? $topic : substr($topic, 0, $i),
            $subscription);
    }
    
    /**
     * @param jMQTT eqLogic the command belongs to
     * @param string $name
     * @return string input name if the command name is valid, corrected cmd name otherwise
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
            return $name;
    }
}
