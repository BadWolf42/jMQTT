
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
		
$("#bt_addMQTTInfo").on('click', function(event) {
    var _cmd = {type: 'info'};
    addCmdToTable(_cmd);
});

$("#bt_addMQTTAction").on('click', function(event) {
    var _cmd = {type: 'action'};
    addCmdToTable(_cmd);
});

$('#bt_healthMQTT').on('click', function () {
    $('#md_modal').dialog({title: "{{Santé jMQTT}}"});
    $('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=health').dialog('open');
});

$("#table_cmd").delegate(".listEquipementInfo", 'click', function() {
    var el = $(this);
    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function(result) {
        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=calcul]');
        calcul.atCaret('insert', result.human);
    });
});

$("#table_cmd").delegate(".listEquipementAction", 'click', function() {
    var el = $(this);
    jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.attr('data-input') + ']');
        calcul.value(result.human);
    });
});

$("#table_cmd").delegate(".listEquipementInfo", 'click', function () {
    var el = $(this);
    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']');
        calcul.atCaret('insert', result.human);
    });
});

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    if (init(_cmd.type) == 'info') {
        var disabled = (init(_cmd.configuration.virtualAction) == '1') ? 'disabled' : '';
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
        tr += '<td>';
	tr += '<span class="cmdAttr" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom de l\'info}}"></td>';
        tr += '<td>';
	tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom : 5px;" />';
	tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
        tr += '</td><td>';
	tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" style="height : 33px;" ' + disabled + ' placeholder="{{Topic}}" readonly=true></textarea>';
        tr += '</td><td>';
        tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="value" style="height : 33px;" ' + disabled + ' placeholder="{{Valeur}}" readonly=true></textarea>';
        tr += '</td><td><input class="cmdAttr form-control input-sm" data-l1key="unite" style="width : 90px;" placeholder="{{Unité}}"></td><td>';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
	tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="parseJson"/>{{parseJson}}</label></span> ';
        tr += '<span class="expertModeVisible"><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span> ';
        tr += '<input style="width : 81%;margin-bottom : 2px;" class="tooltips cmdAttr form-control input-sm" data-l1key="cache" data-l2key="lifetime" placeholder="{{Lifetime cache}}" title="{{Lifetime cache}}">';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width : 40%;display : inline-block;"> ';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width : 40%;display : inline-block;">';
        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
        tr += '</tr>';
        $('#table_cmd tbody').append(tr);
        $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
        if (isset(_cmd.type)) {
            $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
        }
        jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
    }

    if (init(_cmd.type) == 'action') {
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<div class="row">';
        tr += '<div class="col-lg-6">';
        tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icone</a>';
        tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
        tr += '</div>';
        tr += '<div class="col-lg-6">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
        tr += '</div>';
        tr += '</div>';
        tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="display : none;margin-top : 5px;margin-right : 10px;" title="{{La valeur de la commande vaut par défaut la commande}}">';
        tr += '<option value="">Aucune</option>';
        tr += '</select>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom : 5px;" />';
        tr += '<span class="subType" subType="' + init(_cmd.subType) + '" style=""></span>';
        //tr += '<input class="cmdAttr" data-l1key="configuration" data-l2key="virtualAction" value="1" style="display:none;" >';
        tr += '</td>';
        tr += '<td>';
	tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" style="height : 33px;" ' + disabled + ' placeholder="{{Topic}}"></textarea><br/>';
	tr += '</td><td>';
	tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="height : 33px;" ' + disabled + ' placeholder="{{Valeur}}"></textarea>';
	tr += '<a class="btn btn-default btn-sm cursor listEquipementInfo" data-input="request" style="margin-left : 5px;"><i class="fa fa-list-alt "></i> {{Rechercher équipement}}</a>';
	tr +='</select></span>';
        tr += '</td><td>';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span><br> ';
	tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="retain"/>{{Retain}}</label></span><br> ';
	tr += '<span>{{Qos}}: <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="Qos" placeholder="{{Qos}}" title="{{Qos}}" style="width : 40%;display : inline-block;"></span> ';
        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
        tr += '</tr>';

        $('#table_cmd tbody').append(tr);
        //$('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
        var tr = $('#table_cmd tbody tr:last');
	jeedom.eqLogic.builSelectCmd({
	    id: $(".li_eqLogic.active").attr('data-eqLogic_id'),
	    filter: {type: 'info'},
	    error: function (error) {
		$('#div_alert').showAlert({message: error.message, level: 'danger'});
	    },
	    success: function (result) {
		tr.find('.cmdAttr[data-l1key=value]').append(result);
		tr.setValues(_cmd, '.cmdAttr');
		jeedom.cmd.changeType(tr, init(_cmd.subType));
	    }
	});
    }
}

