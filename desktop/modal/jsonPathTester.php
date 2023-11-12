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

<div id="div_alertExpressionTest" data-modalType="md_expressionTest"></div>
<form class="form-horizontal" onsubmit="return false;" style="width:100%">
  <div class="input-group input-group-sm" style="width:100%">
    <span class="input-group-addon roundedLeft" style="width:20%"><i class="fas fa-truck-loading"></i> {{Payload}}</span>
    <textarea class="form-control input-sm roundedRight" id="in_testPayload" style="min-height:80px;height:80px"></textarea>
  </div>
  <div class="input-group input-group-sm" style="width:100%">
  </div>
  <div class="input-group input-group-sm " style="width:100%">
    <span class="input-group-addon roundedLeft" style="width:20%"><i class="fas fa-binoculars"></i> {{Chemin JSON}}</span>
    <input class="form-control input-sm" id="in_testJsonPath">
    <span class="input-group-btn">
      <a class="btn btn-sm btn-default btn-success roundedRight" id="bt_executeTestJsonPath"><i class="fas fa-bolt"></i> {{Exécuter}}</a>
    </span>
  </div>
<br/>
<legend id="out_status"><i class="fas fa-sign-in-alt"></i> {{Résultat}} <span id="out_message"></span></legend>
<div id="div_expressionTestResult" style="width:100%"></div>
<textarea class="form-control input-sm roundedRight" id="out_testResult" style="min-height:80px;height:80px"></textarea>
</form>

<script>
$(function() {
    if ($(window).width() > 800)
        $('#md_modal').dialog("option", "width", 800);
    if ($(window).height() > 330)
        $('#md_modal').dialog("option", "height", 330);
});

$('#in_testJsonPath').keypress(function(event) {
    if (event.which == 13) {
        $('#bt_executeTestJsonPath').trigger('click');
    }
});

$('#bt_executeTestJsonPath').on('click',function() {
    $('#out_status').removeClass('success danger');
    $('#out_message').empty();
    jmqtt.callPluginAjax({
        data: {
            action: "testJsonPath",
            payload: $('#in_testPayload').val(),
            jsonPath: $('#in_testJsonPath').val()
        },
        error: function (error) {
            $('#out_status').addClass('danger');
            $('#out_testResult').value('');
        },
        success: function (data) {
            (data.success) ? $('#out_status').addClass('success') : $('#out_status').addClass('danger');
            $('#out_message').append('[ ' + data.message + ' ]');
            $('#out_testResult').value(data.value);
        }
    });
});
</script>
