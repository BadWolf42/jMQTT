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

// TODO: Remove core4.2 backward compatibility `jeeFrontEnd` js function
//  Remove when Jeedom 4.2 is no longer supported
//  labels: workarround, core4.2, javascript

// Handle retrocompatibility of jeeFrontEnd namespace in Jeedom 4.2
if (typeof jeeFrontEnd === 'undefined') {
    jeeFrontEnd = window;
}

// TODO: Remove core4.2 backward compatibility `addUpdateFunction` js function
//  Remove when Jeedom 4.2 is no longer supported
//  labels: workarround, core4.2, javascript

// Handle retrocompatibility of addUpdateFunction function in Jeedom 4.2
if (typeof jeedom.cmd.addUpdateFunction !== 'function') {
    jeedom.cmd.addUpdateFunction = function(id, func) {
        jeedom.cmd.update[id] = func;
    }
}


// Namespace
jmqtt_globals = {};

// Array of Status Icons descriptors and selectors
jmqtt_globals.icons = {
    broker: {
        status: {
            selector: function(_eq) { var info = jmqtt.getMqttClientInfo(_eq); return (!jmqtt_globals.daemonState) ? false : info.state; },
            ok:       { icon: 'fas fa-check-circle', color: 'success', msg: '{{Connexion au Broker active}}' },
            pok:      { icon: 'fas fa-minus-circle', color: 'warning', msg: '{{Connexion au Broker en échec}}' },
            nok:      { icon: 'fas fa-times-circle', color: 'danger',  msg: '{{Connexion au Broker désactivée}}' },
            false:    { icon: 'fas fa-times-circle', color: 'danger',  msg: "{{Démon non démarré}}" }
        },
        visible: {
            selector: function(_eq) { return _eq.isVisible == '1'; },
            true:     { icon: 'fas fa-eye',       color: 'success', msg: '{{Broker visible}}' },
            false:    { icon: 'fas fa-eye-slash', color: 'warning', msg: '{{Broker masqué}}' }
        },
        learning: {
            selector: function(_eq) { return _eq.cache.realtime_mode == '1'; },
            true:     { icon: 'fas fa-sign-in-alt fa-rotate-90', color: 'danger',  msg: '{{Temps Réel activé}}' },
            false:    { icon: 'far fa-square',                   color: 'success', msg: '{{Temps Réel désactivé}}' }
        },
        battery: {
            selector: function(_eq) { return 'none'; },
            none:     { icon: '', color: '', msg: "" }
        },
        availability: {
            selector: function(_eq) { return 'none'; },
            none:     { icon: '', color: '', msg: "" }
        }
    },
    eqpt: {
        status: {
            selector: function(_eq) { return _eq.isEnable == '1'; },
            true:     { icon: 'fas fa-check', color: 'success', msg: '{{Activé}}' },
            false:    { icon: 'fas fa-times', color: 'danger',  msg: '{{Désactivé}}' }
        },
        visible: {
            selector: function(_eq) { return _eq.isVisible == '1'; },
            true:     { icon: 'fas fa-eye',       color: 'success', msg: '{{Visible}}' },
            false:    { icon: 'fas fa-eye-slash', color: 'warning', msg: '{{Masqué}}' }
        },
        learning: {
            selector: function(_eq) { return _eq.configuration.auto_add_cmd == '1'; },
            true:     { icon: 'fas fa-sign-in-alt fa-rotate-90', color: 'danger', msg: '{{Ajout auto. de commandes activé}}' },
            false:    { icon: 'far fa-square',                   color: 'success', msg: '{{Ajout auto. de commandes désactivé}}' }
        },
        battery: {
            selector: function(_eq) { return (_eq.configuration.battery_cmd == '') ? 'none' : (_eq.status.batterydanger ? 'nok' : (_eq.status.batterywarning ? 'pok' : 'ok')); },
            none:     { icon: 'fas fa-plug',            color: '',        msg: '' },
            ok:       { icon: 'fas fa-battery-full',    color: 'success', msg: '{{Batterie OK}}' },
            pok:      { icon: 'fas fa-battery-quarter', color: 'warning', msg: '{{Batterie en alarme}}' },
            nok:      { icon: 'fas fa-battery-empty',   color: 'danger',  msg: '{{Batterie en fin de vie}}' }
        },
        availability: {
            selector: function(_eq) { return (_eq.configuration.availability_cmd == '') ? 'none' : (_eq.status.warning ? 'nok' : 'ok'); },
            none:     { icon: 'far fa-bell', color: '',        msg: '' },
            ok:       { icon: 'fas fa-bell', color: 'success', msg: '{{Equipement disponible}}' },
            nok:      { icon: 'fas fa-bell', color: 'danger',  msg: '{{Equipement indisponible}}' }
        }
    }
};

// To memorise page refresh timeout when set
jmqtt_globals.refreshTimeout = null;

// To reload Real Time view
jmqtt_globals.refreshRealTime = null;
jmqtt_globals.lockRealTime = false;

// To memorise current eqLogic main subscription topic
jmqtt_globals.mainTopic = '';

// Drop zone counter
jmqtt_globals.dropzoneCpt = 0;

// Update daemon state global variable on reception of a new event (jmqtt_globals.daemonState is initialized by sendVarToJS() in jMQTT.php)
$('body').off('jMQTT::EventDaemonState').on('jMQTT::EventDaemonState', function (_event, _options) {
    jmqtt_globals.daemonState = _options;
});
