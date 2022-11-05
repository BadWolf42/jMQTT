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

// New namespace
function jmqtt() {}
jmqtt.globals = {};

// Array of Equipment logo descriptors
jmqtt.globals.logos = [
	{id: '', name: "{{Aucun}}", file: 'node_.svg'},
	{id: 'barometre', name: "{{Baromètre}}", file: 'node_barometre.svg'},
	{id: 'bell', name: "{{Sonnerie}}", file: 'node_bell.svg'},
	{id: 'boiteauxlettres', name: "{{Boite aux Lettres}}", file: 'node_boiteauxlettres.svg'},
	{id: 'bt', name: "{{Bluetooth}}", file: 'node_bt.svg'},
	{id: 'chauffage', name: "{{Chauffage}}", file: 'node_chauffage.svg'},
	{id: 'compteur', name: "{{Compteur}}", file: 'node_compteur.svg'},
	{id: 'contact', name: "{{Contact}}", file: 'node_contact.svg'},
	{id: 'custom', name: "{{Custom}}", file: 'node_custom.svg'},
	{id: 'dimmer', name: "{{Dimmer}}", file: 'node_dimmer.svg'},
	{id: 'door', name: "{{Porte}}", file: 'node_door.svg'},
	{id: 'energie', name: "{{Energie}}", file: 'node_energie.svg'},
	{id: 'fan', name: "{{Ventilation}}", file: 'node_fan.svg'},
	{id: 'feuille', name: "{{Culture}}", file: 'node_feuille.svg'},
	{id: 'fire', name: "{{Incendie}}", file: 'node_fire.svg'},
	{id: 'garage', name: "{{Garage}}", file: 'node_garage.svg'},
	{id: 'gate', name: "{{Portail}}", file: 'node_gate.svg'},
	{id: 'home-flood', name: "{{Inondation}}", file: 'node_home-flood.svg'},
	{id: 'humidity', name: "{{Humidité}}", file: 'node_humidity.png'},
	{id: 'humiditytemp', name: "{{Humidité et Température}}", file: 'node_humiditytemp.png'},
	{id: 'hydro', name: "{{Hydrométrie}}", file: 'node_hydro.png'},
	{id: 'ir2', name: "{{Infra Rouge}}", file: 'node_ir2.png'},
	{id: 'jauge', name: "{{Jauge}}", file: 'node_jauge.svg'},
	{id: 'light', name: "{{Luminosité}}", file: 'node_light.png'},
	{id: 'lightbulb', name: "{{Lumière}}", file: 'node_lightbulb.svg'},
	{id: 'meteo', name: "{{Météo}}", file: 'node_meteo.png'},
	{id: 'molecule-co', name: "{{CO}}", file: 'node_molecule-co.svg'},
	{id: 'motion', name: "{{Mouvement}}", file: 'node_motion.png'},
	{id: 'motion-sensor', name: "{{Présence}}", file: 'node_motion-sensor.svg'},
	{id: 'multisensor', name: "{{Multisensor}}", file: 'node_multisensor.png'},
	{id: 'nab', name: "{{Nabaztag}}", file: 'node_nab.png'},
	{id: 'power-plug', name: "{{Prise de courant}}", file: 'node_power-plug.svg'},
	{id: 'prise', name: "{{Prise}}", file: 'node_prise.png'},
	{id: 'radiator', name: "{{Radiateur}}", file: 'node_radiator.svg'},
	{id: 'relay', name: "{{Relais}}", file: 'node_relay.png'},
	{id: 'remote', name: "{{Télécommande}}", file: 'node_remote.svg'},
	{id: 'rf433', name: "{{RF433}}", file: 'node_rf433.svg'},
	{id: 'rfid', name: "{{RFID}}", file: 'node_rfid.png'},
	{id: 'sms', name: "{{SMS}}", file: 'node_sms.png'},
	{id: 'teleinfo', name: "{{Téléinfo}}", file: 'node_teleinfo.png'},
	{id: 'temp', name: "{{Température}}", file: 'node_temp.png'},
	{id: 'thermostat', name: "{{Thermostat}}", file: 'node_thermostat.png'},
	{id: 'tv', name: "{{Télévison}}", file: 'node_tv.svg'},
	{id: 'volet', name: "{{Volet}}", file: 'node_volet.svg'},
	{id: 'water-boiler', name: "{{Chaudière}}", file: 'node_water-boiler.svg'},
	{id: 'wifi', name: "{{Wifi}}", file: 'node_wifi.svg'},
	{id: 'window-closed-variant', name: "{{Fenêtre}}", file: 'node_window-closed-variant.svg'},
	{id: 'zigbee', name: "{{Zigbee}}", file: 'node_zigbee.svg'},
	{id: 'zwave', name: "{{ZWave}}", file: 'node_zwave.svg'}
]
jmqtt.globals.logos.sort(function(a, b) { return a.name.localeCompare(b.name); });

// Array of Status Icons descriptors and selectors
jmqtt.globals.icons = {
	broker: {
		status: {
			selector: function(_eq) { var info = jmqtt.getMqttClientInfo(_eq); return (info.la == 'nok') ? false : info.state; },
			ok:       { icon: 'fas fa-check-circle', color: 'success', msg: '{{Connection au Broker active}}' },
			pok:      { icon: 'fas fa-minus-circle', color: 'warning', msg: '{{Connexion au Broker en échec}}' },
			nok:      { icon: 'fas fa-times-circle', color: 'danger',  msg: "{{Le Démon n'est pas démarré}}" },
			false:    { icon: 'fas fa-times-circle', color: 'danger',  msg: '{{Connexion au Broker désactivée}}' }
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
			true:     { icon: 'fas fa-check', color: 'success', msg: '{{Equipement activé}}' },
			false:    { icon: 'fas fa-times', color: 'danger',  msg: '{{Equipement désactivé}}' }
		},
		visible: {
			selector: function(_eq) { return _eq.isVisible == '1'; },
			true:     { icon: 'fas fa-eye',       color: 'success', msg: '{{Equipement visible}}' },
			false:    { icon: 'fas fa-eye-slash', color: 'warning', msg: '{{Equipement masqué}}' }
		},
		learning: {
			selector: function(_eq) { return _eq.configuration.auto_add_cmd == '1'; },
			true:     { icon: 'fas fa-sign-in-alt fa-rotate-90', color: 'warning', msg: '{{Ajout automatique de commandes activée}}' },
			false:    { icon: 'far fa-square',                   color: 'success', msg: '{{Ajout automatique de commandes désactivée}}' }
		},
		battery: {
			selector: function(_eq) { return (_eq.configuration.battery_cmd == '') ? 'none' : (_eq.status.batterydanger ? 'nok' : (_eq.status.batterywarning ? 'pok' : 'ok')); },
			none:     { icon: 'fas fa-plug',            color: '',        msg: "{{Pas d'état de la batterie}}" },
			ok:       { icon: 'fas fa-battery-full',    color: 'success', msg: '{{Batterie OK}}' },
			pok:      { icon: 'fas fa-battery-quarter', color: 'warning', msg: '{{Batterie en alarme}}' },
			nok:      { icon: 'fas fa-battery-empty',   color: 'danger',  msg: '{{Batterie en fin de vie}}' }
		},
		availability: {
			selector: function(_eq) { return (_eq.configuration.availability_cmd == '') ? 'none' : (_eq.status.warning ? 'nok' : 'ok'); },
			none:     { icon: 'far fa-bell', color: '',        msg: "{{Pas d'état de disponibilité}}" },
			ok:       { icon: 'fas fa-bell', color: 'success', msg: '{{Equipement disponible}}' },
			nok:      { icon: 'fas fa-bell', color: 'danger',  msg: '{{Equipement indisponible}}' }
		}
	}
};

// To memorise page refresh timeout when set
jmqtt.globals.refreshTimeout = null;

// To reload Real Time view
jmqtt.globals.refreshRealTime = null;
jmqtt.globals.lockRealTime = false;

// To memorise current eqLogic main subscription topic
jmqtt.globals.mainTopic = '';

// Update daemon state global variable on reception of a new event (jmqtt.globals.daemonState is initialized by sendVarToJS() in jMQTT.php)
$('body').off('jMQTT::EventDaemonState').on('jMQTT::EventDaemonState', function (_event, _options) {
	jmqtt.globals.daemonState = _options;
});

