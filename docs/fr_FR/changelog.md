# Registre des évolutions

## 2023-11-25 (Beta v23.11.2)
- Ajout du template pour Tomotics WiFi switch v1 (merci SuperToma)
- Ajout du numéro de version réel du plugin dans la page de configuration
- Ajout d'un bouton pour accéder aux avis sur le plugin
- Correction de la vue/modale de configuration pour les petits écrans
- Correction du nombre d'équipements remontés dans un nouveau sujet Community (v4.4)
- Utilisation de la nouvelle API pour les statistiques
- Mise à jour de captures d'écran dans la documentation
- Mise à jour de traductions

## 2023-11-21 (Beta v23.11.1)
- Correction du tri par ordre alphabétique des icones des équipements
- Correction de l'affichage de la modale de gestion des templates
- Qualité du code : "Type hinting" autant que possible
- Ajout d'informations supplémentaires lors de la création d'un sujet Community (v4.4)

## 2023-11-19 (Beta v23.11.0)
- Correction des anciennes références au GitHub de Domochip
- Correction du Testeur de Chemin Json : nettoyage du champ de résultat
- Correction de l’ajout automatique de commandes (merci Math82)
- Correction en postSave de certaines commandes info
- Correction des paramètres de certains `json_encode()` dans les requêtes d’interaction
- Traduction complète du plugin en anglais (documentation toujours uniquement en français)
- Ajout de 2 templates pour les Shellies "1PM mini Plus" et "2PM Plus" (merci cgail914)
- Intégration continue : Création d’un Workflow TODO-to-issue
- Intégration continue : Affectation automatique des PR par Dependabot
- Intégration continue : Création d’un Workflow pour les tests statiques PHP (PHPStan remplace lint)
- Qualité du code : Passage à des numéros de version au format standard (`MAJOR.MINOR.PATCH`)
- Qualité du code : Découpage des gros fichiers en plusieurs plus petits fichiers
- Qualité du code : Application des recommandations PHP (par PHPStan)
- Qualité du code : "Type hinting" autant que possible
- Qualité du code : Application de recommandations Python (par flake8)
- Qualité du code : Suppression de `jMQTTCmd::checkCmdName()` (inutilisé)
- Qualité du code : Optimisations de `jMQTT::setType()` & `jMQTT::getType()`


## 2023-09-26 (v22)
- Passage en stable


---


## 2023-09-26 (v22)
- Correction d'un bug du mode Temps Réel pouvant crasher le Démon (cf : https://community.jeedom.com/t/112665)

## 2023-09-25 (v21)
- Ajout du bouton Community (uniquement pour Jeedom v4.4+)
- Ajout du template nécessaire au plugin MCZRemote
- Correction de multiples avertissements lors de la sauvegarde d'un équipement en v4.4
- Correction d'un bug du mode Temps Réel, lorsqu'il n'y a pas beaucoup de nouveaux messages
- Correction de certains log peu explicites
- Préparation pour PHP > 7.4

## 2023-09-04 (v20)
- Suppression de la QoS sur les équipements Broker (car inutilisée)
- Remplacement de la fonction `loadPage()` par `jeedomUtils.loadPage()` (introduite en 4.2 et dépréciée en v4.4)
- Ajout de 3 nouveaux templates Shelly : Flood, Vintage et Plus Plug S (merci Nebz et samud)
- Meilleure initialisation d'un équipement Broker lors de sa création
- Correction d'une erreur de dépendances suite à la migration vers Debian 11
- Correction de certains messages pour les rendre plus explicites
- Suppression de vieux crons orphelins
- Ajout de nouvelles fonctions de debug avancé
- Optimisation de la taille des icones

## 2023-07-15 (v19)
- Ajout de tags utilisables dans les payload des commandes action (cf : https://community.jeedom.com/t/tag-topic/108883)
- **Correction d'un plantage du démon lors de la publication sur un topic contenant `#` ou `?`**
- Avertissement en cas de mauvais topic de publication
- Correction du template du Shelly EM (merci Jeandhom)
- Correction du template du Tasmota Nous A1T
- Ajout de l'information d'historisation des commandes dans les templates
- Ajout du nombre d'équipements et de commandes dans la modale de santé
- Ajout du template pour le Nuki Smart Lock (merci ludomin & JC38)
- Ajout de nouvelles fonctions de debug avancé

## 2023-06-05 (v18)
- Correction mineure (erreur dans les logs)

## 2023-05-21 (v17 beta)
- **Ajout d'un champ pour configurer la durée du mode Temps Réel**
- **Changement du timeout du démon de 135s à 300s**
- Transformation des boutons en icones en haut à droite (à côté du bouton Sauvegarder)
- Mise à jour de l'icône "co" et ajout des icones : air-quality, battery, co2, openevse, solar-panel, switch, ups
- Détection de l'installation de Mosquitto par le plugin ZigbeeLinker (zigbee2mqtt)
- Ajout de l'onglet Temps Réel depuis tous les équipements (sous forme d'icône)
- Ajout du template pour le Shelly Motion 2 (merci Furaxworld)
- Correction d'un problème sur le template pour le Shelly 2.5 Roller Shutter (merci chris777c)
- Correction d'un problème dans le mode Temps Réel avec les topics contenant des guillemets
- Correction d'un problème lors de l'ajout d'une commande depuis mode Temps Réel dans un équipement n'appartenant à aucun objet
- Correction d'un problème dans le mode Temps Réel lorsque le fichier de collecte est vide
- Correction d'un problème d'affichage sur Safari (merci Toms)
- Optimisations du code, corrections syntaxiques et orthographiques (merci Furaxworld)
- Mise à jour de la documentation

## 2023-04-15 (v16)
- Correction de bugs mineur (sur le système de sauvegarde)

## 2023-04-11 (v15 beta)
- Correction d'un problème créant des commandes orphelines lors de la suppression d'un équipement Broker
- Correction des problèmes de multi-lancement du démon avec des signaux de vie entre le démon et Jeedom
- Correction d'un problème d'affichage et de gestion des équipements orphelins sur la page principale du plugin
- Correction d'un problème avec le topic de souscription lors de l'application d'un template
- **Passage à la version 3.0 de galbar/jsonpath : Attention au changement de l'opérateur de recherche recursive ( aka `..`)**
- **Ajout d'un outil de test des chemins Json (documentation à faire, mais fonctionnement simple)**
- Amélioration de l'affichage des commandes Action List
- Ajout du template Tasmota Nous A1T (merci vberder)
- Ajout de 14 nouvelles icones : espeasy, intex, location, mcz-remote, old-phone, openmqttgateway, phone, smartphone, repeater, smoke-detector, sonometer, stove, tasmota & theengs
- Première implémentation fonctionnelle du système de sauvegarde et de restauration de jMQTT indépendamment du Core (en BETA pour le moment)
- Optimisations du code, corrections syntaxiques et orthographiques
- Mise à jour de la documentation

## 2023-03-19 (v14 beta)
- **Ajout de la possibilité d'utiliser un template lors de la création d'un équipement (merci ngrataloup)**
- Ajout d'une alerte lorsqu'un message met trop de temps à être traité par Jeedom (merci rennais35000)
- Ajout du template Shelly 4 Pro PM (merci Furaxworld)
- Ajout du numéro de version et de la compatibilité avec la Luna dans le fichier `info.json`
- Correction lors de la modification du niveau de log, le démon n'était pas averti immédiatement
- Correction d'un bug lorsque le jsonPath n'est pas présent en base de données (merci xavax59)
- Correction des champs sur lesquels l'autocomplete était encore actif dans Chrome (merci ngrataloup)
- Suppression de messages intempestifs lors de la sauvegarde d'un équipement
- Nettoyage supplémentaire lors de la création ou de l'import d'un template
- Intégration progressive du système de sauvegarde de jMQTT
- Nouveau système de mise à jour des objets entre les versions
- Corrections syntaxiques et orthographiques

## 2023-02-27
- Correction de 2 bugs avec les templates: topic de base incorrectement identifié, underscore à la place d'espaces dans le nom
- Les statistiques ne sont poussées que toutes les 5 à 10 minutes en cas d'échec
- Utilisation de `127.0.0.1` au lieu de localhost pour l'url de callback (Workaround par rapport à un problème avec le fichier hosts sur la Luna)

## 2023-02-12
- Collecte de statistiques sur la base installée
- Correction d'un bug lors du changement entre la vue normale et la vue json sur Jeedom 4.4 Alpha (merci jerryzz, Phpvarious & kiboost)
- Correction de bugs lors de la sauvegarde d'un équipement sur Jeedom 4.4 Alpha (merci Phpvarious and jerryzz)

## 2023-02-07
- Correction d'un bug avec la clé API lors du premier lancement du plugin (merci PhilippeJ et Apose)

## 2023-02-04
- Arrêt du support de Python 2.7 et 3.6 (changement sur le package Python "requests" en 2.28.1)
- Ajout de la commande info binaire "connected" aux équipements Broker
- Ajout de boutons pour (re)démarrer ou arrêter le service Mosquitto local
- Ajout d'un bouton pour éditer le fichier de configuration jMQTT.conf du service Mosquitto
- Ajout du remplacement des références aux commandes dans un même équipement, lors du chargement d'un template (incluant des références internes), selon la demande #129
- Ajout de la prise en compte du changement de la clé API dans le Core
- Correction d'un bug lors de l'utilisation de '/' dans un payload (merci Jeandhom)
- Correction d'un bug lors du changement de page ou du rafraichissement, les modifications n'étaient pas signalées
- Correction d'un bug lors de la duplication d'un équipement : des commandes de l'équipement source étaient encore utilisées
- Correction d'un bug lors de la création ou de l'import d'un template : des id des commandes d'origine étaient encore conservées
- Correction d'un bug lorsqu'une commande n'existe pas/plus (merci Loïc)
- Correction d'un bug lors de la sauvegarde d'un équipement sur Jeedom 4.4 Alpha (merci Phpvarious and jerryzz)
- Correction d'un bug lors du changement entre la vue normale et la vue json sur Jeedom 4.4 Alpha (merci Phpvarious)
- Correction de bug d'affichage sur Jeedom 4.4 Alpha
- Correction du remplissage automatique de certains champs
- Passage à la version 2.28.2 du package Python "requests"

## 2022-12-27
- Nouvelle correction pour le bug d'affichage de l'icône sur la page d'un équipement
- Corrections icone -> icône
- Chargement plus rapide des valeurs des commandes

## 2022-12-24
- Nouveau bouton pour ajouter un nouvel équipement depuis la page Temps Réel
- Ajout de boutons et du darg & drop, pour importer les certificats sur les équipements Brokers
- Suggestion d'un nom pour la nouvelle commande ajoutée depuis la page Temps Réel
- Renommage du champ "Catégorie du topic" en "Icône de l'équipement"
- Correction d'un bug lors de l'utilisation de certificats clients en MQTTS et WSS (merci oracle7)
- Correction d'un bug d'affichage des états des équipements Broker lorsque le démon est arrêté
- Correction d'un bug d'affichage des icônes sur la page d'un équipement (merci Jeandhom)
- Correction d'un bug d'affichage des icônes sur la page d'un template
- Correction d'un bug d'affichage des liens Configuration avancée et Supprimer sur la page de santé (merci Bison)
- Corrections en vue d'une traduction globale en anglais
- Corrections de plusieurs typo (merci noodom)
- Défilement indépendant dans la modale de gestion des templates entre la liste des templates et l'aperçu
- Renommage des templates "Zwavejs2mqtt" en "ZWaveJSUI"
- Ajout de 15 templates : Shelly_Plus_2pm (merci Manumdk), OpenMQTTGateway_Xiaomi_Mija_HT, ZWaveJSUI_AEOTEC_ZW116_Nano_Switch, ZWaveJSUI_Fibaro_FGBS-222_Smart_Implant, ZWaveJSUI_Hank_DWS01_Door_Window_Sensor, ZWaveJSUI_Hank_HKZW-SO05_Smart_Plug, ZWaveJSUI_Philio_PST02-1A_Multi_Sensor_4_in_1, ZWaveJSUI_Philio_PST02A_Multi_Sensor_4_in_1 (merci loutre38), ZWaveJSUI_Philips_HUE_White, ZWaveJSUI_Philips_HUE_White_and_Color (merci m.georgein), Dingtian_2R (merci Philippe1155), Shelly_Button_1, Shelly_EM, Shelly_Plus_HT (merci Furaxworld), Tasmota_Teleinfo (merci Manumdk)

## 2022-11-10 Mode Temps Réel
 - **Suppression du mode "Inclusion" au profit du mode "Temps Réel" dans l'onglet Temps Réel du Broker**
 - **Ajout d'une case à cocher pour changer le Client-Id (afin d'essayer d'éviter tous les problèmes utilisateur avec le Client-Id)**
 - **Ajout de champs dans le broker pour définir le topic LWT et les valeurs quand le broker est en-ligne et hors-ligne**
 - **Remplacement de la case à cocher pour l'installation de Mosquitto par des boutons Installer/Réparer/Supprimer**
 - **Détection de la présence de Mosquitto sur le système (ou en docker) et, si possible, quel plugin l'a installé**
 - **Ajout du support des Interactions Jeedom via MQTT**
 - **Meilleure gestion du "topicMismatch", avertissement avant de sauvegarder et visuellement dans les champs lors de la saisie**
 - Ajout du transport du protocole MQTT sur Web Sockets (ws) et Web Sockets Secure (wss)
 - Mise en warning des équipements Broker lorsqu'ils n'arrivent pas à se connecter
 - Utilisation des fonctions de suppression du Core avec un résumé des liaisons
 - Suppression du bouton pour effectuer un changement de Broker, c'est fait à la sauvegarde de l'équipement
 - Moins de log lorsque le Démon est désactivé
 - Ajout de 8 templates : "Shelly 1 (Light)", "Shelly 1 (Relay)", "Shelly 1 (Relay & Temperature)", "Shelly 2.5 (Relay)" et "Shelly 2.5 (Roller Shutter)" (merci ngrataloup), "Shelly Bulb Duo RGBW" (merci Jeandhom), "Zwavejs2mqtt Fibaro Motion Sensor FGMS-001-ZW5" (merci mimilamy2000), "Zigbee2mqtt Lidl HG07834B" (merci seb49), "Zwavejs2mqtt NodOn Wall Switch CWS-3-1-01" (merci pifou)
 - Retrait de 2 anciens templates : "Zwave2mqtt Qubino ZMNHCD" et "Zwave2mqtt Qubino ZMNHOD"
 - Gros nettoyage pour retirer des correctifs temporaires liés au Core < 4.2
 - Support minimum avancé à la version 4.2.11 de Jeedom (au lieu de la version 4.2.16)
 - Amélioration du code JS pour rendre le plugin plus léger et réactif lors du chargement
 - Corrections mineures
 - Ajout de captures d'écrans de jMQTT pour le Market
 - Mise à jour de la documentation, des captures et du Changelog

## 2022-10-16
 - **Création d'une branche spéciale pour Jeedom 3, voir plus bas**
 - **Par défaut, la case "Installer Mosquitto" n'est plus cochée, il faut la cocher si on souhaite installer un broker sur Jeedom via jMQTT**
 - Correction d'un problème de souscription lors du changement d'équipement de Broker
 - Correction d'une erreur dans les logs lorsqu'un Broker n'a pas d'équipement qui lui est rattaché
 - Correction d'une erreur lors de la suppression d'un Broker encore en fonctionnement
 - Correction d'un problème de conversion des valeurs des batteries
 - Déplacement de l'ajout d'équipement jMQTT en haut de page
 - Suppression du mode Inclusion global, déplacé dans les actions en haut à droite dans l'équipement Broker
 - Déplacement des équipements Broker au début de chaque sections (mis en évidence en jaune)
 - Changement de l'onglet Broker pour ressembler à la page de configuration du plugin MQTT Manager
 - Suppression de la gestion des certificats par upload (mis en base de données), cela se configure maintenant sur chaque Broker
 - Implémentation de la TableView et reprise d'une partie des informations de santé dans cette vue
 - Ajout d'un bouton de configuration avancée des équipements en TableView
 - Affichage de la liste des équipements orphelins avant les équipements rattachés à des brokers
 - Mise en surbrillance des changements en temps réel sur la page des Commandes d'un équipement
 - Changement concernant le nombre d'équipement sur chaque Broker est affiché sur la page principale
 - Ajout de 4 templates : "Fibaro FGRGBW-442 RGBW Controller 2" (merci jerome6994), "Shelly 1PM PLUS" (merci Furaxworld), "Fibaro FGMS-001 Motion Sensor" (merci mimilamy2000) et Osram AB3257001NJ Smart+ (merci chris777c)
 - Passage de l'exécution des listeners (pour Pub. Auto) en arrière-plan, pour augmenter les performances
 - Dans le cas d'une commande action, si la valeur est vide, alors le tag correspondant est utilisé (slider, message, color, select)
 - Ajout de plus de détails lors de l'installation (et de l'échec) des dépendances
 - Suppression de reliquats de la bibliothèque "websocket-client"
 - Découpage de Changelog en 2 pages pour une meilleure lisibilité
 - Grosse mise à jour de la documentation avec plus d'explications autour de MQTT en général
 - Mise à jour de la bibliothèque galbar/jsonpath -> 2.1
 - Retrait des correctifs temporaires liés à Jeedom Core 3.3, 4.0 et 4.1.
 - Corrections syntaxiques et orthographiques & Changelog

## 2022-10-15 **Branche Jeedom v3**
 - Création d'une branche spéciale pour Jeedom 3 (il n'y aura plus de mise à jour dans le futur)
 - Mise en place d'un bandeau indiquant que Jeedom 3 ne sera plus supporté par les prochaines versions
 - Correction des derniers problèmes de compatibilité avec Jeedom 3.3
 - Correction d'un problème de souscription lors du changement d'équipement de Broker
 - Correction d'une erreur lors de la suppression d'un Broker encore en fonctionnement
 - Correction d'un problème de conversion des valeurs des batteries
 - Passage de l'exécution des listeners (pour Pub. Auto) en arrière-plan, pour augmenter les performances
 - Mise à jour de la bibliothèque galbar/jsonpath -> 2.1
 - Corrections syntaxiques et orthographiques
