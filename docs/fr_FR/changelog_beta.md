# Registre des évolutions BETA

## 2023-04-11 (v15)
- Passage à la version 3.0 de galbar/jsonpath : Attention aux changement de l'oppérateur de recherche recursive ( aka `..`)
- Correction d'un problème créant des commandes orphelines lors de la suppression d'un équipement Broker
- Correction des problèmes de multi-lancement du démon avec des signaux de vie entre le démon et Jeedom
- Correction d'un problème d'affichage et de gestion des équipements orphelins sur la page principale du plugin
- Correction d'un problème avec le topic de souscription lors de l'application d'un template
- Ajout d'un outil de test des chemins Json (documentation à faire, mais fonctionnement simple)
- Amélioration de l'affichage des commandes Action List
- Ajout du template Tasmota Nous A1T (merci vberder)
- Ajout de 14 nouvelles icones : espeasy, intex, location, mcz-remote, old-phone, openmqttgateway, phone, smartphone, repeater, smoke-detector, sonometer, stove, tasmota & theengs
- Première implémentation fonctionnelle du système de sauvegarde et de restauration de jMQTT indépendamment du Core (en BETA pour le moment)
- Optimisations du code, Corrections syntaxiques et orthographiques
- Mise à jour de la documentation

## 2023-03-19 (v14)
- Ajout de la possibilité d'utiliser un template lors de la création d'un équipement (merci ngrataloup)
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
- Utilisation de `127.0.0.1` au lieu de localhost pour l'url de callback (Workarround par rapport à un problème avec le fichier hosts sur la Luna)


## 2023-02-12
- Passage de la Beta en stable
- Modification de la page Temps Réel pour qu'elle puisse apparaitre sur tous les équipements (fonctionnalité cachée pour le moment)


# Documentations

[Documentation de la branche beta](index_beta)

[Documentation de la branche stable](index)


# Autres registres des évolutions

[Evolutions de la branche beta](changelog_beta)

[Evolutions de la branche stable](changelog)

[Evolutions archivées](changelog_archived)
