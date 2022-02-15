<form class="form-horizontal">
	<fieldset>
		<div class="form-group toDisable">
			<label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
			<div class="col-sm-3">
				<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display: none;" />
				<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="type" style="display: none;" />
				<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement jMQTT}}" />
			</div>
		</div>
		<div class="form-group toDisable">
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
		<div class="form-group toDisable">
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

		<div class="form-group toDisable">
			<label class="col-sm-3 control-label"></label>
			<div class="col-sm-8">
				<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked />{{Activer}}</label>
				<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked />{{Visible}}</label>
			</div>
		</div>

		<div class="form-group typ-std">
			<label class="col-sm-3 control-label">{{Broker associé}}</label>
			<div class="col-sm-2">
				<select id="broker" class="eqLogicAttr form-control"></select>
			</div>
			<div class="col-sm-1">
				<a class="btn btn-success btn-sm eqLogicAction" data-action="move_broker"><i class="icon jeedomapp-done"></i></a>
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
				<input id="mqtttopic" type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="auto_add_topic" placeholder="{{Topic principal de l'équipement jMQTT}}" />
			</div>
		</div>

		<div class="form-group toDisable">
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

		<div class="form-group toDisable">
			<label class="col-sm-3 control-label">{{Dernière communication}}</label>
			<div class="col-sm-3">
				<span class="eqLogicAttr" data-l1key="status" data-l2key="lastCommunication"></span>
			</div>
		</div>

		<div class="form-group toDisable">
			<label class="col-sm-3 control-label">{{Commentaire}}</label>
			<div class="col-sm-3">
				<textarea class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="commentaire"></textarea>
			</div>
		</div>

		<div id='sel_icon_div' class="form-group toDisable typ-std">
			<label class="col-sm-3 control-label">{{Catégorie du topic}}</label>
			<div class="col-sm-3">
				<select id="sel_icon" class="form-control eqLogicAttr" data-l1key="configuration" data-l2key="icone">
					<option value="">{{Aucun}}</option>
					<option value="barometre">{{Baromètre}}</option>
					<option value="bt">{{Bluetooth}}</option>
					<option value="boiteauxlettres">{{Boite aux Lettres}}</option>
					<option value="water-boiler">{{Chaudière}}</option>
					<option value="chauffage">{{Chauffage}}</option>
					<option value="molecule-co">{{CO}}</option>
					<option value="compteur">{{Compteur}}</option>
					<option value="contact">{{Contact}}</option>
					<option value="feuille">{{Culture}}</option>
					<option value="custom">{{Custom}}</option>
					<option value="dimmer">{{Dimmer}}</option>
					<option value="energie">{{Energie}}</option>
					<option value="window-closed-variant">{{Fenêtre}}</option>
					<option value="garage">{{Garage}}</option>
					<option value="humidity">{{Humidité}}</option>
					<option value="humiditytemp">{{Humidité et Température}}</option>
					<option value="hydro">{{Hydrométrie}}</option>
					<option value="fire">{{Incendie}}</option>
					<option value="ir2">{{Infra Rouge}}</option>
					<option value="home-flood">{{Inondation}}</option>
					<option value="jauge">{{Jauge}}</option>
					<option value="lightbulb">{{Lumière}}</option>
					<option value="light">{{Luminosité}}</option>
					<option value="meteo">{{Météo}}</option>
					<option value="motion">{{Mouvement}}</option>
					<option value="multisensor">{{Multisensor}}</option>
					<option value="nab">{{Nabaztag}}</option>
					<option value="gate">{{Portail}}</option>
					<option value="door">{{Porte}}</option>
					<option value="prise">{{Prise}}</option>
					<option value="motion-sensor">{{Présence}}</option>
					<option value="power-plug">{{Prise de courant}}</option>
					<option value="radiator">{{Radiateur}}</option>
					<option value="relay">{{Relais}}</option>
					<option value="433">{{RF433}}</option>
					<option value="rfid">{{RFID}}</option>
					<option value="sms">{{SMS}}</option>
					<option value="bell">{{Sonnerie}}</option>
					<option value="remote">{{Télécommande}}</option>
					<option value="teleinfo">{{Téléinfo}}</option>
					<option value="tv">{{Télévison}}</option>
					<option value="temp">{{Température}}</option>
					<option value="thermostat">{{Thermostat}}</option>
					<option value="fan">{{Ventilation}}</option>
					<option value="volet">{{Volet}}</option>
					<option value="wifi">{{Wifi}}</option>
					<option value="zigbee">{{Zigbee}}</option>
					<option value="zwave">{{ZWave}}</option>
				</select>
			</div>
		</div>

		<div class="form-group typ-std">
			<div style="text-align: center">
				<img id="icon_visu" src="" height="200" />
			</div>
		</div>

	</fieldset>
</form>

<script>
$("#sel_icon").change(function() {
	var text = 'plugins/jMQTT/core/img/node_' + $("#sel_icon").val();
	$("#icon_visu").attr("src", text + '.svg');
});

$("#icon_visu").on("error", function () {
	if ($("#sel_icon").val() != '') {
		$(this).attr("src", 'plugins/jMQTT/core/img/node_' + $("#sel_icon").val() + '.png');
	}
});
</script>
