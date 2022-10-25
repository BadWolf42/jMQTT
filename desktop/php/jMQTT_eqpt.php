<br/>
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

		<div class="form-group toDisable typ-std">
			<label class="col-sm-3 control-label">{{Commande d'état de la batterie}}</label>
			<div class="col-sm-3">
				<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="battery_cmd" title="{{Commande information de Batterie}}">
					<option value="">{{Aucune}}</option>
				</select>
			</div>
		</div>

		<div class="form-group toDisable typ-std">
			<label class="col-sm-3 control-label">{{Commande de disponibilité}}</label>
			<div class="col-sm-3">
				<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="availability_cmd" title="{{Commande information de disponibilité}}">
					<option value="">{{Aucune}}</option>
				</select>
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

		<div class="form-group toDisable typ-std">
			<label class="col-sm-3 control-label">{{Catégorie du topic}}</label>
			<div class="col-sm-3">
				<select class="form-control eqLogicAttr" data-l1key="configuration" data-l2key="icone"></select>
			</div>
		</div>

		<div class="form-group toDisable">
			<label class="col-sm-3 control-label"></label>
			<div class="col-sm-3" style="text-align: center">
				<br /><img id="logo_visu" src="" height="200" />
			</div>
		</div>

	</fieldset>
</form>
