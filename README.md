[![stable version number](https://img.shields.io/badge/dynamic/json?url=https://github.com/BadWolf42/jMQTT/raw/stable/plugin_info/info.json&query=$.pluginVersion&label=Stable%20version%20is)](https://github.com/BadWolf42/jMQTT/tree/stable)
[![beta version number](https://img.shields.io/badge/dynamic/json?url=https://github.com/BadWolf42/jMQTT/raw/beta/plugin_info/info.json&query=$.pluginVersion&label=Beta%20version%20is)](https://github.com/BadWolf42/jMQTT/tree/beta)
[![dev version number](https://img.shields.io/badge/dynamic/json?url=https://github.com/BadWolf42/jMQTT/raw/dev/plugin_info/info.json&query=$.pluginVersion&label=Dev%20version%20is)](https://github.com/BadWolf42/jMQTT/tree/dev)
<br/>
[![dev jeedom version min](https://img.shields.io/badge/dynamic/json?url=https://github.com/BadWolf42/jMQTT/raw/dev/plugin_info/info.json&query=$.require&label=Supports%20Jeedom%20%3e%3d%20)](https://doc.jeedom.com/)
[![commit activity](https://img.shields.io/github/commit-activity/m/BadWolf42/jMQTT)](https://github.com/BadWolf42/jMQTT/pulse)
[![last commit](https://img.shields.io/github/last-commit/BadWolf42/jMQTT)](https://GitHub.com/BadWolf42/jMQTT)
<br/>
[![Check PHP](https://github.com/BadWolf42/jMQTT/actions/workflows/check-php.yml/badge.svg)](https://github.com/BadWolf42/jMQTT/actions/workflows/check-php.yml)
[![Check Python](https://github.com/BadWolf42/jMQTT/actions/workflows/check-python.yml/badge.svg)](https://github.com/BadWolf42/jMQTT/actions/workflows/check-python.yml)
[![pages-build-deployment](https://github.com/BadWolf42/jMQTT/actions/workflows/pages/pages-build-deployment/badge.svg)](https://github.com/BadWolf42/jMQTT/actions/workflows/pages/pages-build-deployment)

__________________

<p align="center">
<a href="https://community.jeedom.com/tag/plugin-jmqtt">Community</a> -
<a href="https://docs.bad.wf/fr_FR/jmqtt/dev">Documentation</a> -
<a href="https://docs.bad.wf/fr_FR/jmqtt/changelog">Change Log</a>
</p>

__________________

<p align="center">
  <img src="jMQTT.svg"/>
</p>

jMQTT is a plugin for Jeedom aiming to connect Jeedom to an MQTT broker to subscribe and publish messages.

Main functionalities are:
  * Automatic installation of the Mosquitto broker;
  * Multi broker support
  * Automatic creation of MQTT equipments, automatic creation of information commands, options to disable these automatisms;
  * Manual addition of MQTT equipement;
  * Duplication of equipments;
  * Decoding of complex JSON payload and creation of related information commands;
  * Manual addition of commands (for publishing), support of the retain mode;

# Sponsoring
I dedicate some of my time to maintain the plugin and help the users/community.

You like my work? You can, if you wish, encourage me with a little coffee or more 😊

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/H2H4QOAUG)

# Disclaimer
- This code does not pretend to be bug-free
- Although it should not harm your Jeedom system, it is provided without any warranty or liability

# Contributions
This plugin is opened for contributions and even encouraged! Please submit your pull requests for improvements/fixes.

# Credits
This plugin relies on the work done by:
- [Domochip](https://github.com/domochip) for maintaining this plugin until October 2023,
- [Domotruc](https://github.com/domotruc) for creating this plugin in December 2017 and maintaining it until December 2019.

__________________

<p align="center">
<a href="https://community.jeedom.com/tag/plugin-jmqtt">Community</a> -
<a href="https://docs.bad.wf/fr_FR/jmqtt/dev">Documentation</a> -
<a href="https://docs.bad.wf/fr_FR/jmqtt/changelog">Change Log</a>
</p>
