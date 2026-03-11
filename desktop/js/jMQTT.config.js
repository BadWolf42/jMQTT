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

// Namespace
jmqtt_config = {};


//////////////////////////////////////////////////////////////////////////////
// Functions

// Copy of jmqtt.callPluginAjax() to handle "My plugins" page when jMQTT.functions.js is not included
jmqtt_config.jmqttAjax = function (_params) {
    $.ajax({
        async: _params.async == undefined ? true : _params.async,
        global: false,
        type: "POST",
        url: "plugins/jMQTT/core/ajax/jMQTT.ajax.php",
        data: _params.data,
        dataType: 'json',
        error: function (request, status, error) {
                if (typeof _params.error === 'function')
                    _params.error(request, status, error);
                else
                    handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (typeof _params.success === 'function')
                _params.success(data);
        }
    });
}

// Toggle spinner icon on button click
jmqtt_config.toggleIco = function (_this) {
    var h = _this.find('i.fas:hidden');
    var v = _this.find('i.fas:visible');
    v.hide();
    h.show();
}

// Helper to set buttons and texts
jmqtt_config.mosquittoStatus = function (_result) {
    if (_result.installed) {
        $('#bt_mosquittoInstall').addClass('disabled');
        $('#bt_mosquittoRepare').removeClass('disabled');
        $('#bt_mosquittoRemove').removeClass('disabled');
        $('#mosquittoService').empty().html(_result.service);
        $('#div_plugin_configuration .local-install').show();
        if (_result.service.includes('running'))
            $('#bt_mosquittoStop').removeClass('disabled');
        else
            $('#bt_mosquittoStop').addClass('disabled');
        if (_result.message.includes('jMQTT'))
            $('#bt_mosquittoEdit').show();
        else
            $('#bt_mosquittoEdit').hide();
    } else {
        $('#bt_mosquittoInstall').removeClass('disabled');
        $('#bt_mosquittoRepare').addClass('disabled');
        $('#bt_mosquittoRemove').addClass('disabled');
        $('#div_plugin_configuration .local-install').hide();
    }
    $('#mosquittoStatus').empty().html(_result.message);
}

// Send log level to daemon dynamically
jmqtt_config.logSaveWrapper = function () {
    btSave = $('#bt_savePluginLogConfig');
    if (!btSave.hasClass('jmqttLog')) { // Avoid multiple declaration of the event on the button
        btSave.addClass('jmqttLog');
        btSave.on('click', function() {
            var level = $('input.configKey[data-l1key="log::level::jMQTT"]:checked')
            if (level.length == 1) { // Found 1 log::level::jMQTT input checked
                jmqtt_config.jmqttAjax({
                    data: {
                        action: "sendLoglevel",
                        level: level.attr('data-l2key')
                    },
                    success: function(data) {
                        if (data.state == 'ok')
                            $.fn.showAlert({message: "{{Niveau de log du démon modifié, il n'est pas nécessaire de le redémarrer.}}", level: 'success'});
                    }
                });
            }
        });
    };
}


//////////////////////////////////////////////////////////////////////////////
// Mosquitto service related buttons

// Launch Mosquitto installation and wait for it to end
$('#bt_mosquittoInstall').on('click', function () {
    if (!$(this).hasClass('disabled')) {
        var btn = $(this);
        bootbox.confirm('{{Êtes-vous sûr de vouloir installer le service Mosquitto en local ?}}', function (result) {
            if (result) {
                jmqtt_config.toggleIco(btn);
                jmqtt_config.jmqttAjax({
                    data: { action: "mosquittoInstall" },
                    error: function (request, status, error) {
                        jmqtt_config.toggleIco(btn);
                        handleAjaxError(request, status, error);
                    },
                    success: function(data) {
                        jmqtt_config.toggleIco(btn);
                        if (data.state == 'ok') {
                            jmqtt_config.mosquittoStatus(data.result);
                            $.fn.showAlert({message: '{{Le service Mosquitto a bien été installé et configuré.}}', level: 'success'});
                        } else {
                            $.fn.showAlert({message: data.result, level: 'danger'});
                        }
                    }
                });
            }
        });
    }
});

// Launch Mosquitto reparation and wait for it to end
$('#bt_mosquittoRepare').on('click', function () {
    if (!$(this).hasClass('disabled')) {
        var btn = $(this);
        bootbox.confirm('{{Êtes-vous sûr de vouloir réparer le service Mosquitto local ?}}', function (result) {
            if (result) {
                jmqtt_config.toggleIco(btn);
                jmqtt_config.jmqttAjax({
                    data: { action: "mosquittoRepare" },
                    error: function (request, status, error) {
                        jmqtt_config.toggleIco(btn);
                        handleAjaxError(request, status, error);
                    },
                    success: function(data) {
                        jmqtt_config.toggleIco(btn);
                        if (data.state == 'ok') {
                            jmqtt_config.mosquittoStatus(data.result);
                            $.fn.showAlert({message: '{{Le service Mosquitto a bien été réparé.}}', level: 'success'});
                        } else {
                            $.fn.showAlert({message: data.result, level: 'danger'});
                        }
                    }
                });
            }
        });
    }
});

// Launch Mosquitto uninstall and wait for it to end
$('#bt_mosquittoRemove').on('click', function () {
    if (!$(this).hasClass('disabled')) {
        var btn = $(this);
        bootbox.confirm('{{Êtes-vous sûr de vouloir supprimer le service Mosquitto local ?}}', function (result) {
            if (result) {
                jmqtt_config.toggleIco(btn);
                jmqtt_config.jmqttAjax({
                    data: { action: "mosquittoRemove" },
                    error: function (request, status, error) {
                        jmqtt_config.toggleIco(btn);
                        handleAjaxError(request, status, error);
                    },
                    success: function(data) {
                        jmqtt_config.toggleIco(btn);
                        if (data.state == 'ok') {
                            jmqtt_config.mosquittoStatus(data.result);
                            $.fn.showAlert({message: '{{Le service Mosquitto a bien été désinstallé du système.}}', level: 'success'});
                        } else {
                            $.fn.showAlert({message: data.result, level: 'danger'});
                        }
                    }
                });
            }
        });
    }
});

// Start/restart Mosquitto service
$('#bt_mosquittoReStart').on('click', function () {
    jmqtt_config.jmqttAjax({
        data: { action: "mosquittoReStart" },
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            if (data.state == 'ok') {
                jmqtt_config.mosquittoStatus(data.result);
                $.fn.showAlert({message: '{{Le service Mosquitto a bien été (re)démarré.}}', level: 'success'});
            } else {
                $.fn.showAlert({message: data.result, level: 'danger'});
            }
        }
    });
});

// Stop Mosquitto service
$('#bt_mosquittoStop').on('click', function () {
    if (!$(this).hasClass('disabled')) {
        jmqtt_config.jmqttAjax({
            data: { action: "mosquittoStop" },
            error: function (request, status, error) {
                handleAjaxError(request, status, error);
            },
            success: function(data) {
                if (data.state == 'ok') {
                    jmqtt_config.mosquittoStatus(data.result);
                    $.fn.showAlert({message: '{{Le service Mosquitto a bien été arrêté.}}', level: 'success'});
                } else {
                    $.fn.showAlert({message: data.result, level: 'danger'});
                }
            }
        });
    }
});

// Modify jMQTT.conf in Mosquitto service system folder
$('#bt_mosquittoEdit').on('click', function () {
    jmqtt_config.jmqttAjax({
        data: { action: "mosquittoConf" },
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(result1) {
            if (result1.state == 'ok') {
                bootbox.confirm({
                    title: '{{Modifier le fichier jMQTT.conf du service Mosquitto}}',
                    message: '<textarea class="bootbox-input bootbox-input-text form-control" type="text" style="height: 50vh;font-family:CamingoCode,monospace; font-size:small!important; line-height:normal;" spellcheck="false" id="mosquittoConf">' + result1.result + '</textarea>',
                    callback: function (result2) {
                        if (result2) {
                            jmqtt_config.jmqttAjax({
                                data: { action: "mosquittoEdit", config: $('#mosquittoConf').value() },
                                error: function (request, status, error) {
                                    handleAjaxError(request, status, error);
                                },
                                success: function(result3) {
                                    if (result3.state == 'ok') {
                                        $.fn.showAlert({message: '{{Le fichier jMQTT.conf a bien été modifiée.<br/>Redémarrez le service Mosquitto pour le prendre en compte.}}', level: 'success'});
                                    } else {
                                        $.fn.showAlert({message: result3.result, level: 'danger'});
                                    }
                                }
                            });
                        }
                    }
                });
            } else {
                $.fn.showAlert({message: result1.result, level: 'danger'});
            }
        }
    });

});


//////////////////////////////////////////////////////////////////////////////
// Docker callback URL override

// On enable/disable jMqtt callback url override
$('#jmqttUrlOverrideEnable').change(function() {
    $oVal = $('#jmqttUrlOverrideValue');
    if ($(this).value() == '1') {
        if ($oVal.attr('valOver') != "")
            $oVal.value($oVal.attr('valOver'));
        $oVal.removeClass('disabled');
    } else {
        $oVal.attr('valOver', $oVal.value());
        $oVal.value($oVal.attr('valStd'));
        $oVal.addClass('disabled');
    }
});

// On jMqtt callback url override apply
$('#bt_jmqttUrlOverride').on('click', function () {
    var $valEn = $('#jmqttUrlOverrideEnable').value()
    jmqtt_config.jmqttAjax({
        data: {
            action: "updateUrlOverride",
            valEn: $valEn,
            valUrl: (($valEn == '1') ? $('#jmqttUrlOverrideValue').value() : $('#jmqttUrlOverrideValue').attr('valOver'))
        },
        success: function(data) {
            if (data.state != 'ok')
                $.fn.showAlert({message: data.result,level: 'danger'});
            else
                $.fn.showAlert({message: '{{Modification effectuée, merci de relancez le Démon.}}', level: 'success'});
        }
    });
});


$(document).ready(function() {
    // Remove unneeded Save button
    $('#bt_savePluginConfig').remove();

    // Display the real version number (X.Y.Z) just before the plugin version number (YYYY-MM-DD hh:mm:ss)
    var dateVersion = $("#span_plugin_install_date").html();
    $("#span_plugin_install_date").empty().append("v" + pluginVersion + " (" + dateVersion + ")");

    // Add a link to the plugin rating
    $('.bt_refreshPluginInfo').after('<a class="btn btn-success btn-sm" target="_blank" href="https://market.jeedom.com/index.php?v=d&p=market_display&id=3166"><i class="fas fa-comment-dots "></i> {{Avis}}</a>');

    // Update link for Community Post
    $('#createCommunityPost[data-plugin_id=jMQTT]')?.removeAttr('id').off('click').on('click', function () {
        jmqtt_config.jmqttAjax({
            data: {
                action: "jMQTTCommunityPost"
            },
            success: function (data) {
                if (data.state != 'ok') return;
                let element = document.createElement('a');
                element.setAttribute('href', data.result.url);
                element.setAttribute('target', '_blank');
                element.style.display = 'none';
                document.body.appendChild(element);
                element.click();
                document.body.removeChild(element);
            }
        });
    });

    if (!dStatus) {
        // Set Mosquitto status
        jmqtt_config.mosquittoStatus(mStatus);
    }

    // Wrap Log Save button
    jmqtt_config.logSaveWrapper();

    // TODO: Fix init tooltips workarround
    //  Init tooltips as a workarround to fix tips loading
    //  labels: workarround, javascript
    jeedomUtils.initTooltips();
});
