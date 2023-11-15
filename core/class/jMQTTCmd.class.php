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

    const CONF_KEY_AUTOPUB              = 'autoPub';
    const CONF_KEY_JSON_PATH            = 'jsonPath';
    const CONF_KEY_PUB_QOS              = 'Qos';
    const CONF_KEY_REQUEST              = 'request';
    const CONF_KEY_RETAIN               = 'retain';

    /**
     * Data shared between preSave and postSave
     * @var null|array values from preSave used for postSave actions
     */
    private $_preSaveInformations;

    /**
     * Create a new command. Command IS NOT saved.
     * @param jMQTT $eqLogic jMQTT equipment the command belongs to
     * @param string $name command name
     * @param string $topic command mqtt topic
     * @return jMQTTCmd new command (NULL if not created)
     */
    public static function newCmd($eqLogic, $name, $topic, $jsonPath = '') {
        $cmd = new jMQTTCmd();
        $cmd->setEqLogic($eqLogic);
        $cmd->setEqLogic_id($eqLogic->getId());
        $cmd->setEqType('jMQTT');
        $cmd->setIsVisible(1);
        $cmd->setType('info');
        $cmd->setSubType('string');
        $cmd->setTopic($topic);
        $cmd->setJsonPath($jsonPath);
        // Cmd name if troncated by the core since 4.1.17
        // Cf https://github.com/jeedom/core/commit/93e590142d774b48eee64e0901859384d246cd41
        $cmd->setName($name);
        return $cmd;
    }

    /**
     * Inform that a command has been created (in the log and to the ui though an event)
     * @param bool $reload indicate if the desktop page shall be reloaded
     */
    private function eventNewCmd($reload=false) {
        $eqLogic = $this->getEqLogic();
        $eqLogic->log(
            'info',
            sprintf(
                __("Commande %1\$s #%2\$s# ajoutée", __FILE__),
                $this->getType(),
                $this->getHumanName()
            )
        );
        // Advise the desktop page (jMQTT.js) that a new command has been added
        event::add(
            'jMQTT::cmdAdded',
            array(
                'eqlogic_id' => $eqLogic->getId(),
                'eqlogic_name' => $eqLogic->getName(),
                'cmd_name' => $this->getName(),
                'reload' => $reload
            )
        );
    }

    /**
     * Return a full export of this command as an array.
     * @param boolean $clean irrelevant values to Daemon must be removed from the return
     * @return array representing the cmd
     */
    public function full_export($clean=false) {
        $cmd = clone $this;
        $cmdValue = $cmd->getCmdValue();
        $cmd->setValue(is_object($cmdValue) ? $cmdValue->getName() : '');
        $return = utils::o2a($cmd);
        if ($clean) { // Remove unneeded informations
            unset($return['alert']);
            unset($return['configuration']['prev_retain']);
            unset($return['configuration']['commentaire']);
            unset($return['isHistorized']);
            unset($return['isVisible']);
            unset($return['display']);
            unset($return['order']);
            unset($return['template']);
        }
        return $return;
    }

    /**
     * Update this command value, and inform all stakeholders about the new value
     * @param int|string $value new command value
     */
    public function updateCmdValue($value) {
        if (in_array(strtolower($this->getName()), ["color", "colour", "couleur", "rgb"])
            || $this->getGeneric_type() == "LIGHT_COLOR") {
            if (is_numeric($value)) {
                $value = jMQTTCmd::DECtoHEX($value);
            } else {
                $json = json_decode($value);
                if ($json != null) {
                    if (isset($json->x) && isset($json->y)) {
                        $value = jMQTTCmd::XYtoHTML($json->x,$json->y);
                    } elseif(isset($json->r) && isset($json->g) && isset($json->b)) {
                        $value = jMQTTCmd::RGBtoHTML($json->r, $json->g, $json->b);
                    }
                }
            }
        }
        $eqLogic = $this->getEqLogic();
        $eqLogic->checkAndUpdateCmd($this, $value);
        $value = $this->getCache('value', 0);
        $eqLogic->log(
            'info',
            sprintf(
                __("Cmd #%1\$s# <- %2\$s", __FILE__),
                $this->getHumanName(),
                $value
            )
        );
        if ($this->isAvailability()
            && ((!boolval($value)) != boolval($eqLogic->getStatus('warning', 0)))) {
            if (!boolval($value)) {
                $eqLogic->setStatus('warning', 1);
                $eqLogic->log('info', sprintf(
                    __("Eq #%s# <- Est Indisponible", __FILE__),
                    $eqLogic->getHumanName()
                ));
            } else {
                $eqLogic->setStatus('warning', 0);
                $eqLogic->log('info', sprintf(
                    __("Eq #%s# <- Est Disponible", __FILE__),
                    $eqLogic->getHumanName()
                ));
            }
        }
        if ($this->isBattery()) {
            $value = ($this->getSubType() == 'binary') ? ($value ? 100 : 10) : $value;
            if ($eqLogic->getStatus('battery') != $value) {
                $eqLogic->batteryStatus($value);
                $eqLogic->log('info', sprintf(
                    __("Eq #%1\$s# <- Batterie à %2\$s%%", __FILE__),
                    $eqLogic->getHumanName(), $value
                ));
            }
        }
    }

    /**
     * Update this command value knowing that this command is derived from a JSON payload
     * Inform all stakeholders about the new value
     * @param array $jsonArray associative array
     */
    public function updateJsonCmdValue($jsonArray) {
        // if dependancy is not yet installed, we avoid issue when someone create some command with JSONPath
        if (!class_exists('JsonPath\JsonObject')) {
            $this->getEqLogic()->log(
                'error',
                __("La bibliothèque JsonPath-PHP n'a pas été trouvée, relancez les dépendances", __FILE__)
            );
            return;
        }
        try {
            // Get and prepare the jsonPath
            $jsonPath = $this->getJsonPath();
            if ($jsonPath == '') return;
            if ($jsonPath[0] != '$') $jsonPath = '$' . $jsonPath;
            // Create JsonObject for JsonPath
            $jsonobject=new JsonPath\JsonObject($jsonArray);
            $value = $jsonobject->get($jsonPath);
            if ($value !== false && $value !== array())
                $this->updateCmdValue(
                    json_encode(
                        (count($value) > 1) ? $value : $value[0],
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    )
                );
            else
                $this->getEqLogic()->log(
                    'info',
                    sprintf(
                        __("Chemin JSON de la commande #%s# n'a pas retourné de résultat sur ce message json", __FILE__),
                        $this->getHumanName()
                    )
                );
        }
        catch (Throwable $e) {
            if (log::getLogLevel(__CLASS__) > 100)
                $this->getEqLogic()->log(
                    'warning',
                    sprintf(
                        __("Chemin JSON '%1\$s' de la commande #%2\$s# a levé l'Exception: %3\$s", __FILE__),
                        $this->getJsonPath(),
                        $this->getHumanName(),
                        $e->getMessage()
                    )
                );
            else // More info in debug mode, no big log otherwise
                $this->getEqLogic()->log(
                    'warning',
                    str_replace(
                        "\n",
                        ' <br/> ',
                        sprintf(
                            __("Chemin JSON '%1\$s' de la commande #%2\$s# a levé l'Exception: %3\$s", __FILE__).
                            ",<br/>@Stack: %4\$s.",
                            $this->getJsonPath(),
                            $this->getHumanName(),
                            $e->getMessage(),
                            $e->getTraceAsString()
                        )
                    )
                );
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
            if (json_last_error() == JSON_ERROR_NONE)
                $this->getEqLogic()->log(
                    'info',
                    sprintf(
                        __("Problème de format JSON sur la commande #%s#: Le message reçu n'est pas au format JSON.", __FILE__),
                        $this->getHumanName()
                    )
                );
            else
                $this->getEqLogic()->log(
                    'warning',
                    sprintf(
                        __("Problème de format JSON sur la commande #%1\$s#: %2\$s (%3\$d)", __FILE__),
                        $this->getHumanName(),
                        json_last_error_msg(),
                        json_last_error()
                    )
                );
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
        $request = $this->getConfiguration(jMQTTConst::CONF_KEY_REQUEST, "");
        $topic = $this->getTopic();
        $qos = $this->getConfiguration(jMQTTConst::CONF_KEY_PUB_QOS, 1);
        $retain = $this->getConfiguration(jMQTTConst::CONF_KEY_RETAIN, 0);
        $eq = $this->getEqLogic();
        // Prevent error when $_options is null or accessing an unavailable $_options
        $_defaults = array(
            'other' => '',
            'slider' => '#slider#',
            'title' => '#title#',
            'message' => '#message#',
            'color' => '#color#',
            'select' => '#select#'
        );
        $_options = is_null($_options) ? $_defaults : array_merge($_defaults, $_options);
        // As per feature request (issue 208: https://github.com/BadWolf42/jMQTT/issues/208#issuecomment-1206207191)
        // If request is empty, the corresponding subtype option is implied (Default/other = '')
        if ($request == '') {
            $request = $_options[$this->getSubType()];
        } else { // Otherwise replace all tags
            $replace = array(
                '#slider#',
                '#title#',
                '#message#',
                '#color#',
                '#select#'
            );
            $replaceBy = array(
                $_options['slider'],
                $_options['title'],
                $_options['message'],
                $_options['color'],
                $_options['select']
            );
            $replace = array_merge(
                $replace,
                array('#id#', '#name#', '#humanName#', '#subType#', '#topic#')
            );
            $replaceBy = array_merge(
                $replaceBy,
                array(
                    $this->getId(),
                    $this->getName(),
                    $this->getHumanName(),
                    $this->getSubType(),
                    $topic
                )
            );
            $replace = array_merge(
                $replace,
                array('#eqId#', '#eqName#', '#eqHumanName#', '#eqTopic#')
            );
            $replaceBy = array_merge(
                $replaceBy,
                array($eq->getId(), $eq->getName(), $eq->getHumanName(), $eq->getTopic())
            );
            $request = str_replace($replace, $replaceBy, $request);
        }
        $request = jeedom::evaluateExpression($request);
        $eq->publish($this->getHumanName(), $topic, $request, $qos, $retain);
        return $request;
    }

    /**
     * Used by jMQTT::applyATemplate() to updates referenced cmd
     * Replace all template names from $cmdsName by ids in $cmdsId
     * @param array $cmdsName list of names (to be replaced by ids)
     * @param array $cmdsId list of ids (to be replace names)
     */
    public function replaceCmdIds(&$cmdsName, &$cmdsId) {
        array_walk_recursive(
            $this->configuration,
            function(&$item, $key, &$nameToIds) {
                if (is_string($item)) {
                    // jMQTT::logger('info', 'Replacing in '.$key.': '.$item);
                    $item = str_replace($nameToIds[0], $nameToIds[1], $item);
                    // jMQTT::logger('info', 'By: '.$item);
                }
            },
            array(&$cmdsName, &$cmdsId)
        );
    }

    /**
     * preSave callback called by the core before saving this command in the DB
     */
    public function preSave() {
        // Check if name is unique on the equipment for a New cmd
        if (
            $this->getId() == ''
            && is_object(jMQTTCmd::byEqLogicIdCmdName($this->getEqLogic()->getId(), $this->getName()))
        ) {
            throw new Exception(
                sprintf(
                    __("Impossible de créer la commande <b>#%s#</b>, car une commande avec le même nom existe déjà !", __FILE__),
                    $this->getHumanName()
                )
            );
        }

        // --- New cmd or Existing cmd ---
        // Saving a new command on a Broker must fail, except for the status command
        if (
            $this->getEqLogic()->getType() == jMQTTConst::TYP_BRK
            && $this->getLogicalId() != jMQTTConst::CLIENT_STATUS
            && $this->getLogicalId() != jMQTTConst::CLIENT_CONNECTED
            && $this->getId() == ''
        ) {
            $err = __("Impossible de créer la commande <b>#%1\$s#</b>, seule les commandes de status sont autorisées sur un équipement Broker (%2\$s)", __FILE__);
            throw new Exception(sprintf($err, $this->getHumanName(), $this->getEqLogic()->getName()));
        }

        $conf = $this->getConfiguration(jMQTTConst::CONF_KEY_REQUEST);
        // If request is an array, it means a JSON (starting by '{') has been parsed in 'request' field (parsed by getValues in jquery.utils.js)
        if (is_array($conf) && (($conf = json_encode($conf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== FALSE)) {
            $this->setConfiguration(jMQTTConst::CONF_KEY_REQUEST, $conf);
        }

        // Reset autoPub if info cmd (should not happen or be possible)
        if ($this->getType() == 'info' && $this->getConfiguration(jMQTTConst::CONF_KEY_AUTOPUB, 0))
            $this->setConfiguration(jMQTTConst::CONF_KEY_AUTOPUB, 0);
        // Check "request" if autoPub enabled
        if ($this->getType() == 'action' && $this->getConfiguration(jMQTTConst::CONF_KEY_AUTOPUB, 0)) {
            $req = $this->getConfiguration(jMQTTConst::CONF_KEY_REQUEST, '');
            // Must check If New cmd, autoPub changed or Request changed
            $must_chk = $this->getId() == '';
            $must_chk = $must_chk || !(self::byId($this->getId())->getConfiguration(jMQTTConst::CONF_KEY_AUTOPUB, 0));
            $must_chk = $must_chk || (self::byId($this->getId())->getConfiguration(jMQTTConst::CONF_KEY_REQUEST, '') != $req);
            if ($must_chk) {
                // Get all commands
                preg_match_all("/#([0-9]*)#/", $req, $matches);
                $cmds = array_unique($matches[1]);
                if (count($cmds) > 0) { // There are commands
                    foreach ($cmds as $cmd_id) {
                        $cmd = cmd::byId($cmd_id);
                        if (!is_object($cmd))
                            throw new Exception(
                                sprintf(
                                    __("Impossible d'activer la publication automatique sur <b>#%1\$s#</b>, car la commande <b>#%2\$s#</b> est invalide", __FILE__),
                                    $this->getHumanName(),
                                    $cmd_id
                                )
                            );
                        if ($cmd->getType() != 'info')
                            throw new Exception(
                                sprintf(
                                    __("Impossible d'activer la publication automatique sur <b>#%1\$s#</b>, car la commande <b>#%2\$s#</b> n'est pas de type info", __FILE__),
                                    $this->getHumanName(),
                                    $cmd->getHumanName()
                                )
                            );
                        if ($cmd->getEqType() =='jMQTT') {
                            /** @var jMQTTCmd $cmd */
                            $cmd_topic = $cmd->isJson() ? substr($cmd->getTopic(), 0, strpos($cmd->getTopic(), '{')) : $cmd->getTopic();
                            if ($this->getTopic() == $cmd_topic)
                                throw new Exception(
                                    sprintf(
                                        __("Impossible d'activer la publication automatique sur <b>#%1\$s#</b>, car la commande <b>#%2\$s#</b> référence le même topic", __FILE__),
                                        $this->getHumanName(),
                                        $cmd->getHumanName()
                                    )
                                );
                        }
                    }
                }
            }
        }

        $this->_preSaveInformations = null;
        $cmd = self::byId($this->getId());
        // It's time to gather informations that will be used in postSave
        if (is_object($cmd)){
            $this->_preSaveInformations = array(
                jMQTTConst::CONF_KEY_RETAIN => $cmd->getConfiguration(jMQTTConst::CONF_KEY_RETAIN, 0),
                jMQTTConst::CONF_KEY_AUTOPUB => $cmd->getConfiguration(jMQTTConst::CONF_KEY_AUTOPUB, 0),
                jMQTTConst::CONF_KEY_REQUEST => $cmd->getConfiguration(jMQTTConst::CONF_KEY_REQUEST, '')
            );
        }
    }

    /**
     * Callback called by the core after having saved this command in the DB
     */
    public function postSave() {
        $eqLogic = $this->getEqLogic();

        // Nothing must be done in postSave on Broker command
        if ($eqLogic->getType() == jMQTTConst::TYP_BRK) {
            // Remove all cmd other than the status cmd
            if ($this->getLogicalId() != jMQTTConst::CLIENT_STATUS && $this->getLogicalId() != jMQTTConst::CLIENT_CONNECTED) {
                $eqLogic->log(
                    'warning',
                    sprintf(
                        __("La commande <b>#%1\$s#</b> a été supprimée du Broker %2\$s, car seule les commandes status et connected sont autorisées sur un équipement Broker.", __FILE__),
                        $this->getHumanName(),
                        $eqLogic->getName()
                    )
                );
                $this->remove();
            }
            return;
        }


        // If _preSaveInformations is null, It's a fresh new cmd.
        if (is_null($this->_preSaveInformations)) {

            // Type Info and deriving from a JSON payload : Initialize value from "root" cmd
            if ($this->getType() == 'info' && $this->isJson()) {

                $root_topic = $this->getTopic();

                $root_cmd = jMQTTCmd::byEqLogicIdAndTopic(
                    $this->getEqLogic_id(),
                    $root_topic,
                    false
                );

                if (is_object($root_cmd)) {
                    $value = $root_cmd->execCmd();
                    if (!is_null($value) && $value !== '') {
                        $jsonArray = $root_cmd->decodeJsonMsg($value);
                        if (!is_null($jsonArray)) {
                            $this->updateJsonCmdValue($jsonArray);
                        }
                    }
                }
            }

            // Update listener on Eq (not Broker) at creation
            $this->listenerUpdate();

            // Send an event regarding this new cmd
            $this->eventNewCmd();
        }
        else { // the cmd has been updated

            // If retain mode changed
            if ($this->_preSaveInformations[jMQTTConst::CONF_KEY_RETAIN]
                != $this->getConfiguration(jMQTTConst::CONF_KEY_RETAIN, 0)) {

                // It's enabled now
                if ($this->getConfiguration(jMQTTConst::CONF_KEY_RETAIN, 0)) {
                    $eqLogic->log(
                        'info',
                        sprintf(
                            __("Mode retain activé sur la commande #%s#", __FILE__),
                            $this->getHumanName()
                        )
                    );
                } else {
                    //If broker eqpt is enabled
                    if ($eqLogic->getBroker()->getIsEnable()) {
                        // A null payload should be sent to the broker to erase the last retained value
                        // Otherwise, this last value remains retained at broker level
                        $eqLogic->log(
                            'info',
                            sprintf(
                                __("Mode retain désactivé sur la commande #%s#, effacement de la dernière valeur dans le Broker", __FILE__),
                                $this->getHumanName()
                            )
                        );
                        $eqLogic->publish(
                            $this->getHumanName(),
                            $this->getTopic(),
                            '',
                            1,
                            1
                        );
                    }
                }
            }

            // Only Update listener if "autoPub" or "request" has changed
            if (
                (
                    $this->_preSaveInformations[jMQTTConst::CONF_KEY_AUTOPUB]
                    != $this->getConfiguration(jMQTTConst::CONF_KEY_AUTOPUB, 0)
                ) || (
                    $this->_preSaveInformations[jMQTTConst::CONF_KEY_REQUEST]
                    != $this->getConfiguration(jMQTTConst::CONF_KEY_REQUEST, '')
                )
            ) {
                $this->listenerUpdate();
            }
        }

        // For info commands (on Equipments), log that the topic is compatible with the subscription command
        if ($this->getType() == 'info'
            && !$eqLogic->getCache(jMQTTConst::CACHE_IGNORE_TOPIC_MISMATCH, 0)
            && !$this->topicMatchesSubscription($eqLogic->getTopic())
        ) {
            $eqLogic->log(
                'warning',
                sprintf(
                    __("Le topic de la commande #%s# est incompatible avec le topic de souscription de l'équipement associé", __FILE__),
                    $this->getHumanName()
                )
            );
        }
    }

// Listener for autoPub action command
    public function listenerUpdate() {
        $cmds = array();
        $eq = $this->getEqLogic();
        if (
            $eq->getIsEnable()
            && $this->getType() == 'action'
            && $this->getConfiguration(jMQTTConst::CONF_KEY_AUTOPUB, 0)
        ) {
            preg_match_all(
                "/#([0-9]*)#/",
                $this->getConfiguration(jMQTTConst::CONF_KEY_REQUEST, ''),
                $matches
            );
            $cmds = array_unique($matches[1]);
        }
        $listener = listener::searchClassFunctionOption(
            __CLASS__,
            'listenerAction',
            '"cmd":"'.$this->getId().'"'
        );
        if (count($listener) == 0) { // No listener found
            $listener = null;
        } else {
            while (count($listener) > 1) {
                // Too many listener for this cmd, let's cleanup
                array_pop($listener)->remove();
            }
            // Get the last listener
            $listener = $listener[0];
        }
        // We need a listener
        if (count($cmds) > 0) {
            if (!is_object($listener))
                $listener = new listener();
            $listener->setClass(__CLASS__);
            $listener->setFunction('listenerAction');
            $listener->emptyEvent();
            foreach ($cmds as $cmd_id) {
                $cmd = cmd::byId($cmd_id);
                if (is_object($cmd) && $cmd->getType() == 'info')
                    $listener->addEvent($cmd_id);
            }
            $listener->setOption('cmd', $this->getId());
            $listener->setOption('eqLogic', $this->getEqLogic_id());
            $listener->setOption('background', true);
            $listener->save();
            $eq->log(
                'debug',
                sprintf(
                    __("Listener installé pour #%s#", __FILE__),
                    $this->getHumanName()
                )
            );
        } else { // We don't want a listener
            if (is_object($listener)) {
                $listener->remove();
                $eq->log(
                    'debug',
                    sprintf(
                        __("Listener supprimé pour #%s#", __FILE__),
                        $this->getHumanName()
                    )
                );
            }
        }
    }

    public static function listenerAction($_options) {
        $cmd = self::byId($_options['cmd']);
        if (
            !is_object($cmd)
            || !$cmd->getEqLogic()->getIsEnable()
            || !$cmd->getType() == 'action'
            || !$cmd->getConfiguration(jMQTTConst::CONF_KEY_AUTOPUB, 0)
        ) {
            listener::byId($_options['listener_id'])->remove();
            $cmd->getEqLogic()->log(
                'debug',
                sprintf(
                    __("Listener supprimé pour #%s#", __FILE__),
                    $_options['cmd']
                )
            );
        } else {
            $cmd->getEqLogic()->log(
                'debug',
                sprintf(
                    __("Publication automatique de #%s#", __FILE__),
                    $cmd->getHumanName()
                )
            );
            $cmd->execute();
        }
    }

    /**
     * preRemove method to log that a command is removed
     */
    public function preRemove() {
        $eqLogic = $this->getEqLogic();
        if ($eqLogic) {
            $eqLogic->log(
                'info',
                sprintf(
                    __("Suppression de la commande #%s#", __FILE__),
                    $this->getHumanName()
                )
            );
            // Remove battery status from eqLogic on delete
            if ($this->isBattery()) {
                $eqLogic->log(
                    'debug',
                    sprintf(
                        __("Suppression de la commande de Batterie de l'équipement #%s#", __FILE__),
                        $eqLogic->getHumanName()
                    )
                );
                $eqLogic->setConfiguration(jMQTTConst::CONF_KEY_BATTERY_CMD, '');
                $eqLogic->save();
            }
            // Remove availability status from eqLogic on delete
            if ($this->isAvailability()) {
                $eqLogic->log(
                    'debug',
                    sprintf(
                        __("Suppression de la commande de Disponibilité de l'équipement #%s#", __FILE__),
                        $eqLogic->getHumanName()
                    )
                );
                $eqLogic->setConfiguration(jMQTTConst::CONF_KEY_AVAILABILITY_CMD, '');
                $eqLogic->save();
            }
        } else {
            jMQTT::logger(
                'info',
                sprintf(
                    __("Suppression de la commande orpheline #%s# (%s)", __FILE__),
                    $this->getId(),
                    $this->getName()
                )
            );
        }
        $listener = listener::searchClassFunctionOption(
            __CLASS__,
            'listenerAction',
            '"cmd":"'.$this->getId().'"'
        );
        foreach ($listener as $l) {
            jMQTT::logger(
                'debug',
                sprintf(
                    __("Listener supprimé pour #%s#", __FILE__),
                    $l->getOption('cmd')
                )
            );
            $l->remove();
        }
    }

    public function setName($name) {
        // Since 3.3.22, the core removes / from command names
        $name = str_replace("/", ":", $name);
        parent::setName($name);
        return $this;
    }

    public function setTopic($topic) {
        $this->setConfiguration('topic', $topic);
    }

    public function getTopic() {
        return $this->getConfiguration('topic');
    }

    public function setJsonPath($jsonPath) {
        $this->setConfiguration(jMQTTConst::CONF_KEY_JSON_PATH, $jsonPath);
    }

    public function getJsonPath() {
        return $this->getConfiguration(jMQTTConst::CONF_KEY_JSON_PATH, '');
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
     * @return null|jMQTTCmd|array(jMQTTCmd)
     */
    public static function byEqLogicIdAndTopic($eqLogic_id, $topic, $multiple=false) {
        // JSON_UNESCAPED_UNICODE used to fix #92
        $confTopic = substr(
            json_encode(
                array('topic' => $topic),
                JSON_UNESCAPED_UNICODE
            ),
            1,
            -1
        );
        $confTopic = str_replace('\\', '\\\\', $confTopic);

        $values = array(
            'eqLogic_id' => $eqLogic_id,
            'topic' => '%' . $confTopic . '%',
        );
        $sql = 'SELECT ' . DB::buildField(__CLASS__);
        $sql .= ' FROM cmd WHERE eqLogic_id=:eqLogic_id AND configuration LIKE :topic';

        // Searching for only one topic
        if (!$multiple) {
            $values['emptyJsonPath'] = '%"jsonPath":""%';
            $values['allJsonPath'] = '%"jsonPath":"%';
            // Empty jsonPath or no jsonPath in config
            $sql .= ' AND (configuration LIKE :emptyJsonPath OR configuration NOT LIKE :allJsonPath)';
        }

        $cmds = DB::Prepare($sql, $values, DB::FETCH_TYPE_ALL, PDO::FETCH_CLASS, __CLASS__);
        if (count($cmds) == 0)
            return null;
        else
            return $multiple ? $cmds : $cmds[0];
    }

    /**
     * Return whether or not this command is the battery value
     * @return boolean
     */
    public function isBattery() {
        if ($this->getType() != 'info' || $this->getSubType() == 'string')
            return false;
        return $this->getId() == $this->getEqLogic()->getBatteryCmd();
    }

    /**
     * Return whether or not this command is the availability value
     * @return boolean
     */
    public function isAvailability() {
        if ($this->getType() != 'info' || $this->getSubType() != 'binary')
            return false;
        return $this->getId() == $this->getEqLogic()->getAvailabilityCmd();
    }

    /**
     * Return whether or not this command is derived from a Json payload
     * @return boolean
     */
    public function isJson() {
        return $this->getJsonPath() != '';
    }

    /**
     * Returns true if the topic of this command matches the given subscription description
     * @param string $subscription subscription to match
     * @return boolean
     */
    public function topicMatchesSubscription($subscription) {
        return mosquitto_topic_matches_sub($subscription, $this->getTopic());
    }


    /**
     * Converts HTML color value to XY values
     * Based on: http://stackoverflow.com/a/22649803
     *
     * @param string $_color HTML color
     * @return array x, y, bri key/value
     */
    public static function HTMLtoXY($_color) {

        $_color = str_replace('0x','', $_color);
        $_color = str_replace('#','', $_color);
        $red = hexdec(substr($_color, 0, 2));
        $green = hexdec(substr($_color, 2, 2));
        $blue = hexdec(substr($_color, 4, 2));

        // Normalize the values to 1
        $normalizedToOne['red'] = $red / 255;
        $normalizedToOne['green'] = $green / 255;
        $normalizedToOne['blue'] = $blue / 255;

        // Make colors more vivid
        $color = array();
        foreach ($normalizedToOne as $key => $normalized) {
            if ($normalized > 0.04045) {
                $color[$key] = pow(($normalized + 0.055) / (1.0 + 0.055), 2.4);
            } else {
                $color[$key] = $normalized / 12.92;
            }
        }

        // Convert to XYZ using the Wide RGB D65 formula
        $xyz['x'] = $color['red'] * 0.664511 + $color['green'] * 0.154324 + $color['blue'] * 0.162028;
        $xyz['y'] = $color['red'] * 0.283881 + $color['green'] * 0.668433 + $color['blue'] * 0.047685;
        $xyz['z'] = $color['red'] * 0.000000 + $color['green'] * 0.072310 + $color['blue'] * 0.986039;

        // Calculate the x/y values
        if (array_sum($xyz) == 0) {
            $x = 0;
            $y = 0;
        } else {
            $x = $xyz['x'] / array_sum($xyz);
            $y = $xyz['y'] / array_sum($xyz);
        }

        return array(
            'x' => $x,
            'y' => $y,
            'bri' => round($xyz['y'] * 255),
        );
    }

    /**
     * Converts XY (and brightness) values to RGB
     *
     * @param float $x X value
     * @param float $y Y value
     * @param int $bri Brightness value
     * @return string red, green, blue
     */
    public static function XYtoHTML($x, $y, $bri = 255) {
        // Calculate XYZ
        $z = 1.0 - $x - $y;
        $xyz['y'] = $bri / 255;
        $xyz['x'] = ($xyz['y'] / $y) * $x;
        $xyz['z'] = ($xyz['y'] / $y) * $z;
        // Convert to RGB using Wide RGB D65 conversion
        $color['r'] = $xyz['x'] * 1.656492 - $xyz['y'] * 0.354851 - $xyz['z'] * 0.255038;
        $color['g'] = -$xyz['x'] * 0.707196 + $xyz['y'] * 1.655397 + $xyz['z'] * 0.036152;
        $color['b'] = $xyz['x'] * 0.051713 - $xyz['y'] * 0.121364 + $xyz['z'] * 1.011530;
        $maxValue = 0;
        foreach ($color as $key => $normalized) {
            // Apply reverse gamma correction
            if ($normalized <= 0.0031308) {
                $color[$key] = 12.92 * $normalized;
            } else {
                $color[$key] = (1.0 + 0.055) * pow($normalized, 1.0 / 2.4) - 0.055;
            }
            $color[$key] = max(0, $color[$key]);
            if ($maxValue < $color[$key]) {
                $maxValue = $color[$key];
            }
        }
        foreach ($color as $key => $normalized) {
            if ($maxValue > 1) {
                $color[$key] /= $maxValue;
            }
            // Scale back from a maximum of 1 to a maximum of 255
            $color[$key] = round($color[$key] * 255);
        }
        return sprintf("#%02X%02X%02X", $color['r'], $color['g'], $color['b']);
    }

    /**
     * @param int|array $r
     * @param int $g
     * @param int $b
     * @return string
     */
    public static function RGBtoHTML($r, $g=-1, $b=-1) {
        if (is_array($r) && sizeof($r) == 3)
            list($r, $g, $b) = $r;

        $r = intval($r);
        $g = intval($g);
        $b = intval($b);

        $r = dechex($r<0?0:($r>255?255:$r));
        $g = dechex($g<0?0:($g>255?255:$g));
        $b = dechex($b<0?0:($b>255?255:$b));

        $color = (strlen($r) < 2?'0':'').$r;
        $color .= (strlen($g) < 2?'0':'').$g;
        $color .= (strlen($b) < 2?'0':'').$b;
        return '#'.$color;
    }

    /**
     * @param string $s
     * @return int
     */
    public static function HEXtoDEC($s) {
        $s = str_replace("#", "", $s);
        $output = 0;
        for ($i=0; $i<strlen($s); $i++) {
            $c = $s[$i]; // you don't need substr to get 1 symbol from string
            if ( ($c >= '0') && ($c <= '9') )
                $output = $output*16 + ord($c) - ord('0'); // two things: 1. multiple by 16 2. convert digit character to integer
            elseif ( ($c >= 'A') && ($c <= 'F') ) // care about upper case
                $output = $output*16 + ord($s[$i]) - ord('A') + 10; // note that we're adding 10
            elseif ( ($c >= 'a') && ($c <= 'f') ) // care about lower case
                $output = $output*16 + ord($c) - ord('a') + 10;
        }
        return $output;
    }

    /**
     * @param int $d
     * @return string
     */
    public static function DECtoHEX($d) {
        return("#".substr("000000".dechex($d),-6));
    }
}
