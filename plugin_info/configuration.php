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
  <div class="form-group">
    <fieldset>

    <div class="form-group">
            <label class="col-lg-4 control-label">{{IP de Mosquitto : }}</label>
            <div class="col-lg-4">
				<input id="mosquitto_por" class="configKey form-control" data-l1key="mqttAdress" style="margin-top:5px" placeholder="127.0.0.1"/>
            </div>
        </div>
    <div class="form-group">
            <label class="col-lg-4 control-label">{{Port de Mosquitto : }}</label>
            <div class="col-lg-4">
				<input id="mosquitto_por" class="configKey form-control" data-l1key="mqttPort" style="margin-top:5px" placeholder="1883"/>
            </div>
        </div>

        <div class="form-group">
                <label class="col-lg-4 control-label">{{Identifiant de Connexion : }}</label>
                <div class="col-lg-4">
    				<input id="mosquitto_por" class="configKey form-control" data-l1key="mqttId" style="margin-top:5px" placeholder="Jeedom"/>
                </div>
        </div>
        <div class="form-group">
                <label class="col-lg-4 control-label">{{Compte de Connexion (non obligatoire) : }}</label>
                <div class="col-lg-4">
    				<input id="mosquitto_por" class="configKey form-control" data-l1key="mqttUser" style="margin-top:5px" placeholder="Jeedom"/>
                </div>
         </div>
        <div class="form-group">
				<label class="col-lg-4 control-label">{{Mot de passe de Connexion (non obligatoire) : }}</label>
				<div class="col-lg-4">
					<input id="mosquitto_por" type="password" class="configKey form-control" data-l1key="mqttPass" style="margin-top:5px" placeholder="Jeedom"/>
				</div>
        </div>
		<div class="form-group">
				<label class="col-sm-4 control-label">{{Decouverte Automatique des topics: }}</label>
				<div class="col-sm-2">
					<input id="mosquitto_por" type="checkbox" class="configKey autoCheck" data-l1key="mqttAuto" onchange="auto_changed()" checked /> 					
				</div>
        </div>
		<div class="form-group" id="mqtt_topic">
				<label class="col-lg-4 control-label">{{Topic root (defaut: Tous): }}</label>
				<div class="col-lg-4">
					<input id="mosquitto_por" class="configKey form-control" data-l1key="mqttTopic" style="margin-top:5px" placeholder="#"/>
				</div>
        </div>
		<div class="form-group" id="mqtt_qos">
			<label class="col-lg-4 control-label">{{Qos}}</label>
			<div class="col-lg-4">
				<select style="width : 40pxpx;" class="configKey form-control" data-l1key="mqttQos">
					<option value="0">0</option>
					<option value="1" selected>1</option>
					<option value="2">2</option>
				</select>
			</div>
        </div>
    
	</fieldset>
</form>
 
<script type="text/javascript">
function auto_changed()
{
    if($('.autoCheck').is(":checked"))  { 
        $("#mqtt_topic").show();
		$("#mqtt_qos").show();
	}
    else {
        $("#mqtt_topic").hide();
		$("#mqtt_qos").hide();
	}
}
</script>
