# Registre des évolutions BETA

## 2023-01-08
- Ajout de boutons pour (re)démarrer ou arrêter le service Mosquitto local
- Ajout d'un bouton pour éditer le fichier de configuration jMQTT.conf du service Mosquitto
- Correction d'un bug lors du changement de page ou du rafraichissement, les modifications n'étaient pas signalées

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
