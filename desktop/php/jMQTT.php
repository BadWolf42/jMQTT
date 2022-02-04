<?php
if (! isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'jMQTT');
/** @var jMQTT[][] $eqNonBrokers */
$eqNonBrokers = jMQTT::getNonBrokers();
/** @var jMQTT[] $eqBrokers */
$eqBrokers = jMQTT::getBrokers();

$eqBrokersName = array();
foreach ($eqBrokers as $id => $eqL) {
    $eqBrokersName[$id] = $eqL->getName();
}
sendVarToJS('eqBrokers', $eqBrokersName);

$has_orphans = false;
$node_images = scandir(__DIR__ . '/../../core/img/');
?>

<style>

.eqLogicThumbnailContainer div.eqLogicDisplayAction {
    padding-top: 25px !important;
    height: 168px;
}

.eqLogicDisplayCard.disableCard {
    opacity: 0.35;
}

.eqLogicDisplayCard > div.jmqttauto {
    position:absolute;
    top:25px;
    width:100%;
    text-align:center;
    margin-left:-4px;
}
.eqLogicDisplayCard .jmqttauto .fas {
    transform: rotate(90deg);
}

.eqLogicThumbnailContainer .eqLogicDisplayCard .jmqttauto i {
    font-size:20px !important;
    color: #8000FF;
}

.row div.eqLogicDisplayAction.card.include .fas {
    color: #8000FF !important;
    font-size: 52px !important;
}

.row div.eqLogicDisplayAction.card.include span {
    font-weight: bold;
    color: #8000FF;
}

.row div.eqLogicDisplayAction.card:not(.include ) {
    background-color: #FFFFFF;
}

.row div.eqLogic div.row {
    margin-left: 0;
    margin-right: 0;
}

.eqLogicDisplayAction.disableCard {
    opacity: 0.35;
    cursor: default;
}

.disabled {
    pointer-events: none;
    opacity: 0.4;
}

td.fitwidth {
    white-space: nowrap;
}

<?php 
if ($_SESSION['user']->getOptions('bootstrap_theme') == 'darksobre') {
    echo "div#div_pageContainer div.eqLogicThumbnailDisplay div.eqLogicThumbnailContainer div.eqLogicDisplayCard {";
    echo "height: 155px !important;";
    echo "min-height: 0px !important;";
    echo "}";
    
    echo "div#div_pageContainer div.eqLogicThumbnailDisplay div.eqLogicThumbnailContainer div.eqLogicDisplayAction,";
    echo "div#div_pageContainer div.eqLogicThumbnailDisplay div.eqLogicThumbnailContainer div.jmqttauto {";
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
    echo '<div class="eqLogicDisplayAction eqLogicAction cursor ' . $class . '"';
    if ($attr != '')
        echo ' ' . $attr;
    echo '><i class="fas ' . $fa_icon . '"></i><br><span>' . $action_name . '</span></div>';
}

/**
 *
 * @param jMQTT $eqL
 */
function displayEqLogicCard($eqL, $node_images) {
    $opacity = $eqL->getIsEnable() ? '' : 'disableCard';
    echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqL->getId() . '" jmqtt_type="' . $eqL->getType() . '">';
    if ($eqL->getAutoAddCmd() && $eqL->getType() == jMQTT::TYP_EQPT) {
       echo '<i class="fas fa-sign-in-alt fa-rotate-90" style="font-size:0.9em !important;position:absolute;margin-top:10px;margin-left:4px"></i>';
    }
    if ($eqL->getIsVisible()) {
        echo '<i class="fas fa-eye" style="font-size:0.9em !important;position:absolute;margin-top:25px;margin-left:4px"></i>';
    } else {
        echo '<i class="fas fa-eye-slash" style="font-size:0.9em !important;position:absolute;margin-top:25px;margin-left:4px"></i>';
    }
    if ($eqL->getType() == jMQTT::TYP_BRK) {
        $file = 'node_broker.svg';
        echo '<i class="status-circle fas fa-circle" style="font-size:1em !important;position:absolute;margin-top:23px;margin-left:55px;color:' . jMQTT::getBrokerColorFromState($eqL->getMqttClientState()) . '"></i>';
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

    echo '<img class="lazy" src="plugins/jMQTT/core/img/' . $file . '"/>';
    echo "<br>";
    echo '<span class="name">' . $eqL->getHumanName(true, true) . '</span>';
    echo '</div>';
}

?>

<div id="div_cmdMsg"></div>
<div id="div_newEqptMsg"></div>
<div id="div_inclusionModeMsg"></div>
<div class="row row-overflow">
    <div class="col-xs-12 eqLogicThumbnailDisplay">
        <input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
        <legend><i class="fas fa-cog"></i> {{Gestion plugin et brokers}}</legend>
        <div class="eqLogicThumbnailContainer">
        <?php
        displayActionCard('{{Configuration}}', 'fa-wrench', 'data-action="gotoPluginConf"', 'logoSecondary');
        displayActionCard('{{Santé}}', 'fa-medkit', 'data-action="healthMQTT"', 'logoSecondary');
        displayActionCard('{{Templates}}', 'fa-cubes', 'data-action="templatesMQTT"', 'logoSecondary');
        displayActionCard('{{Ajouter un broker}}', 'fa-plus-circle', 'data-action="add_jmqtt"', 'logoSecondary');
        foreach ($eqBrokers as $eqB) {
            displayEqLogicCard($eqB, $node_images);
        }
        ?>
        </div>
    
        <?php
        foreach ($eqBrokers as $eqB) {
            echo '<legend><i class="fas fa-table"></i> {{Equipements connectés à}} <b>' . $eqB->getName() . '</b></legend>';
            echo '<div class="eqLogicThumbnailContainer">';
            displayActionCard('{{Ajouter un équipement}}', 'fa-plus-circle', 'data-action="add_jmqtt" brkId="' . 
                $eqB->getId() . '"', 'logoSecondary', true);
            displayActionCard('{{Mode inclusion}}', 'fa-sign-in-alt fa-rotate-90', 'data-action="changeIncludeMode" brkId="' .
                $eqB->getId() . '"', 'logoSecondary card', true);
            if (array_key_exists($eqB->getId(), $eqNonBrokers)) {
                foreach ($eqNonBrokers[$eqB->getId()] as $eqL) {
                    displayEqLogicCard($eqL, $node_images);
                }
            }
            echo '</div>';
        }
        
        if ($has_orphans) {
            echo '<legend><i class="fas fa-table"></i> {{Equipements}} {{orphelins}}</legend>';
            echo '<div class="eqLogicThumbnailContainer">';
            foreach ($eqNonBrokers as $id => $nonBrokers) {
                if (! array_key_exists($id, $eqBrokers)) {
                    foreach ($nonBrokers as $eqL) {
                        displayEqLogicCard($eqL, $node_images);
                    }
                }
            }
            echo '</div>';
        }
        ?>
    </div>

    <div class="col-xs-12 eqLogic" style="display: none;">
      <div class="row">
        <div class="input-group pull-right" style="display:inline-flex">
            <a class="btn btn-warning btn-sm eqLogicAction applyTemplate typ-std roundedLeft"><i class="fas fa-share"></i> {{Appliquer Template}}</a>
            <a class="btn btn-primary btn-sm eqLogicAction createTemplate typ-std"><i class="fas fa-cubes"></i> {{Créer Template}}</a>
            <a class="btn btn-default btn-sm eqLogicAction" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}</a>
            <a class="btn btn-default btn-sm eqLogicAction typ-std toDisable" data-action="copy"><i class="fas fa-copy"></i> {{Dupliquer}}</a>
            <a class="btn btn-success btn-sm eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
            <a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove_jmqtt"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
        </div>
        <div class="input-group pull-left" style="display:inline-flex">
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
                <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="eqlogictab" role="tab"
                    data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
                <li role="presentation" class="typ-brk" style="display: none;"><a href="#brokertab"
                    aria-controls="brokertab" role="tab" data-toggle="tab"><i class="fas fa-rss"></i> {{Broker}}</a></li>
                <li role="presentation"><a href="#commandtab" aria-controls="commandtab" role="tab" data-toggle="tab"><i
                        class="fas fa-list-alt"></i> {{Commandes}}</a></li>
                <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="refreshPage"><i class="fas fa-sync"></i></a></li>
            </ul>
        </div>
    </div>
        <div id="menu-bar" style="display: none;">
            <div class="form-actions">
                <a class="btn btn-default btn-sm toDisable" id="bt_addMQTTAction"><i class="fas fa-plus-circle"></i>
                    {{Ajouter une commande action}}</a>
                <a class="btn btn-default btn-sm toDisable" id="bt_addMQTTInfo"><i class="fas fa-plus-circle"></i>
                    {{Ajouter une commande info}}</a>
                <div class="btn-group pull-right" data-toggle="buttons">
                    <a id="bt_classic" class="btn btn-sm btn-primary active"><input type="radio" autocomplete="off"
                        checked><i class="fas fa-list-alt"></i> Classic </a> <a id="bt_json"
                        class="btn btn-sm btn-default"><input type="radio" autocomplete="off"><i class="fas fa-sitemap"></i>
                        JSON </a>
                </div>
            </div>
            <hr style="margin-top: 5px; margin-bottom: 5px;">
        </div>
        <div class="tab-content" style="height: calc(100% - 120px); overflow: auto; overflow-x: hidden;">
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <?php include_file('desktop', 'jMQTT_eqpt', 'php', 'jMQTT'); ?>
            </div>
            <div role="tabpanel" class="tab-pane toDisable" id="brokertab">
                <?php include_file('desktop', 'jMQTT_broker', 'php', 'jMQTT'); ?>
            </div>
            <div role="tabpanel" class="tab-pane toDisable" id="commandtab">
                <table id="table_cmd" class="table tree table-bordered table-condensed table-striped">
                    <thead>
                        <tr>
                            <th style="width:1px;">#</th>
                            <th style="min-width:150px;width:300px;">{{Nom}}</th>
                            <th style="width:130px;">{{Type}}</th>
                            <th style="min-width:180px;">{{Topic}}</th>
                            <th style="min-width:180px;">{{Valeur}}</th>
                            <th style="width:1px;">{{Unité}}</th>
                            <th style="min-width:100px;width:120px;">{{Options}}</th>
                            <th style="min-width:135px;width:135px;"></th>
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

//$('.eqLogicThumbnailContainer').packery();

<?php
// Initialise the automatic inclusion button display according to include_mode configuration parameter
foreach ($eqBrokers as $eqL) {
    echo 'setIncludeModeActivation(' . $eqL->getId() . ',"' . $eqL->getMqttClientState() . '");';
    echo 'configureIncludeModeDisplay(' . $eqL->getId() . ',' . $eqL->getIncludeMode() . ');';
}
?>

</script>
