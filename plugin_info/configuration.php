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

?>
<form class="form-horizontal">
	<div class="row">
	<div class="col-sm-6">
		<legend><i class="fas fa-cog"></i>{{Installation}}</legend>
		<div class="form-group">
			<label class="col-sm-4 control-label">{{Installer Mosquitto}}</label>
			<div class="col-sm-8">
				<input type="checkbox" class="configKey" data-l1key="installMosquitto" />
			</div>
		</div>
<?php
if (file_exists('/.dockerenv') || config::byKey('forceDocker', 'jMQTT', '0') == '1') {
	// To fix issue: https://community.jeedom.com/t/87727/39
	$regularVal = jMQTT::get_callback_url();
	$overrideEn = config::byKey('urlOverrideEnable', 'jMQTT', '0') == '1';
	$overrideVal = config::byKey('urlOverrideValue', 'jMQTT', $regularVal);
	$curVal = ($overrideEn) ? $overrideVal : $regularVal;
?>
		<div class="form-group">
			<label class="col-sm-4 control-label" style="color:var(--al-danger-color);">{{URL de Callback}} <sup><i class="fa fa-question-circle tooltips"
				title="{{Si Jeedom tourne en Docker, des problèmes d'identification entre ports internes et externes peuvent survenir.<br />
				Dans ce cas uniquement, il peut être nécessaire de personaliser cette url, car elle est mal détectée par jMQTT.<br />
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
			<div class="col-sm-1">
			</div>
		</div>
<?php } ?>
	</div>
	<div class="col-sm-6">
<!-- TODO (important) Backup/restore completly jMQTT
		<legend><i class="fas fa-exchange-alt"></i>{{Sauvegarde et Restauration}}</legend>
		<div class="form-group ">
			<div class="col-sm-6 col-xs-6">
				<a class="btn btn-success bt_backupJMQTT" style="width:100%;"><i class="fas fa-sync fa-spin" style="display:none;"></i> <i class="fas fa-save"></i> {{Sauvegarder en l'état jMQTT}}</a>
			</div>
			<div class="col-sm-6 col-xs-12">
				<a class="btn btn-warning" id="bt_restoreJMQTT" style="width:100%;"><i class="fas fa-sync fa-spin" style="display:none;"></i> <i class="far fa-file"></i> {{Restaurer une sauvegarde de jMQTT}}</a>
			</div>
		</div>
-->
		<div class="form-group"><br /></div>
	</div>
	</div>
</form>
<script>
$('#bt_jmqttUrlOverride').on('click', function (){
	var $valEn = $('#jmqttUrlOverrideEnable').value()
	$.ajax({
		type: "POST",
		url: "plugins/jMQTT/core/ajax/jMQTT.ajax.php",
		data: {
			action: "updateUrlOverride",
			valEn: $valEn,
			valUrl: (($valEn == '1') ? $('#jmqttUrlOverrideValue').value() : $('#jmqttUrlOverrideValue').attr('valOver'))
		},
		global : false,
		dataType: 'json',
		error: function(request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function(data) {
			if (data.state != 'ok')
				$.fn.showAlert({message: data.result,level: 'danger'});
			else
				$.fn.showAlert({message: '{{Modification effectuée. Relancez le Démon.}}' ,level: 'success'});
		}
	});
});

$('#jmqttUrlOverrideEnable').change(function(){
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

$btSave = $('#bt_savePluginLogConfig');
if (!$btSave.hasClass('jmqttLog')) { // Avoid multiple declaration of the event on the button
	$btSave.addClass('jmqttLog');
	$btSave.on('click', function() {
		var level = $('input.configKey[data-l1key="log::level::jMQTT"]:checked')
		if (level.length == 1) { // Found 1 checked log::level::jMQTT input
			$.ajax({
				type: "POST",
				url: "plugins/jMQTT/core/ajax/jMQTT.ajax.php",
				data: {
					action: "sendLoglevel",
					level: level.attr('data-l2key')
				},
				global : false,
				dataType: 'json',
				error: function(request, status, error) {
					handleAjaxError(request, status, error);
				},
				success: function(data) {
					if (data.state == 'ok')
						$.fn.showAlert({message: "{{Le démon est averti, il n'est pas nécessire de le redémarrer.}}" ,level: 'success'});
				}
			});
		}
	});
};

</script>
