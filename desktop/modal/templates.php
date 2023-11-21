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

if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>

<div class="col-lg-3 col-md-3 col-sm-3" id="div_listJmqtt" style="height:100%">
    <div class="bs-sidebar nav nav-list bs-sidenav" style="height:calc(100%);overflow:auto;overflow-x:hidden;">
        <div class="form-group">
            <span class="btn btn-default btn-file" style="width:100%;">
                <i class="fas fa-upload"></i> {{Importer un template depuis un fichier}}<input id="bt_jmqttTemplateUp" type="file" name="file" accept=".json" data-url="plugins/jMQTT/core/ajax/jMQTT.ajax.php?action=fileupload&amp;dir=template" style="display : inline-block;width:100%;">
            </span>
        </div>
        <legend>{{Templates existants}}</legend>
        <ul id="ul_jmqttTemplateList" class="nav nav-list bs-sidenav"></ul>
    </div>
</div>
<div class="col-lg-9 col-md-9 col-sm-9" id="div_listJmqttTemplate" style="display:none;height:100%">
    <form class="form-horizontal" style="height:calc(100%);overflow:auto;overflow-x:hidden;">
        <a class="btn btn-sm btn-primary" id="bt_jmqttTemplateDownload"><i class="fas fa-cloud-download-alt"></i> {{Télécharger}}</a>
        <!--<a class='btn btn-sm btn-success pull-right' id='bt_jmqttTemplateApply'><i class="far fa-check-circle"></i> {{Appliquer}}</a>-->
        <a class="btn btn-sm btn-danger" id="bt_jmqttTemplateDelete"><i class="fas fa-times"></i> {{Supprimer}}</a>
        <br/>
        <legend><i class="fas fa-tachometer-alt"></i> {{Aperçu de l'équipement}}</legend>
        <div id='div_jmqttTemplateEqlogic'></div>
        <legend><i class="fas fa-list-alt"></i> {{Aperçu des commandes}}</legend>
        <table id="table_jmqttTemplateCmds" class="table tree table-bordered table-condensed table-striped">
            <thead>
                <tr>
                    <th style="min-width:60px;">{{Nom}}</th>
                    <th style="width:60px;">{{Type}}</th>
                    <th style="min-width:60px;">{{Topic}}</th>
                    <th style="min-width:60px;">{{Valeur}}</th>
                    <th style="min-width:60px;width:70px;">{{Paramètres}}</th>
                    <th style="min-width:95px;width:95px;">{{Options}}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </form>
</div>

<script>
$('#bt_jmqttTemplateUp').fileupload({
    dataType: 'json',
    replaceFileInput: false,
    done: function (e, data) {
        if (data.result.state != 'ok') {
            $.fn.showAlert({message: data.result.result, level: 'danger'});
        } else {
            $.fn.showAlert({message: 'Template ajouté avec succès', level: 'success'});
            refreshJmqttTemplateList()
        }
        $('#bt_jmqttTemplateUp').val(null);
    }
});

function refreshJmqttTemplateList() {
    jmqtt.callPluginAjax({
        data: {
            action: "getTemplateList",
        },
        error: function(error) {
            $.fn.showAlert({message: error.message, level: 'danger'})
        },
        success: function (dataresult) {
            $('#div_listJmqttTemplate').hide()
            $('#ul_jmqttTemplateList').empty()
            li = ''
            for (var i in dataresult) {
                li += "<li class='cursor li_jmqttTemplate' data-name='" + dataresult[i][0] + "' data-file='" + dataresult[i][1] + "'><a>" + dataresult[i][0] + "</a></li>"
            }
            $('#ul_jmqttTemplateList').html(li)
        }
    });
}
refreshJmqttTemplateList()

$('#ul_jmqttTemplateList').on('click', '.li_jmqttTemplate', function(event) {
    $('#div_listJmqttTemplate').hide()
    $('#ul_jmqttTemplateList .li_jmqttTemplate').removeClass('active')
    $('#table_jmqttTemplateCmds tbody').empty()
    $(this).addClass('active')
    if ($('#ul_jmqttTemplateList li.active').attr('data-name').startsWith('[Perso]'))
        $('#bt_jmqttTemplateDelete').show()
    else
        $('#bt_jmqttTemplateDelete').hide()
    jmqtt.callPluginAjax({
        data: {
            action: "getTemplateByFile",
            file: $(this).attr('data-file')
        },
        error: function(error) {
            $.fn.showAlert({message: error.message, level: 'danger'})
        },
        success: function (data) {
            $('#div_listJmqttTemplate').show()
            var eq =  '';
            // Nom
            eq += '<div class="form-group toDisable"><label class="col-sm-3 control-label">'+"{{Nom de l'équipement}}"+'</label><div class="col-sm-4"><input type="text" class="eqLogicAttr form-control" data-l1key="name" value="'+init(data.name)+'" disabled /></div></div>';
            // Category
            eq += '<div class="form-group toDisable"><label class="col-sm-3 control-label">{{Catégorie}}</label><div class="col-sm-8">';
            eq += '<?php
            foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value)
                echo '<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="'.$key.'" \'+((init(data.category.'.$key.')=="1")?\'checked \':\'\')+\'disabled />'.$value['name'].'</label>'; ?>';
            eq += '</div></div>';
            // Auto add cmd
            eq += '<div class="form-group toDisable typ-std"><label class="col-sm-3 control-label">{{Ajout automatique des commandes}}</label><div class="col-sm-3">';
            eq += '<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="auto_add_cmd" '+((init(data.configuration.auto_add_cmd)=="1")?'checked ':'')+'disabled />';
            eq += '</div></div>';
            // Topic
            eq += '<div class="form-group toDisable typ-std"><label class="col-sm-3 control-label">{{Inscrit au Topic}}</label><div class="col-sm-4">';
            eq += '<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="auto_add_topic" value="'+init(data.configuration.auto_add_topic)+'" disabled />';
            eq += '</div></div>';
            // Qos
            eq += '<div class="form-group toDisable"><label class="col-sm-3 control-label">{{Qos}}</label><div id="mqttqos" class="col-sm-1">';
            eq += '<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="Qos" disabled>';
            eq += '<option selected>'+init(data.configuration.Qos)+'</option></select>';
            eq += '</div></div>';
            // Alimentation
            eq += '<div class="form-group toDisable typ-std"><label class="col-sm-3 control-label">'+"{{Type d'alimentation}}"+'</label><div class="col-sm-4">';
            eq += '<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="battery_type" value="'+init(data.configuration.battery_type)+'" disabled />';
            eq += '</div></div>';
            // Battery
            eq += '<div class="form-group toDisable typ-std"><label class="col-sm-3 control-label">'+"{{Commande d'état de la batterie}}"+'</label><div class="col-sm-3">';
            eq += '<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="battery_cmd" disabled>';
            eq += '<option value="">{{Aucune}}</option>';
            if (init(data.configuration.battery_cmd))
                eq += '<option selected>'+init(data.configuration.battery_cmd)+'</option></select>';
            eq += '</select>';
            eq += '</div></div>';
            // Availability
            eq += '<div class="form-group toDisable typ-std"><label class="col-sm-3 control-label">'+"{{Commande d'état de disponibilité}}"+'</label><div class="col-sm-3">';
            eq += '<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="availability_cmd" disabled>';
            eq += '<option value="">{{Aucune}}</option>';
            if (init(data.configuration.availability_cmd))
                eq += '<option selected>'+init(data.configuration.availability_cmd)+'</option></select>';
            eq += '</select>';
            eq += '</div></div>';
            // Comment
            eq += '<div class="form-group toDisable"><label class="col-sm-3 control-label">{{Commentaire}}</label><div class="col-sm-8">';
            eq += '<textarea class="eqLogicAttr form-control" style="resize:vertical!important;" data-l1key="configuration" data-l2key="commentaire" disabled>'+init(data.configuration.commentaire)+'</textarea>';
            eq += '</div></div>';
            // Logo
            eq += '<div class="form-group toDisable typ-std"><label class="col-sm-3 control-label">&nbsp;</label><div class="col-sm-4">';
            eq += '<img id="logo_visu_tpl" style="margin-top: 10px;" src="plugins/jMQTT/core/img/node_' + init(data.configuration.icone) + '.svg" height="100" />';
            eq += '</div></div>';
            // Display equipements
            $('#div_jmqttTemplateEqlogic').empty().html(eq);

            // Load commands
            for (var i in data['commands']) {
                _cmd = data['commands'][i]
                if (!isset(_cmd))
                    var _cmd = {configuration: {}};
                if (!isset(_cmd.configuration))
                    _cmd.configuration = {};
                if (!isset(_cmd.tree_id)) {
                    //looking for all tree-id, keep part before the first dot, convert to Int
                    var root_tree_ids = $('[tree-id]').map((pos,e) => parseInt(e.getAttribute("tree-id").split('.')[0]))
                    //if some tree-id has been found
                    if (root_tree_ids.length > 0)
                        _cmd.tree_id = (Math.max.apply(null, root_tree_ids) + 1).toString(); //use the highest one plus one
                    else
                        _cmd.tree_id = '1'; // else this is the first one
                }
                if (init(_cmd.type) == 'info') {
                    var tr = '<tr class="cmd" tree-id="' + _cmd.tree_id + '" style="height: 88px!important;">';
                    tr += '<td>';
                    if (init(_cmd.display) && init(_cmd.display.icon)) {
                        tr += '<div class="input-group"><input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" disabled>';
                        tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;">'+init(_cmd.display.icon)+'</span>';
                        tr += '</div>';
                    } else {
                        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" disabled>';
                    }
                    tr += '</td><td>';
                    tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom:5px;width:120px;" />';
                    tr += '<select style="width : 120px;margin-top : 5px;" class="cmdAttr form-control input-sm" disabled data-l1key="subType">';
                    if (init(_cmd.subType) == 'numeric') tr += '<option value="numeric">Numérique</option>';
                    if (init(_cmd.subType) == 'binary')  tr += '<option value="binary">Binaire</option>';
                    if (init(_cmd.subType) == 'string')  tr += '<option value="string">Autre</option>';
                    tr += '</select></td><td>';
                    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" placeholder="{{Topic}}" style="margin-bottom:5px;" disabled>';
                    tr += '<input class="cmdAttr form-control input-sm col-lg-11 col-md-10 col-sm-10 col-xs-10" style="float: right;" data-l1key="configuration" data-l2key="jsonPath" placeholder="{{Chemin JSON}}" disabled >';
                    tr += '</td><td>';
                    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="resize:none!important;height:65px;" disabled></textarea>';
                    tr += '</td><td>';
                    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" disabled title="{{Min}}" style="width:50px;display:inline-block;">';
                    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" disabled title="{{Max}}" style="width:50px;display:inline-block;">';
                    tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" disabled title="{{Unité}}" style="width:50px;display:inline-block;margin-right:5px;">';
                    tr += '</td><td>';
                    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" disabled data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
                    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" disabled data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
                    if (init(_cmd.subType) == 'binary')
                        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" disabled data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span> ';
                    tr += '</td></tr>';
                    $('#table_jmqttTemplateCmds tbody').append(tr);
                    $('#table_jmqttTemplateCmds [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
                }
                if (init(_cmd.type) == 'action') {
                    var tr = '<tr class="cmd" tree-id="' +  _cmd.tree_id + '" style="height: 88px!important;">';
                    tr += '<td>';
                    if (init(_cmd.display) && init(_cmd.display.icon)) {
                        tr += '<div class="input-group"><input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" disabled>';
                        tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;">'+init(_cmd.display.icon)+'</span>';
                        tr += '</div>';
                    }
                    else
                        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" disabled>';
                    tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="margin-top:5px;margin-right:10px;" disabled><option>' + init(_cmd.value, 'Aucune') + '</option></select>';
                    tr += '</td>';
                    tr += '<td>';
                    tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom:5px;width:120px;" />';
                    tr += '<select style="width : 120px;margin-top : 5px;" class="cmdAttr form-control input-sm" disabled data-l1key="subType">';
                    if (init(_cmd.subType) == 'other')   tr += '<option value="other">Défaut</option>';
                    if (init(_cmd.subType) == 'slider')  tr += '<option value="slider">Curseur</option>';
                    if (init(_cmd.subType) == 'message') tr += '<option value="message">Message</option>';
                    if (init(_cmd.subType) == 'color')   tr += '<option value="color">Couleur</option>';
                    if (init(_cmd.subType) == 'select')  tr += '<option value="select">Liste</option>';
                    tr += '</select></td>';
                    tr += '<td>';
                    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" style="resize:none!important;height:65px;" disabled placeholder="{{Topic}}"></textarea>';
                    tr += '</td><td>';
                    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="resize:none!important;height:65px;" disabled placeholder="{{Valeur}}"></textarea>';
                    tr += '</td><td>';
                    if (init(_cmd.subType) == 'slider') {
                        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" disabled style="width:50px;display:inline-block;">';
                        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" disabled style="width:50px;display:inline-block;">';
                    }
                    if (init(_cmd.subType) == 'select')
                        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste : valeur|texte}}" disabled title="{{Liste : valeur|texte (séparées entre elles par des points-virgules)}}">';
                    tr += '</td><td>';
                    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" disabled checked/>{{Afficher}}</label></span><br/> ';
                    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" disabled data-l2key="retain"/>{{Retain}}</label></span><br/> ';
                    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" disabled data-l2key="autoPub"/>{{Pub. auto}}</label></span><br/> ';
                    tr += '<span class="checkbox-inline"><input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" disabled data-l2key="Qos" placeholder="{{Qos}}" title="{{Qos}}" style="width:50px;display:inline-block;"></span> ';
                    tr += '</td></tr>';
                    $('#table_jmqttTemplateCmds tbody').append(tr);
                    $('#table_jmqttTemplateCmds [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
                }
            }
            $('#div_listJmqttTemplate').children(0).scrollTop(0);
        }
    });
});

$('#bt_jmqttTemplateDelete').on('click', function() {
    if ($('#ul_jmqttTemplateList li.active').attr('data-file') == undefined) {
        $.fn.showAlert({message: "{{Sélectionnez d'abord un template}}", level: 'danger'})
        return
    }
    bootbox.confirm('{{Êtes-vous sûr de vouloir supprimer ce template ?}}', function(result) {
        if (result) {
            jmqtt.callPluginAjax({
                data: {
                    action: "deleteTemplateByFile",
                    file: $('#ul_jmqttTemplateList li.active').attr('data-file'),
                },
                error: function(error) {
                    $.fn.showAlert({message: error.message, level: 'danger'})
                },
                success: function(data) {
                    if (data) {
                        $.fn.showAlert({message: '{{Template supprimé.}}', level: 'success'})
                        refreshJmqttTemplateList()
                    } else
                        $.fn.showAlert({message: '{{Ce template ne peut pas être supprimé.}}', level: 'danger'})
                }
            })
        }
    })
})

$('#bt_jmqttTemplateDownload').on('click',function() {
    if ($('#ul_jmqttTemplateList li.active').attr('data-file') == undefined) {
        $.fn.showAlert({message: "{{Sélectionnez d'abord un template}}", level: 'danger'})
        return
    }
    window.open('core/php/downloadFile.php?pathfile=' + $('#ul_jmqttTemplateList li.active').attr('data-file'), "_blank", null)
})
</script>
