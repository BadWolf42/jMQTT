# Registre des évolutions

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
- Ajout du remplacement des références aux commandes dans un même équipement, lors du chargement d'un template (incluant des références internes), selon la [demande #129](https://github.com/Domochip/jMQTT/issues/129)
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

## 2022-07-25
 - Correction de problèmes avec les Heartbeat
 - Désactivation du timeout côté démon
 - Augmentation du nombre de Heartbeat et allongement du timeout

## 2022-07-24
 - Correction d'un problème avec le démon lorsque Jeedom n'est pas à la racine du serveur web
 - Correction de l'utilisation de `netstat` pour valider le PID du démon (ajout de ss et lsof pour être sûr)
 - Correction de la prise en charge des redirections (max 3) dans le démon (ex : Let's Encrpyt 80 -> 443)
 - Correction de l'événement ONLINE sur un Broker dont la StatusCmd n'existe pas
 - **Ajoutez un champ de configuration sur les systèmes Docker pour personnaliser (si besoin) l'URL de callback.**
 - Correction du délai d'expiration du démon si aucun message n'est reçu/envoyé pendant trop longtemps
 - Correction problème de journalisation dans JeedomMsg.py
 - Préparation des équipements pour "Tableview"

## 2022-07-22
 - Correction d'un bug lorsque Jeedom n'écoute pas en http ou sur le port 80
 - Correction d'un bug avec Jeedom en Docker si lancé sans `--privileged=true`
 - Correction du nom de la commande dans le log lors d'une publication

## 2022-07-19 Démon uniquement en Python
 - **Suppression du démon PHP, sans que jMQTT ne perdre de fonctionnalité**
 - **Amélioration les performances et réduction de la consommation mémoire**
 - **Prise en compte immédiate du changement du niveau de log par le démon**
 - **Configuration dynamique du port du démon, plus besoin de le définir**
 - **Ajout d'un bouton permettant de changer tous les Topics d'un Equipement**
 - **Ajout d'un champ de configuration pour la commande définissant la Batterie d'un Equipement**
 - **Ajout d'un champ de configuration pour la commande définissant l'état de Disponibilité d'un Equipement**
 - Suppression des dépendances PHP à Ratchet
 - Amélioration de la détection de l'état des Broker et du démon
 - **Changement du nom du fichier de log du démon pour correspondre au standard de Jeedom (jMQTTd)**
 - Déplacement de la barre de recherche pour correspondre au standard de Jeedom
 - Amélioration de la gestion du cache interne
 - **Ajout de plus de debug lors de l'ajout de commandes, au cas où les erreurs MySQL 'Duplicate entry' continueraient**
 - Ajoute de plus de log en cas d'Exception
 - Amélioration des log du plugin, entièrement en français et prêt à être traduits dans les autres langues
 - **Consolidation des logs en rapport avec un Broker : ils sont tous dans le log du Broker**
 - Utilisation le plus souvent possible de $cmd->getHumanName() pour parler d'un Objet, Equipement, Broker ou Commande
 - Amélioration des logs du démon pour les rendre plus lisibles (mais toujours en anglais)
 - Ajout d'un niveau de log "verbose" pour avoir les logs des bibliothèques utilisées par le démon
 - Ajout d'une fonction de debug avancé capturant l'état des fil d'exécution du démon
 - Remplacement de `jeedom.py` (communication avec Jeedom) par une version plus élaborée
 - Simplification du code du démon python suite à la suppression du démon php
 - Retrait de la suppression du fichier PID par le démon (tâche à effectuer par le Core)
 - Déplacement de la classe jMqttClient vers un autre fichier Python
 - Ajout d'une modale de debug avancé
 - Ajout de signaux de vie entre le démon et Jeedom
 - Ajout et nettoyage des symboles de traduction
 - Préparatifs pour le mode tableau de la vue des équipements
 - Simplification des logs du démon
 - Correction d'un bug lors de l'utilisation du même Topic sur plusieurs équipements
 - Correction d'un bug (non impactant) dans le démon
 - Remplacement de la fonction obsolète jeedom.eqLogic.builSelectCmd() par buildSelectCmd()
 - Correction du padding des liens de la documentation
 - Mise à jour des documentations
 - Corrections syntaxiques et orthographiques

## 2022-06-15
 - Correction d'un bug lors de la vérification des dépendances chez certains utilisateurs

## 2022-06-13
 - Nettoyage des noms des templates, ajout en commentaire de liens vers community ou les sources des templates
 - Ajout de 30 nouveaux templates, merci à Nicoca-ine et Mikael, Meute, Jbval, lolo_95 et iPaaad !
 - Ajout d'une fonction permettant à un plugin tiers d'ajouter/modifier facilement un équipement dans jMQTT avec un template
 - Modifications et réécritures mineures des certaines fonctions
 - Correction d'erreurs dans le fonction jMQTT::HTMLtoXY
 - Implémentation d'un Environnement Virtuel python3 pour mieux gérer les dépendances
 - Correction de l'échappement du chemin JSON
 - Correction d'une erreur javascript lors du changement d'équipement
 - **Un message MQTT compressé avec zlib est automatiquement reçu décompressé dans Jeedom sur la commande info associée**
 - **Un message MQTT binaire est automatiquement reçu en base64 dans Jeedom sur la commande info associée**
 - Changement du début du format des logs du démon Python pour être plus lisible en v4.2
 - Corrections concernant la nouvelle fonction createEqWithTemplate
 - Conversion à la volée du chemin JSON lors de l'utilisation d'anciennes Template
 - Ajout du nombre d'équipement par Broker dans les pages de Santé
 - Nettoyage et embellissement des pages de Santé
 - Affichage d'informations sur l'équipement dans le Gestionnaire de Template
 - Simplifications de certaines parties du code (lecture des fichiers de Template) et ajout de plus de gestion d'erreurs
 - Utilisation d'icônes différentes selon les états des Brokers (pour ceux qui distinguent mal les couleurs)
 - Alignement visuel des champs des commandes actions
 - Correction du défilement sur la page des commandes d'un équipement
 - Plus de messages de débug lors d'une Exception sur on_mqtt_message
 - Une commande action peut maintenant aussi est "irremovable" (besoin pour plugins tiers)

## 2022-02-28
 - Correction d'un bug en cas de tentative de suppression d'une commande orpheline (sans EqLogic)
 - Correction du nettoyage des info broker des equipements (les champs ayant '' pour valeur étaient supprimés avant envoi)
 - Nouvel affichage du selecteur d'icônes sur les commandes
 - Mise en place d'une alerte sur les dépendances Composer
 - Ajout de la dépendance galbar/jsonpath
 - **Suppression de la classe intermédiaire jMQTTBase**
 - **Séparation du chemin JSON et du topic dans un nouveau champ de configuration (jsonPath)**
 - **Déplacement du topic de souscription des équipements dans un nouveau champ de configuration (auto_add_topic)**
 - Correction de la progression de l'installation des dépendances

## 2022-01-31
 - Prise en compte de la logique de répetition des infos
 - Suppression du niveau de batterie si les commandes infos ne remplissent plus le critère
 - Correction de la verification des informations de certificat
 - Correction de fuite d'information des brokers dans les equipements
 - Suppression des infos brokers dans les equipements
 - Suppression des infos brokers dans les templates
 - Suppression des infos brokers dans les templates perso
 - Correction d'un bug qui ajoute des \ avant chaque / lors du traitement de commande info JSON
 - Correction du démon Python pouvait planter lors d'une sub/unsub invalide
 - Gestion de l'évolution de la fonction export() pour les futures versions de Jeedom

## 2022-01-12
 - Amélioration du log en cas d'erreur sur le démon PHP
 - Création automatique d'un fichier de configuration Mosquitto s'il n'y en a pas
 - Utilisation d'une librairie pour améliorer la lisibilité lors de l'installation des dépendances
 - Correction temporaire de la librairie RatchetPHP concernant les payload de plus de ~65Ko sur les OS 32bits et RPi
 - La reinstallation des dépendances n'est plus forcée lors de l'installation initiale de jMQTT
 - Ajout de python3-wheel lors de l'installation des dépendances

## 2021-11-29 AutoPublish
  - Ajout de la fonctionalité  "Pub. Auto" qui permet la Publication automatique en MQTT lors du changement du champ Valeur d'une commande action (Attention : la charge engendrée sur le système est actuellement inconnue)
  - Fix du prototype de la fonction jMQTT:cron()

## 2021-11-16 Template Manager
  - Améliorations mineures du log de DEBUG
  - Les images sont maintenant dans "core/img" pour assurer la compatibilité avec le Core Jeedom 4.2.5
  - Ajout du gestionaire de Template (permet d'ajouter, de télécharger et de supprimer des Templates et d'en visualiser les commandes)

## 2021-09-18
  - Correction : l'équipement Broker "local" ne se créée plus si un équipement Broker existant est configuré avec l'ip de la machine

## 2021-09-17 Nouveaux démons
**ATTENTION :  De gros changements ont été apporté au plugin**
En cas de problème, merci d'ouvrir un thread sur [community ici](https://community.jeedom.com/tag/plugin-jmqtt) ou une issue sur [Github ici](https://github.com/Domochip/jMQTT/issues)
  - Changement complet de moteur MQTT
  - Passage au nouveau démon (PHPWebSocket + Python) utilisant la gestion fournie par Jeedom Core
  - Réécriture complète de la gestion des Création/Modification/Suppression des équipements
  - Structure de classes permettant l'utilisation dans de futurs plugins satellites
  - Nouvelles dépendances plus légères et maintenues
  - Renommage de l'ancienne partie démon en MqttClient
  - Retrait de Ratchet des sources et ajout au méchanisme d'installation de dépendances
  - Amélioration de la gestion des erreurs dans le démon WebSocket PHP
  - Nettoyage des anciennes dépendances lors de l'update du plugin
  - Ajout du support de TLS et modification de la documentation en conséquence
  - Correction temporaire de la transformation des entiers en texte dans les JSON des commandes actions (Jeedom Core [PR#1829](https://github.com/jeedom/core/pull/1829))
  - Amélioraton du démon Python
  - TLS : Ajout de boutons pour l'upload/suppression de fichiers de CA/Cert/Key
  - Correction de Bug sur l'application des Template : La souscription au topic ne se faisait pas après l'application d'un template + des messages de topics mismatch apparaissaient
  - Augmentation du timeout WebSocket et kill -9 des démon si pas arrêté au bout de 10 secondes
  - Ajout de l'icône Nabaztag
  - Ajout de template de volet roulant
  - Stockage des template dans le dossier data permettant de les conserver durant les MAJ du plugin
  - IMAGES : Changement du logo du plugin, reprise des captures d'écran de la documentation, optimisation des tailles
  - Correction de l'Ajout automatique des commandes qui pouvait générer des erreurs MySQL de type "Duplicate entry", voir [Issue#89](https://github.com/Domochip/jMQTT/issues/89).
  - Le passage au nouveau démon déclenche correctement l'installation des nouvelles dépendances

## 2021-06-02
  - Correction [PR#86](https://github.com/Domochip/jMQTT/pull/86) : mosquitto_pub ne prend plus en charge les '/' dans le clientId. Ils ont donc été remplacé par des '-'

## 2021-04-27
  - Correction [PR#40](https://github.com/Domochip/jMQTT/pull/40): gestion du cas d'erreur de renommage du log d'un broker lorsque le log n'existe pas encore
  - Désactivation et retrait de l'Ajout automatique des commandes pour les équipements de type broker
  - Remplacement de la fonction topicMatchesSub de Mosquitto-PHP par un portage PHP de la fonction mosquitto_topic_matches_sub

## 2021-04-18
  - Réécriture des premiers chapitre de la documentation
  - Le bouton de suppression de commandes s'affiche sur les nouvelles commandes en mode "classic"
  - Les champs compte et mot de passe de connexion n'ont plus d'autocomplete
  - Correction [Issue#35](https://github.com/Domochip/jMQTT/issues/35): Bug d'ajout de commande action puis info...

## 2021-04-13
  - Correction de la conversion de texte transformé en entier dans les payload (Ex : {"bidule":"007"} -> {"bidule":7})
  - Les équipements de type broker ne sont plus en mode ajout automatique à la création
  - On ne peux plus ajouter/supprimer de commandes sur les équipements de type Broker
  - Ajout dans la santé du plugin du champ "Inscrit au Topic" sur chaque équipement
  - L'API JSON RPC over MQTT est maintenant désactivée par défaut
  - Création d'un broker par défaut suite à l'installation de mosquitto

## 2021-04-07
  - Mises à jour du README, de la documentation et des informations du plugin
  - Amélioration de la remontée du niveau de batterie : la valeur ne doit pas être un JSON (JSON pas encore éclaté d'un auto-inclusion) ou vide
  - Amélioration de la remontée du niveau de batterie : commande dont le nom termine par "battery" ou "batterie" ou taggué avec le type générique batterie
  - Amélioration de la remontée du niveau de batterie : prise en compte des formules, limites, etc.
  - Correction : la fenêtre "Configuration commande" ne s'affiche plus lors du double-click sur un champ textarea d'une commande (PR faite auprès de Jeedom Core en parallèle)
  - Correction : la fenêtre "Configuration commande" ne s'affiche plus sur les commandes qui ne sont pas encore sauvegardées (pas d'ID)
  - Amélioration du style des combobox sur les équipements
  - Rotation du symbole d'inclusion sur la liste des équipements
  - Descente du champ Commentaire
  - Correction : bug du numéro de commande action sur 2 lignes
  - Amélioration des performances de la vue JSON : Ajout des lignes commandes comme non visibles d'abord
  - Amélioration des performances de la vue JSON : Suppression de la librairie jQuery-TreeGrid et réécriture complète de la gestion de l'arborescence
  - Amélioration [PR#26](https://github.com/Domochip/jMQTT/pull/26): Ajout d'une case à cocher afin d'afficher/masquer toutes les commandes (Amélioration cachée pour le moment)

## 2021-03-31
  - Correction : Bug lors de la publication de message avec username/password pour le broker

## 2021-03-26
  - Correction [PR#22](https://github.com/Domochip/jMQTT/pull/22): Bug grisant toutes les commandes à la création d'un équipement jMQTT (revert d'une partie de la PR#17)

## 2021-03-25
  - Correction [PR#17](https://github.com/Domochip/jMQTT/pull/17) [PR#19](https://github.com/Domochip/jMQTT/pull/19): Bug grisant toutes les commandes si l'on place un équipement jMQTT sur une Vue
  - Correction [PR#11](https://github.com/Domochip/jMQTT/pull/11): Typo dans le code d'application de template

## 2021-03-23
  - Amélioration [PR#7](https://github.com/Domochip/jMQTT/pull/7): Ajout de nouvelles icônes d'équipement (de mika41)
  - Correction [PR#10](https://github.com/Domochip/jMQTT/pull/10): Désactivation de l'utilisation du js minifié
  - Correction [PR#9](https://github.com/Domochip/jMQTT/pull/9): Correction problème d'affichage pour Jeedom 4.2 (de mika41)
  - Amélioration [PR#8](https://github.com/Domochip/jMQTT/pull/8): Ajout de Secteur dans le placeholder de Type d'alimentation
  - Amélioration [PR#6](https://github.com/Domochip/jMQTT/pull/6): Conversion des couleurs en HTML sur les commandes nommées "color","colour","couleur" ou "rgb"
  - Amélioration [PR#5](https://github.com/Domochip/jMQTT/pull/5): Mise en place de petites icônes de visibilité et mode incluson sur les équipement + lazy image
  - Amélioration [PR#4](https://github.com/Domochip/jMQTT/pull/4): Utilisation d'une liste ordonnée et indentée pour le choix de l'Objet parent
  - Amélioration [PR#3](https://github.com/Domochip/jMQTT/pull/3): Ajout du remplacement de #select#
  - Amélioration [PR#2](https://github.com/Domochip/jMQTT/pull/2): Remplissage du topic de l'équipement sur double-click + ajout des champs min, max et listValue

## 2021-03-21
  - Amélioration du nommage des fichiers template
  - Remontée du niveau de batterie à partir des commandes nommées "battery" ou "batterie" en plus du type générique batterie
  - Correction [PR#1](https://github.com/Domochip/jMQTT/pull/1): typo dans le code de vérification des dépendances

## 2021-03-20
  - Correction : Changement du code de publication de message pour réduire les erreurs "invalid function argument provided"

## 2021-03-17
  - Ajout de la gestion des templates
  - Correction : Affichage des "Type d'alimentation" et "Catégorie du topic" sur les Equipements uniquement (pas les brokers)

## 2021-02-20
  - Remontée du niveau de batterie des équipements depuis la commande tagguée 'Batterie' en Type générique
  - Ajout du champ "Type d'alimentation" sur les équipements
  - Changement du code de publication de message
  - Correction : Bug d'affichage des champs de saisie des commandes (textarea) pour Jeedom 4.1
  - Correction : Le Titre n'est plus obligatoire lors d'envoi de message par une commande action de type message


# Documentations

[Documentation de la branche beta](index_beta)

[Documentation de la branche stable](index)

# Autres registres des évolutions

[Evolutions de la branche beta](changelog_beta)

[Evolutions de la branche stable](changelog)

[Evolutions archivées](changelog_archived)
