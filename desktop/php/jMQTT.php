<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'jMQTT');
$eqLogics = eqLogic::byType('jMQTT');
?>

<div id="div_newEqptMsg"></div>
<div id="div_inclusionModeMsg"></div>
<div class="row row-overflow">
    <div class="col-lg-2 col-sm-3 col-sm-4">
	<div class="bs-sidebar">
	    <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
		<a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter un équipement}}</a>
		<li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
		<?php
		foreach ($eqLogics as $eqLogic) {
		    $opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
		    echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '" style="' . $opacity . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
		}
		?>
	    </ul>
	</div>
    </div>

    <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
	<legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
	<div class="eqLogicThumbnailContainer">
	    <div class="cursor eqLogicAction" data-action="add" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
		<center>
		    <i class="fa fa-plus-circle" style="font-size:6em;color:#f8d800;"></i>
		</center>
		<span style="font-size : 1.1em;font-weight: bold;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#f8d800;"><center>{{Ajouter}}</center></span>
	    </div>

	    <?php
	    // Insert the automatic inclusion button: display according to the include_mode configuration parameter is done at the end of this page
	    ?>
	    <div class="cursor bt_changeIncludeMode include card" data-mode="0" style="background-color:#ffffff;height:140px;margin-bottom:10px;padding:5px;border-radius:2px;width:160px;margin-left:10px;">
		<center><i class="fa fa-sign-in fa-rotate-90" style="font-size : 6em;color:#f8d800;"></i></center>
		<span style="font-size:1.1em;font-weight:bold;position:relative;top:15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#f8d800;"><center></center></span>
	    </div>

<!-- 	    
	    if (config::byKey('include_mode', 'jMQTT', 0) == 1) {
		echo '<div class="cursor bt_changeIncludeMode include card" data-mode="1" style="background-color : #8000FF; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
		echo '<center>';
		echo '<i class="fa fa-sign-in fa-rotate-90" style="font-size : 6em;color:#f8d800;"></i>';
		echo '</center>';
		echo '<span style="font-size:1.1em;font-weight:bold;position:relative;top:15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#f8d800;"><center>{{Arrêter inclusion}}</center></span>';
		echo '</div>';
	    } else {
		echo '<div class="cursor bt_changeIncludeMode include card" data-mode="0" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
		echo '<center>';
		echo '<i class="fa fa-sign-in fa-rotate-90" style="font-size : 6em;color:#f8d800;"></i>';
		echo '</center>';
		echo '<span style="font-size : 1.1em;font-weight: bold;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#f8d800;"><center>{{Mode inclusion}}</center></span>';
		echo '</div>';
	    }
	     -->
	    <div class="cursor eqLogicAction" data-action="gotoPluginConf" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
		<center>
		    <i class="fa fa-wrench" style="font-size:5.4em;color:#767676;"></i>
		</center>
		<span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word"><center>{{Configuration}}</center></span>
	    </div>
	    <div class="cursor" id="bt_healthMQTT" style="background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
		<center>
		    <i class="fa fa-medkit" style="font-size:6em;color:#767676;"></i>
		</center>
		<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word"><center>{{Santé}}</center></span>
	    </div>
	</div>


	<legend><i class="fa fa-table"></i>  {{Mes jMQTT}}
	</legend>
	<div class="eqLogicThumbnailContainer">
	    <?php
	    $dir = dirname(__FILE__) . '/../../resources/images/';
	    $files = scandir($dir);
	    foreach ($eqLogics as $eqLogic) {
		$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
		if ($eqLogic->getConfiguration('auto_add_cmd', 1)  == 1)
		    echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff;border:8px solid #8000FF;height:200px;margin-bottom:10px;padding:5px;border-radius:18px;width:160px;margin-left:10px;' . $opacity . '" >';
		else
		    echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff;height:200px;margin-bottom:10px;padding:5px;border-radius:2px;width:160px;margin-left:10px;' . $opacity . '" >';
		echo "<center>";
		$test = 'node_' . $eqLogic->getConfiguration('icone') . '.png';
		if (in_array($test, $files)) {
		    $path = 'node_' . $eqLogic->getConfiguration('icone');
		} else {
		    $path = 'mqtt_icon';
		}
		echo '<img src="plugins/jMQTT/resources/images/' . $path . '.png" height="105" width="95" />';
		echo "</center>";
		echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
		echo '</div>';
	    }
	    ?>
	</div>
    </div>
    <div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
	<a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
	<a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
	<a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
	<a class="btn btn-default eqLogicAction pull-right" data-action="copy"><i class="fa fa-files-o"></i> {{Dupliquer}}</a>
	<ul class="nav nav-tabs" role="tablist">
	    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
	    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
	    <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
	</ul>
	<div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
	    <div role="tabpanel" class="tab-pane active" id="eqlogictab">
		<form class="form-horizontal">
		    <fieldset>
			<div class="form-group">
			    <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
			    <div class="col-sm-3">
				<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
				<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement jMQTT}}"/>
			    </div>
			</div>
			<div class="form-group">
			    <label class="col-sm-3 control-label" >{{Objet parent}}</label>
			    <div class="col-sm-3">
				<select class="form-control eqLogicAttr" data-l1key="object_id">
				    <option value="">{{Aucun}}</option>
				    <?php
				    foreach (object::all() as $object) {
					echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
				    }
				    ?>
				</select>
			    </div>
			</div>
			<div class="form-group">
			    <label class="col-sm-3 control-label">{{Catégorie}}</label>
			    <div class="col-sm-8">
				<?php
				foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
				    echo '<label class="checkbox-inline">';
				    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
				    echo '</label>';
				}
				?>

			    </div>
			</div>

			<div class="form-group">
			    <label class="col-sm-3 control-label" ></label>
			    <div class="col-sm-8">
				<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
				<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
			    </div>
			</div>

			<div class="form-group">
			    <label class="col-sm-3 control-label">{{Commentaire}}</label>
			    <div class="col-sm-3">
				<textarea class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="commentaire" ></textarea>
			    </div>
			</div>

			<div class="form-group">
			    <label class="col-sm-3 control-label">{{Inscrit au Topic}}</label>
			    <div class="col-sm-3">
				<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="topic" placeholder="{{Topic principal de l'équipement jMQTT}}"/>
			    </div>
			</div>

			<div class="form-group">
			    <label class="col-sm-3 control-label">{{Ajout automatique des commandes}}</label>
			    <div class="col-sm-3">
				<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="auto_add_cmd" checked/>
			    </div>
			</div>


			<div class="form-group">
			    <label class="col-sm-3 control-label">{{Qos}}</label>
			    <div id="mqttqos" class="col-sm-3">
				<select style="width : 40pxpx;" class="eqLogicAttr form-control input-sm" data-l1key="configuration" data-l2key="Qos">
				    <option value="0">0</option>
				    <option value="1" selected>1</option>
				    <option value="2">2</option>
				</select>
			    </div>
			</div>

			<div class="form-group">
			    <label class="col-sm-3 control-label">{{Dernière Activité}}</label>
			    <div class="col-sm-3">
				<span class="eqLogicAttr" data-l1key="configuration" data-l2key="updatetime"></span>
			    </div>
			</div>

			<div class="form-group">
			    <label class="col-sm-3 control-label">{{Catégorie du topic}}</label>
			    <div class="col-sm-3">
				<select id="sel_icon" class="form-control eqLogicAttr" data-l1key="configuration" data-l2key="icone">
				    <option value="">{{Aucun}}</option>
				    <option value="433">{{RF433}}</option>
				    <option value="barometre">{{Baromètre}}</option>
				    <option value="boiteauxlettres">{{Boite aux Lettres}}</option>
				    <option value="chauffage">{{Chauffage}}</option>
				    <option value="compteur">{{Compteur}}</option>
				    <option value="contact">{{Contact}}</option>
				    <option value="feuille">{{Culture}}</option>
				    <option value="custom">{{Custom}}</option>
				    <option value="dimmer">{{Dimmer}}</option>
				    <option value="energie">{{Energie}}</option>
				    <option value="garage">{{Garage}}</option>
				    <option value="humidity">{{Humidité}}</option>
				    <option value="humiditytemp">{{Humidité et Température}}</option>
				    <option value="hydro">{{Hydrométrie}}</option>
				    <option value="ir2">{{Infra Rouge}}</option>
				    <option value="jauge">{{Jauge}}</option>
				    <option value="light">{{Luminosité}}</option>
				    <option value="meteo">{{Météo}}</option>
				    <option value="motion">{{Mouvement}}</option>
				    <option value="multisensor">{{Multisensor}}</option>
				    <option value="prise">{{Prise}}</option>
				    <option value="relay">{{Relais}}</option>
				    <option value="rfid">{{RFID}}</option>
				    <option value="teleinfo">{{Téléinfo}}</option>
				    <option value="temp">{{Température}}</option>
				    <option value="thermostat">{{Thermostat}}</option>
				    <option value="volet">{{Volet}}</option>
				</select>
			    </div>
			</div>

			<div class="form-group">
			    <div style="text-align: center">
				<img name="icon_visu" src="" width="160" height="200"/>
			    </div>
			</div>

		    </fieldset>
		</form>
	    </div>
	    <div role="tabpanel" class="tab-pane" id="commandtab">

		<form class="form-horizontal">
		    <fieldset>
			<div class="form-actions">
			    <a class="btn btn-success btn-sm cmdAction" id="bt_addMQTTAction"><i class="fa fa-plus-circle"></i> {{Ajouter une commande action}}</a>
			</div>
		    </fieldset>
		</form>
		<br />
		<table id="table_cmd" class="table table-bordered table-condensed">
		    <thead>
			<tr>
			    <th style="width: 50px;">#</th>
			    <th style="width: 150px;">{{Nom}}</th>
			    <th style="width: 110px;">{{Sous-Type}}</th>
			    <th>{{Topic}}</th>
			    <th style="width: 500px;">{{Valeur}}</th>
			    <th style="width: 100px;">{{Paramètres}}</th>
			    <th style="width: 100px;"></th>
			</tr>
		    </thead>
		    <tbody>
		    </tbody>
		</table>
	    </div>
	</div>
    </div>
</div>

<?php include_file('desktop', 'jMQTT', 'js', 'jMQTT'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>

<script>
 <?php
 // Initialise the automatic inclusion button display according to include_mode configuration parameter
 echo 'configureIncludeModeDisplay(' . config::byKey('include_mode', 'jMQTT', 0) . ');';
 ?>

 $( "#sel_icon" ).change(function(){
     var text = 'plugins/jMQTT/resources/images/node_' + $("#sel_icon").val() + '.png';
     //$("#icon_visu").attr('src',text);
     document.icon_visu.src=text;
 });
</script>
