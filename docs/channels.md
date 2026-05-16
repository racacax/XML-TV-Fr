# Liste des chaines

## Format du fichier

La liste des chaines est définie dans `config/channels.json` au format JSON. Chaque clé correspond à l'ID d'une chaine tel que défini dans les fichiers de chaines par provider (dossier `resources/channel_config/`).

```json
{
  "France2.fr": {
    "name": "France 2",
    "alias": "france2",
    "icon": "https://example.com/france2.png",
    "priority": ["Telerama", "Orange"]
  },
  "TF1.fr": {}
}
```

## Champs disponibles

Tous les champs sont optionnels.

| Champ | Description |
|-------|-------------|
| `name` | Nom affiché de la chaine dans le guide |
| `alias` | ID alternatif utilisé dans le fichier XMLTV de sortie. Si absent, c'est l'ID par défaut de XML TV Fr qui est utilisé. |
| `icon` | URL du logo de la chaine |
| `priority` | Ordre de priorité des providers pour cette chaine (voir ci-dessous) |

## Priorité par chaine

Le champ `priority` accepte un tableau de noms de providers (nom des classes dans `src/Component/Provider/`). Les providers sont appelés dans l'ordre indiqué : si le premier échoue, le suivant est essayé, et ainsi de suite.

```json
"France2.fr": {
  "priority": ["Telerama", "Orange", "SFR"]
}
```

Si aucun provider ne trouve de données pour la chaine à une date donnée, la chaine est marquée HS pour ce jour.

Si le champ `priority` est absent, l'ordre par défaut défini par les priorités globales des providers s'applique (voir [`priority_orders` dans la configuration](./configuration.md#priority_orders)).

## Combiner plusieurs fichiers de chaines

Il n'est pas nécessaire de regrouper toutes les chaines dans un seul fichier. La configuration des guides (`guides` dans `config/config.json`) accepte un tableau de fichiers de chaines qui sont fusionnés au moment de la génération :

```json
"guides": [
  {
    "channels": ["config/channels.json", "config/channels_extra.json"],
    "filename": "xmltv"
  }
]
```

Cela permet par exemple de séparer les chaines françaises des chaines belges, ou de maintenir un fichier de chaines communautaire à part.

## Générer plusieurs guides XMLTV

Chaque entrée du tableau `guides` génère un fichier XMLTV distinct. On peut ainsi produire plusieurs fichiers avec des sélections de chaines différentes en un seul lancement :

```json
"guides": [
  {"channels": ["config/channels_fr.json"], "filename": "xmltv_fr"},
  {"channels": ["config/channels_be.json"], "filename": "xmltv_be"},
  {"channels": ["config/channels_fr.json", "config/channels_be.json"], "filename": "xmltv_all"}
]
```

## Trouver les IDs des chaines

Les IDs disponibles sont listés dans les fichiers `resources/channel_config/channels_*.json`, un fichier par provider. Un même ID peut apparaître dans plusieurs providers — XML TV Fr choisit automatiquement le meilleur selon les priorités.
