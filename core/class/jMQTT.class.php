<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/../../resources/mosquitto_topic_matches_sub.php';
require_once __DIR__ . '/jMQTTConst.class.php';
require_once __DIR__ . '/jMQTTCallbacks.class.php';
require_once __DIR__ . '/jMQTTDaemon.class.php';
require_once __DIR__ . '/jMQTTPlugin.class.php';
require_once __DIR__ . '/jMQTTComToDaemon.class.php';
require_once __DIR__ . '/jMQTTCmd.class.php';

// Load JsonPath-PHP library
if (file_exists(__DIR__ . '/../../resources/JsonPath-PHP/vendor/autoload.php'))
    require_once __DIR__ . '/../../resources/JsonPath-PHP/vendor/autoload.php';


class jMQTT extends eqLogic {
    /**
     * Data shared between preSave and postSave
     * @var null|array values from preSave used for postSave actions
     */
    private $_preSaveInformations;

    /**
     * Broker jMQTT object related to this object
     * @var null|jMQTT broker object
     */
    private $_broker;

    /**
     * Status command of the broker related to this object
     * @var null|jMQTTCmd
     */
    private $_statusCmd;

    /**
     * Connected command of the broker related to this object
     * @var null|jMQTTCmd
     */
    private $_connectedCmd;


    public static function templateRead($_file) {
        // read content from file without error handeling!
        $content = file_get_contents($_file);
        // decode template file content to json (or raise)
        $templateContent = json_decode($content, true);
        // first key is the template itself
        $templateKey = array_keys($templateContent)[0];
        // return tuple of name & value
        return [$templateKey, $templateContent[$templateKey]];
    }

    /**
     * Return a list of all templates name and file.
     *
     * @return array[] list of name and file array.
     */
    public static function templateList() {
        // self::logger('debug', 'templateList()');
        $return = array();
        // Get personal templates
        foreach (
            ls(
                __DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO,
                '*.json', false, array('files', 'quiet')
            ) as $file
        ) {
            try {
                [$templateKey, $templateValue] = self::templateRead(
                    __DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO . $file
                );
                $return[] = array(
                    '[Perso] '.$templateKey,
                    'plugins/jMQTT/' . jMQTTConst::PATH_TEMPLATES_PERSO . $file
                );
            } catch (Throwable $e) {
                self::logger(
                    'warning',
                    sprintf(
                        __("Erreur lors de la lecture du Template '%s'", __FILE__),
                        jMQTTConst::PATH_TEMPLATES_PERSO . $file
                    )
                );
            }
        }
        // Get official templates
        foreach (
            ls(
                __DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_JMQTT,
                '*.json', false, array('files', 'quiet')
            ) as $file
        ) {
            try {
                [$templateKey, $templateValue] = self::templateRead(
                    __DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_JMQTT . $file
                );
                $return[] = array(
                    $templateKey,
                    'plugins/jMQTT/' . jMQTTConst::PATH_TEMPLATES_JMQTT . $file
                );
            } catch (Throwable $e) {
                self::logger(
                    'warning',
                    sprintf(
                        __("Erreur lors de la lecture du Template '%s'", __FILE__),
                        jMQTTConst::PATH_TEMPLATES_JMQTT . $file
                    )
                );
            }
        }
        return $return;
    }

    /**
     * Return a template content (from json files).
     *
     * @param string $_name template name to look for
     * @return array Template as an array
     * @throws Exception if template in not readable
     */
    public static function templateByName($_name) {
        // self::logger('debug', 'templateByName: ' . $_name);
        if (strpos($_name , '[Perso] ') === 0) {
            // Get personal templates
            $name = substr($_name, strlen('[Perso] '));
            $folder = '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO;
        } else {
            // Get official templates
            $name = $_name;
            $folder = '/../../' . jMQTTConst::PATH_TEMPLATES_JMQTT;
        }
        $log = sprintf(
            __("Erreur lors de la lecture du Template '%s'", __FILE__),
            $_name
        );
        foreach (
            ls(
                __DIR__ . $folder,
                '*.json', false, array('files', 'quiet')
            ) as $file
        ) {
            try {
                [$templateKey, $templateValue] = self::templateRead(
                    __DIR__ . $folder . $file
                );
                if ($templateKey == $name)
                    return $templateValue;
            } catch (Throwable $e) {
            }
        }
        self::logger('warning', $log);
        throw new Exception($log);
    }

    /**
     * Return one templates content (from json file name).
     * @param string $_filename template name to look for
     * @return array
     */
    public static function templateByFile($_filename = ''){
        // self::logger('debug', 'templateByFile: ' . $_filename);
        $existing_files = self::templateList();
        $exists = false;
        foreach ($existing_files as list($n, $f))
            if ($f == $_filename) {
                $exists = true;
                break;
            }
        if (!$exists)
            throw new Exception(
                __("Le template demandé n'existe pas !", __FILE__)
            );
        // self::logger('debug', '    get='.__DIR__ . '/../../../../' . $_filename);
        try {
            [$templateKey, $templateValue] = self::templateRead(
                __DIR__ . '/../../../../' . $_filename
            );
            return $templateValue;
        } catch (Throwable $e) {
            throw new Exception(
                sprintf(
                    __("Erreur lors de la lecture du Template '%s'", __FILE__),
                    $_filename
                )
            );
        }
    }

    /**
     * Split topic and jsonPath of all commands for the template file.
     *
     * @param string $_filename template name to look for.
     * @throws Exception if template in not readable
     */
    public static function moveTopicToConfigurationByFile($_filename = '') {

        try {
            [$templateKey, $templateValue] = self::templateRead(
                __DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO . $_filename
            );

            // if 'configuration' key exists in this template
            if (isset($templateValue['configuration'])) {

                // if auto_add_cmd doesn't exists in configuration, we need to move topic from logicalId to configuration
                if (!isset($templateValue['configuration'][jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC])) {
                    $topic = $templateValue['logicalId'];
                    $templateValue['configuration'][jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC] = $topic;
                    $templateValue['logicalId'] = '';
                }
            }

            // Save back template in the file
            $jsonExport = json_encode(
                array($templateKey=>$templateValue),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
            file_put_contents(
                __DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO . $_filename,
                $jsonExport
            );
        } catch (Throwable $e) {
            throw new Exception(
                sprintf(
                    __("Erreur lors de la lecture du Template '%s'", __FILE__),
                    $_filename
                )
            );
        }
    }

    /**
     * Delete user defined template by filename.
     *
     * @param string $_filename template file name to look for.
     * @return bool success
     */
    public static function deleteTemplateByFile($_filename){
        // self::logger('debug', 'deleteTemplateByFile: ' . $_filename);
        /** @var null|string $_filename */
        if (
            !isset($_filename)
            || is_null($_filename) // @phpstan-ignore-line
            || $_filename == ''
        ) {
            return false;
        }
        $existing_files = self::templateList();
        $exists = false;
        foreach ($existing_files as list($n, $f))
            if ($f == $_filename) {
                $exists = true;
                break;
            }
        if (!$exists)
            return false;
        return unlink(__DIR__ . '/../../../../' . $_filename);
    }

    /**
     * Apply a template (from json) to the current equipement.
     *
     * @param array $_template content of the template to apply
     * @param string $_baseTopic subscription topic
     * @param bool $_keepCmd keep existing commands
     */
    public function applyATemplate($_template, $_baseTopic, $_keepCmd = true){
        if (
            $this->getType() != jMQTTConst::TYP_EQPT
            || is_null($_template) // @phpstan-ignore-line
        ) {
            return;
        }

        // Cleanup base topic (remove '/', '#' and '+' at the end)
        if (
            substr($_baseTopic, -1) == '#'
            || substr($_baseTopic, -1) == '+'
        ) {
            $_baseTopic = substr($_baseTopic, 0, -1);
        }
        if (substr($_baseTopic, -1) == '/') {
            $_baseTopic = substr($_baseTopic, 0, -1);
        }

        // Raise up the flag that cmd topic mismatch must be ignored
        $this->setCache(jMQTTConst::CACHE_IGNORE_TOPIC_MISMATCH, 1);

        // import template
        $this->import($_template, $_keepCmd);

        // Ensure topic has a wildcard at the end
        $mainTopic = sprintf(
            $_template['configuration'][jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC],
            $_baseTopic
        );
        if (
            substr($mainTopic, -1) != '/'
            && substr($mainTopic, -1) != '#'
            && substr($mainTopic, -1) != '+'
        ) {
             $mainTopic .= '/';
        }
        if (
            substr($mainTopic, -1) != '#'
            && substr($mainTopic, -1) != '+'
        ) {
            $mainTopic .= '#';
        }
        $this->setTopic($mainTopic);
        $this->save();

        // Create a replacement array with cmd names & id for further use
        $cmdsId = array();
        $cmdsName = array();
        foreach ($this->getCmd() as $cmd) {
            $cmdsId[] = '#' . $cmd->getId() . '#';
            $cmdsName[] = '#[' . $cmd->getName() . ']#';
            // Update battery linked info command
            if ($this->getConf(jMQTTConst::CONF_KEY_BATTERY_CMD) == $cmd->getName())
                $this->setConfiguration(jMQTTConst::CONF_KEY_BATTERY_CMD, $cmd->getId());
            // Update availability linked info command
            if ($this->getConf(jMQTTConst::CONF_KEY_AVAILABILITY_CMD) == $cmd->getName())
                $this->setConfiguration(jMQTTConst::CONF_KEY_AVAILABILITY_CMD, $cmd->getId());
        }
        if (
            $this->getConf(jMQTTConst::CONF_KEY_BATTERY_CMD) != ""
            || $this->getConf(jMQTTConst::CONF_KEY_AVAILABILITY_CMD) != ""
        ) {
            $this->save();
        }

        /** @var jMQTTCmd $cmd */
        // complete cmd topics and replace template cmd names by cmd ids
        foreach ($this->getCmd() as $cmd) {
            $cmd->setTopic(sprintf($cmd->getTopic(), $_baseTopic));
            $cmd->replaceCmdIds($cmdsName, $cmdsId);
            $cmd->save();
        }

        // remove topic mismatch ignore flag
        $this->setCache(jMQTTConst::CACHE_IGNORE_TOPIC_MISMATCH, 0);
    }

    /**
     * create a template from the current equipement (to json).
     * @param string $_tName name of the template to create
     */
    public function createTemplate($_tName) {

        if ($this->getType() != jMQTTConst::TYP_EQPT)
            return true;

        // Cleanup template name
        $_tName = ucfirst(str_replace('  ', ' ', trim($_tName)));
        $_tName = preg_replace('/[^a-zA-Z0-9 ()_-]+/', '', $_tName);

        // Export
        $exportedTemplate[$_tName] = $this->export();
        $exportedTemplate[$_tName]['name'] = $_tName;

        // Remove brkId from eqpt configuration
        unset($exportedTemplate[$_tName]['configuration'][jMQTTConst::CONF_KEY_BRK_ID]);

        // TODO: Remove core4.2 backward compatibility for `cmd` in templates
        //  Remove when Jeedom 4.2 is no longer supported
        //  Older version of Jeedom (4.2.6 and bellow) export commands in 'cmd'
        //  Fixed here : https://github.com/jeedom/core/commit/05b8ecf34b405d5a0a0bb7356f8e3ecb1cf7fa91
        //  labels: workarround, core4.2, php
        if (isset($exportedTemplate[$_tName]['cmd'])) {
            // Rename 'cmd' to 'commands' for Jeedom import ...
            $exportedTemplate[$_tName]['commands'] = $exportedTemplate[$_tName]['cmd'];
            unset($exportedTemplate[$_tName]['cmd']);
        }

        // Create a replacement array with cmd names & id for further use
        $cmdsId = array();
        $cmdsName = array();
        /** @var jMQTTCmd $cmd */
        foreach ($this->getCmd() as $cmd) {
            $cmdsId[] = '#' . $cmd->getId() . '#';
            $cmdsName[] = '#[' . $cmd->getName() . ']#';
            // Update battery linked info command
            if ($cmd->isBattery())
                $exportedTemplate[$_tName]['configuration'][
                    jMQTTConst::CONF_KEY_BATTERY_CMD
                ] = $cmd->getName();
            // Update availability linked info command
            if ($cmd->isAvailability())
                $exportedTemplate[$_tName]['configuration'][
                    jMQTTConst::CONF_KEY_AVAILABILITY_CMD
                ] = $cmd->getName();
        }

        // Convert and save to file
        $jsonExport = json_encode(
            $exportedTemplate,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Convert relative cmd id to '#[Name]#' format in request
        $jsonExport = str_replace($cmdsId, $cmdsName, $jsonExport);

        // Looking for baseTopic from equipement
        $baseTopic = $this->getTopic();
        if (substr($baseTopic, -1) == '#' || substr($baseTopic, -1) == '+') {
            $baseTopic = substr($baseTopic, 0, -1);
        }
        if (substr($baseTopic, -1) == '/') {
            $baseTopic = substr($baseTopic, 0, -1);
        }

        // Convert topic to string format
        if ($baseTopic != '') {
            $toReplace = array(
                '"'.jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC.'": "'.$baseTopic,
                '"topic": "'.$baseTopic
            );
            $replaceBy = array(
                '"'.jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC.'": "%s',
                '"topic": "%s'
            );
            $jsonExport = str_replace($toReplace, $replaceBy, $jsonExport);
        }

        // Write template file
        file_put_contents(
            __DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO . str_replace(' ', '_', $_tName) . '.json',
            $jsonExport
        );
    }

    /**
     * Create a new equipment given its name, subscription topic and
     *   broker the equipment is related to.
     * IMPORTANT: broker can be null (or empty) and then, this is the
     *   responsability of the caller to attach the new equipment to a broker.
     *   Equipment is enabled, and saved.
     *
     * @param jMQTT $broker broker the equipment is related to
     * @param string $name equipment name
     * @param string $topic subscription topic
     * @return jMQTT new object
     */
    public static function createEquipment($broker, $name, $topic) {
        $eqpt = new jMQTT();
        $eqpt->setName($name);
        $eqpt->setIsEnable(1);
        $eqpt->setTopic($topic);

        if (is_object($broker)) {
            $broker->log(
                'info',
                sprintf(
                    __("Création de l'équipement %1\$s pour le topic %2\$s", __FILE__),
                    $name,
                    $topic
                )
            );
            $eqpt->setBrkId($broker->getId());
        }
        $eqpt->save();

        // Advise the desktop page (jMQTT.js) that a new equipment has been added
        event::add('jMQTT::eqptAdded', array('eqlogic_name' => $name));

        return $eqpt;
    }

    /**
     * Create a new equipment given its name, subscription topic and
     *   broker the equipment is related to.
     * IMPORTANT:
     *   brk_addr can be null (or empty) and then, it is the responsability
     *   of the caller to attach the new equipment to a broker.
     *   If a command already exists with the same logicalId,
     *   then it will be kept and updated, otherwise a new cmd will be created.
     *   Equipment is enabled, and saved.
     *
     * @param null|string $brk_addr is the IP/hostname of an EXISTING broker
     * @param string $name of the new equipment to create
     * @param string $template_path to the template json file
     * @param string $topic is the subscription base topic to apply to the template file
     * @param string $uuid is a unique ID provided at creation time to enable this equipment to be found later on
     * @return jMQTT object of a new eqLogic or an existing one if matched
     * @throws Exception is Broker could not be found
     */
    public static function createEqWithTemplate($brk_addr, $name, $template_path, $topic, $uuid = null) {
        // self::logger('debug', 'createEqWithTemplate: name=' . $name . ', brk_addr=' . $brk_addr .
        //              ', topic=' . $topic . ', template_path=' . $template_path . ', uuid=' . $uuid);
        // Check if file is in Jeedom directory and exists
        if (strpos(realpath($template_path), getRootPath()) === false)
            throw new Exception(__("Le fichier template est en-dehors de Jeedom.", __FILE__));
        if (!file_exists($template_path))
            throw new Exception(__("Le fichier template n'a pas pu être trouvé.", __FILE__));

        // Locate the expected broker, if not found then raise !
        $brk_addr = (is_null($brk_addr) || $brk_addr == '') ? '127.0.0.1' : gethostbyname($brk_addr);
        $broker = null;

        foreach (self::getBrokers() as $brk) {
            $ip = gethostbyname($brk->getConf(jMQTTConst::CONF_KEY_MQTT_ADDRESS));
            if ($ip == $brk_addr || (substr($ip, 0, 4) == '127.' && substr($brk_addr, 0, 4) == '127.')) {
                $broker = $brk;
                self::logger(
                    'debug',
                    sprintf(
                        "createEqWithTemplate %s: Broker #%s# has been found.",
                        $name,
                        $broker->getName()
                    )
                );
                break;
            }
        }

        if (!is_object($broker))
            throw new Exception(
                __("Aucun Broker n'a pu être identifié, créez un Broker dans jMQTT avant de créer un équipement.", __FILE__)
            );

        $eq = null;

        // Try to locate the Eq is uuid is provided
        if (!is_null($uuid)) {
            // Search for a jMQTT Eq with $uuid, if found apply template to it
            $type = json_encode(array(jMQTTConst::CONF_KEY_TEMPLATE_UUID => $uuid));
            $eqpts = self::byTypeAndSearchConfiguration(__CLASS__, substr($type, 1, -1));
            foreach ($eqpts as $eqpt) {
                // If it's attached to correct broker
                if ($eqpt->getBrkId() == $broker->getId()) {
                    self::logger(
                        'debug',
                        sprintf(
                            "createEqWithTemplate %s: Eq #%s# has been found.",
                            $name,
                            $eqpt->getHumanName()
                        )
                    );
                    $eq = $eqpt;
                    break;
                }
                self::logger(
                    'debug',
                    sprintf(
                        "createEqWithTemplate %s: Eq #%s# was found with this UUID, but on wrong Broker.",
                        $name,
                        $eqpt->getHumanName()
                    )
                );
            }
            if (is_null($eq))
                self::logger(
                    'debug',
                    sprintf(
                        "createEqWithTemplate %s: Impossible to find an Eq corresponding to the UUID on this Broker.",
                        $name
                    )
                );
        }
        // If the Eq is not located create it
        if (is_null($eq)) {
            $eq = self::createEquipment($broker, $name, $topic);
            self::logger(
                'debug',
                sprintf(
                    "createEqWithTemplate %s: New equipment created.",
                    $name
                )
            );
            if (!is_null($uuid)) {
                $eq->setConfiguration(jMQTTConst::CONF_KEY_TEMPLATE_UUID, $uuid);
                $eq->save();
            }
        }

        // Get template content from file
        try {
            [$templateKey, $templateValue] = self::templateRead($template_path);
        } catch (Throwable $e) {
            throw new Exception(
                sprintf(
                    __('Erreur lors de la lecture du ficher Template %s', __FILE__),
                    $template_path
                )
            );
        }

        // Apply the template
        $eq->applyATemplate($templateValue, $topic, true);

        // Return the Eq with the applied template
        return $eq;
    }

    /**
     * Overload the equipment copy method
     * All information are copied BUT:
     *   suscribed topic (left empty),
     *   enable status (left disabled), and
     *   information commands.
     *
     * @param string $_name new equipment name
     * @return jMQTT copied and disociated eqLogic
     */
    public function copy($_name) {

        $this->log(
            'info',
            sprintf(
                __("Copie de l'équipement %1\$s depuis l'équipement #%2\$s#", __FILE__),
                $_name,
                $this->getHumanName()
            )
        );

        // Clone the equipment and change properties that shall be changed
        // . new id will be given at saving
        // . suscribing topic let empty to force the user to change it
        // . remove commands: they are defined at the next step (as done in the parent method)
        /** @var jMQTT $eqLogicCopy */
        $eqLogicCopy = clone $this;
        $eqLogicCopy->setId('');
        $eqLogicCopy->setName($_name);
        if ($eqLogicCopy->getIsEnable()) {
            $eqLogicCopy->setIsEnable(0);
        }
        foreach ($eqLogicCopy->getCmd() as $cmdCopy) {
            $cmdCopy->remove();
        }
        $eqLogicCopy->save(); // Needed here to get an Id

        $cmdsNameList = array();
        $cmdsOldId = array();
        $cmdsNewId = array();
        // Clone commands
        /** @var jMQTTCmd $cmd */
        foreach ($this->getCmd() as $cmd) {
            $cmdCopy = clone $cmd;
            $cmdCopy->setId('');
            $cmdCopy->setEqLogic_id($eqLogicCopy->getId());
            $cmdCopy->setEqLogic($eqLogicCopy);
            if ($cmd->getType() == 'action') { // Replace linked info cmd Id by its Name
                $cmdValue = $cmd->getCmdValue();
                $cmdCopy->setValue(is_object($cmdValue) ? $cmdValue->getName() : '');
            }
            $cmdCopy->save(); // Needed here to get an Id

            // Gather mapping data
            $cmdsNameList[$cmdCopy->getName()] = $cmdCopy->getId();
            // Store all cmd names -> id for further usage
            $cmdsOldId[] = '#' . $cmd->getId() . '#';
            $cmdsNewId[] = '#' . $cmdCopy->getId() . '#';

            // Update battery linked info command
            if ($cmd->isBattery()) {
                $eqLogicCopy->setConfiguration(
                    jMQTTConst::CONF_KEY_BATTERY_CMD,
                    $cmdCopy->getId()
                );
            }

            // Update availability linked info command
            if ($cmd->isAvailability()) {
                $eqLogicCopy->setConfiguration(
                    jMQTTConst::CONF_KEY_AVAILABILITY_CMD,
                    $cmdCopy->getId()
                );
            }
            $this->log('info', sprintf(
                __("Copie de la commande %1\$s #%2\$s# vers la commande #%3\$s#", __FILE__),
                $cmd->getType(),
                $cmd->getHumanName(),
                $cmdCopy->getHumanName()
            ));
        }
        if ($eqLogicCopy->getConf(jMQTTConst::CONF_KEY_BATTERY_CMD) != ""
            || $eqLogicCopy->getConf(jMQTTConst::CONF_KEY_AVAILABILITY_CMD) != "")
            $eqLogicCopy->save();

        foreach ($eqLogicCopy->getCmd() as $cmdCopy) {
            if ($cmdCopy->getType() != 'action') // Only on action cmds
                continue;
            // Update linked info cmd
            if ($cmdCopy->getValue() != '')
                $cmdCopy->setValue($cmdsNameList[$cmdCopy->getValue()]);
            // Update relative (to eqLogic) info cmd in action cmd payload
            $request = $cmdCopy->getConfiguration(jMQTTConst::CONF_KEY_REQUEST, "");
            $cmdCopy->setConfiguration(
                jMQTTConst::CONF_KEY_REQUEST,
                str_replace($cmdsOldId, $cmdsNewId, $request)
            );
            $cmdCopy->save();
        }
        return $eqLogicCopy;
    }

    /**
     * Return a full export (inc. commands) of all eqLogic as an array starting by Brokers.
     *
     * @param boolean $clean irrelevant values to Daemon must be removed from the return
     * @return array representing the eqLogic and its cmd
     */
    public static function full_export($clean=false) {
        $brks = array();
        $brks_id = array();
        $eqpts = array();
        $cmds = array();
        foreach (eqLogic::byType(__CLASS__) as $eq) {
            /** @var jMQTT $eq */
            $eqar = utils::o2a($eq, true);
            // $eqar['cache'] = $eq->getCache();
            if (is_object($eq->getObject())) {
                $obj_name = $eq->getObject()->getName();
            } else {
                $obj_name = __('Aucun', __FILE__);
            }
            $eqar['name'] = $obj_name.':'.$eqar['name'];
            if ($clean) { // Remove unneeded informations
                unset($eqar['category']);
                unset($eqar['configuration']['battery_type']);
                unset($eqar['configuration']['createtime']);
                unset($eqar['configuration']['commentaire']);
                unset($eqar['configuration']['updatetime']);
                unset($eqar['comment']);
                unset($eqar['display']);
                unset($eqar['isVisible']);
                unset($eqar['object_id']);
                unset($eqar['tags']);
                unset($eqar['timeout']);
            }
            if ($eqar['configuration']['type'] == jMQTTConst::TYP_BRK) {
                $brks[] = $eqar;
                $brks_id[] = $eq->getId();
            } else {
                $eqpts[] = $eqar;
            }
            /** @var jMQTTCmd $cmd */
            foreach ($eq->getCmd() as $cmd) {
                // Do not send cmds of eqBroker, as used only inside Jeedom
                if (!$clean || !in_array($cmd->getEqLogic_id(), $brks_id))
                    $cmds[] = $cmd->full_export($clean);
            }
        }

        return array_merge($brks, $eqpts, $cmds);
    }

    /**
     * Return jMQTT objects of type broker
     *
     * @return jMQTT[] array of eqBroker
     */
    public static function getBrokers() {
        /** @var jMQTT[] $brokers */
        $brokers = self::byTypeAndSearchConfiguration(
            __CLASS__,
            substr(
                json_encode(array('type' => jMQTTConst::TYP_BRK)),
                1,
                -1
            )
        );
        $returns = array();

        foreach ($brokers as $broker) {
            $returns[$broker->getId()] = $broker;
        }

        return $returns;
    }

    /**
     * Return jMQTT objects of type standard equipement
     *
     * @return jMQTT[][] array of arrays of jMQTT eqLogic objects
     */
    public static function getNonBrokers() {
        /** @var jMQTT[] $eqls */
        $eqls = self::byType(__CLASS__);
        $returns = array();

        foreach ($eqls as $eql) {
            if ($eql->getType() != jMQTTConst::TYP_BRK) {
                $returns[$eql->getBrkId()][] = $eql;
            } elseif (!isset($returns[$eql->getBrkId()])) {
                $returns[$eql->getBrkId()] = array();
            }
        }

        return $returns;
    }

    /**
     * Overload preSave to apply some checks/initialization and prepare postSave
     */
    public function preSave() {
        // Check Type: No Type => jMQTTConst::TYP_EQPT
        if ($this->getType() != jMQTTConst::TYP_BRK && $this->getType() != jMQTTConst::TYP_EQPT) {
            $this->setType(jMQTTConst::TYP_EQPT);
        }

        // Check eqType_name: should be __CLASS__
        if ($this->eqType_name != __CLASS__) {
            $this->setEqType_name(__CLASS__);
        }

        // ------------------------ New or Existing Broker eqpt ------------------------
        if ($this->getType() == jMQTTConst::TYP_BRK) {
            // Check for a broker eqpt with the same name (which is not this)
            foreach (self::getBrokers() as $broker) {
                if (
                    $broker->getName() == $this->getName()
                    && $broker->getId() != $this->getId()
                ) {
                    throw new Exception(
                        sprintf(
                            __("Le Broker #%1\$s# porte déjà le même nom (#%2\$s#)", __FILE__),
                            $broker->getId(),
                            $broker->getName()
                        )
                    );
                }
            }

            // TODO: Test in `preSave()` if provided certificates are OK
            //  labels: enhancement, php
            // jMQTTConst::CONF_KEY_MQTT_TLS_CHECK
            // jMQTTConst::CONF_KEY_MQTT_TLS_CA
            // jMQTTConst::CONF_KEY_MQTT_TLS_CLI_CERT
            // jMQTTConst::CONF_KEY_MQTT_TLS_CLI_KEY

            // Cleanup Broker from Equipment config keys
            $toRemove = array(
                jMQTTConst::CONF_KEY_AUTO_ADD_CMD,
                jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC,
                jMQTTConst::CONF_KEY_AVAILABILITY_CMD,
                jMQTTConst::CONF_KEY_BATTERY_CMD,
                jMQTTConst::CONF_KEY_BRK_ID,
                jMQTTConst::CONF_KEY_ICON,
                jMQTTConst::CONF_KEY_QOS
            );
            foreach ($toRemove as $key) {
                $this->setConfiguration($key, null);
            }
        }
        // ------------------------ New or Existing Normal eqpt ------------------------
        else {
            try {
                // Try to get Broker from id (will raise if bad or absent)
                self::getBrokerFromId(
                    $this->getConf(jMQTTConst::CONF_KEY_BRK_ID)
                );
            } catch (Throwable $e) {
                throw new Exception(
                    sprintf(
                        __('Erreur sur le Broker sélectionné: %s', __FILE__),
                        $e->getMessage()
                    )
                );
            }

            // Cleanup Equipment from Broker config keys
            $toRemove = array(
                jMQTTConst::CONF_KEY_LOGLEVEL,
                jMQTTConst::CONF_KEY_MQTT_PROTO,
                jMQTTConst::CONF_KEY_MQTT_ADDRESS,
                jMQTTConst::CONF_KEY_MQTT_PORT,
                jMQTTConst::CONF_KEY_MQTT_WS_URL,
                jMQTTConst::CONF_KEY_MQTT_USER,
                jMQTTConst::CONF_KEY_MQTT_PASS,
                jMQTTConst::CONF_KEY_MQTT_ID,
                jMQTTConst::CONF_KEY_MQTT_ID_VALUE,
                jMQTTConst::CONF_KEY_MQTT_LWT,
                jMQTTConst::CONF_KEY_MQTT_LWT_TOPIC,
                jMQTTConst::CONF_KEY_MQTT_LWT_ONLINE,
                jMQTTConst::CONF_KEY_MQTT_LWT_OFFLINE,
                jMQTTConst::CONF_KEY_MQTT_TLS_CHECK,
                jMQTTConst::CONF_KEY_MQTT_TLS_CA,
                jMQTTConst::CONF_KEY_MQTT_TLS_CLI,
                jMQTTConst::CONF_KEY_MQTT_TLS_CLI_CERT,
                jMQTTConst::CONF_KEY_MQTT_TLS_CLI_KEY,
                jMQTTConst::CONF_KEY_MQTT_INT,
                jMQTTConst::CONF_KEY_MQTT_INT_TOPIC,
                jMQTTConst::CONF_KEY_MQTT_API,
                jMQTTConst::CONF_KEY_MQTT_API_TOPIC
            );
            foreach ($toRemove as $key) {
                $this->setConfiguration($key, null);
            }
        }

        // ------------------------ New or Existing Broker or Normal eqpt ------------------------
        foreach (eqLogic::byType(__CLASS__) as $eqpt) {
            if (
                $eqpt->getHumanName() == $this->getHumanName()
                && $eqpt->getId() != $this->getId()
            ) {
                if ($this->getType() == jMQTTConst::TYP_BRK) {
                    $msg = __("Le Broker #%1\$s# porte déjà le même nom (#%2\$s#)", __FILE__);
                } else {
                    $msg = __("L'équipement #%1\$s# porte déjà le même nom (#%2\$s#)", __FILE__);
                }
                throw new Exception(
                    sprintf($msg, $eqpt->getId(), $eqpt->getHumanName())
                );
            }
        }

        // It's time to gather informations that will be used in postSave
        $this->_preSaveInformations = null;

        // load current eqLogic from DB
        /** @var jMQTT $eqLogic */
        $eqLogic = self::byId($this->getId());
        // If existing eqpt
        if (is_object($eqLogic)) {
            $this->_preSaveInformations = array();
            $this->_preSaveInformations['name'] = $eqLogic->getName();
            $this->_preSaveInformations['object'] = $eqLogic->getObject_id();
            $this->_preSaveInformations['isEnable'] = $eqLogic->getIsEnable();
            $saveMe = array(
                // Broker config keys
                jMQTTConst::CONF_KEY_LOGLEVEL,
                jMQTTConst::CONF_KEY_MQTT_PROTO,
                jMQTTConst::CONF_KEY_MQTT_ADDRESS,
                jMQTTConst::CONF_KEY_MQTT_PORT,
                jMQTTConst::CONF_KEY_MQTT_WS_URL,
                jMQTTConst::CONF_KEY_MQTT_USER,
                jMQTTConst::CONF_KEY_MQTT_PASS,
                jMQTTConst::CONF_KEY_MQTT_ID,
                jMQTTConst::CONF_KEY_MQTT_ID_VALUE,
                jMQTTConst::CONF_KEY_MQTT_LWT,
                jMQTTConst::CONF_KEY_MQTT_LWT_TOPIC,
                jMQTTConst::CONF_KEY_MQTT_LWT_ONLINE,
                jMQTTConst::CONF_KEY_MQTT_LWT_OFFLINE,
                jMQTTConst::CONF_KEY_MQTT_TLS_CHECK,
                jMQTTConst::CONF_KEY_MQTT_TLS_CA,
                jMQTTConst::CONF_KEY_MQTT_TLS_CLI,
                jMQTTConst::CONF_KEY_MQTT_TLS_CLI_CERT,
                jMQTTConst::CONF_KEY_MQTT_TLS_CLI_KEY,
                jMQTTConst::CONF_KEY_MQTT_INT,
                jMQTTConst::CONF_KEY_MQTT_INT_TOPIC,
                jMQTTConst::CONF_KEY_MQTT_API,
                jMQTTConst::CONF_KEY_MQTT_API_TOPIC,
                // Equipment config keys
                jMQTTConst::CONF_KEY_BRK_ID,
                jMQTTConst::CONF_KEY_BATTERY_CMD,
                jMQTTConst::CONF_KEY_AVAILABILITY_CMD,
                jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC,
                // jMQTTConst::CONF_KEY_AUTO_ADD_CMD,
                jMQTTConst::CONF_KEY_QOS
            );
            foreach ($saveMe as $key) {
                $this->_preSaveInformations[$key] = $eqLogic->getConf($key);
            }
        }
    }

    /**
     * postSave apply changes to MqttClient and log
     */
    public function postSave() {
        $sendUpdate = false;

        // ------------------------ Broker eqpt ------------------------
        if ($this->getType() == jMQTTConst::TYP_BRK) {

            // --- New broker ---
            if (is_null($this->_preSaveInformations)) {

                // Create log of this broker
                config::save(
                    'log::level::' . $this->getMqttClientLogFile(),
                    '{"100":"0","200":"0","300":"0","400":"0","1000":"0","default":"1"}',
                    __CLASS__
                );

                // Create status and connected cmds
                $this->getMqttClientStatusCmd(true);
                $this->getMqttClientConnectedCmd(true);

                $sendUpdate = true;
            }
            // --- Existing broker ---
            else {
                // isEnable changed
                if ($this->_preSaveInformations['isEnable'] != $this->getIsEnable()) {
                    if ($this->getIsEnable()) {
                        // Force current status to offline
                        $this->getMqttClientStatusCmd(true)->event(
                            jMQTTConst::CLIENT_STATUS_OFFLINE
                        );
                        // Force current connected to 0
                        $this->getMqttClientConnectedCmd(true)->event(0);
                        $this->setStatus('warning', 1); // And a warning
                    }
                    $sendUpdate = true;
                }
                // LogLevel change
                if (
                    $this->_preSaveInformations[jMQTTConst::CONF_KEY_LOGLEVEL]
                    != $this->getConf(jMQTTConst::CONF_KEY_LOGLEVEL)
                ) {
                    config::save(
                        'log::level::' . $this->getMqttClientLogFile(),
                        $this->getConf(jMQTTConst::CONF_KEY_LOGLEVEL),
                        __CLASS__
                    );
                }
                // Name changed
                if ($this->_preSaveInformations['name'] != $this->getName()) {
                    $old_log = __CLASS__ . '_' . str_replace(' ', '_', $this->_preSaveInformations['name']);
                    $new_log = $this->getMqttClientLogFile();
                    if (file_exists(log::getPathToLog($old_log))) {
                        rename(log::getPathToLog($old_log), log::getPathToLog($new_log));
                    }
                    config::save(
                        'log::level::' . $new_log,
                        config::byKey('log::level::' . $old_log, __CLASS__),
                        __CLASS__
                    );
                    config::remove('log::level::' . $old_log, __CLASS__);
                    $sendUpdate = true;
                }

                // Check other changes that would trigger MQTT Client update
                $checkChanged = array(
                    jMQTTConst::CONF_KEY_MQTT_PROTO,
                    jMQTTConst::CONF_KEY_MQTT_ADDRESS,
                    jMQTTConst::CONF_KEY_MQTT_PORT,
                    jMQTTConst::CONF_KEY_MQTT_WS_URL,
                    jMQTTConst::CONF_KEY_MQTT_USER,
                    jMQTTConst::CONF_KEY_MQTT_PASS,
                    jMQTTConst::CONF_KEY_MQTT_ID,
                    jMQTTConst::CONF_KEY_MQTT_ID_VALUE,
                    jMQTTConst::CONF_KEY_MQTT_LWT,
                    jMQTTConst::CONF_KEY_MQTT_LWT_TOPIC,
                    jMQTTConst::CONF_KEY_MQTT_LWT_ONLINE,
                    jMQTTConst::CONF_KEY_MQTT_LWT_OFFLINE,
                    jMQTTConst::CONF_KEY_MQTT_TLS_CHECK,
                    jMQTTConst::CONF_KEY_MQTT_TLS_CA,
                    jMQTTConst::CONF_KEY_MQTT_TLS_CLI,
                    jMQTTConst::CONF_KEY_MQTT_TLS_CLI_CERT,
                    jMQTTConst::CONF_KEY_MQTT_TLS_CLI_KEY,
                    jMQTTConst::CONF_KEY_MQTT_INT,
                    jMQTTConst::CONF_KEY_MQTT_INT_TOPIC,
                    jMQTTConst::CONF_KEY_MQTT_API,
                    jMQTTConst::CONF_KEY_MQTT_API_TOPIC
                );
                foreach ($checkChanged as $key) {
                    if ($this->_preSaveInformations[$key] != $this->getConf($key)) {
                        $sendUpdate = true;
                        break;
                    }
                }
            }

            // In the end, does Daemon data need to be updated
            if ($sendUpdate) {
                $data = utils::o2a($this, true);
                $data['cache'] = $this->getCache();
                if (is_object($this->getObject())) {
                    $obj_name = $this->getObject()->getName();
                } else {
                    $obj_name = __('Aucun', __FILE__);
                }
                $data['name'] = $obj_name.':'.$data['name'];
                // Do some cleanup
                unset($data['category']);
                unset($data['configuration']['battery_type']);
                unset($data['configuration']['createtime']);
                unset($data['configuration']['commentaire']);
                unset($data['configuration']['updatetime']);
                unset($data['comment']);
                unset($data['display']);
                unset($data['isVisible']);
                unset($data['object_id']);
                unset($data['status']);
                unset($data['tags']);
                unset($data['timeout']);
                // Send update of eqLogic (and only eqLogic) to Daemon
                jMQTTComToDaemon::brokerSet($data);
            }
        }
        // ------------------------ Normal eqpt ------------------------
        else {

            // --- New eqpt ---
            if (is_null($this->_preSaveInformations)) {
                $sendUpdate = true;
            }
            // --- Existing eqpt ---
            else {

                // isEnable changed
                if ($this->_preSaveInformations['isEnable'] != $this->getIsEnable()) {
                    if ($this->getIsEnable()) {
                        $this->listenersAdd();
                    } else {
                        $this->listenersRemove();
                    }
                    $sendUpdate = true;
                }

                // brkId changed
                if (
                    $this->_preSaveInformations[jMQTTConst::CONF_KEY_BRK_ID]
                    != $this->getConf(jMQTTConst::CONF_KEY_BRK_ID)
                ) {
                    self::logger(
                        'debug',
                        sprintf(
                            'UPDATED %s BrkId %s->%s',
                            $this->getHumanName(),
                            json_encode($this->_preSaveInformations[jMQTTConst::CONF_KEY_BRK_ID]),
                            json_encode($this->getConf(jMQTTConst::CONF_KEY_BRK_ID))
                        )
                    );
                    // Get the new Broker
                    $this->_broker = self::getBrokerFromId(
                        $this->getConf(jMQTTConst::CONF_KEY_BRK_ID)
                    );
                    $sendUpdate = true;
                }

                // Battery removed -> Clear Battery status
                if (
                    $this->_preSaveInformations[jMQTTConst::CONF_KEY_BATTERY_CMD] != ''
                    && $this->getConf(jMQTTConst::CONF_KEY_BATTERY_CMD) == ''
                ) {
                    $this->setStatus('battery', null);
                    $this->setStatus('batteryDatetime', null);
                    $this->log(
                        'debug',
                        sprintf(
                            "Cleaning-up Battery of equipment #%s#",
                            $this->getHumanName()
                        )
                    );
                }

                // Availability removed -> Clear Availability (Timeout) status
                if (
                    $this->_preSaveInformations[jMQTTConst::CONF_KEY_AVAILABILITY_CMD] != ''
                    && $this->getConf(jMQTTConst::CONF_KEY_AVAILABILITY_CMD) == ''
                ) {
                    $this->setStatus('warning', null);
                    $this->log(
                        'debug',
                        sprintf(
                            "Cleaning-up Availability of equipment #%s#",
                            $this->getHumanName()
                        )
                    );
                }

                // Name or Object changed
                if (
                    $this->_preSaveInformations['name'] != $this->getName()
                    || $this->_preSaveInformations['object'] != $this->getObject_id()
                ) {
                    $sendUpdate = true;
                }

                // Check other changes that would trigger MQTT Client update
                $checkChanged = array(
                    // jMQTTConst::CONF_KEY_AUTO_ADD_CMD,
                    jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC,
                    jMQTTConst::CONF_KEY_QOS
                );
                foreach ($checkChanged as $key) {
                    if ($this->_preSaveInformations[$key] != $this->getConf($key)) {
                        $sendUpdate = true;
                        break;
                    }
                }
            }

            // In the end, does Daemon data need to be updated
            if ($sendUpdate) {
                $data = utils::o2a($this, true);
                $data['cache'] = $this->getCache();
                if (is_object($this->getObject())) {
                    $obj_name = $this->getObject()->getName();
                } else {
                    $obj_name = __('Aucun', __FILE__);
                }
                $data['name'] = $obj_name.':'.$data['name'];
                // Do some cleanup
                unset($data['category']);
                unset($data['configuration']['battery_type']);
                unset($data['configuration']['createtime']);
                unset($data['configuration']['commentaire']);
                unset($data['configuration']['updatetime']);
                unset($data['comment']);
                unset($data['display']);
                unset($data['isVisible']);
                unset($data['object_id']);
                unset($data['status']);
                unset($data['tags']);
                unset($data['timeout']);
                // Send update of eqLogic (and only eqLogic) to Daemon
                jMQTTComToDaemon::eqptSet($data);
            }
        }
    }

    /**
     * preRemove method to check if the MQTT Client shall be restarted
     */
    public function preRemove() {

        // ------------------------ Broker eqpt ------------------------
        if ($this->getType() == jMQTTConst::TYP_BRK) {

            $this->log(
                'info',
                sprintf(
                    __("Suppression du Broker %s", __FILE__),
                    $this->getName()
                )
            );

            // Disable first the broker to Stop MQTT Client
            if ($this->getIsEnable()) {
                $this->setIsEnable(0);
                $this->save();

                // Wait up to 3s for MqttClient to stop
                for ($i=0; $i < 12; $i++) {
                    if ($this->getMqttClientState() != jMQTTConst::CLIENT_OK)
                        break;
                    usleep(250000);
                }
            }

            // Remove log level from config
            $log = $this->getMqttClientLogFile();
            config::remove('log::level::' . $log, __CLASS__);

            jMQTTComToDaemon::brokerDel($this->getId());
        }
        // ------------------------ Normal eqpt ------------------------
        else {
            $this->log(
                'info',
                sprintf(
                    __("Suppression de l'équipement #%s#", __FILE__),
                    $this->getHumanName()
                )
            );
            jMQTTComToDaemon::eqptDel($this->getId());
        }
    }

    /**
     * Core callback for health page informations
     *
     * @return array
     */
    public static function health() {
        $return = array();
        foreach (self::getBrokers() as $broker) {
            if(!$broker->getIsEnable()) {
                $return[] = array(
                    'test' => __('Accès au broker', __FILE__) . ' <b>' . $broker->getName() . '</b>',
                    'result' => __('Client jMQTT désactivé', __FILE__),
                    'advice' => '',
                    'state' => true
                );
                continue;
            }
            $mosqHost = $broker->getConf(jMQTTConst::CONF_KEY_MQTT_ADDRESS);
            $mosqPort = $broker->getConf(jMQTTConst::CONF_KEY_MQTT_PORT);
            $socket = socket_create(AF_INET, SOCK_STREAM, 0);
            $state = false;
            if ($socket !== false) {
                $state = socket_connect($socket, $mosqHost, $mosqPort);
                socket_close($socket);
            }

            $return[] = array(
                'test' => __('Accès au broker', __FILE__) . ' <b>' . $broker->getName() . '</b>',
                'result' => $state ? __('OK', __FILE__) : __('NOK', __FILE__),
                'advice' => $state ? '' : __('Vérifiez les paramètres de connexion réseau', __FILE__),
                'state' => $state
            );

            if ($state) {
                $info = $broker->getMqttClientInfo();
                $return[] = array(
                    'test' => __('Configuration du broker', __FILE__) . ' <b>' . $broker->getName() . '</b>',
                    'result' => strtoupper($info['launchable']),
                    'advice' => ($info['launchable'] != jMQTTConst::CLIENT_OK ? $info['message'] : ''),
                    'state' => ($info['launchable'] == jMQTTConst::CLIENT_OK)
                );
                if (end($return)['state']) {
                    $return[] = array(
                        'test' => __('Connexion au broker', __FILE__) . ' <b>' . $broker->getName() . '</b>',
                        'result' => strtoupper($info['state']),
                        'advice' => ($info['state'] != jMQTTConst::CLIENT_OK ? $info['message'] : ''),
                        'state' => ($info['state'] == jMQTTConst::CLIENT_OK)
                    );
                }
            }
        }
        return $return;
    }


    ###################################################################################################################
    ##
    ##                   PLUGIN RELATED METHODS
    ##
    ###################################################################################################################

    /**
     * Core callback for the plugin cron every minute
     */
    public static function cron() {
        jMQTTPlugin::cron();
    }

    /**
     * Core callback to get information about the daemon
     *
     * @return array
     */
    public static function deamon_info() {
        $return = array('launchable' => jMQTTConst::CLIENT_OK, 'log' => jMQTT::class);
        $return['state'] = (jMQTTDaemon::check()) ? jMQTTConst::CLIENT_OK : jMQTTConst::CLIENT_NOK;
        return $return;
    }

    /**
     * Core callback to start daemon
     */
    public static function deamon_start() {
        jMQTTDaemon::start();
    }

    /**
     * Core callback to stop the daemon
     */
    public static function deamon_stop() {
        jMQTTDaemon::stop();
    }

    /**
     * Core callback to provide dependancy information
     *
     * @return array
     */
    public static function dependancy_info() {
        return jMQTTPlugin::dependancy_info();
    }

    /**
     * Core callback to provide dependancy installation script
     *
     * @return array
     */
    public static function dependancy_install() {
        return jMQTTPlugin::dependancy_install();
    }

    /**
     * Create or update all autoPub listeners
     */
    public static function listenersAddAll() {
        /** @var jMQTTCmd $cmd */
        foreach (
            cmd::searchConfiguration(
                '"'.jMQTTConst::CONF_KEY_AUTOPUB.'":"1"',
                __CLASS__
            ) as $cmd
        ) {
            $cmd->listenerUpdate();
        }
    }

    /**
     * Remove all autoPub listeners from all eqLogics
     */
    public static function listenersRemoveAll() {
        foreach (listener::byClass('jMQTTCmd') as $l)
            $l->remove();
    }

    /**
     * Create or update all autoPub listeners from this eqLogic
     */
    public function listenersAdd() {
        foreach (
            jMQTTCmd::searchConfigurationEqLogic(
                $this->getId(),
                '"'.jMQTTConst::CONF_KEY_AUTOPUB.'":"1"'
            ) as $cmd
        ) {
            $cmd->listenerUpdate();
        }
    }

    /**
     * Remove all autoPub listeners from this eqLogic
     */
    public function listenersRemove() {
        $listener = listener::searchClassFunctionOption(
            'jMQTTCmd',
            'listenerAction',
            '"eqLogic":"'.$this->getId().'"'
        );
        foreach ($listener as $l)
            $l->remove();
    }

    /**
     * Callback on daemon auto mode change
     *
     * @param bool $_mode deamonAutoMode new value
     */
    public static function deamon_changeAutoMode($_mode) {
        if ($_mode)
            self::logger(
                'info',
                __("Le démarrage automatique du Démon est maintenant Activé", __FILE__)
            );
        else
            self::logger(
                'warning',
                __("Le démarrage automatique du Démon est maintenant Désactivé", __FILE__)
            );
    }

    /**
     * Callback to check daemon auto mode status
     *
     * @return bool deamonAutoMode is enabled
     */
    public static function getDaemonAutoMode() {
        return (config::byKey('deamonAutoMode', __CLASS__, 1) == 1);
    }

    // TODO: Remove unused function `jMQTT::deadCmd()`
    //  labels: quality, php
    /*
    public static function deadCmd() {
        // return eqLogic::deadCmdGeneric(__CLASS__);
        $sql = "SELECT `cmd`.`id`, `cmd`.`name`, `cmd`.`eqLogic_id`, `cmd`.`type`, `cmd`.`subType`";
        $sql .= " FROM `cmd` LEFT JOIN `eqLogic` ON `eqLogic`.`id` = `cmd`.`eqLogic_id`";
        $sql .= " WHERE `cmd`.`eqType` = 'jMQTT' AND `eqLogic`.`name` IS NULL";
        $results = DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL);
        $return = array();
        foreach ($results as $result) {
            $return[] = array(
                'detail' => $result['name'],
                'who' => '#' . $result['id'] . '#' . ' (' . __('ancien équipement :', __FILE__) . ' #' . $result['eqLogic_id'] . '#)',
                'help' => $result['type'] . ' / ' . $result['subType'] . '<span class="label label-info eId hidden">30</span><a class="eqLogicAction" data-action="removeEq"><i class="fas fa-minus-circle"></i></a>'
                // . '<a href="/index.php?v=d&m=jMQTT&p=jMQTT&id=">' . __('Supprimer', __FILE__) . '</a>'
            );
        }
        return $return;
    }
    */

    /**
     * Core callback on API key change
     *
     * @param string $_apikey New API key
     */
    public static function preConfig_api($_apikey) {
        // Different message when API key is not set
        $oldApiKey = config::byKey('api', __CLASS__);

        if ($oldApiKey == '') {
            if (log::getLogLevel(__CLASS__) > 100)
                self::logger('info', __('Définition de la clé API de jMQTT', __FILE__));
            else // Append more info in debug
                self::logger(
                    'info',
                    sprintf(
                        __('Définition de la clé API de jMQTT : %1$.8s...', __FILE__),
                        $_apikey
                    )
                );
        } else {
            if (log::getLogLevel(__CLASS__) > 100)
                self::logger(
                    'info',
                    __('Changement de la clé API de jMQTT', __FILE__)
                );
            else // Append more info in debug
                self::logger(
                    'info',
                    sprintf(
                        __('Changement de la clé API de jMQTT : %1$.8s... est remplacé par %2$.8s...', __FILE__),
                        $oldApiKey,
                        $_apikey
                    )
                );
        }

        // Inform Daemon only if API key changed (to prevent a recursion loop)
        if ($oldApiKey != '')
            jMQTTComToDaemon::changeApiKey($_apikey);

        // Always return new API key
        return $_apikey;
    }


    ###################################################################################################################
    ##
    ##                   MQTT CLIENT RELATED METHODS
    ##
    ###################################################################################################################


    /**
     * Return MQTT Client information
     *
     * @return array MQTT Client information array
     */
    public function getMqttClientInfo() {
        // Not a Broker
        if ($this->getType() != jMQTTConst::TYP_BRK)
            return array(
                'message' => '',
                'launchable' => jMQTTConst::CLIENT_NOK,
                'state' => jMQTTConst::CLIENT_NOK
            );

        // Daemon is down
        if (!jMQTTDaemon::state())
            return array(
                'launchable' => jMQTTConst::CLIENT_NOK,
                'state' => jMQTTConst::CLIENT_NOK,
                'message' => __("Démon non démarré", __FILE__)
            );

        // Client is connected to the Broker
        if ($this->getCache(jMQTTConst::CACHE_MQTTCLIENT_CONNECTED, false))
            return array(
                'launchable' => jMQTTConst::CLIENT_OK,
                'state' => jMQTTConst::CLIENT_OK,
                'message' => __("Le Démon jMQTT est correctement connecté à ce Broker", __FILE__)
            );

        // Client is disconnected from the Broker
        if ($this->getIsEnable())
            return array(
                'launchable' => jMQTTConst::CLIENT_OK,
                'state' => jMQTTConst::CLIENT_POK,
                'message' => __("Le Démon jMQTT n'arrive pas à se connecter à ce Broker", __FILE__)
            );

        // Client is disabled
        return array(
            'launchable' => jMQTTConst::CLIENT_NOK,
            'state' => jMQTTConst::CLIENT_NOK,
            'message' => __("La connexion à ce Broker est désactivée", __FILE__)
        );
    }

    /**
     * Return MQTT Client state
     *   - jMQTTConst::CLIENT_OK: MQTT Client is running and mqtt broker is online
     *   - jMQTTConst::CLIENT_POK: MQTT Client is running but mqtt broker is offline
     *   - jMQTTConst::CLIENT_NOK: daemon is not running or Eq is disabled
     *
     * @return string ok or nok
     */
    public function getMqttClientState() {
        if (!jMQTTDaemon::state() || $this->getType() != jMQTTConst::TYP_BRK)
            return jMQTTConst::CLIENT_NOK;
        if ($this->getCache(jMQTTConst::CACHE_MQTTCLIENT_CONNECTED, false))
            return jMQTTConst::CLIENT_OK;
        if ($this->getIsEnable())
            return jMQTTConst::CLIENT_POK;
        return jMQTTConst::CLIENT_NOK;
    }

    /**
     * Send a jMQTT::EventState event to the UI containing eqLogic
     */
    public function sendMqttClientStateEvent() {
        event::add('jMQTT::EventState', $this->toArray());
    }


    ###################################################################################################################
    ##
    ##                   MQTT BROKER METHODS
    ##
    ###################################################################################################################

    /**
     * Function to handle message matching Interaction subscribed topic.
     * Reply Payload is sent on jMQTTConst::CONF_KEY_MQTT_INT_TOPIC/reply
     *   with value in json like: $param + {"query": string, "reply": string}
     *
     * @param string $query Interaction Query message
     * @param array $param Interaction Query advanced options
     */
    private function interactMessage($query, $param=array()) {
        try {
            // Validate query
            if (!is_string($query))
                $param['query'] = '';
            else
                $param['query'] = $query;
            // Process parameters
            if (isset($param['utf8']) && $param['utf8'])
                $query = mb_convert_encoding($query, 'UTF-8', 'ISO-8859-1');
            if (isset($param['reply_cmd'])) {
                $reply_cmd = cmd::byId($param['reply_cmd']);
                if (is_object($reply_cmd)) {
                    $param['reply_cmd'] = $reply_cmd;
                    $param['force_reply_cmd'] = 1;
                }
            }

            // Process Interactions
            $reply = interactQuery::tryToReply($query, $param);

            // Put some logs on the Broker
            $this->log(
                'info',
                sprintf(
                    __("Interaction demandée '%1\$s', réponse '%2\$s'", __FILE__),
                    $query,
                    $reply['reply']
                )
            );

            // Send reply on a /reply subtopic
            if (!is_array($reply))
                $reply = array('reply' => $reply);
            $reply = array_merge(array('status' => ''), $param, $reply, array('status' => 'ok'));
            $this->publish(
                $this->getName(),
                $this->getConf(jMQTTConst::CONF_KEY_MQTT_INT_TOPIC) . '/reply',
                json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                1,
                false
            );
        } catch (Throwable $e) {
            if (log::getLogLevel(__CLASS__) > 100) {
                self::logger(
                    'warning',
                    sprintf(
                        __("L'Interaction '%1\$s' a levé l'Exception: %2\$s", __FILE__),
                        $query,
                        $e->getMessage()
                    )
                );
            } else { // More info in debug mode, no big log otherwise
                self::logger('warning', str_replace( "\n", ' <br/> ', sprintf(
                    __("L'Interaction '%1\$s' a levé l'Exception: %2\$s", __FILE__).
                    ",<br/>@Stack: %3\$s.",
                    $query,
                    $e->getMessage(),
                    $e->getTraceAsString()
                )));
            }

            // Send reply on a /reply subtopic
            $reply = array_merge(
                array('status' => ''),
                $param,
                array('reply' => '', 'status' => 'nok', 'error' => $e->getMessage())
            );

            $this->publish(
                $this->getName(),
                $this->getConf(jMQTTConst::CONF_KEY_MQTT_INT_TOPIC) . '/reply',
                json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                1,
                false
            );
        }
    }

    // TODO: Split `jMQTT::brokerMessageCallback()` in smaller functions
    //  labels: quality, php
    /**
     * Callback called each time a message matching subscribed topic is received from the broker.
     *
     * @param string $msgTopic topic of the message
     * @param string $msgValue payload of the message
     * @param int $msgQos qos level of the message
     * @param bool $msgRetain retain status of the message
     */
    public function brokerMessageCallback($msgTopic, $msgValue, $msgQos, $msgRetain) {

        $start_t = microtime(true);
        $this->setStatus(array('lastCommunication' => date('Y-m-d H:i:s'), 'timeout' => 0));

        // Is Interact topic enabled ?
        if ($this->getConf(jMQTTConst::CONF_KEY_MQTT_INT)) {
            // If "simple" Interact topic, process the request
            if ($msgTopic == $this->getConf(jMQTTConst::CONF_KEY_MQTT_INT_TOPIC)) {
                // Request Payload: string
                $this->interactMessage($msgValue);
                // Reply Payload on /reply: {"query": string, "reply": string}
            }
            // If "advanced" Interact topic, process the request
            if ($msgTopic == $this->getConf(jMQTTConst::CONF_KEY_MQTT_INT_TOPIC) . '/advanced') {
                // Request Payload on /advanced: {"query": string, "utf8": bool, "emptyReply": ???, profile": ???, "reply_cmd": <cmdId>, "force_reply_cmd": bool}
                $param = json_decode($msgValue, true);
                $this->interactMessage($param['query'], $param);
                // Reply Payload on /reply: $param + {"reply": string}
            }
        }

        // If this is the API topic, process the request
        if ($this->getConf(jMQTTConst::CONF_KEY_MQTT_API)
            && $msgTopic == $this->getConf(jMQTTConst::CONF_KEY_MQTT_API_TOPIC)) {
            $this->processApiRequest($msgValue);
        }

        // Loop on jMQTT equipments and get ones that subscribed to the current message
        $elogics = array();
        foreach (self::byBrkId($this->getId()) as $eqpt) {
            if (mosquitto_topic_matches_sub($eqpt->getTopic(), $msgTopic)) $elogics[] = $eqpt;
        }

        //
        // Loop on enabled equipments listening to the current message
        //
        $related_cmd = '';
        foreach ($elogics as $eqpt) {
            if (!$eqpt->getIsEnable()) {
                continue;
            }
            // Looking for all cmds matching Eq and Topic in the DB
            $cmds = jMQTTCmd::byEqLogicIdAndTopic($eqpt->getId(), $msgTopic, true);
            if (is_null($cmds))
                $cmds = array();
            $jsonCmds = array();
            // Keep only info cmds in $cmds and put all JSON info commands in $jsonCmds
            foreach ($cmds as $k => $cmd) {
                if ($cmd->getType() == 'action') {
                    $this->log('debug', sprintf(
                        "Cmd #%s# is of action type: ignored",
                        $cmd->getHumanName()
                    ));
                    unset($cmds[$k]);
                } elseif ($cmd->isJson()) {
                    $this->log('debug', sprintf(
                        "Cmd #%s# is of info-Json type: ignored",
                        $cmd->getHumanName()
                    ));
                    unset($cmds[$k]);
                    $jsonCmds[] = $cmd;
                }
            }
            // If there is no info cmd matching exactly with the topic (non JSON)
            if (empty($cmds)) {
                // Is automatic command creation enabled?
                if ($eqpt->getAutoAddCmd()) {
                    // Determine the futur name of the command.
                    // Suppress starting topic levels that are common with the equipment suscribing topic
                    $sbscrbTopicArray = explode("/", $eqpt->getTopic());
                    $msgTopicArray = explode("/", $msgTopic);
                    foreach ($sbscrbTopicArray as $s) {
                        if ($s == '#' || $s == '+')
                            break;
                        else
                            next($msgTopicArray);
                    }
                    // @phpstan-ignore-next-line
                    if (current($msgTopicArray) === false) {
                        $cmdName = end($msgTopicArray);
                    } else {
                        $cmdName = current($msgTopicArray);
                    }
                    while (next($msgTopicArray) !== false) {
                        $cmdName = $cmdName . '/' . current($msgTopicArray);
                    }
                    // Ensure whitespaces are treated well
                    $cmdName = substr(trim($cmdName), 0, 120);
                    $allCmdsNames = array();
                    // Get all commands names for this equipment
                    foreach (jMQTTCmd::byEqLogicId($eqpt->getId()) as $cmd)
                        $allCmdsNames[] = strtolower(trim($cmd->getName()));
                    // If cmdName is already used, add suffix '-<number>'
                    if (false !== array_search($cmdName, $allCmdsNames)) {
                        $cmdName .= '-';
                        $increment = 1;
                        do {
                            $increment++;
                        } while (
                            false !== array_search(
                                strtolower($cmdName . $increment),
                                $allCmdsNames
                            )
                        );
                        $cmdName .= $increment;
                    }
                    // Create the new cmd
                    $newCmd = jMQTTCmd::newCmd($eqpt, $cmdName, $msgTopic);
                    try {
                        $newCmd->save();
                        $cmds[] = $newCmd;
                        $this->log('debug', sprintf(
                            "Cmd #%s# created automatically for topic '%s'.",
                            $newCmd->getHumanName(),
                            $msgTopic
                        ));
                    } catch (Throwable $e) {
                        if (log::getLogLevel(__CLASS__) > 100) {
                            $this->log('error', sprintf(
                                __("L'enregistrement de la nouvelle commande #%1\$s# a levé l'Exception: %2\$s", __FILE__),
                                $newCmd->getHumanName(),
                                $e->getMessage()
                            ));
                        } else { // More info in debug mode, no big log otherwise
                            $this->log('error', str_replace("\n", ' <br/> ', sprintf(
                                __("L'enregistrement de la nouvelle commande #%1\$s# a levé l'Exception: %2\$s", __FILE__).
                                ",<br/>@Stack: %3\$s,<br/>@Dump: %4\$s.",
                                $newCmd->getHumanName(),
                                $e->getMessage(),
                                $e->getTraceAsString(),
                                json_encode($newCmd)
                            )));
                        }
                    }
                } else {
                    $this->log('debug', sprintf(
                        "No command has been created for topic %s in equipment #%s# (auto cmd creation is disabled).",
                        $msgTopic,
                        $eqpt->getHumanName()
                    ));
                }
            }

            // If there is some cmd matching exactly with the topic
            if (is_array($cmds) && count($cmds)) {
                foreach ($cmds as $cmd) {
                    // Update the command value
                    $cmd->updateCmdValue($msgValue);
                    $related_cmd .= ', #' . $cmd->getHumanName() . '#';
                }
            }

            // If there is some cmd matching exactly with the topic with JSON path
            if (is_array($jsonCmds) && count($jsonCmds)) {
                // decode JSON payload
                $jsonArray = reset($jsonCmds)->decodeJsonMsg($msgValue);
                if (isset($jsonArray)) {
                    foreach ($jsonCmds as $cmd) {
                        // Update JSON derived commands
                        $cmd->updateJsonCmdValue($jsonArray);
                        $related_cmd .= ', #' . $cmd->getHumanName() . '#';
                    }
                }
            }
        }

        $duration_ms = round((microtime(true) - $start_t)*1000);
        if ($duration_ms > 300) {
            if (strlen($related_cmd) == 0) {
                $related_cmd = __(": Aucune", __FILE__);
            } else {
                $related_cmd[0] = ':';
            }
            $this->log('warning', sprintf(
                __("Attention, ", __FILE__) .
                __("Payload '%1\$s' reçu sur le Topic '%2\$s' traité en %3\$dms", __FILE__) .
                __(" (très long), vérifiez les commandes affiliées %4\$s", __FILE__),
                $msgValue, $msgTopic, $duration_ms, $related_cmd
            ));
        } elseif (log::getLogLevel(__CLASS__) <= 100) {
            $this->log('debug', sprintf(
                __("Payload '%1\$s' reçu sur le Topic '%2\$s' traité en %3\$dms", __FILE__) .
                __(", commandes affiliées %4\$s", __FILE__),
                $msgValue, $msgTopic, $duration_ms, $related_cmd
            ));
        }
    }

    /**
     * Publish a given message to the MQTT broker attached to this object
     *
     * @param string $cmdName command name (for log purpose only)
     * @param string $topic topic
     * @param bool|array|int|string $payload payload
     * @param int $qos quality of service used to send the message ('0', '1' or '2')
     * @param bool $retain whether or not the message should be retained ('0' or '1')
     */
    public function publish($cmdName, $topic, $payload, $qos, $retain) {
        if (is_bool($payload) || is_array($payload)) {
            // Fix #80
            // One can wonder why not encoding systematically the message?
            // Answer is that it does not work in some cases:
            //   * If payload is empty => "(null)" is sent instead of (null)
            //   * If payload contains ", they are backslashed \"
            // Fix #110
            // Since Core commit https://github.com/jeedom/core/commit/430f0049dc74e914c4166b109fb48b4375f11ead
            // payload can become more than int/bool/string
            $payload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $payloadLogMsg = ($payload === '') ? '\'\' (null)' : "'".$payload."'";
        if (!jMQTTDaemon::state()) {
            if (!self::getDaemonAutoMode()) {
                $this->log('info', sprintf(
                    __("Cmd #%1\$s# -> %2\$s Message non publié, car le démon jMQTT est désactivé", __FILE__),
                    $cmdName,
                    $payloadLogMsg
                ));
                return;
            }
            $this->log('info', sprintf(
                __("Cmd #%1\$s# -> %2\$s Message non publié, car le démon jMQTT n'est pas démarré", __FILE__),
                $cmdName,
                $payloadLogMsg
                ));
            return;
        }

        $broker = $this->getBroker();
        if (!$broker->getIsEnable()) {
            $this->log('info', sprintf(
                __("Cmd #%1\$s# -> %2\$s Message non publié, car le Broker jMQTT %3\$s n'est pas activé", __FILE__),
                $cmdName,
                $payloadLogMsg,
                $broker->getName()
            ));
            return;
        }

        if ($broker->getMqttClientState() != jMQTTConst::CLIENT_OK) {
            $this->log('warning', sprintf(
                __("Cmd #%1\$s# -> %2\$s Message non publié, car le Broker jMQTT %3\$s n'est pas connecté au Broker MQTT", __FILE__),
                $cmdName,
                $payloadLogMsg,
                $broker->getName()
            ));
            return;
        }

        if (log::getLogLevel(__CLASS__) > 100) {
            $this->log('info', sprintf(
                __("Cmd #%1\$s# -> %2\$s sur le topic '%3\$s'", __FILE__),
                $cmdName,
                $payloadLogMsg,
                $topic
            ));
        } else {
            $this->log('info', sprintf(
                __("Cmd #%1\$s# -> %2\$s sur le topic '%3\$s' (qos=%4\$s, retain=%5\$s)", __FILE__),
                $cmdName,
                $payloadLogMsg,
                $topic,
                $qos,
                $retain
            ));
        }

        jMQTTComToDaemon::brokerPublish($this->getBrkId(), $topic, $payload, $qos, $retain);
        $d = date('Y-m-d H:i:s');
        $this->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
        if ($this->getType() == jMQTTConst::TYP_EQPT)
            $broker->setStatus(array('lastCommunication' => $d, 'timeout' => 0));
        // $this->log('debug', "Message published");
    }

    /**
     * Return the MQTT status information command of this broker
     * It is the responsability of the caller to check that this object
     *   is a broker before calling the method.
     * If $create, then create and save the MQTT status information
     *   command of this broker if not already existing
     *
     * @param bool $create create the command if it does not exist
     * @return jMQTTCmd cmd status information command.
     */
    public function getMqttClientStatusCmd($create = false) {
        // Get cmd if it exists
        if (!is_object($this->_statusCmd))
            $this->_statusCmd = cmd::byEqLogicIdAndLogicalId(
                $this->getId(),
                jMQTTConst::CLIENT_STATUS
            );
        // If cmd does not exist
        if ($create && !is_object($this->_statusCmd)) {
            // Topic and jsonPath are irrelevant here
            $cmd = jMQTTCmd::newCmd($this, jMQTTConst::CLIENT_STATUS, '', '');
            $cmd->setLogicalId(jMQTTConst::CLIENT_STATUS);
            $cmd->setConfiguration('irremovable', 1);
            $cmd->save();
            $this->_statusCmd = $cmd;
        }
        return $this->_statusCmd;
    }

    /**
     * Return the MQTT connected binary information command of this broker
     * It is the responsability of the caller to check that this object
     *   is a broker before calling the method.
     * If $create, then create and save the MQTT connected information
     *   command of this broker if not already existing
     *
     * @param bool $create create the command if it does not exist
     * @return jMQTTCmd cmd connected information command.
     */
    public function getMqttClientConnectedCmd($create = false) {
        // Get cmd if it exists
        if (!is_object($this->_connectedCmd))
            $this->_connectedCmd = cmd::byEqLogicIdAndLogicalId(
                $this->getId(),
                jMQTTConst::CLIENT_CONNECTED
            );
        // If cmd does not exist
        if ($create && !is_object($this->_connectedCmd)) {
            // Topic and jsonPath are irrelevant here
            $cmd = jMQTTCmd::newCmd($this, jMQTTConst::CLIENT_CONNECTED, '', '');
            $cmd->setLogicalId(jMQTTConst::CLIENT_CONNECTED);
            $cmd->setSubType('binary');
            $cmd->setConfiguration('irremovable', 1);
            $cmd->save();
            $this->_connectedCmd = $cmd;
        }
        return $this->_connectedCmd;
    }

    ###################################################################################################################

    /**
     * Process the API request
     *
     * @param string $msg API message to process
     */
    private function processApiRequest($msg) {
        try {
            $request = new mqttApiRequest($msg, $this);
            $request->processRequest($this->getConf(jMQTTConst::CONF_KEY_MQTT_API));
        } catch (Throwable $e) {
            if (log::getLogLevel(__CLASS__) > 100) {
                self::logger('error', sprintf(
                    __("%1\$s() a levé l'Exception: %2\$s", __FILE__),
                    __METHOD__,
                    $e->getMessage()
                ));
            } else {
                self::logger('error', str_replace("\n", ' <br/> ', sprintf(
                    __("%1\$s() a levé l'Exception: %2\$s", __FILE__).
                    ",<br/>@Stack: %3\$s,<br/>@BrkId: %4\$s,".
                    "<br/>@Topic: %5\$s,<br/>@Payload: %6\$s.",
                    __METHOD__,
                    $e->getMessage(),
                    $e->getTraceAsString(),
                    $this->getBrkId(),
                    $this->getConf(jMQTTConst::CONF_KEY_MQTT_API),
                    $msg
                )));
            }
        }
    }

    /**
     * Return the name of the log file attached to this jMQTT object.
     * The log file is cached for optimization.
     *
     * @return string MQTT Client log filename.
     */
    public function getMqttClientLogFile() {
        return __CLASS__ . '_' .
            str_replace(' ', '_', $this->getBroker()->getName());
    }

    /**
     * Log messages to eqBroker log file
     *
     * @param string $level
     * @param string $msg
     */
    public function log($level, $msg) {
        // log can't be written during removal of an eqLogic next to his broker eqLogic removal
        // the name of the broker can't be found (and log file has already been deleted)
        try {
            $log = $this->getMqttClientLogFile();
            log::add($log, $level, $msg);
        } catch (Throwable $e) {
            // nothing to do in that particular case?
        }
    }

    /**
     * Log messages to jMQTT log file
     *
     * @param string $level
     * @param string $msg
     */
    public static function logger($level, $msg) {
        log::add(__CLASS__, $level, $msg);
    }

    /**
     * @param string $_key
     * @return mixed
     */
    public function getConf($_key) {
        // Default value is returned if config is null or an empty string
        return $this->getConfiguration($_key, $this->getDefaultConfiguration($_key));
    }

    /**
     * @param string $_key
     * @return string|int
     */
    private function getDefaultConfiguration($_key) {
        if ($_key == jMQTTConst::CONF_KEY_MQTT_PORT) {
            $proto = $this->getConf(jMQTTConst::CONF_KEY_MQTT_PROTO);
            if ($proto == 'mqtt')
                return 1883;
            elseif ($proto == 'mqtts')
                return 8883;
            elseif ($proto == 'ws')
                return 1884;
            elseif ($proto == 'wss')
                return 8884;
            else
                return 0;
        }
        $defValues = array(
            jMQTTConst::CONF_KEY_MQTT_PROTO => 'mqtt',
            jMQTTConst::CONF_KEY_MQTT_ADDRESS => 'localhost',
            jMQTTConst::CONF_KEY_MQTT_ID => '0',
            jMQTTConst::CONF_KEY_QOS => '1',
            jMQTTConst::CONF_KEY_MQTT_LWT => '1',
            jMQTTConst::CONF_KEY_MQTT_LWT_TOPIC => 'jeedom/status',
            jMQTTConst::CONF_KEY_MQTT_LWT_ONLINE => 'online',
            jMQTTConst::CONF_KEY_MQTT_LWT_OFFLINE => 'offline',
            jMQTTConst::CONF_KEY_MQTT_TLS_CHECK => 'public',
            jMQTTConst::CONF_KEY_MQTT_TLS_CLI => '0',
            jMQTTConst::CONF_KEY_AUTO_ADD_CMD => '1',
            jMQTTConst::CONF_KEY_MQTT_INT => '0',
            jMQTTConst::CONF_KEY_MQTT_INT_TOPIC => 'jeedom/interact',
            jMQTTConst::CONF_KEY_MQTT_API => '0',
            jMQTTConst::CONF_KEY_MQTT_API_TOPIC => 'jeedom/api',
            jMQTTConst::CONF_KEY_BRK_ID => -1
        );
        // If not in list, default value is ''
        return isset($defValues[$_key]) ? $defValues[$_key] : '';
    }

    /**
     * Set the log level
     * Called when saving a broker eqLogic
     * If log level is changed, save the new value and restart the MQTT Client
     *
     * @param string $log_level
     */
    public function setLogLevel($log_level) {
        $decodedLogLevel = json_decode($log_level, true);
        $this->setConfiguration(
            jMQTTConst::CONF_KEY_LOGLEVEL,
            reset($decodedLogLevel)
        );
    }

    /**
     * Get this jMQTT object topic
     *
     * @return string
     */
    public function getTopic() {
        return $this->getConf(jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC);
    }

    /**
     * Set this jMQTT object topic
     *
     * @param string $topic
     */
    public function setTopic($topic) {
        $this->setConfiguration(jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC, $topic);
    }

    /**
     * Move this jMQTT object auto_add_topic to configuration
     */
    public function moveTopicToConfiguration() {
        // Detect presence of auto_add_topic
        $keyPresence = $this->getConfiguration(
            jMQTTConst::CONF_KEY_AUTO_ADD_TOPIC,
            'ThereIsNoKeyHere'
        );
        if ($keyPresence == 'ThereIsNoKeyHere') {
            $this->setTopic($this->getLogicalId());
            $this->setLogicalId('');
            // Direct save to avoid daemon notification and Exception that daemon is not Up
            $this->save(true);
        }
    }

    /**
     * Get this jMQTT object type
     *
     * @return string either jMQTT::TYPE_EQPT, jMQTTConst::TYP_BRK, or empty string if not defined
     */
    public function getType() {
        return $this->getConfiguration(jMQTTConst::CONF_KEY_TYPE, '');
    }

    /**
     * Set this jMQTT object type
     *
     * @param string $type either jMQTTConst::TYP_BRK or jMQTTConst::TYP_EQPT (default)
     */
    public function setType($type = jMQTTConst::TYP_EQPT) {
        $this->setConfiguration(
            jMQTTConst::CONF_KEY_TYPE,
            ($type == jMQTTConst::TYP_BRK) ? jMQTTConst::TYP_BRK : jMQTTConst::TYP_EQPT
        );
    }

    /**
     * Get this jMQTT object related broker eqLogic Id
     *
     * @return string|int eqLogic Id or -1 if not defined
     */
    public function getBrkId() {
        if ($this->getType() == jMQTTConst::TYP_BRK) {
            return $this->getId();
        }
        return $this->getConf(jMQTTConst::CONF_KEY_BRK_ID);
    }

    /**
     * Set this jMQTT object related broker eqLogic Id
     *
     * @param string|int $id
     */
    public function setBrkId($id) {
        $this->setConfiguration(jMQTTConst::CONF_KEY_BRK_ID, $id);
    }
    /**
     * Set this jMQTT object related broker eqLogic Id
     * Used by utils::a2o to set de Broker Id
     *
     * @param string|int $id
     */
    public function setEqLogic($id) {
        $this->setConfiguration(jMQTTConst::CONF_KEY_BRK_ID, $id);
    }

    /**
     * Get this jMQTT object Qos
     *
     * @return string
     */
    public function getQos() {
        return $this->getConf(jMQTTConst::CONF_KEY_QOS);
    }

    /**
     * Get this jMQTT object auto_add_cmd configuration parameter
     *
     * @return string
     */
    public function getAutoAddCmd() {
        return $this->getConf(jMQTTConst::CONF_KEY_AUTO_ADD_CMD);
    }

    /**
     * Set this jMQTT object auto_add_cmd configuration parameter
     *
     * @param string $auto_add_cmd
     */
    public function setAutoAddCmd($auto_add_cmd) {
        $this->setConfiguration(jMQTTConst::CONF_KEY_AUTO_ADD_CMD, $auto_add_cmd);
    }

    /**
     * Get the broker object attached to this jMQTT object.
     * Broker is cached for optimisation.
     *
     * @return jMQTT
     * @throws Exception if the broker is not found
     */
    public function getBroker() {
        if ($this->getType() == jMQTTConst::TYP_BRK) {
            return $this;
        }
        if (! isset($this->_broker)) {
            $this->_broker = self::getBrokerFromId($this->getBrkId());
        }
        return $this->_broker;
    }

    /**
     * Get the Battery command defined in this eqLogic
     *
     * @return string Return the Battery command defined
     */
    public function getBatteryCmd() {
        return $this->getConf(jMQTTConst::CONF_KEY_BATTERY_CMD);
    }

    /**
     * Get the Availability command defined in this eqLogic
     *
     * @return string Return the Availability command defined
     */
    public function getAvailabilityCmd() {
        return $this->getConf(jMQTTConst::CONF_KEY_AVAILABILITY_CMD);
    }

    /**
     * Get the jMQTT broker object which eqLogic Id is given
     *
     * @param string|int $id id of the broker
     * @return jMQTT
     * @throws Exception if $id is not a valid broker id
     */
    public static function getBrokerFromId($id) {
        /** @var null|jMQTT $broker */
        $broker = self::byId($id);
        if (!is_object($broker)) {
            throw new Exception(
                sprintf(
                    __("Pas d'équipement jMQTT avec l'id %s.", __FILE__),
                    $id
                )
            );
        }
        if ($broker->getType() != jMQTTConst::TYP_BRK) {
            throw new Exception(
                sprintf(
                    __("L'équipement n'est pas de type Broker (id=%s)", __FILE__),
                    $id
                )
            );
        }
        return $broker;
    }

    /**
     * Return all jMQTT objects attached to the specified broker id
     *
     * @param int|string $id
     * @return jMQTT[]
     */
    public static function byBrkId($id) {
        $brkId = json_encode(array('eqLogic' => $id));
        $returns = self::byTypeAndSearchConfiguration(__CLASS__, substr($brkId, 1, -1));
        return $returns;
    }
}
