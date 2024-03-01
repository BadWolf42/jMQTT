<br/>
<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
            <div class="col-sm-3">
                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display: none;" />
                <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="type" style="display: none;" />
                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}" />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Objet parent}}</label>
            <div class="col-sm-3">
                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                    <option value="">{{Aucun}}</option>
                    <?php
                    foreach ((jeeObject::buildTree(null, false)) as $object) {
                        echo '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group toDisable typ-std typ-brk">
            <label class="col-sm-3 control-label">{{Catégorie}}</label>
            <div class="col-sm-8">
                <?php
                foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                    echo '<label class="checkbox-inline">';
                    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' .
                        $value['name'];
                    echo '</label>';
                }
                ?>

                </div>
        </div>

        <div class="form-group toDisable typ-std typ-brk">
            <label class="col-sm-3 control-label">&nbsp;</label>
            <div class="col-sm-8">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked />{{Activer}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked />{{Visible}}</label>
            </div>
        </div>

        <div class="form-group typ-std typ-brk-select">
            <label class="col-sm-3 control-label">{{Broker associé}}</label>
            <div class="col-sm-3">
                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="eqLogic"></select>
            </div>
        </div>

        <div class="form-group toDisable typ-std">
            <label class="col-sm-3 control-label">{{Ajout automatique des commandes}}</label>
            <div class="col-sm-3">
                <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="auto_add_cmd" checked />
            </div>
        </div>

        <div class="form-group toDisable typ-std">
            <label class="col-sm-3 control-label">{{Inscrit au Topic}}</label>
            <div class="col-sm-3">
                <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="auto_add_topic" placeholder="{{Topic principal de l'équipement jMQTT}}" />
            </div>
        </div>

        <div class="form-group toDisable typ-std">
            <label class="col-sm-3 control-label">{{Qos}}</label>
            <div id="mqttqos" class="col-sm-1">
                <select class="eqLogicAttr form-control" data-l1key="configuration"
                    data-l2key="Qos">
                    <option value="0">0</option>
                    <option value="1" selected>1</option>
                    <option value="2">2</option>
                </select>
            </div>
        </div>

        <div class="form-group toDisable typ-std">
            <label class="col-sm-3 control-label">{{Type d'alimentation}}</label>
            <div class="col-sm-3">
                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="battery_type" placeholder="{{Doit être indiqué sous la forme : Secteur, 1xCR123A, 3xAA, ...}}"/>
            </div>
        </div>

        <div class="form-group toDisable typ-std">
            <label class="col-sm-3 control-label">{{Commande d'état de la batterie}}</label>
            <div class="col-sm-3">
                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="battery_cmd" title="{{Commande information de Batterie}}">
                    <option value="">{{Aucune}}</option>
                </select>
            </div>
        </div>

        <div class="form-group toDisable typ-std">
            <label class="col-sm-3 control-label">{{Commande d'état de disponibilité}}</label>
            <div class="col-sm-3">
                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="availability_cmd" title="{{Commande information de disponibilité}}">
                    <option value="">{{Aucune}}</option>
                </select>
            </div>
        </div>

        <div class="form-group toDisable typ-std typ-brk">
            <label class="col-sm-3 control-label">{{Dernière communication}}</label>
            <div class="col-sm-3">
                <span class="eqLogicAttr" data-l1key="status" data-l2key="lastCommunication"></span>
            </div>
        </div>

        <div class="form-group toDisable typ-std typ-brk">
            <label class="col-sm-3 control-label">{{Commentaire}}</label>
            <div class="col-sm-3">
                <textarea class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="commentaire"></textarea>
            </div>
        </div>

        <div class="form-group typ-std">
            <label class="col-sm-3 control-label">{{Icône de l'équipement}}</label>
            <div class="col-sm-3">
                <select class="form-control eqLogicAttr" data-l1key="configuration" data-l2key="icone"><?php

$icons = array();
$icons['']                      = __('Aucun', __FILE__);
$icons['air-quality']           = __("Qualité de l'air", __FILE__);
$icons['awtrix']                = "Awtrix";
$icons['barometre']             = __('Baromètre', __FILE__);
$icons['battery']               = __('Batterie', __FILE__);
$icons['bell']                  = __('Sonnerie', __FILE__);
$icons['boiteauxlettres']       = __('Boite aux Lettres', __FILE__);
$icons['bt']                    = 'Bluetooth';
$icons['chauffage']             = __('Chauffage', __FILE__);
$icons['compteur']              = __('Compteur', __FILE__);
$icons['contact']               = __('Contact', __FILE__);
$icons['custom']                = __('Personnalisé', __FILE__);
$icons['dimmer']                = __('Dimmer', __FILE__);
$icons['door']                  = __('Porte', __FILE__);
$icons['energie']               = __('Energie', __FILE__);
$icons['espeasy']               = 'ESP Easy';
$icons['fan']                   = __('Ventilation', __FILE__);
$icons['feuille']               = __('Culture', __FILE__);
$icons['fire']                  = __('Incendie', __FILE__);
$icons['garage']                = __('Garage', __FILE__);
$icons['gate']                  = __('Portail', __FILE__);
$icons['home-flood']            = __('Inondation', __FILE__);
$icons['humidity']              = __('Humidité', __FILE__);
$icons['humiditytemp']          = __('Humidité et Température', __FILE__);
$icons['hydro']                 = __('Hydrométrie', __FILE__);
$icons['intex']                 = 'Intex';
$icons['ir2']                   = __('Infra Rouge', __FILE__);
$icons['jauge']                 = __('Jauge', __FILE__);
$icons['lift-pump']             = __('Pompe de relevage', __FILE__);
$icons['light']                 = __('Luminosité', __FILE__);
$icons['lightbulb']             = __('Lumière', __FILE__);
$icons['location']              = __('Localisation', __FILE__);
$icons['mcz-remote']            = 'MCZ Remote';
$icons['meteo']                 = __('Météo', __FILE__);
$icons['molecule-co']           = __('CO (Monoxyde de carbone)', __FILE__);
$icons['molecule-co2']          = __('CO2 (Dioxyde de carbone)', __FILE__);
$icons['motion']                = __('Mouvement', __FILE__);
$icons['motion-sensor']         = __('Présence', __FILE__);
$icons['multisensor']           = __('Multisensor', __FILE__);
$icons['nab']                   = 'Nabaztag';
$icons['openevse']              = 'OpenEVSE';
$icons['openmqttgateway']       = 'OpenMQTTGateway';
$icons['old-phone']             = __('Téléphone fixe', __FILE__);
$icons['phone']                 = __('Téléphone', __FILE__);
$icons['power-plug']            = __('Prise de courant', __FILE__);
$icons['prise']                 = __('Prise', __FILE__);
$icons['radiator']              = __('Radiateur', __FILE__);
$icons['relay']                 = __('Relais', __FILE__);
$icons['repeater']              = __('Répéteur', __FILE__);
$icons['remote']                = __('Télécommande', __FILE__);
$icons['rf433']                 = 'RF433';
$icons['rfid']                  = 'RFID';
$icons['solar-panel']           = __('Panneau solaire', __FILE__);
$icons['smartphone']            = __('Téléphone portable', __FILE__);
$icons['smoke-detector']        = __('Décteur de fumée', __FILE__);
$icons['sms']                   = __('SMS', __FILE__);
$icons['switch']                = __('Interrupteur', __FILE__);
$icons['sonometer']             = __('Sonomètre', __FILE__);
$icons['stove']                 = __('Poêle', __FILE__);
$icons['tasmota']               = 'Tasmota';
$icons['teleinfo']              = __('Téléinfo', __FILE__);
$icons['temp']                  = __('Température', __FILE__);
$icons['theengs']               = 'Theengs';
$icons['thermostat']            = __('Thermostat', __FILE__);
$icons['tv']                    = __('Télévison', __FILE__);
$icons['ups']                   = __('Onduleur', __FILE__);
$icons['volet']                 = __('Volet', __FILE__);
$icons['water-boiler']          = __('Chaudière', __FILE__);
$icons['wifi']                  = 'Wifi';
$icons['window-closed-variant'] = __('Fenêtre', __FILE__);
$icons['zigbee']                = 'Zigbee';
$icons['zwave']                 = 'ZWave';

/**
 * @param mixed $a
 * @param mixed $b
 * @return int
 */
function compareASCII($a, $b) {
    return strcmp(
        strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $a)),
        strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $b))
    );
}

// Mandatory for correct sorting: locale backup, change and restore
$old_locale = setlocale(LC_ALL, '0');
setlocale(LC_ALL, 'en_US.UTF-8');
uasort($icons, 'compareASCII');
setlocale(LC_ALL, $old_locale);

foreach ($icons as $id => $name) {
    echo '<option value="' . $id . '">' . $name . '</option>';
}

?></select>
            </div>
        </div>

        <div class="form-group toDisable">
            <label class="col-sm-3 control-label">&nbsp;</label>
            <div class="col-sm-3" style="text-align: center">
                <br/><img id="logo_visu" src="plugins/jMQTT/core/img/node_.svg" height="200" />
            </div>
        </div>

    </fieldset>
</form>
