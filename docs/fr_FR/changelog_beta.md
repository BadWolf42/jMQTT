# Registre des évolutions BETA

## 2022-10-28 à 01h
 - **Retour en arrière sur la suppression des commandes 'status' sur les équipements Broker. Elles sont utilisées pour avoir l'état de connexion de jMQTT au Broker**
 - Paufinage du mode Temps Réel

## 2022-10-26 à 15h
 - Correction d'un bug dans le script d'installation du plugin, **il est NÉCESSAIRE DE RESTAURER UNE BACKUP si vous avez mis à jour avec la version de la nuit**
 - Ajout de 8 templates : "Shelly 1 (Light)", "Shelly 1 (Relay)", "Shelly 1 (Relay & Temperature)", "Shelly 2.5 (Relay)" et "Shelly 2.5 (Roller Shutter)" (merci ngrataloup), "Shelly Bulb Duo RGBW" (merci Jeandhom), "Zwavejs2mqtt Fibaro Motion Sensor FGMS-001-ZW5" (merci mimilamy2000), "Zigbee2mqtt Lidl HG07834B" (merci seb49), "Zwavejs2mqtt NodOn Wall Switch CWS-3-1-01" (merci pifou)
 - Retrait de 2 anciens templates : "Zwave2mqtt Qubino ZMNHCD" et "Zwave2mqtt Qubino ZMNHOD"

## 2022-10-26 à 01h Mode Temps Réel
 - **Suppression du mode "Inclusion" au profit du mode "Temps Réel"**
 - Utilisation des fonctions de suppression du Core avec un résumé des liaisons
 - Suppression du bouton pour effectuer un changement de Broker, c'est fait à la sauvegarde de l'équipement
 - Moins de log lorsque le Démon est désactivé
 - Amélioration du code JS pour rendre le plugin plus léger et réactif lors du chargement

## 2022-10-19 Interactions
 - **Déplacement/Suppression de toutes les commandes présentes sur les équipements Broker**
 - **Meilleure gestion du "topicMismatch", avertissement avant de sauvegarder et visuellement dans les champs lors de la saisie**
 - **Ajout du support des Interactions Jeedom via MQTT**
 - Support minimum de la version 4.2.11 de Jeedom
 - Ajout du transport du protocole MQTT sur Web Sockets (ws) et Web Sockets Secure (wss)
 - Ajout de champs dans le broker pour définir le topic LWT et les valeurs quand le broker est en-ligne et hors-ligne
 - Mise en place de champs dans le broker pour définir le topic API
 - Utilisation du nom de l'objet (plutôt que du nom du broker) et du nom de l'équipement pour construire le topic, lors du double-clique sur un topic d'inclusion vide
 - Gros nettoyage pour retirer des correctifs temporaires liés au Core < 4.2


# Documentations

[Documentation de la branche beta](index_beta)

[Documentation de la branche stable](index)


# Autres registres des évolutions

[Evolutions de la branche beta](changelog_beta)

[Evolutions de la branche stable](changelog)

[Evolutions archivées](changelog_archived)
