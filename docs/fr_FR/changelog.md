# Registre des évolutions

## Beta
 - Nettoyage des nom des templates, ajout en commentaire de liens vers community ou les sources des templates
 - Ajout de 30 nouvelles templates, merci à Nicoca-ine et Mikael, Meute, Jbval, lolo_95 et iPaaad !
 - Ajout d'une fonction permettant à un plugin tiers d'ajouter/modifier facilement un équipement dans jMQTT avec un template
 - Modifications et réécritures mineures des certaines fonctions
 - Correction d'erreurs dans le fonction jMQTT::HTMLtoXY
 - Implémentation d'un Environnement Virtuel python3 pour mieux gérer les dépendances
 - Correction de l'échappement du chemin JSON
 - Correction d'une erreur javascript lors du changement d'équipement
 - **Un message MQTT compressé avec zlib est automatiquement reçu décompressé dans Jeedom sur la commande info associée**
 - **Un message MQTT binaire est automatiquement reçu en base64 dans Jeedom sur la commande info associée**

## 2022-02-28
 - Correction d'un bug en cas de tentative de suppression d'une commande orpheline (sans EqLogic)
 - Correction du nettoyage des info broker des equipements (les champs ayant '' pour valeur étaient supprimés avant envoi)
 - Nouvel affichage du selecteur d'icones sur les commandes
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
 - Correction du daemon Python pouvait planter lors d'une sub/unsub invalide
 - Gestion de l'évolution de la fonction export() pour les futures versions de Jeedom

## 2022-01-12
 - Amélioration du log en cas d'erreur sur le daemon PHP
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
  - Correction : l'équipement broker "local" ne se créée plus si un équipement broker existant est configuré avec l'ip de la machine

## 2021-09-17 Nouveaux Démons
**ATTENTION :  De gros changements ont été apporté au plugin**
En cas de problème, merci d'ouvrir un thread sur [community ici](https://community.jeedom.com/tag/plugin-jmqtt) ou une issue sur [Github ici](https://github.com/Domochip/jMQTT/issues)
  - Changement complet de moteur MQTT
  - Passage au nouveau démon (PHPWebSocket + Python) utilisant la gestion fournie par Jeedom Core
  - Réécriture complète de la gestion des Création/Modification/Suppression des équipements
  - Structure de classes permettant l'utilisation dans de futurs plugins satellites
  - Nouvelles dépendances plus légères et maintenues
  - Renommage de l'ancienne partie Daemon en MqttClient
  - Retrait de Ratchet des sources et ajout au méchanisme d'installation de dépendances
  - Amélioration de la gestion des erreurs dans le daemon WebSocket PHP
  - Nettoyage des anciennes dépendances lors de l'update du plugin
  - Ajout du support de TLS et modification de la documentation en conséquence
  - Correction temporaire de la transformation des entiers en texte dans les JSON des commandes actions (Jeedom Core [PR#1829](https://github.com/jeedom/core/pull/1829))
  - Amélioraton du daemon Python
  - TLS : Ajout de boutons pour l'upload/suppression de fichiers de CA/Cert/Key
  - Correction de Bug sur l'application des Template : La souscription au topic ne se faisait pas après l'application d'un template + des messages de topics mismatch apparaissaient 
  - Augmentation du timeout WebSocket et kill -9 des daemon si pas arrêté au bout de 10 secondes
  - Ajout de l'icone Nabaztag
  - Ajout de template de volet roulant
  - Stockage des template dans le dossier data permettant de les conserver durant les MAJ du plugin
  - IMAGES : Changement du logo du plugin, reprise des captures d'écran de la documentation, optimisation des tailles
  - Correction de l'Ajout automatique des commandes qui pouvait générer des erreurs MySQL de type "Duplicate entry", voir [Issue#89](https://github.com/Domochip/jMQTT/issues/89).
  - le passage au nouveau démon déclenche correctement l'installation des nouvelles dépendances

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
  - on ne peux plus ajouter/supprimer de commandes sur les équipements de type Broker
  - Ajout dans la santé du plugin du champ "Inscrit au Topic" sur chaque équipement
  - l'API JSON RPC over MQTT est maintenant désactivée par défaut
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
  - Amélioration [PR#7](https://github.com/Domochip/jMQTT/pull/7): Ajout de nouvelles icones d'équipement (de mika41)
  - Correction [PR#10](https://github.com/Domochip/jMQTT/pull/10): Désactivation de l'utilisation du js minifié
  - Correction [PR#9](https://github.com/Domochip/jMQTT/pull/9): Correction problème d'affichage pour Jeedom 4.2 (de mika41)
  - Amélioration [PR#8](https://github.com/Domochip/jMQTT/pull/8): Ajout de Secteur dans le placeholder de Type d'alimentation
  - Amélioration [PR#6](https://github.com/Domochip/jMQTT/pull/6): Conversion des couleurs en HTML sur les commandes nommées "color","colour","couleur" ou "rgb"
  - Amélioration [PR#5](https://github.com/Domochip/jMQTT/pull/5): Mise en place de petites icones de visibilité et mode incluson sur les équipement + lazy image
  - Amélioration [PR#4](https://github.com/Domochip/jMQTT/pull/4): Utilisation d'une liste ordonnée et indentée pour le choix de l'Objet parent
  - Amélioration [PR#3](https://github.com/Domochip/jMQTT/pull/3): AJout du remplacement de #select#
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

## 2019-12-02
  - Corrige [domotruc\#94](https://github.com/domotruc/jMQTT/issues/94): non rafraichissement de payload json ayant la valeur false

## 2019-12-01
  - Corrige [domotruc\#90](https://github.com/domotruc/jMQTT/issues/90): filtrage des valeurs null dans une payload JSON
  - Corrige [domotruc\#92](https://github.com/domotruc/jMQTT/issues/92): topic comprenant des caractères spéciaux

## 2019-11-03
  - Amélioration [domotruc\#29](https://github.com/domotruc/jMQTT/issues/29): ajouter manuellement des commandes "info" et éditer leurs topics.
    Cette modification est expérimentale et sera maintenue selon les retours qui en sont faits [ici](https://community.jeedom.com/t/ajout-manuel-de-commandes-info-et-edition-de-leurs-topics/6534?u=domotruc).

## 2019-11-01
  - Corrige [domotruc\#87](https://github.com/domotruc/jMQTT/issues/87): icônes pour plier/déplier les noeuds JSON devenus invisibles en core v4

## 2019-10-31
  - Corrige [domotruc\#85](https://github.com/domotruc/jMQTT/issues/85): disparition de commandes JSON
  - Corrige [domotruc\#86](https://github.com/domotruc/jMQTT/issues/86): disparition statuts démon et erreur id sur relance démon en core v3

## 2019-10-27
  - Compatibilité v4
  - Force la relance des démons sur mise à jour plugin

## 2019-10-23
  - Correction de [domotruc\#82](https://github.com/domotruc/jMQTT/issues/82): erreur de décodage JSON suite version 2019-10-19

## 2019-10-19
  - Amélioration [domotruc\#76](https://github.com/domotruc/jMQTT/issues/76): pour les payloads JSON, il est maintenant possible de choisir individuellement les valeurs qui sont des commandes info.

> **Important**
> 
> Cette version modifie complêtement la gestion des payloads JSON (disparition du parseJSON), bien lire le chapitre de la documentation s'y rapportant, [ici](#commandes-de-type-information).

## 2019-09-01
  - Ajout support debian buster et php 7.3
  - Correction de [domotruc\#80](https://github.com/domotruc/jMQTT/issues/80): problème avec les commandes action dont la payload a pour valeur true ou false

## 2019-08-22 (beta)
  - Support php 7.3
  - Correction de [domotruc\#79](https://github.com/domotruc/jMQTT/issues/79): message d'alerte "L'équipement n'est pas de type broker"

## 2019-07-23
  - Amélioration [domotruc\#78](https://github.com/domotruc/jMQTT/issues/78): ajout fonctionnalité permettant de changer un équipement de broker  

## 2019-07-14
  - Correction de [domotruc\#77](https://github.com/domotruc/jMQTT/issues/77): le broker reste offline

## 2019-07-09
  - Ajout de traces dans la fonction de migration vers multi broker

## 2019-06-30
  - Passage en stable des améliorations des 2 betas précédentes

## 2019-05-31 (beta)
  - Amélioration [domotruc\#74](https://github.com/domotruc/jMQTT/issues/74): mise à jour de lastCommunication sur publication d'un message
  - Amélioration [domotruc\#73](https://github.com/domotruc/jMQTT/issues/73): remplacement de Dernière Activité par Dernière Communication

## 2019-05-14 (beta)
  - Amélioration [domotruc\#63](https://github.com/domotruc/jMQTT/issues/63): ajout du support multi broker
  - Amélioration [domotruc\#54](https://github.com/domotruc/jMQTT/issues/54): commande information donnant le status de connexion au broker

## 2019-04-10
  - Correction de [domotruc\#70](https://github.com/domotruc/jMQTT/issues/70): problème de reconnaissance des topics avec accents

## 2019-04-06
  - Transition vers style V4

> **Important**
> 
> Core Jeedom 3.3.19 ou supérieur requis à partir de la version 2019-04-06.

## 2019-03-06
  - Passage en stable des évolutions des 2 versions betas précédentes

## 2019-03-05 (beta)
  - Amélioration documentation (commandes slider)
  - Rend les boutons avancer/reculer du navigateur fonctionnels
  - Correction mineure pour compatibilité avec core 3.3

## 2019-03-01 (beta)
  - Correction de [domotruc\#69](https://github.com/domotruc/jMQTT/issues/69): message de confirmation de fermeture de page inopportun

## 2019-02-20
  - Amélioration [domotruc\#68](https://github.com/domotruc/jMQTT/issues/68): amélioration robustesse aux deconnections intempestives

## 2018-02-16
  - Passage en stable des évolutions des 4 versions betas précédentes

## 2019-02-09 (beta)
  - Amélioration [domotruc\#67](https://github.com/domotruc/jMQTT/issues/67): visualisation de la mise à jour des valeurs dans le panneau commande
  - Amélioration [domotruc\#66](https://github.com/domotruc/jMQTT/issues/66): amélioration perfo en ne sauvant pas les commandes sur maj valeur
  - Amélioration de la documentation ([domotruc\#65](https://github.com/domotruc/jMQTT/issues/65))

## 2018-12-27 (beta)
  - Correction de [domotruc\#64](https://github.com/domotruc/jMQTT/issues/64): problème affichage barre de bouton du panneau commandes

## 2018-12-08 (beta)
  - Correction de [domotruc\#62](https://github.com/domotruc/jMQTT/issues/62): Erreur sur jMQTT::daemon() : Using $this when not in object context

## 2018-11-11 (beta)
  - Amélioration code pour besoin de tests

## 2018-11-04
  - Correction de [domotruc\#58](https://github.com/domotruc/jMQTT/issues/58): MySql duplicate entry error sur topic dépassant 45 caractères. Voir documentation, chapitre [Commandes de type Information](#commandes-de-type-information).
  - Amélioration initialisation équipement sur création manuelle de façon à afficher les paramètres pas défaut comme Qos
  - Ajout du chapitre exemple dans la documentation

## 2018-10-24
  - Amélioration script installation des dépendances pour corriger [domotruc\#59](https://github.com/domotruc/jMQTT/issues/59)
  - Correction typo dans message affiché à l’utilisateur (équipment ⇒ équipement), merci Gwladys

## 2018-09-16
  - Correction de [domotruc\#57](https://github.com/domotruc/jMQTT/issues/57): perte de connexion du démon sur rafale de message (merci jmc)

## 2018-06-12
  - Intégration des évolutions des 3 versions betas précédentes

## 2018-06-11 (beta)
  - Correction bugs mineurs relatif à [domotruc\#53](https://github.com/domotruc/jMQTT/issues/53):
      - Lorsque le topic de retour n’est pas défini ⇒ affiche une erreur;
      - Lorsque l’encodage JSON de la requête est incorrect ⇒ affiche un message d’erreur plus clair.

## 2018-06-10 (beta)
  - Complément à l’amélioration [domotruc\#53](https://github.com/domotruc/jMQTT/issues/53):
      - Ajoute un paramètre pour activer/désactiver l’API dans la configuration plugin;
      - topic jeedom/jeeAPI changé en jeedom/api;
      - Mise à jour doc.

## 2018-06-07 (beta)
  - Amélioration [domotruc\#53](https://github.com/domotruc/jMQTT/issues/53): api MQTT

## 2018-06-03
  - Intégration des évolutions des 3 versions betas précédentes

## 2018-06-03 (beta)
  - Amélioration [domotruc\#55](https://github.com/domotruc/jMQTT/issues/55): ajout d’une vue de visualisation hiérarchique JSON dans le panneau des commandes

## 2018-05-26 (beta)
  - Amélioration [domotruc\#45](https://github.com/domotruc/jMQTT/issues/45): décodage immédiat des payloads JSON sur activation de parseJSON
  - Amélioration [domotruc\#52](https://github.com/domotruc/jMQTT/issues/52): activer l’export d’un équipement dans un fichier json

## 2018-05-24 (beta)
  - Amélioration [domotruc\#51](https://github.com/domotruc/jMQTT/issues/51): ajoute des messages d’alerte informant sur la création de commandes
  - Amélioration [domotruc\#50](https://github.com/domotruc/jMQTT/issues/50): ajoute un bouton d’actualisation dans la page équipement
  - Correction [domotruc\#49](https://github.com/domotruc/jMQTT/issues/49): pas de demande de confirmation de sortie de page sur modifications de certains paramètres

## 2018-05-11
  - Correction [domotruc\#47](https://github.com/domotruc/jMQTT/issues/47): erreur "call to undefined function mb\_check\_encoding"

## 2018-05-10
  - Intégration des évolutions des 2 versions beta précédentes

## 2018-05-10 (beta)
  - Correction [domotruc\#46](https://github.com/domotruc/jMQTT/issues/46): mauvaise payload avec caractères non ASCII corrompt la commande information associée
  - Amélioration [domotruc\#44](https://github.com/domotruc/jMQTT/issues/44): amélioration de l’affichage dans le panneau de commandes

## 2018-05-08 (beta)
  - Correction [domotruc\#42](https://github.com/domotruc/jMQTT/issues/42): log erroné sur création d’une commande info
  - Correction [domotruc\#41](https://github.com/domotruc/jMQTT/issues/41): retour de jMQTT dans la catégorie protocole domotique (au lieu de passerelle domotique).
  - Amélioration [domotruc\#43](https://github.com/domotruc/jMQTT/issues/43): logguer qu’un équipement ou une commande est supprimé.

## 2018-04-29
  - Amélioration [domotruc\#40](https://github.com/domotruc/jMQTT/issues/40): ajout du champ "valeur de la commande par defaut" (voir [post de vincnet68 sur le forum](https://www.jeedom.com/forum/viewtopic.php?f=96&t=32675&p=612364#p602740)).
  - MAJ icone et fichier info.json suite évolution processus de publication sur le market (mail <partenaire@jeedom.com> du 16/04/2018).

## 2018-02-15
  - Amélioration [domotruc\#36](https://github.com/domotruc/jMQTT/issues/36): le mode inclusion automatique d’équipements s’active maintenant via un bouton d’inclusion depuis la page du plugin et se désactive automatiquement après 2 à 3 min.
  - Correction [domotruc\#37](https://github.com/domotruc/jMQTT/issues/37): la bordure mettant en évidence un équipement dont l’ajout automatique de commandes est actif, est correctement affichée quelque soit le thème.

## 2018-02-06
  - Amélioration [domotruc\#26](https://github.com/domotruc/jMQTT/issues/26): ajout d’une case à cocher dans l’équipement permettant de désactiver la création automatique des commandes de type information.

## 2018-02-05
  - Correction [domotruc\#30](https://github.com/domotruc/jMQTT/issues/30): les commandes action n’étaient pas envoyées immédiatement depuis des scénarios.
  - Correction [domotruc\#25](https://github.com/domotruc/jMQTT/issues/25): les commandes avec Qos=2 n’étaient pas envoyées.
  - Correction [domotruc\#28](https://github.com/domotruc/jMQTT/issues/28): rend possible la définition de commandes action JSON (voir exemples dans la documentation: [Commandes de type Action](#commandes-de-type-action)).
  - Correction [domotruc\#31](https://github.com/domotruc/jMQTT/issues/31): message de log erroné sur accusé de réception de souscription.

## 2018-01-26
  - Correction [domotruc\#23](https://github.com/domotruc/jMQTT/issues/23): sur une rafale de commande, seule la dernière était envoyée.

## 2018-01-24
  - Amélioration [domotruc\#19](https://github.com/domotruc/jMQTT/issues/19): ajoute une option pour ne pas installer Mosquitto localement.

## 2018-01-15
  - Amélioration [domotruc\#10](https://github.com/domotruc/jMQTT/issues/10): duplication d’équipement (voir la doc).
  - Correction [domotruc\#15](https://github.com/domotruc/jMQTT/issues/15): les topics commençant par / n’étaient pas souscrits après désactivation du mode manuel

> **Important**
> 
> Si vous avez des topics commençant par / créés avant cette version, il faut ajouter le / en début de topic souscrit dans les équipements concernés. Les commandes de types info vont être recréer par le plugin, il faudra supprimer les anciennes (celles dont le topic ne commencent pas par /). En cas de doutes, de questions, n’hésiter pas à poster sur le forum.

  - Correction [domotruc\#13](https://github.com/domotruc/jMQTT/issues/13): commande null systématiquement envoyée sur création d’une commande action.
  - Correction [domotruc\#14](https://github.com/domotruc/jMQTT/issues/14): le champ de sélection value, sous le nom d’une commande de type action, est supprimé car il n’avait pas d’effet.
  - Amélioration [domotruc\#17](https://github.com/domotruc/jMQTT/issues/17): autorise les équipements avec topic vide.
  - Correction [domotruc\#18](https://github.com/domotruc/jMQTT/issues/18): arrête de créer une commande info relative à une commande action.

## 2018-01-08
  - Correction [domotruc\#9](https://github.com/domotruc/jMQTT/issues/9): l’installation se bloque à 80% au redémarrage du serveur apache.

## 2018-01-06
  - Correction [domotruc\#7](https://github.com/domotruc/jMQTT/issues/7): erreur "Le nom de l’équipement ne peut pas être vide" et arrêt du démon sur réception d’un topic commençant par /.
  - Amélioration de l’installation: ajout du statut de progression, lisibilité fichier de log
  - Correction [domotruc\#1](https://github.com/domotruc/jMQTT/issues/1): dernière valeur maintenue retain au niveau du broker sur suppression du mode retain d’une commande.
  - Correction [domotruc\#6](https://github.com/domotruc/jMQTT/issues/6): case inversion cochée par défaut pour information binaire.

## 2018-01-04
  - MAJ du README côté GitHub

## 2018-01-03
  - MAJ de la documentation

## 2018-01-01
  - Supprime les tentatives de reconnexion toutes les secondes sur problème de connexion au broker: rend maintenant la main au core Jeedom qui relancera le démon (et donc la reconnexion) toutes les 5min.
  - Correction bug sur authentification auprès du broker (merci Nicolas)
  - Message d’erreur sur définition d’un topic vide
  - MAJ fichier internationalisation
  - Changement de la couleur de l’icône et des images du plugin (jaune au lieu de bleu)
  - MAJ liens de la doc

## 2017-12-26
  - Version initiale
