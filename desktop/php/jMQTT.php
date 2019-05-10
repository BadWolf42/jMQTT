<?php
if (! isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'jMQTT');
/** @var jMQTT[][] $eqNonBrokers */
$eqNonBrokers = jMQTT::getNonBrokers();
/** @var jMQTT[] $eqBrokers */
$eqBrokers = jMQTT::getBrokers();
$node_images = scandir(__DIR__ . '/../../resources/images/');
?>

<style>

#in_searchEqlogic {
  margin-bottom: 20px;
}
.eqLogicThumbnailContainer div.cursor img {
  padding-top: 0px;
}
.eqLogicThumbnailContainer .eqLogicDisplayCard,
.eqLogicDisplayAction {
  height: 155px !important;
}

.eqLogicDisplayCard.disableCard {
    opacity: 0.35;
}

.eqLogicDisplayCard > div.auto {
    position:absolute;
    top:5px;
    width:100%;
    text-align:center;
    margin-left:-4px;
}
.eqLogicDisplayCard .auto .fas {
    transform: rotate(90deg);
}

.eqLogicThumbnailContainer .eqLogicDisplayCard .auto i {
    font-size:20px !important;
    color: #8000FF;/*var(--logo-primary-color);*/
}

/* .row div.eqLogicAction.card.include { */
/*  background-color: #8000FF !important; */
/* } */
.row div.eqLogicDisplayAction.card.include .fa {
    background-color: #8000FF !important;
    font-size: 48px !important;
    padding: 5px;
}

.row div.eqLogicDisplayAction.card.include span {
    font-weight: bold;
    color: #8000FF;
}

.row div.eqLogicDisplayAction.card:not(.include ) {
    background-color: #FFFFFF;
}

.eqLogicDisplayAction.disableCard {
    opacity: 0.35;
    cursor: default;
}

<?php 
if ($_SESSION['user']->getOptions('bootstrap_theme') == 'darksobre') {
    echo "div#div_pageContainer div.eqLogicThumbnailDisplay div.eqLogicThumbnailContainer div.eqLogicDisplayCard {";
    echo "height: 155px !important;";
    echo "min-height: 0px !important;";
    echo "}";
    
    echo "div#div_pageContainer div.eqLogicThumbnailDisplay div.eqLogicThumbnailContainer div.eqLogicDisplayAction,";
    echo "div#div_pageContainer div.eqLogicThumbnailDisplay div.eqLogicThumbnailContainer div.auto {";
    echo "background-color:rgba(0, 0, 0, 0) !important;";
    echo "color:#ccc !important;";
    echo "border: 0px !important;";
    echo "min-height: 0px !important;";
    echo "}";

    echo "div#div_pageContainer div.eqLogicThumbnailDisplay div.eqLogicThumbnailContainer div.eqLogicAction {";
    echo "background-color:rgba(0, 0, 0, 0) !important;";
    echo "color:#ccc !important;";
    echo "border: 0px !important;";
    echo "min-height: 0px !important;";
    echo "}";
}
?>
</style>

<?php
/*
 * $plugin = plugin::byId('jMQTT');
 * $plugin->callInstallFunction('update', true);
 */
function displayActionCard($action_name, $fa_icon, $attr = '', $class = '') {
    echo '<div class="eqLogicAction cursor ' . $class . '"';
    if ($attr != '')
        echo ' ' . $attr;
    echo '>';
    echo '<i class="fa ' . $fa_icon . '"></i><br>';
    echo '<span>' . $action_name . '</span>';
    echo '</div>';
}

/**
 *
 * @param jMQTT $eqL
 */
function displayEqLogicCard($eqL, $node_images) {
    $opacity = $eqL->getIsEnable() ? '' : 'disableCard';
    echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqL->getId() . '" jmqtt_type="' . $eqL->getType() . '">';
    if ($eqL->getConfiguration('auto_add_cmd', 1) == 1) {
       echo '<div class="auto"><i class="fas fa-sign-in-alt"></i></div>';
    }
    if ($eqL->getType() == jMQTT::TYP_BRK) {
        $file = 'node_broker_' . $eqL->getDaemonState() . '.svg';
    }
    else {
        $icon = 'node_' . $eqL->getConfiguration('icone');
        $test = $icon . '.svg';
        $file = 'node_.png';
        if (in_array($test, $node_images)) {
            $file = $test;
        }
        else {
            $test = $icon . '.png';
            if (in_array($test, $node_images)) {
                $file = $test;
            }
        }
    }

    echo '<img src="plugins/jMQTT/resources/images/' . $file . '"/>';
    echo "<br>";
    echo '<span class="name">' . $eqL->getHumanName(true, true) . '</span>';
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
                        echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqL->getId() . '" jmqtt_type="' . $eqL->getType() .
                            '" style="' . $opacity . '"><a>' . $eqL->getHumanName(true) . '</a></li>';
                    }
                    ?>
                </ul></li>
                <?php
                foreach ($eqNonBrokers as $id => $nonBrokers) {
                    if (array_key_exists($id, $eqBrokers)) {
                        echo '<li><i class="fa fa-table"></i><span> ' . $eqBrokers[$id]->getName() . '</span>';
                        echo '<ul class="nav nav-list bs-sidenav sub-nav-list">';
                        foreach ($nonBrokers as $eqL) {
                            $opacity = ($eqL->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
                            echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqL->getId() . '" jmqtt_type="' . $eqL->getType() .
                            '" style="' . $opacity . '"><a>' . $eqL->getHumanName(true) . '</a></li>';
                        }
                        echo '</ul></li>';
                    }
                }
                ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
        <div class="col-lg-5 col-md-5 col-sm-5">
            <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
            <div class="eqLogicThumbnailContainer">
	        <?php
            displayActionCard('{{Configuration}}', 'fa-wrench', 'data-action="gotoPluginConf"', 'logoSecondary');
            displayActionCard('{{Santé}}', 'fa-medkit', 'data-action="healthMQTT"', 'logoSecondary');
            ?>
            </div>
	    </div>

        <div class="col-lg-7 col-md-7 col-sm-7">
            <legend><i class="fas fa-rss"></i> {{Brokers MQTT}}</legend>
            <div class="eqLogicThumbnailContainer">
            <?php
            displayActionCard('{{Ajouter un broker}}', 'fa-plus-circle', 'data-action="add_jmqtt"', 'logoSecondary');

            foreach ($eqBrokers as $eqB) {
                displayEqLogicCard($eqB, $node_images);
            }
            ?>
            </div>
        </div>


        <?php
        foreach ($eqBrokers as $eqB) {
            echo '<div class="col-lg-12 col-md-12 col-sm-12">';
            echo '<legend><i class="fas fa-table"></i> {{Modules connectés à}} ' . $eqB->getName() . '</legend>';
            echo '<input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />';
            echo '<div class="eqLogicThumbnailContainer">';
            displayActionCard('{{Ajouter un équipement}}', 'fa-plus-circle', 'data-action="add_jmqtt" brkId="' . $eqB->getId() . '"', 'eqLogicDisplayAction logoSecondary', true);
            displayActionCard('{{Mode inclusion}}', 'fa-sign-in fa-rotate-90', 'data-action="changeIncludeMode" brkId="' . $eqB->getId() . '"', 'eqLogicDisplayAction logoSecondary card', true);
            if (array_key_exists($eqB->getId(), $eqNonBrokers)) {
                foreach ($eqNonBrokers[$eqB->getId()] as $eqL) {
                    displayEqLogicCard($eqL, $node_images);
                }
            }
            echo '</div></div>';
        }
        ?>
    </div>

    <div class="col-lg-10 col-md-9 col-sm-8 eqLogic"
        style="border-left: solid 1px #EEE; padding-left: 25px; display: none;">
        <div class="row">
            <a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i>{{Sauvegarder}}</a>
            <a class="btn btn-danger eqLogicAction pull-right" data-action="remove_jmqtt"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
            <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
            <a class="btn btn-default eqLogicAction pull-right typ-std" data-action="copy" style="display: none;"><i class="fa fa-files-o"></i> {{Dupliquer}}</a>
            <a class="btn btn-default eqLogicAction pull-right" data-action="export"><i class="fa fa-sign-out"></i> Export</a>
            <a class="btn btn-default eqLogicAction pull-left" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a>
            <ul class="nav nav-tabs pull-left" role="tablist">
                <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="eqlogictab" role="tab"
                    data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
                <li role="presentation" class="typ-brk" style="display: none;"><a href="#brokertab"
                    aria-controls="brokertab" role="tab" data-toggle="tab"><i class="fa fa-rss"></i> {{Broker}}</a></li>
                <li role="presentation"><a href="#commandtab" aria-controls="commandtab" role="tab" data-toggle="tab"><i
                        class="fa fa-list-alt"></i> {{Commandes}}</a></li>
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
        <div class="tab-content" style="height: calc(100% - 120px); overflow: auto; overflow-x: hidden;">
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <?php include_file('desktop', 'jMQTT_eqpt', 'php', 'jMQTT'); ?>
	        </div>
            <div role="tabpanel" class="tab-pane" id="brokertab">
                <?php include_file('desktop', 'jMQTT_broker', 'php', 'jMQTT'); ?>                
            </div>
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <table id="table_cmd" class="table tree table-bordered table-condensed table-striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="width: 250px;">{{Nom}}</th>
                            <th style="width: 60px;">{{Sous-Type}}</th>
                            <th style="width: 300px;">{{Topic}}</th>
                            <th style="width: 300px;">{{Valeur}}</th>
                            <th style="width: 60px;">{{Unité}}</th>
                            <th style="width: 150px;">{{Paramètres}}</th>
                            <th style="width: 150px;"></th>
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
<?php include_file('3rdparty', 'jquery.treegrid', 'css', 'jMQTT'); ?>
<?php include_file('3rdparty', 'jquery.treegrid.min', 'js', 'jMQTT'); ?>
<?php include_file('3rdparty', 'jquery.treegrid.bootstrap3', 'js', 'jMQTT'); ?>

<script>

$('.eqLogicThumbnailContainer').packery();

<?php
// Initialise the automatic inclusion button display according to include_mode configuration parameter
foreach ($eqBrokers as $eqL) {
    echo 'setIncludeModeActivation(' . $eqL->getId() . ',"' . $eqL->getDaemonState() . '");';
    echo 'configureIncludeModeDisplay(' . $eqL->getId() . ',' . $eqL->getIncludeMode() . ');';
}
?>

</script>
