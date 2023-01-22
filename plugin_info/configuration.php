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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
	include_file('desktop', '404', 'php');
	die();
}

// Send Mosquitto installation status
sendVarToJS('mStatus', class_exists('jMQTT') ? jMQTT::mosquittoCheck() : array('installed' => false, 'message' => __("Etat inconnu", __FILE__), 'service' => ''));

$docker = file_exists('/.dockerenv') || config::byKey('forceDocker', 'jMQTT', '0') == '1';
sendVarToJS('dStatus', $docker);

?>
<form class="form-horizontal">
	<div class="row">
	<div class="col-sm-6">
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
				<i class="fas fa-sync fa-spin" style="display:none;"></i> <i class="fas fa-save"></i> {{Installer}}</a>
			</div>
			<div class="col-sm-2">
				<a id="bt_mosquittoRepare" class="btn btn-warning disabled" style="width:100%;"
					title="{{Supprime la configuration actuelle de Mosquitto et remet la configuration par défaut de jMQTT. Cette option est particulièrement intéressante dans le cas où un autre plugin a déjà installé Mosquitto et que vous souhaitez que jMQTT le remplace.}}">
				<i class="fas fa-sync fa-spin" style="display:none;"></i> <i class="fas fa-magic"></i> {{Réparer}}</a>
			</div>
			<div class="col-sm-2">
				<a id="bt_mosquittoRemove" class="btn btn-danger disabled" style="width:100%;"
					title="{{Supprime complètement Mosquitto du système, par exemple dans le cas où vous voulez arrêter d'utiliser Mosquitto en local, ou pour le réinstaller avec un autre plugin.}}">
				<i class="fas fa-sync fa-spin" style="display:none;"></i> <i class="fas fa-trash"></i> {{Supprimer}}</a>
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
	$regularVal = jMQTT::get_callback_url();
	$overrideEn = config::byKey('urlOverrideEnable', 'jMQTT', '0') == '1';
	$overrideVal = config::byKey('urlOverrideValue', 'jMQTT', $regularVal);
	$curVal = ($overrideEn) ? $overrideVal : $regularVal;
?>
		<legend><i class="fab fa-docker "></i>{{Paramètres spécifiques Docker}}</legend>
		<div class="form-group">
			<label class="col-sm-4 control-label">{{URL de Callback du Démon}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
				title="{{Si Jeedom tourne en Docker, des problèmes d'identification entre ports internes et externes peuvent survenir.<br />
				Dans ce cas uniquement, il peut être nécessaire de personnaliser cette url, car elle est mal détectée par jMQTT.<br />
				<b>N'activez ce champ et ne touchez à cette valeur que si vous savez ce que vous faites !</b>}}"></i></sup></label>
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
		<div class="form-group"><br /></div>
	</div>
	<div class="col-sm-6">
<!-- TODO NEW Uncomment when Backup/Restore jMQTT is OK
		<legend><i class="fas fa-folder-open"></i>{{Sauvegarder les équipements et la configuation de jMQTT}}</legend>
		<div class="form-group">
			<label class="col-sm-1 control-label"> </label>
			<div class="col-sm-5">
				<a class="btn btn-success" id="bt_backupJMqttStart" style="width:100%;"><i class="fas fa-sync fa-spin" style="display:none;"></i> <i class="fas fa-save"></i> {{Lancer une sauvegarde}}</a>
			</div>
			<div class="col-sm-6"></div>
		</div>
		<legend><i class="fas fa-tape"></i>{{Sauvegardes disponibles}}</legend>
		<div class="form-group">
			<label class="col-sm-1 control-label"> </label>
			<div class="col-sm-10">
				<select class="form-control" id="sel_backupJMqtt">
<?php
// List all jMQTT backup files
$backup_dir = realpath(__DIR__ . '/../data/backups');
$backups = ls($backup_dir, '*.tgz', false, array('files', 'quiet', 'datetime_asc'));
foreach ($backups as $backup)
	echo '<option value="'.$backup.'">'.$backup.' ('.sizeFormat(filesize($backup_dir.'/'.$backup)).")</option>\n";
?>
				</select>
			</div>
			<div class="col-sm-1"></div>
		</div>
		<div class="form-group">
			<label class="col-sm-1 control-label"> </label>
			<div class="col-sm-5">
				<a class="btn btn-danger" id="bt_backupJMqttRemove" style="width:100%;"><i class="fas fa-trash"></i> {{Supprimer la sauvegarde}}</a>
			</div>
			<div class="col-sm-5">
				<a class="btn btn-warning" id="bt_backupJMqttRestore" style="width:100%;"><i class="fas fa-sync fa-spin" style="display:none;"></i> <i class="far fa-file"></i> {{Restaurer la sauvegarde}}</a>
			</div>
			<div class="col-sm-1"></div>
		</div>
		<div class="form-group">
			<label class="col-sm-1 control-label"> </label>
			<div class="col-sm-5">
					<a class="btn btn-success" id="bt_backupJMqttDownload" style="width:100%;"><i class="fas fa-cloud-download-alt"></i> {{Télécharger la sauvegarde}}</a>
			</div>
			<div class="col-sm-5">
				<span class="btn btn-default btn-file" style="width:100%;">
					<i class="fas fa-cloud-upload-alt"></i> {{Ajouter une sauvegarde}}<input id="bt_backupJMqttUpload" type="file" accept=".tgz" name="file" data-url="plugins/jMQTT/core/ajax/jMQTT.ajax.php?action=uploadBackup">
				</span>
			</div>
			<div class="col-sm-1"></div>
		</div>
-->
		<div class="form-group"><br /></div>
	</div>
	</div>
</form>
<script>
// Remove unneeded Save button
$('#bt_savePluginConfig').remove();

// Copy of jmqtt.callPluginAjax() to handle "My plugins" page when jMQTT.functions.js is not included
function jmqttAjax(_params) {
	$.ajax({
		async: _params.async == undefined ? true : _params.async,
		global: false,
		type: "POST",
		url: "plugins/jMQTT/core/ajax/jMQTT.ajax.php",
		data: _params.data,
		dataType: 'json',
		error: function (request, status, error) {
				if (typeof _params.error === 'function')
					_params.error(request, status, error);
				else
					handleAjaxError(request, status, error);
		},
		success: function (data) {
			if (typeof _params.success === 'function')
				_params.success(data);
		}
	});
}

// Toggle spinner icon on button click
function toggleIco(_this) {
	var h = _this.find('i.fas:hidden');
	var v = _this.find('i.fas:visible');
	v.hide();
	h.show();
}

if (!dStatus) {

	// Helper to set buttons and texts
	function mosquittoStatus(_result) {
		if (_result.installed) {
			$('#bt_mosquittoInstall').addClass('disabled');
			$('#bt_mosquittoRepare').removeClass('disabled');
			$('#bt_mosquittoRemove').removeClass('disabled');
			$('#mosquittoService').empty().html(_result.service);
			$('.local-install').show();
			if (_result.service.includes('running'))
				$('#bt_mosquittoStop').removeClass('disabled');
			else
				$('#bt_mosquittoStop').addClass('disabled');
			if (_result.message.includes('jMQTT'))
				$('#bt_mosquittoEdit').show();
			else
				$('#bt_mosquittoEdit').hide();
		} else {
			$('#bt_mosquittoInstall').removeClass('disabled');
			$('#bt_mosquittoRepare').addClass('disabled');
			$('#bt_mosquittoRemove').addClass('disabled');
			$('.local-install').hide();
		}
		$('#mosquittoStatus').empty().html(_result.message);
	}

	// Set Mosquitto status
	$(document).ready(function() {
		mosquittoStatus(mStatus);
	});

	// Launch Mosquitto installation and wait for it to end
	$('#bt_mosquittoInstall').on('click', function () {
		if (!$(this).hasClass('disabled')) {
			var btn = $(this);
			bootbox.confirm('{{Etes-vous sûr de vouloir installer le service Mosquitto en local ?}}', function (result) {
				if (result) {
					toggleIco(btn);
					jmqttAjax({
						data: { action: "mosquittoInstall" },
						error: function (request, status, error) {
							toggleIco(btn);
							handleAjaxError(request, status, error);
						},
						success: function(data) {
							toggleIco(btn);
							if (data.state == 'ok') {
								mosquittoStatus(data.result);
								$.fn.showAlert({message: '{{Le service Mosquitto a bien été installé et configuré.}}', level: 'success'});
							} else {
								$.fn.showAlert({message: data.result, level: 'danger'});
							}
						}
					});
				}
			});
		}
	});

	// Launch Mosquitto reparation and wait for it to end
	$('#bt_mosquittoRepare').on('click', function () {
		if (!$(this).hasClass('disabled')) {
			var btn = $(this);
			bootbox.confirm('{{Etes-vous sûr de vouloir réparer le service Mosquitto local ?}}', function (result) {
				if (result) {
					toggleIco(btn);
					jmqttAjax({
						data: { action: "mosquittoRepare" },
						error: function (request, status, error) {
							toggleIco(btn);
							handleAjaxError(request, status, error);
						},
						success: function(data) {
							toggleIco(btn);
							if (data.state == 'ok') {
								mosquittoStatus(data.result);
								$.fn.showAlert({message: '{{Le service Mosquitto a bien été réparé.}}', level: 'success'});
							} else {
								$.fn.showAlert({message: data.result, level: 'danger'});
							}
						}
					});
				}
			});
		}
	});

	// Launch Mosquitto uninstall and wait for it to end
	$('#bt_mosquittoRemove').on('click', function () {
		if (!$(this).hasClass('disabled')) {
			var btn = $(this);
			bootbox.confirm('{{Etes-vous sûr de vouloir supprimer le service Mosquitto local ?}}', function (result) {
				if (result) {
					toggleIco(btn);
					jmqttAjax({
						data: { action: "mosquittoRemove" },
						error: function (request, status, error) {
							toggleIco(btn);
							handleAjaxError(request, status, error);
						},
						success: function(data) {
							toggleIco(btn);
							if (data.state == 'ok') {
								mosquittoStatus(data.result);
								$.fn.showAlert({message: '{{Le service Mosquitto a bien été désinstallé du système.}}', level: 'success'});
							} else {
								$.fn.showAlert({message: data.result, level: 'danger'});
							}
						}
					});
				}
			});
		}
	});

	// Start/restart Mosquitto service
	$('#bt_mosquittoReStart').on('click', function () {
		jmqttAjax({
			data: { action: "mosquittoReStart" },
			error: function (request, status, error) {
				handleAjaxError(request, status, error);
			},
			success: function(data) {
				if (data.state == 'ok') {
					mosquittoStatus(data.result);
					$.fn.showAlert({message: '{{Le service Mosquitto a bien été (re)démarré.}}', level: 'success'});
				} else {
					$.fn.showAlert({message: data.result, level: 'danger'});
				}
			}
		});
	});

	// Stop Mosquitto service
	$('#bt_mosquittoStop').on('click', function () {
		if (!$(this).hasClass('disabled')) {
			jmqttAjax({
				data: { action: "mosquittoStop" },
				error: function (request, status, error) {
					handleAjaxError(request, status, error);
				},
				success: function(data) {
					if (data.state == 'ok') {
						mosquittoStatus(data.result);
						$.fn.showAlert({message: '{{Le service Mosquitto a bien été arrêté.}}', level: 'success'});
					} else {
						$.fn.showAlert({message: data.result, level: 'danger'});
					}
				}
			});
		}
	});

	// Modify jMQTT.conf in Mosquitto service system folder
	$('#bt_mosquittoEdit').on('click', function () {
		jmqttAjax({
			data: { action: "mosquittoConf" },
			error: function (request, status, error) {
				handleAjaxError(request, status, error);
			},
			success: function(result1) {
				if (result1.state == 'ok') {
					bootbox.confirm({
						title: '{{Modifier le fichier jMQTT.conf du service Mosquitto}}',
						message: '<textarea class="bootbox-input bootbox-input-text form-control" type="text" style="height: 50vh;font-family:CamingoCode,monospace; font-size:small!important; line-height:normal;" id="mosquittoConf">' + result1.result + '</textarea>',
						callback: function (result2) {
							if (result2) {
								jmqttAjax({
									data: { action: "mosquittoEdit", config: $('#mosquittoConf').value() },
									error: function (request, status, error) {
										handleAjaxError(request, status, error);
									},
									success: function(result3) {
										if (result3.state == 'ok') {
											$.fn.showAlert({message: '{{Le fichier jMQTT.conf a bien été modifiée.<br />Redémarrez le service Mosquitto pour le prendre en compte.}}', level: 'success'});
										} else {
											$.fn.showAlert({message: result3.result, level: 'danger'});
										}
									}
								});
							}
						}
					});
				} else {
					$.fn.showAlert({message: result1.result, level: 'danger'});
				}
			}
		});

	});

} else {

	$('#bt_jmqttUrlOverride').on('click', function () {
		var $valEn = $('#jmqttUrlOverrideEnable').value()
		jmqttAjax({
			data: {
				action: "updateUrlOverride",
				valEn: $valEn,
				valUrl: (($valEn == '1') ? $('#jmqttUrlOverrideValue').value() : $('#jmqttUrlOverrideValue').attr('valOver'))
			},
			success: function(data) {
				if (data.state != 'ok')
					$.fn.showAlert({message: data.result,level: 'danger'});
				else
					$.fn.showAlert({message: '{{Modification effectuée. Relancez le Démon.}}', level: 'success'});
			}
		});
	});

	$('#jmqttUrlOverrideEnable').change(function() {
		$oVal = $('#jmqttUrlOverrideValue');
		if ($(this).value() == '1') {
			if ($oVal.attr('valOver') != "")
				$oVal.value($oVal.attr('valOver'));
			$oVal.removeClass('disabled');
		} else {
			$oVal.attr('valOver', $oVal.value());
			$oVal.value($oVal.attr('valStd'));
			$oVal.addClass('disabled');
		}
	});

}
// Launch jMQTT backup and wait for it to end
$('#bt_backupJMqttStart').on('click', function () {
	console.log('bt_backupJMqttStart');
});

// Remove selected jMQTT backup
$('#bt_backupJMqttRemove').on('click', function () {
	if (!$('#sel_backupJMqtt option:selected').length)
		return;
	var btn = $(this)
	bootbox.confirm('{{Êtes-vous sûr de vouloir supprimer}} <b>' + $('#sel_backupJMqtt option:selected').text() + '</b> ?', function(result) {
		if (!result)
			return;
		jmqttAjax({
			data: {
				action: "removeBackup",
				file: $('#sel_backupJMqtt').value()
			},
			error: function (request, status, error) {
				handleAjaxError(request, status, error);
			},
			success: function(data) {
				if (data.state == 'ok') {
					$.fn.showAlert({message: '{{Sauvegarde supprimée.}}', level: 'success'});
					$('#sel_backupJMqtt option:selected').remove();
				} else {
					$.fn.showAlert({message: data.result, level: 'danger'});
				}
			}
		});
	});
});

// Launch jMQTT restoration and wait for it to end
$('#bt_backupJMqttRestore').on('click', function () {
	if (!$('#sel_backupJMqtt option:selected').length)
		return;
	var btn = $(this)
	bootbox.confirm('{{Êtes-vous sûr de vouloir restaurer}} <b>' + $('#sel_backupJMqtt option:selected').text() + '</b> ?', function(result) {
		if (!result)
			return;
		toggleIco(btn);
		// TODO
		console.log('bt_backupJMqttRestore:', $('#sel_backupJMqtt').value());
		toggleIco(btn);
	});
});

// Download the selected jMQTT backup
$('#bt_backupJMqttDownload').on('click', function () {
	if (!$('#sel_backupJMqtt option:selected').length)
		return;
	window.open('core/php/downloadFile.php?pathfile=' + $('#sel_backupJMqtt').value(), "_blank", null);
});

// Add a new jMQTT backup file by upload to the list
$('#bt_backupJMqttUpload').fileupload({
	dataType: 'json',
	replaceFileInput: false,
	done: function(e, data) {
		if (data.result.state != 'ok') {
			$.fn.showAlert({message: data.result.result, level: 'danger'});
			return;
		}
		var oVal = data.result.result.name;
		var oText = data.result.result.name + ' (' + data.result.result.size +')';
		$('#sel_backupJMqtt').append('<option selected value="' + oVal + '">' + oText + '</option>');
		$.fn.showAlert({message: '{{Fichier(s) ajouté(s) avec succès}}', level: 'success'})
	}
});

// Send log level to daemon dynamically
$btSave = $('#bt_savePluginLogConfig');
if (!$btSave.hasClass('jmqttLog')) { // Avoid multiple declaration of the event on the button
	$btSave.addClass('jmqttLog');
	$btSave.on('click', function() {
		var level = $('input.configKey[data-l1key="log::level::jMQTT"]:checked')
		if (level.length == 1) { // Found 1 checked log::level::jMQTT input
			jmqttAjax({
				data: {
					action: "sendLoglevel",
					level: level.attr('data-l2key')
				},
				success: function(data) {
					if (data.state == 'ok')
						$.fn.showAlert({message: "{{Le démon est averti, il n'est pas nécessire de le redémarrer.}}", level: 'success'});
				}
			});
		}
	});
};
</script>
