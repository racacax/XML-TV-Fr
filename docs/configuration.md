# Configuration (config/config.json)

## Exemple complet

```json
{
  "fetch_policies": {
    "cache-first": [1, 2, 3, 4, 5, 6, 7],
    "network-first": [0],
    "cache-only": [-2, -1]
  },
  "cache_ttl": 8,
  "cache_physical_ttl": 8,
  "output_path": "var/export/",
  "time_limit": null,
  "memory_limit": -1,
  "export_handlers": [
    {"class": "GZExport", "params": {}},
    {"class": "ZIPExport", "params": {}}
  ],
  "delete_raw_xml": false,
  "enable_dummy": false,
  "priority_orders": {"Telerama": 0.2, "UltraNature": 0.5},
  "guides": [
    {"channels": ["config/channels.json"], "filename": "xmltv"}
  ],
  "nb_threads": 1,
  "min_endtime": 84600,
  "ui": "MultiColumnUI",
  "connectivity_check_url": "https://xmltvfr.fr",
  "provider_limits": {"SFR": 5},
  "extra_params": {}
}
```

## Description des paramètres

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `fetch_policies` | objet | voir ci-dessous | Politique de récupération par jour |
| `cache_ttl` | entier | `8` | Nombre de jours avant qu'un cache EPG soit considéré expiré |
| `cache_physical_ttl` | entier | `8` | Nombre de jours avant la suppression physique du cache sur le disque |
| `output_path` | chaîne | `"var/export/"` | Dossier de destination des fichiers XMLTV |
| `time_limit` | entier\|null | `null` | Temps d'exécution max en secondes (`null` = illimité) |
| `memory_limit` | entier | `-1` | Quantité de mémoire max (`-1` = illimitée) |
| `export_handlers` | tableau | GZ + ZIP | Liste des formats d'export ([voir détails](./export.md)) |
| `delete_raw_xml` | booléen | `false` | Supprimer le XML brut après compression |
| `enable_dummy` | booléen | `false` | Afficher un EPG mire si aucun programme n'est trouvé pour une chaine |
| `priority_orders` | objet | `{}` | Modifier la priorité globale de certains providers |
| `guides` | tableau | voir ci-dessous | Liste des fichiers XMLTV à générer ([voir détails](./channels.md#générer-plusieurs-guides-xmltv)) |
| `nb_threads` | entier | `1` | Nombre de threads parallèles (nécessite l'accès shell) |
| `min_endtime` | entier | `84600` | Heure minimale (en secondes depuis minuit) à laquelle le dernier programme doit se terminer pour que le jour soit considéré complet (84600 = 23h30) |
| `ui` | chaîne | `"MultiColumnUI"` | Interface terminal : `MultiColumnUI` ou `ProgressiveUI` |
| `connectivity_check_url` | chaîne\|null | `"https://xmltvfr.fr"` | URL testée pour vérifier la connectivité internet lorsque plusieurs providers échouent consécutivement. `null` pour désactiver. |
| `provider_limits` | objet | `{"SFR": 5}` | Nombre maximum d'utilisations simultanées par provider (ex: `{"SFR": 5}` autorise 5 chaînes SFR en parallèle). |
| `extra_params` | objet | `{}` | Paramètres supplémentaires transmis aux providers (ex: `{"mycanal_enable_details": true}`). |

## Politique de récupération (`fetch_policies`)

Les jours sont exprimés en décalage par rapport à aujourd'hui : `0` = aujourd'hui, `1` = demain, `-1` = hier, etc.

| Politique | Comportement |
|-----------|-------------|
| `network-first` | Récupère toujours les données depuis le provider. Si le provider échoue, utilise le cache en fallback. Utilisé par défaut pour aujourd'hui (`[0]`). |
| `cache-first` | Utilise le cache s'il est disponible et valide, sinon récupère depuis le provider. |
| `cache-only` | Utilise uniquement le cache existant, sans aucune requête réseau. |

Un cache est considéré valide si son dernier programme se termine après `min_endtime` et si son âge est inférieur à `cache_ttl` jours.

Un jour peut apparaître dans une seule politique à la fois. Les jours non déclarés dans `fetch_policies` ne sont pas récupérés.

## Priorités des providers (`priority_orders`)

Permet de modifier globalement la priorité d'un provider pour toutes les chaines. La valeur est un flottant entre 0 et 1 (plus élevé = appelé en premier).

```json
"priority_orders": {
  "Telerama": 0.9,
  "Orange": 0.3
}
```

La priorité par chaine (champ `priority` dans `channels.json`) prend le dessus sur `priority_orders`. Voir [liste des chaines](./channels.md#priorité-par-chaine).

## Limites par provider (`provider_limits`)

Par défaut, un provider ne peut traiter qu'une seule chaine à la fois en mode multi-thread, sauf exceptions. `provider_limits` permet d'augmenter ce nombre pour les providers qui supportent la parallélisation.

```json
"provider_limits": {
  "SFR": 5,
  "Orange": 3
}
```

La valeur par défaut `{"SFR": 5}` reflète le fait que SFR tolère plusieurs requêtes simultanées.

## Paramètres supplémentaires (`extra_params`)

Certains providers acceptent des paramètres de configuration spécifiques transmis via `extra_params` :

| Paramètre | Provider | Type | Défaut | Description |
|-----------|----------|------|--------|-------------|
| `mycanal_enable_details` | MyCanal | booléen | `true` | Active la récupération des détails de programme (synopsis, casting, etc.). Désactiver accélère la récupération mais réduit la richesse des données. |

```json
"extra_params": {
  "mycanal_enable_details": false
}
```

## Vérification de connectivité (`connectivity_check_url`)

Lorsque plus de 2 providers différents échouent consécutivement avec une erreur réseau, XML TV Fr teste la connectivité internet en faisant une requête vers `connectivity_check_url`. Si la requête échoue, le processus s'arrête immédiatement (code de sortie 1) plutôt que de continuer à tenter des appels réseau voués à l'échec.

Mettre à `null` pour désactiver ce comportement :

```json
"connectivity_check_url": null
```

## Interface terminal (`ui`)

Deux modes d'affichage sont disponibles :

- **`MultiColumnUI`** (défaut) : affichage multi-colonnes avec l'état en temps réel de chaque thread.
- **`ProgressiveUI`** : affichage ligne par ligne, utile pour les environnements sans terminal interactif (logs, CI).
