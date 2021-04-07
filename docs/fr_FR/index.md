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

jMQTT présente 2 types d'équipements:
  - les équipements de type broker gérant la connexions avec les brokers MQTT, appelés équipements broker dans la suite;
  - les équipements "standards", appélés simplement équipements dans la suite, 

Le panneau supérieur gauche, intitulé *Gestion plugin et brokers*, permet de configurer le plugin, et d'afficher les équipements de type broker gérant la connexion avec les brokers MQTT. Dans l'ordre d'affichage des icônes:
  - L'icône *Configuration* permet d'accéder à la [Configuration du plugin](#configuration-du-plugin);
  - L'icône *Santé* permet d'accéder à la page [Santé du plugin](#santé-du-plugin);
  - L'icône **+** permet l'[ajout d'un équipement broker](#ajout-dun-équipement-broker);
  - Les icônes des équipements broker, présentant dans leur coin supérieur droit un point de couleur indiquant l'état de la connexion au broker:
     * Vert: la connexion au broker est nominale;
     * Orange: le démon tourne mais la connexion au broker n'est pas établie;
     * Rouge: le démon est arrêté.

Viennent ensuite pour chaque broker, un panneau occupant toute la largeur de la page et présentant les équipements connectés au broker nommé dans la légende du panneau. Outre les équipements, le panneau comprend :
  - Un bouton **+** permettant l'[ajout manuel d'un équipement](#ajout-manuel-dun-équipement);
  - Un icône d'activation du mode [Inclusion automatique des équipements](#inclusion-automatique-des-équipements).

A noter qu'un équipement :
  - Grisé est inactif;
  - Présenté avec un petit icône d’inclusion superposé, est un équipement dont l'*Ajout automatique des commandes* est activé (détails dans [Onglet Equipement](#onglet-equipement));
  - Est présenté avec un petit icône d'oeil barré ou non indiquant qu'il est non visible ou visible.
  
> **Important**
> 
> Un panneau intitulé _Equipement orphelins_ pourrait aussi être présent suite à la migration vers la version supportant le multi-brokers. Il regroupe les équipements qui n'ont pas été migrés automatiquement. Editer chacun d'eux et les associer à l'équipement broker de votre installation (qui est unique normalement après migration), voir paramètre _Broker associé_ au chapitre [Paramètres de l'équipement](#paramètres-de-léquipement).

## Ajout d'un équipement broker

Pour commencer à utiliser le plugin, il est nécessaire de créer au moins un équipement broker. Dans la page de [Gestion des équipements](#gestion-des-équipements), cliquer sur le bouton **+** *Ajouter un broker* et saisir son nom.

Dans la foulée de sa création, un message indique que la commande *status* a été ajoutée à l'équipement. Celle-ci, que nous retrouvons dans le panneau *Commandes*, donne l'état de connexion au broker MQTT de l'équipement broker. Elle prend 2 valeurs : *online* et *offline*. Elle est publiée de manière persistante auprès du broker. Cet état permet à un équipement externe à Jeedom de connaitre son statut de connexion. Il peut aussi servir en interne Jeedom pour monitorer la connexion au broker via un scénario.

Un équipement broker se comporte comme un équipement "standard" et présente donc les onglets _Equipements_ et _Commandes_ que ce dernier : nous nous reporterons donc au chapitre [Paramètres de l'équipement](#paramètres-de-léquipement) concernant la description de ces onglets.

Nous nous attachons ici à décrire l'onglet spécifique.

### Onglet Broker

Par défaut, un équipement broker est configuré pour s’inscrire au broker Mosquitto installé localement sur la machine hébergeant Jeedom. Si cette configuration convient, activer l'équipement dans l'onglet _Equipement_ et sauvegarder. Revenir sur l'onglet _Broker_, le status du démon devrait passer à OK.

Pour particulariser la configuration du plugin, les paramètres sont:
  - _IP de Mosquitto_ : adresse IP du broker (par défaut localhost i.e. la machine hébergeant Jeedom);
  - _Port de Mosquitto_ : port du broker (1883 par défaut);
  - _Identifiant de connexion_ : identifiant avec lequel l'équipement broker s’inscrit auprès du broker MQTT (jeedom par défaut).
    > - **Attention**: cet identifiant doit être unique par client et par broker. Sinon les clients portant le même identifiant vont se déconnecter l’un-l’autre à chaque connection.
    - Cet identifiant est utilisé dans le nom du topic des commandes info *status* et *api* que l'on retrouve dans l'onglet *Commandes*. Les topics sont automatiquement mis à jour si l'identifiant est modifié. 
  - _Compte et mot de passe de connexion_ : compte et mot de passe de connexion au broker (laisser vide par défaut, notamment si jMQTT se charge de l’installation du broker, sauf bien-sûr si vous modifiez la configuration de ce dernier pour activer l’authentification et dans ce cas vous savez ce que vous faites).
  - _Topic de souscription en mode inclusion automatique des équipements_ : topic de souscription automatique à partir duquel le plugin va découvrir les équipements de manière automatique, nous y revenons dans la partie équipements (\# par défaut, i.e. tous les topics).
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

  - _Broker associé_ : broker auquel est associé l'équipement. **Attention**: ne modifier ce paramètre qu'en sachant bien ce que vous faites;

  - _Inscrit au Topic_ : topic de souscription auprès du broker MQTT. Pour un équipement de type broker, ce paramètre n'est pas modifiable, il est imposé par l'identifiant de connexion au broker, voir [Onglet Broker](#onglet-broker);

  - _Ajout automatique des commandes_ : si coché, les [commandes de type information](#commandes-de-type-information) seront automatiquement créés par le plugin, et l’équipement apparaitra avec un petit icône d’inclusion superposé dans la page de [Gestion des équipements](#gestion-des-équipements). La case est cochée par défaut;

  - _Qos_ : qualité de service souscrit;

  - _Type d'alimentation_ : paramètre libre vous permettant de préciser le type d'alimentation de l'équipement (non disponible pour un équipement broker);

  - _Dernière communication_ : date de dernière communication avec le broker MQTT, que ce soit en réception (commande information) ou publication (commande action);

  - _Catégorie du topic_ : sélection d’une image spécifique à l’équipement. Pour un équipement broker, ce paramètre n'est pas disponible car l'image est imposée.
  

> **Important**
> 
> Une fois les commandes créés, il est conseillé de décocher la case *Ajout automatique des commandes* pour éviter la création d’informations non souhaitées.

Concernant les boutons en haut à droite:

  - `Appliquer Template` TODO;

  - `Créer Template` TODO;

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

| Nom         | Sous-Type | Topic              | Valeur   |
| ----------- | --------- | ------------------ | -------- |
| brand       | info      | boiler/brand       | viesmann |
| burner      | info      | boiler/burner      | 0        |
| temp        | info      | boiler/temp        | 70.0     |
| ext\_temp   | info      | boiler/ext\_temp   | 19.3     |
| hw:setpoint | info      | boiler/hw/setpoint | 50       |
| hw:temp     | info      | boiler/hw/temp     | 49.0     |

> **Note**
> 
>   * Le nom de la commande est initialisée automatiquement par le plugin à partir du topic. Il peut ensuite être modifié comme souhaité.
>   * Jeedom, dans sa version actuelle, limite la longueur des noms de commande à 45 caractères. Dans le cas de commandes de longueur supérieure, jMQTT remplace leurs noms par leur code de hashage md4 sur 32 caractères (e.g. 5182636929901af7fa5fd97da5e279e1). L’utilisateur devra alors remplacer ces noms par le nommage de son choix.

**Payload JSON**

Dans le cas d’une payload JSON, le plugin sait décoder le contenu et créer les informations associées, et ceci indépendamment de l’état de la case *Ajout automatique des commandes* de l’Onglet Equipement.

Prenons l’exemple de la payload JSON suivante:

    esp/temperatures {"device": "ESP32", "sensorType": "Temperature", "values": [9.5, 18.2, 20.6]}

Au premier message reçu, jMQTT créé automatiquement l’information suivante:

| Nom          | Sous-Type | Topic            | Valeur                                                                          |
| ------------ | --------- | ---------------- | ------------------------------------------------------------------------------- |
| temperatures | info      | esp/temperatures | {"device": "ESP32", "sensorType": "Temperature", "values": \[9.5, 18.2, 20.6\]} |


En basculant dans la vue JSON, via le bouton dédié en haut à droite de la page, et en dépliant complêtement l'arbre manuellement, nous obtenons:

| # |   | Nom                      | Sous-Type | Topic                        | Valeur                                                                          |
| - | - |------------------------- | --------- | ---------------------------- | ------------------------------------------------------------------------------- |
| > |   | temperatures             | info      | esp/temperatures             | {"device": "ESP32", "sensorType": "Temperature", "values": \[9.5, 18.2, 20.6\]} |
|   |   |                          | info      | esp/temperatures{device}     | "ESP32"                                                                         |
|   |   |                          | info      | esp/temperatures{sensorType} | "Temperature"                                                                   |
|   | > |                          | info      | esp/temperatures{values}     | \[9.5, 18.2, 20.6\]                                                             |
|   |   |                          | info      | esp/temperatures{values}{0}  | 9.5                                                                             |
|   |   |                          | info      | esp/temperatures{values}{1}  | 18.2                                                                            |
|   |   |                          | info      | esp/temperatures{values}{2}  | 20.6                                                                            |

Seule la première ligne est une commande, reconnaissable parce qu'elle a un id, un nom et des paramètres.

Pour créer des commandes associées à chaque température, il suffit de saisir un nom dans chaque commande (par exemple _temp0_, _temp1_ et _temp2_) et de sauvegarder.

L'affichage bascule dans la vue normale, montrant toutes les commandes de l'équipement ; dans notre cas:

| Nom          | Sous-Type | Topic                        | Valeur                                                                          |
| ------------ | --------- | ---------------------------- | ------------------------------------------------------------------------------- |
| temp0        | info      | esp/temperatures{values}{0}  | 9.5                                                                             |
| temp1        | info      | esp/temperatures{values}{1}  | 18.2                                                                            |
| temp2        | info      | esp/temperatures{values}{2}  | 20.6                                                                            |
| temperatures | info      | esp/temperatures             | {"device": "ESP32", "sensorType": "Temperature", "values": \[9.5, 18.2, 20.6\]} |

Si nous rebasculons dans la vue JSON, nous obtenons alors:

| # |   | Nom                      | Sous-Type | Topic                        | Valeur                                                                          |
| - | - |------------------------- | --------- | ---------------------------- | ------------------------------------------------------------------------------- |
| > |   | temperatures             | info      | esp/temperatures             | {"device": "ESP32", "sensorType": "Temperature", "values": \[9.5, 18.2, 20.6\]} |
|   |   |                          | info      | esp/temperatures{device}     | "ESP32"                                                                         |
|   |   |                          | info      | esp/temperatures{sensorType} | "Temperature"                                                                   |
|   | > |                          | info      | esp/temperatures{values}     | \[9.5, 18.2, 20.6\]                                                             |
|   |   | temp0                    | info      | esp/temperatures{values}{0}  | 9.5                                                                             |
|   |   | temp1                    | info      | esp/temperatures{values}{1}  | 18.2                                                                            |
|   |   | temp2                    | info      | esp/temperatures{values}{2}  | 20.6                                                                            |

> **Note**
> 
>   * Le nom des commandes peut être modifié comme souhaité, jMQTT se base sur le champ Topic pour associer la bonne valeur. 
>   * Une fois les commandes filles d'une commande JSON créé, il est possible de supprimer la commande mère sans affecter la mise à jour des commandes filles. 

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
  - La vue **JSON** affiche un arbre hiérarchique permettant de naviguer à l'intérieur des payload JSON, de les déplier/replier, et de créer les commandes information souhaitées (se reporter au paragraphe _Payload JSON_) de la section [Commandes de type Information](#commandes-de-type-information)). Dans cette vue, l’ordonnancement des commandes via glissé/déposé est désactivée.

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

**Exemple:**

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

Les problèmes connus en cours d’investigation sont sur GitHub: [Issues jMQTT](https://github.com/domochip/jMQTT/issues).

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

[Voir ici](changelog)
