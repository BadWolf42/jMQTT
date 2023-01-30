# Registre des évolutions BETA

## 2023-01-31
- Ajout de la prise en compte du changement de la clé API dans le Core
- Ajout du remplacement des références aux commandes dans un même équipement, lors du chargement d'un template (incluant des références internes), selon la [demande #129](https://github.com/Domochip/jMQTT/issues/129)
- Correction du remplissage automatique de certains champs
- Implémentation définitive du système de sauvegarde de jMQTT (la restauration reste à faire), indépendamment du Core (fonctionnalité cachée pour le moment)
- Nettoyage du code de la page de configuration
- Nettoyage du code php/python en général

## 2023-01-17
- Correction d'un bug lors de l'utilisation de '/' dans un payload (merci Jeandhom)
- Correction d'un bug lors du changement de page ou du rafraichissement, les modifications n'étaient pas signalées
- Ajout de boutons pour (re)démarrer ou arrêter le service Mosquitto local
- Ajout d'un bouton pour éditer le fichier de configuration jMQTT.conf du service Mosquitto
- Ajout de la commande info binaire "connected" aux équipements Broker
- Passage à la version 2.28.2 du package Python "requests"

## 2023-01-03
- Correction d'un bug lors de la duplication d'un équipement : des commandes de l'équipement source étaient encore utilisées
- Correction d'un bug lors de la création ou de l'import d'un template : des id des commandes d'origine étaient encore conservées

## 2022-12-28
- Correction d'un bug lorsqu'une commande n'existe pas/plus (merci Loïc)
- Arrêt du support de Python 2.7 et 3.6 (changement sur le package Python "requests" en 2.28.1)
- Suppression du test GitHub CI sur Python 3.6


## 2022-12-27
- Passage de la Beta en stable
- Modification de la page Temps Réel pour qu'elle puisse apparaitre sur tous les équipements (fonctionnalité cachée pour le moment)


# Documentations

[Documentation de la branche beta](index_beta)

[Documentation de la branche stable](index)


# Autres registres des évolutions

[Evolutions de la branche beta](changelog_beta)

[Evolutions de la branche stable](changelog)

[Evolutions archivées](changelog_archived)
