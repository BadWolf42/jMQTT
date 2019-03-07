<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'jMQTT');
$eqNonBrokers = jMQTT::getNonBrokers();
$eqBrokers = jMQTT::getBrokers();

/*
$plugin = plugin::byId('jMQTT');
$plugin->callInstallFunction('update', true);
*/

function displayActionCard($action_name, $fa_icon, $card_color, $action='', $add_class='') {
    echo '<div class="cursor eqLogicAction ' . $add_class . '"';
    if ($action != '')
        echo ' data-action="' . $action . '"';
    echo ' style="text-align:center;height:200px;width:160px;margin-bottom:10px;padding:5px;border-radius:10px;margin-right:10px;float:left;" >';
    echo '<div class="center-block" style="width:130px;height:130px;display:flex;align-items: center;justify-content:center;">';
    echo '<i class="fa ' . $fa_icon . '" style="font-size:6em;color:' . $card_color . ';"></i>';
    echo "</div>";
    echo '<span style="font-size:1.1em;font-weight:bold;position:relative;top:10px;word-break:break-all;white-space:pre-wrap;word-wrap:break-word;">' .
        $action_name . '</span>';
    echo '</div>';
}

        /**
 *
 * @param eqLogic $eqL
 */
function displayEqLogicCard($eqL) {
    $opacity = $eqL->getIsEnable() ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
    if ($eqL->getConfiguration('auto_add_cmd', 1)  == 1) {
        echo '<div class="eqLogicDisplayCard cursor auto" data-eqLogic_id="' . $eqL->getId() .
            '" style="text-align:center;height:200px;width:160px;margin-bottom:10px;padding:5px;margin-right:10px;float:left;' .
            $opacity . '" >';
    }
    else {
        echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqL->getId() .
            '" style="text-align:center;height:200px;width:160px;margin-bottom:10px;padding:5px;margin-right:10px;float:left;' .
            $opacity . '" >';
    }
    
    $dir = dirname(__FILE__) . '/../../resources/images/';
    $files = scandir($dir);
    $test = 'node_' . $eqL->getConfiguration('icone') . '.png';
    if (in_array($test, $files)) {
        $path = 'node_' . $eqL->getConfiguration('icone');
    }
    else {
        $path = 'mqtt_icon';
    }
    
    echo '<center style="height:120px;padding-top:10px">';
    echo '<img src="plugins/jMQTT/resources/images/' . $path . '.png" height="105" width="92" />';
    echo "</center>";
    echo '<span style="font-size:1.1em;position:relative;top:10px;word-break:break-all;white-space:pre-wrap;word-wrap:break-word;">' .
        $eqL->getHumanName(true, true) . '</span>';
    echo '</div>';
}

?>

<div id="div_newCmdMsg"></div>
<div id="div_newEqptMsg"></div>
<div id="div_inclusionModeMsg"></div>
<div class="row row-overflow">
    <div class="col-lg-2 col-sm-3 col-sm-4">
        <div class="bs-sidebar">
            <a class="btn btn-default eqLogicAction" style="width: 100%; margin-top: 5px; margin-bottom: 5px;"
                data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter un équipement}}</a> <a class="filter"
                style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}"
                style="width: 100%" /></a> <br />
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <li><i class="fa fa-rss"></i><span> {{Brokers}}</span>
                    <ul class="nav nav-list bs-sidenav sub-nav-list">
		<?php
		foreach ($eqBrokers as $eqL) {
		    $opacity = ($eqL->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
		    echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqL->getId() . '" style="' . $opacity . '"><a>' .
		  		    $eqL->getHumanName(true) . '</a></li>';
		}
		?>
	    </ul></li>
                <li><i class="fa fa-table"></i><span> {{Mes jMQTT}}</span>
                    <ul class="nav nav-list bs-sidenav sub-nav-list">
        <?php
        foreach ($eqNonBrokers as $eqL) {
            $opacity = ($eqL->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
            echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqL->getId() . '" style="' . $opacity . '"><a>' .
                $eqL->getHumanName(true) . '</a></li>';
        }
        ?>
        </ul></li>
            </ul>
        </div>
    </div>

    <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay"
        style="border-left: solid 1px #EEE; padding-left: 25px;">
        <div class="row">
            <div class="col-lg-4 col-md-4 col-sm-4">        
            <legend><i class="fa fa-cog"></i> {{Gestion}}</legend>
    	    <?php
    	    displayActionCard('{{Configuration}}', 'fa-wrench', '#767676', 'gotoPluginConf');
    	    displayActionCard('{{Santé}}', 'fa-medkit', '#767676', 'healthMQTT');
    	    ?>
    	   </div>

            <div class="col-lg-8 col-md-8 col-sm-8">        
            <legend><i class="fa fa-rss"></i> {{Brokers MQTT}}</legend>
            <?php
            displayActionCard('{{Ajouter un broker}}', 'fa-plus-circle', '#f8d800', 'addBroker');
        
            foreach ($eqBrokers as $eqB) {
                displayEqLogicCard($eqB);
            }
            ?>
            </div>
        </div>

        <legend><i class="fa fa-table"></i> {{Mes jMQTT}}</legend>
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">        
            <?php
            displayActionCard('{{Ajouter un équipement}}', 'fa-plus-circle', '#f8d800', 'add');
    
            // Insert the automatic inclusion button: display according to the include_mode configuration parameter is done at the end of this page
            displayActionCard('{{Mode inclusion}}', 'fa-sign-in fa-rotate-90', '#f8d800', '',
                'bt_changeIncludeMode card');
    
            foreach ($eqNonBrokers as $eqL) {
                displayEqLogicCard($eqL);
            }
            ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
        <div class="row">
    	<a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
    	<a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
    	<a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
    	<a class="btn btn-default eqLogicAction pull-right typ-std" data-action="copy" style="display:none;"><i class="fa fa-files-o"></i> {{Dupliquer}}</a>
    	<a class="btn btn-default eqLogicAction pull-right" data-action="export"><i class="fa fa-sign-out"></i> Export</a>
    	<a class="btn btn-default eqLogicAction pull-left" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a>
    	<ul class="nav nav-tabs pull-left" role="tablist">
    	    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="eqlogictab" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
            <li role="presentation" class="typ-brk" style="display:none;"><a href="#brokertab" aria-controls="brokertab" role="tab" data-toggle="tab"><i class="fa fa-rss"></i> {{Broker}}</a></li>
    	    <li role="presentation"><a href="#commandtab" aria-controls="commandtab" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
    	</ul>
        <a class="btn btn-default eqLogicAction pull-left" data-action="refreshPage"><i class="fa fa-refresh"></i></a>
        </div>
        <div id="menu-bar" style="display: none;">
            <div class="form-actions">
                <a class="btn btn-success btn-sm cmdAction" id="bt_addMQTTAction"><i class="fa fa-plus-circle"></i>
                    {{Ajouter une commande action}}</a>
                <div class="btn-group pull-right" data-toggle="buttons">
                    <a id="bt_classic" class="btn btn-sm btn-primary active"><input type="radio" autocomplete="off"
                        checked><i class="fa fa-list-alt"></i> Classic </a> <a id="bt_json"
                        class="btn btn-sm btn-default"><input type="radio" autocomplete="off"><i class="fa fa-sitemap"></i>
                        JSON </a>
                </div>
            </div>
            <hr style="margin-top: 5px; margin-bottom: 5px;">
        </div>
        <div class="tab-content" style="height:calc(100% - 120px);overflow:auto;overflow-x: hidden;">
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
        <div role="tabpanel" class="tab-pane active" id="brokertab">
        <form class="form-horizontal">
        </form>
        </div>
	    <div role="tabpanel" class="tab-pane" id="commandtab">
		<table id="table_cmd" class="table tree table-bordered table-condensed table-striped">
		    <thead>
			<tr>
			    <th style="width:50px;">#</th>
			    <th style="width:250px;">{{Nom}}</th>
			    <th style="width:60px;">{{Sous-Type}}</th>
			    <th style="width:300px;">{{Topic}}</th>
			    <th style="width:300px;">{{Valeur}}</th>
			    <th style="width:60px;">{{Unité}}</th>
			    <th style="width:150px;">{{Paramètres}}</th>
			    <th style="width:150px;"></th>
			</tr>
		    </thead>
		    <tbody>
		    </tbody>
		</table>
	    </div>
	</div>
    </div>
</div>

<?php include_file('desktop', 'jMQTT.min', 'js', 'jMQTT'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
<?php include_file('3rdparty', 'jquery.treegrid', 'css', 'jMQTT'); ?>
<?php include_file('3rdparty', 'jquery.treegrid.min', 'js', 'jMQTT'); ?>
<?php include_file('3rdparty', 'jquery.treegrid.bootstrap3', 'js', 'jMQTT'); ?>

<?php // The !important keyword is used as some themes (such as darksobre) overrides below property with this keyword (fix #37) ?>
<style>
 div.eqLogicDisplayCard.auto {
     border:8px solid #8000FF !important;
     border-radius:18px !important;
     padding-top:5px !important;
 }
 div.eqLogicDisplayCard:not(auto) {
     border-width:1px !important;
     border-style:solid !important;
     border-color: #FFFFFF;
     border-radius:18px !important;
     padding-top:12px !important;
 }
 .row div.eqLogicAction.bt_changeIncludeMode.include {
     background-color: #8000FF !important;
 }
 .row div.eqLogicAction.bt_changeIncludeMode:not(.include) {
     background-color: #FFFFFF;
 }
</style>

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
