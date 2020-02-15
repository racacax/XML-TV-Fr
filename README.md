# XML TV Fr

XML TV Fr est un service permettant de récupérer un guide des programmes au format XMLTV.


# Prérequis

PHP >=5.4 avec les extensions
 - curl
 - zip
 - mbstring
 - xml
 - json

# Configuration

Cette partie va vous permettre de configurer XML TV Fr.

## Liste des chaines (channels.json)

La liste des chaines doit être indiquée dans le fichier channels.json au format JSON. Chaque chaine correspond à l'ID d'une chaine (Exemple : France2.fr) présente dans les fichiers de chaines par services (dossier channels_by_providers).
La structure d'un item se fait comme ceci :

    "IdDelaChaine":{"name":"Nom de la chaine","icon":"http://icone de la chaine","priority":["Service1","Service2"]}
Les champs name, icon et priority sont optionnels. 
Le champ priority donne un ordre de priorité différent de celui par défaut en indiquant les noms des services (nom des classes dans le dossier classes). Dans l'exemple, Service1 sera appelé en premier et Service2 ne sera appelé que si Service1 échoue. Par exemple si on met en priorité Télérama puis Orange, Télérama sera lancé. Si aucun programme n'est trouvé sur Télérama, Orange est lancé, sinon on continue. Si aucun programme n'est trouvé sur tous les services, la chaine est indiquée HS pour le jour concerné.

## Configuration du programme (config.json)

Le fichier config.json est au format JSON. Le champ days correspond au nombre de jours suivant la date du jour que l'on souhaite obtenir.

# Lancer le script
Pour démarrer la récupération du guide des programmes, lancez cette commande dans votre terminal (dans le dossier du programme).

    php script_all.php
# Sortie

## Logs
Les logs sont stockés dans le dossier logs au format JSON.
## XML TV
Les fichiers de sorties XML sont stockés dans le dossier xmltv au format XML, ZIP et GZ.
Pour vérifier si celui-ci est valide, il suffit de lancer :
`php xmlvalidator.php`
Cette commande indiquera si le dernier fichier XML généré est valide.

# Ajouter des services

Il est possible d'ajouter des services autres que ceux fournis. Pour cela, il faut ajouter une classe dans le dossier classes qui implémente la classe Provider. 
Le constructeur aura le chemin des XML temporaires d'indiqué.
La méthode *getPriority()* renverra un flottant de préférence entre 0 et 1 pour indiquer la priorité par rapport à d'autres services (comparez les valeurs des autres scripts pour vous situer).
La méthode   *constructEPG(channel,date)* construira un fichier XML pour une chaine à une date donnée. Elle retourne **true** si la tâche s'est déroulée avec succès, sinon **false**. Le fichier doit être stocké dans le dossier des XML temporaires et doit être de la forme **[ID de la chaine]_[Date].xml**. La méthode statique *generateFilePath(xmlpath,channel,date)* dela classe Utils le construit automatiquement.

Attention, le nom de la classe du service doit correspondre à son nom de fichier. Bien que PHP, contrairement à Java autorise des noms différents, le programme ici ne le permet pas.

