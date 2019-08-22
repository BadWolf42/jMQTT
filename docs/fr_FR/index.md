# Présentation

Ce plugin permet de connecter Jeedom à un ou plusieurs brokers MQTT comme Mosquitto afin de recevoir les messages souscrits et de publier ses propres messages.

MQTT est un protocole de communication sur IP, basé sur une architecture client/serveur et un mécanisme de souscription/publication. Il est léger, ouvert, simple, caractéristiques qui ont favorisées sa démocratisation dans la communication Machine to Machine (M2M) et l’internet des objets (IoT - Internet of Things).

Pour tout savoir sur MQTT, la série d’article [MQTT Essentials](https://www.hivemq.com/mqtt-essentials/) est à recommander.

# Démarrage facile

La façon la plus rapide d'être opérationnel avec jMQTT est de le laisser installer le broker MQTT sur la machine hébergeant Jeedom et de configurer ses modules MQTT pour qu'ils se connectent à ce broker.

La configuration côté jMQTT est alors très simple:
  1. Installer et activer le plugin en laissant les paramètres par défaut;
  2. Ajouter un équipement broker et l'activer en laissant également tous les paramètres par défaut.

# Configuration du plugin

> **Important**
> 
> Pour ne pas installer le broker Mosquitto localement, c’est à dire sur la machine hébergeant Jeedom, commencer par décocher la case *Installer Mosquitto localement*, sans oublier de sauvegarder la configuration.

Après installation du plugin, l’activer. Celui-ci prend quelques minutes pour installer les dépendances. Le suivi détaillé de la progression est possible via le log `jMQTT_dep` qui apparaît au bout d’un moment en rafraîchissant la page.

> **Tip**
> 
> Configurer le niveau de log minimum à Info pour que le fichier de log apparaisse la première fois et avoir des informations intéressantes. Sauvegarder et relancer le démon pour rendre le changement actif.

> **Important**
> 
> Il peut arriver que l’installation du plugin se bloque. Cela se produit si le serveur apache est relancé pendant l’installation (voir log `jMQTT_dep`). Pour se débloquer, se connecter à sa station Jeedom et supprimer le fichier `/tmp/jeedom/jMQTT/progress_dep.txt`.

# Gestion des équipements

La page de gestion des équipements est accessible via le menu `Plugins → Protocole domotique → jMQTT`.

Depuis la version prenant en charge la connexion à plusieurs brokers, jMQTT présente 2 types d'équipements:
  - les équipements de type broker gérant la connexions avec les brokers MQTT, appelés équipements broker dans la suite;
  - les équipements "standards", appélés simplement équipements dans la suite, 

Le panneau supérieur gauche, intitulé *Gestion*, permet d'accéder :
  - A la [Configuration du plugin](#configuration-du-plugin)
  - A la page [Santé du plugin](#santé-du-plugin).

Le panneau supérieur droit, *Brokers MQTT*, affiche les équipements de type broker gérant la connexions avec les brokers MQTT. Un icône `+` permet l'[ajout d'un équipement broker](#ajout-dun-équipement-broker).
L'icône de chaque broker présente dans son coin supérieur droit un point de couleur indiquant l'état de la connexion au broker:
  - Vert: la connexion au broker est nominale;
  - Orange: le démon tourne mais la connexion au broker n'est pas établie;
  - Rouge: le démon est arrêté.

Viennent ensuite pour chaque broker, un panneau occupant toute la largeur de la page et présentant les équipements connectés au broker nommé dans la légende du panneau. Outre les équipements, le panneau comprend :
  - Un bouton **+** permettant l'[ajout manuel d'un équipement](#ajout-manuel-dun-équipement);
  - Un icône d'activation du mode [Inclusion automatique des équipements](#inclusion-automatique-des-équipements).

A noter qu'un équipement :
  - Grisé est inactif;
  - Présenté avec un petit icône d’inclusion superposé, est un équipement dont l'*Ajout automatique des commandes* est activé (détails dans [Onglet Equipement](#onglet-equipement)).
  
> **Important**
> 
> Un panneau intitulé _Equipement orphelins_ pourrait aussi être présent suite à la migration vers la version supportant le multi-brokers. Il regroupe les équipements qui n'ont pas été migrés automatiquement. Editer chacun d'eux et les associer à l'équipement broker de votre installation (unique normalement après migration), voir paramètre _Broker associé_ au chapitre [Paramètres de l'équipement](#paramètres-de-léquipement).

## Ajout d'un équipement broker

Pour commencer à utiliser le plugin, il est nécessaire de créer un équipement broker au moins. Dans la page de [Gestion des équipements](#gestion-des-équipements), cliquer sur le bouton **+** *Ajouter un broker* et saisir son nom.

Dans la foulée de sa création, un message indique que la commande *status* a été ajoutée à l'équipement. Celle-ci, que nous retrouvons dans le panneau *Commandes*, donne l'état de connexion au broker MQTT de l'équipement broker. Elle prend 2 valeurs : *online* et *offline*. Elle est publiée de manière persistante auprès du broker. Cet état permet à un équipement externe à Jeedom de connaitre son statut de connexion. Il peut aussi servir en interne Jeedom pour monitorer la connexion au broker via un scénario.

Un équipement broker se comporte comme un équipement "standard" et présente donc les onglets _Equipements_ et _Commandes_ que ce dernier : nous nous reporterons donc au chapitre [Paramètres de l'équipement](#paramètres-de-léquipement) concernant la description de ces onglets.

Nous nous attachons ici à décrire l'onglet spécifique.

### Onglet Broker

Par défaut, un équipement broker est configuré pour s’inscrire au broker Mosquitto installé localement sur la machine hébergeant Jeedom. Si cette configuration convient, activer l'équipement dans l'onglet _Equipement_ et sauvegarder. Revenir sur l'onglet _Broker_, le status du démon devrait passer à OK.

Pour particulariser la configuration du plugin, les paramètres sont:
  - _IP de Mosquitto_ : adresse IP du broker (par défaut localhost i.e. la machine hébergeant Jeedom);
  - _Port de Mosquitto_ : port du broker (1883 par défaut);
  - _Identifiant de connexion_ : identifiant avec lequel l'équipement broker s’inscrit auprès du broker MQTT (jeedom par défaut).
    - **Attention**: cet identifiant doit être unique par client et par broker. Sinon les clients portant le même identifiant vont se déconnecter l’un-l’autre à chaque connection.
    - Cet identifiant est utilisé dans le nom du topic des commandes info *status* et *api* que l'on retrouve dans l'onglet *Commandes*. Les topics seront automatiquement renommés si l'identifiant est modifié. 
  - _Compte et mot de passe de connexion_ : compte et mot de passe de connexion au broker (laisser vide par défaut, notamment si jMQTT se charge de l’installation du broker, sauf bien-sûr si vous modifiez la configuration de ce dernier pour activer l’authentification et dans ce cas vous savez ce que vous faites).
  - _Topic de souscription en mode inclusion automatique des équipements_ : topic de souscription automatique à partir duquel le plugin va découvrir les équipements de manière automatique, nous y revenons dans la partie équipements (défaut \#, i.e. tous les topics).
  - _Accès API_ : à activer pour utiliser l'[API](#api).

La sauvegarde de la configuration relance le démon et la souscription au broker MQTT avec les nouveaux paramètres.

> **Tip**
> 
>
> - Dès que l'équipement broker est activé (via la case à cocher de l'onglet _Equipement_), son démon est lancé, se connecte au broker MQTT et traite les messages souscrits;
> - En cas de déconnection intempestive au broker MQTT (consécutive à un problème réseau par exemple), le démon tentera une reconnection immédiatement, puis toutes les 15s.
> - Pour déconnecter un équipement broker du broker MQTT, ainsi que tous les équipements associés à cet équipement broker, il faut le désactiver (via la case à cocher de l'onglet _Equipement_).

A noter que l'équipement broker possède son propre fichier de log suffixé par le nom de l'équipement. Si l'équipement est renommé, le fichier de log le sera également.



## Inclusion automatique des équipements

Le mode inclusion automatique permet la découverte et la création automatique des équipements. Il s’active, pour le broker concerné, en cliquant sur le bouton *Mode inclusion*. Il se désactive en recliquant sur le même bouton, ou automatiquement après 2 à 3 min.

Le plugin souscrit auprès du broker le topic configuré dans [l'onglet broker](#onglet-broker) (\# par défaut, i.e. tous les topics) de l'équipement broker concerné. A réception d’un message auquel aucun équipement n’a souscrit, le plugin crée automatiquement un équipement associé au topic de premier niveau.

Prenons comme exemple une payload MQTT publiant les messages suivants:

    boiler/brand "viesmann"
    boiler/burner 0
    boiler/temp 70.0

A l’arrivée du premier message, le plugin crée automatiquement un équipement nommé *boiler*. Nous verrons dans la section [Onglet Commandes](#onglet-commandes) que, par défaut, il créé aussi les informations associées à chaque message.

> **Tip**
> 
> Le mode inclusion automatique des équipements n’influe que sur la création de l’équipement, et pas sur la création des informations associées, qui dépend du paramètre *Ajout automatique des commandes* que nous verrons dans le chapitre suivant.

> **Note**
> 
> Une fois les équipements découverts, il est conseillé de quitter le mode automatique pour éviter la création d’équipements non souhaités, notamment dans les situations suivantes : publication de messages (si un équipement broker reste souscrit à tous les topics, il écoutera ses propres publications), essais avec le broker, tests de nouveaux équipements, …​

## Paramètres de l’équipement

Ce chapitre est applicable à tous les équipements jMQTT, y compris les équipments de type broker.

### Onglet Equipement

Dans le premier onglet d’un équipement jMQTT, nous trouvons les paramètres communs aux autres équipements Jeedom, ainsi que cinq paramètres spécifiques au plugin:

  - _Broker associé_ : broker auquel est associé l'équipement. **Attention**: ne modifier ce paramètre qu'en sachant bien ce que vous faites.

  - _Inscrit au Topic_ : topic de souscription auprès du broker MQTT. Pour un équipement de type broker, ce paramètre n'est pas modifiable, il est imposé par l'identifiant de connexion au broker, voir [Onglet Broker](#onglet-broker);

  - _Ajout automatique des commandes_ : si coché, les [commandes de type information](#commandes-de-type-information) seront automatiquement créés par le plugin, et l’équipement apparaitra avec un petit icône d’inclusion superposé dans la page de [Gestion des équipements](#gestion-des-équipements). La case est cochée par défaut;

  - _Qos_ : qualité de service souscrit;

  - _Catégorie du topic_ : sélection d’une image spécifique à l’équipement. Pour un équipement broker, ce paramètre n'est pas disponible car l'image est imposée.
  
  - _Dernière communication_ : date de dernière communication avec le broker MQTT, que ce soit en réception (commande information) ou publication (commande action).

> **Important**
> 
> Une fois les commandes créés, il est conseillé de décocher la case *Ajout automatique des commandes* pour éviter la création d’informations non souhaitées.

Concernant les boutons en haut à droite:

  - `Export` permet d’obtenir un fichier JSON de toutes les informations de l’équipement. Il n’y a pas de fonctionalité import, le fichier peut surtout s’avérer utile pour investiguer sur problèmes;

  - `Dupliquer` permet de [Dupliquer un équipement](#dupliquer-un-équipement). Cette fonction n'est pas disponible pour un équipement broker.

### Onglet Commandes

#### Commandes de type Information

Les commandes de type information (informations dans la suite) sont créés, automatiquement, uniquement si la case *Ajout automatique des commandes* de l’Onglet Equipement est cochée : lorsque le plugin reçoit un message dont le topic correspond au topic de souscription, il créé alors la commande correspondante lorsque celle-ci est nouvelle.

Voyons celà sur des exemples en fonction du type de payload.

**Payload simple**

Reprenons l’exemple de la payload MQTT publiant les messages simples suivants:

    boiler/brand "viesmann"
    boiler/burner 0
    boiler/temp 70.0
    boiler/ext_temp 19.3
    boiler/hw/setpoint 50
    boiler/hw/temp 49.0

Le plugin créé les informations suivantes:

| Nom                | Sous-Type | Topic              | Valeur   |
| ------------------ | --------- | ------------------ | -------- |
| boiler:brand       | info      | boiler/brand       | viesmann |
| boiler:burner      | info      | boiler/burner      | 0        |
| boiler:temp        | info      | boiler/temp        | 70.0     |
| boiler:ext\_temp   | info      | boiler/ext\_temp   | 19.3     |
| boiler:hw:setpoint | info      | boiler/hw/setpoint | 50       |
| boiler:hw:temp     | info      | boiler/hw/temp     | 49.0     |

> **Note**
> 
>   * Le nom de la commande est initialisée automatiquement par le plugin à partir du topic. Il peut ensuite être modifié comme souhaité.
>   * Jeedom, dans sa version actuelle, limite la longueur des noms de commande à 45 caractères. Dans le cas de commandes de longueur supérieure, jMQTT remplace leurs noms par leur code de hashage md4 sur 32 caractères (e.g. 5182636929901af7fa5fd97da5e279e1). L’utilisateur devra alors remplacer ces noms par le nommage de son choix.

**Payload JSON.**

Dans le cas d’une payload JSON, le plugin peut décoder le contenu et créer les informations associées, et ceci indépendamment de l’état de la case *Ajout automatique des commandes* de l’Onglet Equipement. Cette fonctionnalité doit être activée manuellement pour chaque commande information de ce type.

Prenons l’exemple de la payload JSON suivante:

    esp/temperatures {"device": "ESP32", "sensorType": "Temperature", "values": [9.5, 18.2, 20.6]}

Au premier message reçu, jMQTT créé automatiquement l’information suivante:

| Nom              | Sous-Type | Topic            | Valeur                                                                          | Paramètres      |
| ---------------- | --------- | ---------------- | ------------------------------------------------------------------------------- | --------------- |
| esp:temperatures | info      | esp/temperatures | {"device": "ESP32", "sensorType": "Temperature", "values": \[9.5, 18.2, 20.6\]} | `[ ]` parseJSON |

En cochant l’option *parseJSON*, les informations complémentaires sont instantanément créés, ce qui donne:

| Nom                      | Sous-Type | Topic                        | Valeur                                                                          | Paramètres      |
| ------------------------ | --------- | ---------------------------- | ------------------------------------------------------------------------------- | --------------- |
| esp:temperatures         | info      | esp/temperatures             | {"device": "ESP32", "sensorType": "Temperature", "values": \[9.5, 18.2, 20.6\]} | `[X]` parseJSON |
| temperatures{device}     | info      | esp/temperatures{device}     | "ESP32"                                                                         | `[ ]` parseJSON |
| temperatures{sensorType} | info      | esp/temperatures{sensorType} | "Temperature"                                                                   | `[ ]` parseJSON |
| temperatures{values}     | info      | esp/temperatures{values}     | \[9.5, 18.2, 20.6\]                                                             | `[ ]` parseJSON |

Enfin, le vecteur des températures peut également être séparé en cochant la case *parseJSON*, pour finalement obtenir:

| Nom                      | Sous-Type | Topic                        | Valeur                                                                          | Paramètres      |
| ------------------------ | --------- | ---------------------------- | ------------------------------------------------------------------------------- | --------------- |
| esp:temperatures         | info      | esp/temperatures             | {"device": "ESP32", "sensorType": "Temperature", "values": \[9.5, 18.2, 20.6\]} | `[X]` parseJSON |
| temperatures{device}     | info      | esp/temperatures{device}     | "ESP32"                                                                         | `[ ]` parseJSON |
| temperatures{sensorType} | info      | esp/temperatures{sensorType} | "Temperature"                                                                   | `[ ]` parseJSON |
| temperatures{values}     | info      | esp/temperatures{values}     | \[9.5, 18.2, 20.6\]                                                             | `[X]` parseJSON |
| temperatures{values}{0}  | info      | esp/temperatures{values}{0}  | 9.5                                                                             | `[ ]` parseJSON |
| temperatures{values}{1}  | info      | esp/temperatures{values}{1}  | 18.2                                                                            | `[ ]` parseJSON |
| temperatures{values}{2}  | info      | esp/temperatures{values}{2}  | 20.6                                                                            | `[ ]` parseJSON |

> **Note**
> 
> Le nom des commandes peut être modifié comme souhaité, jMQTT se base sur le champ Topic pour associer la bonne valeur.

#### Commandes de type Action

Les commandes de type action permettent au plugin jMQTT de publier des messages vers le broker MQTT. Pour cela, créer une commande via le bouton *+ Ajouter une commande action* et remplir les champs selon le besoin:
  - Nom: champ libre;
  - Valeur par défaut de la commande: pour lier la valeur de la commande affichée sur le dashboard à une commande de type Information (exemple [ici](https://www.jeedom.com/forum/viewtopic.php?f=96&t=32675&p=612364#p602740));
  - Sous-type: voir exemples ci-dessous;
  - Topic: topic de publication;
  - Valeur: définit la valeur publiée, i.e. la payload en langage MQTT, voir exemples ci-dessous;
  - Retain: si coché, la valeur sera persistante (conservée par le broker et publiée vers tout nouveau souscripteur);
  - Qos: niveau de qualité de service utilisé pour publier la commande (1 par défaut).

**Sous-type Défaut**

Les exemples du tableau suivant:

| Nom               | Sous-Type       | Topic             | Valeur                                                         |
| ----------------- | --------------- | ----------------- | -------------------------------------------------------------- |
| set\_hw\_setpoint | action - Défaut | `hw/setpoint/set` | `40`                                                           |
| set\_hw\_setpoint | action - Défaut | `hw/set`          | `{"name": "setpoint", "value": 40}`                            |
| set\_hw\_setpoint | action - Défaut | `hw/set`          | `{"name": "setpoint", "value": #[home][boiler][hw_setpoint]#}` |

Publieront respectivement:

    hw/setpoint/set 40
    hw/set {"name": "setpoint", "value": 40}
    hw/set {"name": "setpoint", "value": 45}

En supposant que `#[home][boiler][hw_setpoint]#` a pour valeur 45.

**Sous-type Curseur**

Les configurations suivantes publieront la valeur saisie via un widget de type curseur:

| Nom               | Sous-Type        | Topic             | Valeur                                    |
| ----------------- | ---------------- | ----------------- | ----------------------------------------- |
| set\_hw\_setpoint | action - Curseur | `hw/setpoint/set` | `#slider#`                                |
| set\_hw\_setpoint | action - Curseur | `hw/set`          | `{"name": "setpoint", "value": #slider#}` |

Soit respectivement, en supposant que la valeur du curseur est 50:

    hw/setpoint/set 50
    hw/set {"name": "setpoint", "value": 50}

> **Note**
> 
> Pour configurer les valeurs min/max de la jauge affichée pour une commande slider, éditer les paramètres avancées de la commande slider (la roue crantée à gauche du bouton **Tester**), aller dans l’onglet **Affichage** et ajouter **minValue** et **maxValue** dans la section **Paramètres optionnels widget** (cette configuration est apportée par le core de Jeedom, elle n’est pas spécifique à jMQTT).

**Sous-type Message**

Pour un message dont le titre est `ecs` et le contenu est `50`, la configuration ci-après publiera:

    boiler {"setpoint": "ecs", "value": 50}

| Nom                | Sous-Type        | Topic    | Valeur                                        |
| ------------------ | ---------------- | -------- | --------------------------------------------- |
| set\_ecs\_setpoint | action - Message | `boiler` | `{"setpoint": "#title#", "value": #message#}` |

**Sous-type Couleur**

La configuration suivante publiera le code couleur sélectionnée via un widget sélecteur de couleur, par exemple:

    room/lamp/color #e63939

| Nom        | Sous-Type        | Topic             | Valeur    |
| ---------- | ---------------- | ----------------- | --------- |
| set\_color | action - Couleur | `room/lamp/color` | `#color#` |

#### Vue Classic, vue JSON

Deux boutons en haut à droite de la page permettent de choisir entre 2 types du vue:
  - La vue **Classic** montre les commandes dans l’ordre d’affichage sur la tuile du Dashboard. Elle permet de les réordonner par glissé/déposé;
  - La vue **JSON** affiche un arbre hiérarchique permettant de naviguer dans les commandes JSON, de les déplier/replier. Dans cette vue, l’ordonnancement des commandes via glissé/déposé est désactivée.

## Ajout manuel d'un équipement

Il est aussi possible de créer manuellement des équipements jMQTT. Cliquer sur le bouton **+** et saisir le nom de l’équipement. Dans la page [Onglet Equipement](#onglet-equipement), le topic de souscription définit les informations qui seront souscrites par l’équipement.

Pour plus d’information sur les topics MQTT, nous conseillons la lecture de [MQTT Essentials Part 5: MQTT Topics & Best Practices](https://www.hivemq.com/blog/mqtt-essentials-part-5-mqtt-topics-best-practices).

## Dupliquer un équipement

Un équipement peut être dupliqué via le bouton `Dupliquer` situé en haut à gauche de la page de configuration de l’équipement.

Une boite de dialogue demande le nom du nouvel équipement. Sont dupliqués:
  - Tous les paramètres de l’équipement y compris les paramètres de configuration avancés, sauf:
      - Le nom bien sûr,
      - Le statut *Activer* : l’équipement est désactivé par défaut,
      - Le topic de souscription qui est laissé vide;
  - Les commandes de type action y compris leurs paramètres de configuration accessibles via la roue crantée.

> **Important**
> 
> Le topic des commandes dupliquées de type action doit être modifié manuellement.

> **Note**
> 
> Les commandes de type info ne sont pas dupliquées. Elles seront découvertes automatiquement après définition du topic de souscription et activation de l’équipement, si la case *Ajout automatique des commandes* est cochée.

## Santé du plugin

Le bouton *Santé*, présent dans la page de [Gestion des équipements](#gestion-des-équipements), permet d'afficher l'état de santé des équipements broker et de leurs équipements 

# API

Le plugin permet d’accéder à toutes les méthodes de l’[API JSON RPC](http://jeedom.github.io/core/fr_FR/jsonrpc_api) au travers du protocole MQTT.

> **Important**
> 
> Pour activer l’API:
> 1.  Activer l’accès à l’API jMQTT dans l'[onglet broker](#onglet-broker) de l'équipement broker concerné.
> 2.  Activer l'**API JSON RPC** dans la configuration Jeedom.

Les payloads requêtes doivent être adressées au topic `Identifiant de Connexion/api`, `jeedom/api` par défaut, l’identifiant de connexion étant jeedom par défaut et se configurant via l'[onglet broker](#onglet-broker) de l'équipement broker concerné.

Leur format JSON est:

    {"method": "Méthode de la requête", "id": "Id. de la requête", "params": { Paramètres additionels }, "topic": "Topic de retour"}

Où:
  - `Méthode de la requête` : nom de la méthode invoquée, voir [API JSON RPC](http://jeedom.github.io/core/fr_FR/jsonrpc_api);
  - `Id. de la requête` (optionnel) : identifiant qui sera retourné dans la réponse, doit être une chaine. Si absent, l’id de retour sera null;
  - `Paramètres additionels` (optionnel) : paramètres relatifs à la méthode de la requête, voir [API JSON RPC](http://jeedom.github.io/core/fr_FR/jsonrpc_api);
  - `Topic de retour` (optionnel) : topic sous lequel le plugin jMQTT publie la réponse à la requête. Si absent, la réponse n’est pas publiée.

**Exemple:.**

Le tableau suivant fournit quelques exemples:

| Requête                                                                                                         | Réponse                                                                                                | Description                                         |
| --------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ | --------------------------------------------------- |
| `{"method":"ping","topic":"retour"}`                                                                            | `{"jsonrpc":"2.0","id":null,"result":"pong"}` sur le topic `retour`                                    | Teste la communication avec Jeedom                  |
| `{"method":"ping","id":"3","topic":"retour"}`                                                                   | `{"jsonrpc":"2.0","id":"3","result":"pong"}` sur le topic `retour`                                     | Teste la communication avec Jeedom                  |
| `{"method":"cmd::execCmd","topic":"emetteur/retour", "id":"a","params":{"id":"798","options":{"slider":"40"}}}` | `{"jsonrpc":"2.0", "id":"a", "result":{"value":"40","collectDate":""}}` sur le topic `emetteur/retour` | Exécute la commande 798: positionne le curseur à 40 |

# FAQ

## Quelles données fournir pour investigation en cas de problèmes ?

En cas de problèmes à l’installation, fournir les fichiers de log jMQTT (niveau Debug) et jMQTT\_dep.

En cas de problèmes à l’utilisation, fournir:
  - Le fichier de log `jMQTT`, niveau Debug, le fichier de log `jMQTT_{nom_broker}` du broker concerné. 
  - Le résultat de la commande suivante:

<!-- end list -->

        mosquitto_sub -h localhost -t "#" -v| xargs -d$'\n' -L1 bash -c 'date "+%Y-%m-%d %T.%3N $0"' | tee /tmp/mosquitto_sub.log

En remplaçant, si besoin, `localhost` par le nom ou l’ip de la machine hébergeant le broker MQTT concerné.

Si le broker requiert une authentification, il faudra ajouter `-u username` et `-p password` avant le `-v`.

## Le démon se déconnecte avec le message "Erreur sur jMQTT::daemon() : The connection was lost."

Vérifier qu’il n’y a pas 2 clients ayant le même identifiant, voir *Identifiant de connexion* dans l'[onglet broker](#onglet-broker) de l'équipement broker concerné.

# Problèmes connus

Les problèmes connus en cours d’investigation sont sur GitHub: [Issues jMQTT](https://github.com/domotruc/jMQTT/issues).

Il peut arriver que l’installation des dépendances se bloque, se référer au chapitre [Configuration du plugin](#configuration-du-plugin).

# Exemples d’utilisation

## Commander un Virtuel via un message MQTT

Supposons un équipement virtuel, appellé *Saison Virtuel*, dont une commande action de sous-type Liste permette de définir la saison (été, hiver). L’objectif est de pouvoir définir cette saison via un message MQTT envoyé par une application externe.

Supposons donc également un équipement jMQTT que nous aurons créer, appelé *Saison\_jMQTT*, souscrivant au topic `saison/#`, dont une commande info est `saison/set`.

Nous souhaitons que lorsqu'une application publie le message `saison/set hiver` sur le broker, la commande info saison du virtuel soit mise à jour avec *hiver*.

Pour ce faire, il faut créer une deuxième commande action côté virtuel (commande *set\_saison* ci-dessous) qui mette à jour l’information saison du virtuel à partir de celle de l’équipement jMQTT. Le virtuel est donc configuré comme ceci:

![saison virtuel](../images/saison_virtuel.png)

Côté équipement jMQTT, nous avons la configuration simple suivante:

![saison jmqtt](../images/saison_jmqtt.png)

Ensuite, il y a deux solutions pour lier les commandes:

  - Créer un scénario avec la commande info `[Saison jMQTT][set]` comme déclencheur, qui executerait la commande action `[Saison Virtuel][set_saison]`; ou

  - Configurer une *action sur valeur* en cliquant sur la roue crantée à droite de la commande info `[Saison jMQTT][set]`, onglet *Configuration*:

![saison action sur valeur](../images/saison_action_sur_valeur.png)

Attention, quelque soit la solution, il est important de configurer la *Gestion de la répétition des valeurs* de la commande info `[Saison jMQTT][set]` à *Toujours répéter* pour que toutes les valeurs remontent au virtuel. Pour celà, toujours en cliquant sur la roue crantée à droite de cette dernière, onglet *Configuration*:

![saison repetition](../images/saison_repetition.png)

<a id="changelog"></a>

# Registre des évolutions

##### 2019-08-22 (beta)

  - Support php 7.3
  - Correction de [\#79](https://github.com/domotruc/jMQTT/issues/77): message d'alerte "L'équipement n'est pas de type broker"

##### 2019-07-23

  - Amélioration [\#78](https://github.com/domotruc/jMQTT/issues/78): ajout fonctionnalité permettant de changer un équipement de broker  

##### 2019-07-14

  - Correction de [\#77](https://github.com/domotruc/jMQTT/issues/77): le broker reste offline

##### 2019-07-09

  - Ajout de traces dans la fonction de migration vers multi broker

##### 2019-06-30

  - Passage en stable des améliorations des 2 betas précédentes

##### 2019-05-31 (beta)
  - Amélioration [\#74](https://github.com/domotruc/jMQTT/issues/74): mise à jour de lastCommunication sur publication d'un message
  - Amélioration [\#73](https://github.com/domotruc/jMQTT/issues/73): remplacement de Dernière Activité par Dernière Communication
  

##### 2019-05-14 (beta)
  - Amélioration [\#63](https://github.com/domotruc/jMQTT/issues/63): ajout du support multi broker
  - Amélioration [\#54](https://github.com/domotruc/jMQTT/issues/54): commande information donnant le status de connexion au broker

##### 2019-04-10
  - Correction de [\#70](https://github.com/domotruc/jMQTT/issues/70): problème de reconnaissance des topics avec accents

##### 2019-04-06

  - Transition vers style V4

> **Important**
> 
> Core Jeedom 3.3.19 ou supérieur requis à partir de la version 2019-04-06.

##### 2019-03-06

  - Passage en stable des évolutions des 2 versions betas précédentes

##### 2019-03-05 (beta)

  - Amélioration documentation (commandes slider)
  - Rend les boutons avancer/reculer du navigateur fonctionnels
  - Correction mineure pour compatibilité avec core 3.3

##### 2019-03-01 (beta)

  - Correction de [\#69](https://github.com/domotruc/jMQTT/issues/69): message de confirmation de fermeture de page inopportun

##### 2019-02-20

  - Amélioration [\#68](https://github.com/domotruc/jMQTT/issues/68): amélioration robustesse aux deconnections intempestives

##### 2018-02-16

  - Passage en stable des évolutions des 4 versions betas précédentes

##### 2019-02-09 (beta)

  - Amélioration [\#67](https://github.com/domotruc/jMQTT/issues/67): visualisation de la mise à jour des valeurs dans le panneau commande

  - Amélioration [\#66](https://github.com/domotruc/jMQTT/issues/66): amélioration perfo en ne sauvant pas les commandes sur maj valeur

  - Amélioration de la documentation ([\#65](https://github.com/domotruc/jMQTT/issues/65))

##### 2018-12-27 (beta)

  - Correction de [\#64](https://github.com/domotruc/jMQTT/issues/64): problème affichage barre de bouton du panneau commandes

##### 2018-12-08 (beta)

  - Correction de [\#62](https://github.com/domotruc/jMQTT/issues/62): Erreur sur jMQTT::daemon() : Using $this when not in object context

##### 2018-11-11 (beta)

  - Amélioration code pour besoin de tests

##### 2018-11-04

  - Correction de [\#58](https://github.com/domotruc/jMQTT/issues/58): MySql duplicate entry error sur topic dépassant 45 caractères. Voir documentation, chapitre [Commandes de type Information](#commandes-de-type-information).

  - Amélioration initialisation équipement sur création manuelle de façon à afficher les paramètres pas défaut comme Qos

  - Ajout du chapitre exemple dans la documentation

##### 2018-10-24

  - Amélioration script installation des dépendances pour corriger [\#59](https://github.com/domotruc/jMQTT/issues/59)

  - Correction typo dans message affiché à l’utilisateur (équipment ⇒ équipement), merci Gwladys

##### 2018-09-16

  - Correction de [\#57](https://github.com/domotruc/jMQTT/issues/57): perte de connexion du démon sur rafale de message (merci jmc)

##### 2018-06-12

  - Intégration des évolutions des 3 versions betas précédentes

##### 2018-06-11 (beta)

  - Correction bugs mineurs relatif à [\#53](https://github.com/domotruc/jMQTT/issues/53):
    
      - Lorsque le topic de retour n’est pas défini ⇒ affiche une erreur;
    
      - Lorsque l’encodage JSON de la requête est incorrect ⇒ affiche un message d’erreur plus clair.

##### 2018-06-10 (beta)

  - Complément à l’amélioration [\#53](https://github.com/domotruc/jMQTT/issues/53):
    
      - Ajoute un paramètre pour activer/désactiver l’API dans la configuration plugin;
    
      - topic jeedom/jeeAPI changé en jeedom/api;
    
      - Mise à jour doc.

##### 2018-06-07 (beta)

  - Amélioration [\#53](https://github.com/domotruc/jMQTT/issues/53): api MQTT

##### 2018-06-03

  - Intégration des évolutions des 3 versions betas précédentes

##### 2018-06-03 (beta)

  - Amélioration [\#55](https://github.com/domotruc/jMQTT/issues/55): ajout d’une vue de visualisation hiérarchique JSON dans le panneau des commandes

##### 2018-05-26 (beta)

  - Amélioration [\#45](https://github.com/domotruc/jMQTT/issues/45): décodage immédiat des payloads JSON sur activation de parseJSON

  - Amélioration [\#52](https://github.com/domotruc/jMQTT/issues/52): activer l’export d’un équipement dans un fichier json

##### 2018-05-24 (beta)

  - Amélioration [\#51](https://github.com/domotruc/jMQTT/issues/51): ajoute des messages d’alerte informant sur la création de commandes

  - Amélioration [\#50](https://github.com/domotruc/jMQTT/issues/50): ajoute un bouton d’actualisation dans la page équipement

  - Correction [\#49](https://github.com/domotruc/jMQTT/issues/49): pas de demande de confirmation de sortie de page sur modifications de certains paramètres

##### 2018-05-11

  - Correction [\#47](https://github.com/domotruc/jMQTT/issues/47): erreur "call to undefined function mb\_check\_encoding"

##### 2018-05-10

  - Intégration des évolutions des 2 versions beta précédentes

##### 2018-05-10 (beta)

  - Correction [\#46](https://github.com/domotruc/jMQTT/issues/46): mauvaise payload avec caractères non ASCII corrompt la commande information associée

  - Amélioration [\#44](https://github.com/domotruc/jMQTT/issues/44): amélioration de l’affichage dans le panneau de commandes

##### 2018-05-08 (beta)

  - Correction [\#42](https://github.com/domotruc/jMQTT/issues/42): log erroné sur création d’une commande info

  - Correction [\#41](https://github.com/domotruc/jMQTT/issues/41): retour de jMQTT dans la catégorie protocole domotique (au lieu de passerelle domotique).

  - Amélioration [\#43](https://github.com/domotruc/jMQTT/issues/43): logguer qu’un équipement ou une commande est supprimé.

##### 2018-04-29

  - Amélioration [\#40](https://github.com/domotruc/jMQTT/issues/40): ajout du champ "valeur de la commande par defaut" (voir [post de vincnet68 sur le forum](https://www.jeedom.com/forum/viewtopic.php?f=96&t=32675&p=612364#p602740)).

  - MAJ icone et fichier info.json suite évolution processus de publication sur le market (mail <partenaire@jeedom.com> du 16/04/2018).

##### 2018-02-15

  - Amélioration [\#36](https://github.com/domotruc/jMQTT/issues/36): le mode inclusion automatique d’équipements s’active maintenant via un bouton d’inclusion depuis la page du plugin et se désactive automatiquement après 2 à 3 min.

  - Correction [\#37](https://github.com/domotruc/jMQTT/issues/37): la bordure mettant en évidence un équipement dont l’ajout automatique de commandes est actif, est correctement affichée quelque soit le thème.

##### 2018-02-06

  - Amélioration [\#26](https://github.com/domotruc/jMQTT/issues/26): ajout d’une case à cocher dans l’équipement permettant de désactiver la création automatique des commandes de type information.

##### 2018-02-05

  - Correction [\#30](https://github.com/domotruc/jMQTT/issues/30): les commandes action n’étaient pas envoyées immédiatement depuis des scénarios.

  - Correction [\#25](https://github.com/domotruc/jMQTT/issues/25): les commandes avec Qos=2 n’étaient pas envoyées.

  - Correction [\#28](https://github.com/domotruc/jMQTT/issues/28): rend possible la définition de commandes action JSON (voir exemples dans la documentation: [Commandes de type Action](#commandes-de-type-action)).

  - Correction [\#31](https://github.com/domotruc/jMQTT/issues/31): message de log erroné sur accusé de réception de souscription.

##### 2018-01-26

  - Correction [\#23](https://github.com/domotruc/jMQTT/issues/23): sur une rafale de commande, seule la dernière était envoyée.

##### 2018-01-24

  - Amélioration [\#19](https://github.com/domotruc/jMQTT/issues/19): ajoute une option pour ne pas installer Mosquitto localement.

##### 2018-01-15

  - Amélioration [\#10](https://github.com/domotruc/jMQTT/issues/10): duplication d’équipement (voir la doc).

  - Correction [\#15](https://github.com/domotruc/jMQTT/issues/15): les topics commençant par / n’étaient pas souscrits après désactivation du mode manuel

> **Important**
> 
> Si vous avez des topics commençant par / créés avant cette version, il faut ajouter le / en début de topic souscrit dans les équipements concernés. Les commandes de types info vont être recréer par le plugin, il faudra supprimer les anciennes (celles dont le topic ne commencent pas par /). En cas de doutes, de questions, n’hésiter pas à poster sur le forum.

  - Correction [\#13](https://github.com/domotruc/jMQTT/issues/13): commande null systématiquement envoyée sur création d’une commande action.

  - Correction [\#14](https://github.com/domotruc/jMQTT/issues/14): le champ de sélection value, sous le nom d’une commande de type action, est supprimé car il n’avait pas d’effet.

  - Amélioration [\#17](https://github.com/domotruc/jMQTT/issues/17): autorise les équipements avec topic vide.

  - Correction [\#18](https://github.com/domotruc/jMQTT/issues/18): arrête de créer une commande info relative à une commande action.

##### 2018-01-08

  - Correction [\#9](https://github.com/domotruc/jMQTT/issues/9): l’installation se bloque à 80% au redémarrage du serveur apache.

##### 2018-01-06

  - Correction [\#7](https://github.com/domotruc/jMQTT/issues/7): erreur "Le nom de l’équipement ne peut pas être vide" et arrêt du démon sur réception d’un topic commençant par /.

  - Amélioration de l’installation: ajout du statut de progression, lisibilité fichier de log

  - Correction [\#1](https://github.com/domotruc/jMQTT/issues/1): dernière valeur maintenue retain au niveau du broker sur suppression du mode retain d’une commande.

  - Correction [\#6](https://github.com/domotruc/jMQTT/issues/6): case inversion cochée par défaut pour information binaire.

##### 2018-01-04

  - MAJ du README côté GitHub

##### 2018-01-03

  - MAJ de la documentation

##### 2018-01-01

  - Supprime les tentatives de reconnexion toutes les secondes sur problème de connexion au broker: rend maintenant la main au core Jeedom qui relancera le démon (et donc la reconnexion) toutes les 5min.

  - Correction bug sur authentification auprès du broker (merci Nicolas)

  - Message d’erreur sur définition d’un topic vide

  - MAJ fichier internationalisation

  - Changement de la couleur de l’icône et des images du plugin (jaune au lieu de bleu)

  - MAJ liens de la doc

##### 2017-12-26

  - Version initiale
