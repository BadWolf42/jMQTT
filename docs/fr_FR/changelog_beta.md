# Registre des évolutions BETA

## 2023-02-27
- Correction de 2 bug avec les templates: topic de base incorrectement identifié, underscore à la place d'espaces dans le nom
- Les statistiques ne sont poussées que toutes les 5 à 10 minutes en cas d'échec
- Utilisation de 127.0.0.1 au lieu de localhost pour l'url de callback (Workarround par rapport à un problème avec le fichier hosts sur la Luna)


## 2023-02-12
- Passage de la Beta en stable
- Implémentation définitive du système de sauvegarde de jMQTT (la restauration reste à faire), indépendamment du Core (fonctionnalité cachée pour le moment)
- Modification de la page Temps Réel pour qu'elle puisse apparaitre sur tous les équipements (fonctionnalité cachée pour le moment)


# Documentations

[Documentation de la branche beta](index_beta)

[Documentation de la branche stable](index)


# Autres registres des évolutions

[Evolutions de la branche beta](changelog_beta)

[Evolutions de la branche stable](changelog)

[Evolutions archivées](changelog_archived)
