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

require_once __DIR__ . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}

require_once __DIR__ . '/../core/class/jMQTT.class.php';
sendVarToJS('version', config::byKey('version', 'jMQTT', 'unknown', true));


// Send Mosquitto installation status
sendVarToJS('mStatus', class_exists('jMQTT') ? jMQTTPlugin::mosquittoCheck() : array('installed' => false, 'message' => __("Etat inconnu", __FILE__), 'service' => ''));

$docker = file_exists('/.dockerenv') || config::byKey('forceDocker', 'jMQTT', '0') == '1';
sendVarToJS('dStatus', $docker);

?>
<form class="form-horizontal" style="min-height: 250px;">
    <div class="row">
    <div class="col-lg-6 col-sm-12">
<?php
if (!$docker) {
?>
        <legend><i class="fas fa-toolbox"></i>{{Broker MQTT en local (Service Mosquitto)}}</legend>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{Etat d'installation}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                title="{{Si Mosquitto est installé en local, jMQTT essaye de détecter par quel plugin.}}"></i></sup></label>
            <div class="col-sm-8">
                <span id="mosquittoStatus"></span>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{Installation locale}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                title="{{Ces boutons permettent de gérer l'installation de Mosquitto en tant que service local sur ce système Jeedom.}}"></i></sup></label>
            <div class="col-sm-2">
                <a id="bt_mosquittoInstall" class="btn btn-success disabled" style="width:100%;" title="{{Lance l'installation de Mosquitto en local.}}">
                <i class="fas fa-sync fa-spin" style="display:none;"></i>&nbsp;<i class="fas fa-save"></i> {{Installer}}</a>
            </div>
            <div class="col-sm-2">
                <a id="bt_mosquittoRepare" class="btn btn-warning disabled" style="width:100%;"
                    title="{{Supprime la configuration actuelle de Mosquitto et remet la configuration par défaut de jMQTT.<br/>Cette option est particulièrement intéressante dans le cas où un autre plugin a déjà installé Mosquitto et que vous souhaitez que jMQTT le remplace.}}">
                <i class="fas fa-sync fa-spin" style="display:none;"></i>&nbsp;<i class="fas fa-magic"></i> {{Réparer}}</a>
            </div>
            <div class="col-sm-2">
                <a id="bt_mosquittoRemove" class="btn btn-danger disabled" style="width:100%;"
                    title="{{Supprime complètement Mosquitto du système, par exemple dans le cas où vous voulez arrêter d'utiliser Mosquitto en local, ou pour le réinstaller avec un autre plugin.}}">
                <i class="fas fa-sync fa-spin" style="display:none;"></i>&nbsp;<i class="fas fa-trash"></i> {{Supprimer}}</a>
            </div>
        </div>
        <div class="form-group local-install" style="display:none;">
            <label class="col-sm-4 control-label">{{Etat du service}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                title="{{Si Mosquitto est installé en local, jMQTT remonte ici l'état du service (systemd).}}"></i></sup></label>
            <div class="col-sm-8">
                <span id="mosquittoService"></span>
            </div>
        </div>
        <div class="form-group local-install" style="display:none;">
            <label class="col-sm-4 control-label">{{Service Mosquitto}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                title="{{Ces boutons permettent de gérer l'état de Mosquitto en tant que service local sur ce système Jeedom.}}"></i></sup></label>
            <div class="col-sm-3">
                <a id="bt_mosquittoReStart" class="btn btn-success" style="width:100%;" title="{{Démarre (ou redémarre) le service Mosquitto local.}}">
                <i class="fas fa-play"></i> {{(Re)Démarrer}}</a>
            </div>
            <div class="col-sm-3">
                <a id="bt_mosquittoStop" class="btn btn-danger disabled" style="width:100%;" title="{{Arrête le service Mosquitto local.}}">
                <i class="fas fa-stop"></i> {{Arrêter}}</a>
            </div>
            <div class="col-sm-1">
                <a id="bt_mosquittoEdit" class="btn btn-warning" style="width:100%;display:none;" title="{{Edition du fichier de configuration jMQTT.conf du service Mosquitto local.}}">
                <i class="fas fa-pen"></i></a>
            </div>
        </div>
<?php
} /* !$docker */

if ($docker) {
    // To fix issue: https://community.jeedom.com/t/87727/39
    $regularVal = jMQTTDaemon::get_callback_url();
    $overrideEn = config::byKey('urlOverrideEnable', 'jMQTT', '0') == '1';
    $overrideVal = config::byKey('urlOverrideValue', 'jMQTT', $regularVal);
    $curVal = ($overrideEn) ? $overrideVal : $regularVal;
?>
        <legend><i class="fab fa-docker "></i>{{Paramètres spécifiques Docker}}</legend>
        <div class="form-group">
            <label class="col-sm-4 control-label">{{URL de Callback du Démon}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                title="{{Si Jeedom tourne en Docker, des problèmes d'identification entre ports internes et externes peuvent survenir.<br/>Dans ce cas uniquement, il peut être nécessaire de personnaliser cette url, car elle est mal détectée par jMQTT.<br/><b>N'activez ce champ et ne touchez à cette valeur que si vous savez ce que vous faites !</b>}}"></i></sup></label>
            <div class="col-sm-7">
                <div class="row">
                    <div class="col-sm-1">
                        <input type="checkbox" class="form-control" <?php if ($overrideEn) echo 'checked'; ?> id="jmqttUrlOverrideEnable" />
                    </div>
                    <div class="col-sm-10">
                        <input class="form-control<?php if (!$overrideEn) echo ' disabled'; ?>" id="jmqttUrlOverrideValue"
                            value="<?php echo $curVal; ?>" valOver="<?php echo $overrideVal; ?>" valStd="<?php echo $regularVal; ?>" />
                    </div>
                    <div class="col-sm-1">
                        <span class="btn btn-success btn-sm" id="bt_jmqttUrlOverride" style="position:relative;margin-top: 2px;" title="{{Appliquer}}">
                            <i class="fas fa-check"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-sm-1"></div>
        </div>
<?php } /* $docker */ ?>
    </div>
    <div class="col-lg-6 col-sm-12">
        <legend><i class="fas fa-folder-open"></i>{{Sauvegarder les équipements et la configuration de jMQTT}}</legend>
        <div class="form-group">
            <label class="col-sm-1 control-label">&nbsp;</label>
            <div class="col-sm-5">
                <a class="btn btn-success" id="bt_backupJMqttStart" style="width:100%;"><i class="fas fa-sync fa-spin" style="display:none;"></i> <i class="fas fa-save"></i> {{Lancer une sauvegarde}}</a>
            </div>
            <div class="col-sm-6"></div>
        </div>
        <legend><i class="fas fa-tape"></i>{{Sauvegardes disponibles}}</legend>
        <div class="form-group">
            <label class="col-sm-1 control-label">&nbsp;</label>
            <div class="col-sm-10">
                <select class="form-control" id="sel_backupJMqtt">
<?php
// List all jMQTT backup files
$backup_dir = realpath(__DIR__ . '/../' . jMQTTConst::PATH_BACKUP);
$backups = ls($backup_dir, '*.tgz', false, array('files', 'quiet'));
rsort($backups);
foreach ($backups as $backup)
    echo '<option value="'.$backup.'">'.$backup.' ('.sizeFormat(filesize($backup_dir.'/'.$backup)).")</option>\n";
?>
                </select>
            </div>
            <div class="col-sm-1"></div>
        </div>
        <div class="form-group">
            <label class="col-sm-1 control-label">&nbsp;</label>
            <div class="col-sm-5">
                <a class="btn btn-danger" id="bt_backupJMqttRemove" style="width:100%;"><i class="fas fa-trash"></i> {{Supprimer la sauvegarde}}</a>
            </div>
            <div class="col-sm-5">
                <a class="btn btn-warning" id="bt_backupJMqttRestore" style="width:100%;"><i class="fas fa-sync fa-spin" style="display:none;"></i>&nbsp;<i class="far fa-file"></i> {{Restaurer la sauvegarde}} <span class="danger">(BETA)</span></a>
            </div>
            <div class="col-sm-1"></div>
        </div>
        <div class="form-group">
            <label class="col-sm-1 control-label">&nbsp;</label>
            <div class="col-sm-5">
                    <a class="btn btn-success" id="bt_backupJMqttDownload" style="width:100%;"><i class="fas fa-cloud-download-alt"></i> {{Télécharger la sauvegarde}}</a>
            </div>
            <div class="col-sm-5">
                <span class="btn btn-info btn-file" style="width:100%;">
                    <i class="fas fa-cloud-upload-alt"></i> {{Ajouter une sauvegarde}}<input id="bt_backupJMqttUpload" type="file" accept=".tgz" name="file" data-url="plugins/jMQTT/core/ajax/jMQTT.ajax.php?action=fileupload&amp;dir=backup">
                </span>
            </div>
            <div class="col-sm-1"></div>
        </div>
    </div>
    </div>
</form>
<?php include_file('desktop', 'jMQTT.config', 'js', 'jMQTT'); ?>
