# Registre des évolutions BETA

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


# Documentations

[Documentation de la branche beta](index_beta)

[Documentation de la branche stable](index)


# Autres registres des évolutions

[Evolutions de la branche beta](changelog_beta)

[Evolutions de la branche stable](changelog)

[Evolutions archivées](changelog_archived)
