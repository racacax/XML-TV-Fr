# XML TV Fr

XML TV Fr est un service permettant de récupérer un guide des programmes au format XMLTV.

Site web et documentation : https://xmltvfr.fr/


# Installation

## Natif

PHP >=8.0 avec les extensions curl, zip, mbstring, xml, json, dom, simplexml, xmlreader, libxml, pcntl, posix, intl, ainsi que Composer.

```bash
composer install
```

## Docker

```bash
docker build -t xmltvfr .
docker run -v ./var/export:/app/var/export -v ./config/:/app/config xmltvfr
```

[Documentation complète de l'installation](docs/installation.md)


# Configuration

## Liste des chaines (`config/channels.json`)

Chaque chaine est identifiée par son ID (ex : `France2.fr`) tel que défini dans les fichiers `resources/channel_config/`. Tous les champs sont optionnels.

```json
{
  "France2.fr": {
    "name": "France 2",
    "alias": "france2",
    "icon": "https://example.com/france2.png",
    "priority": ["Telerama", "Orange"]
  }
}
```

Plusieurs fichiers de chaines peuvent être combinés dans un même guide XMLTV (voir configuration des guides).

[Documentation complète des chaines](docs/channels.md)

## Configuration du programme (`config/config.json`)

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
  "priority_orders": {},
  "guides": [
    {"channels": ["config/channels.json"], "filename": "xmltv"}
  ],
  "nb_threads": 1,
  "ui": "MultiColumnUI"
}
```

Principaux paramètres :

| Paramètre | Description |
|-----------|-------------|
| `fetch_policies` | Politique de récupération par jour (`network-first`, `cache-first`, `cache-only`) |
| `cache_ttl` | Durée de validité du cache EPG en jours |
| `export_handlers` | Formats de sortie : `GZExport`, `ZIPExport`, `CommandLineExport` |
| `guides` | Liste des fichiers XMLTV à générer, chacun avec un ou plusieurs fichiers de chaines |
| `nb_threads` | Nombre de threads parallèles |
| `priority_orders` | Priorité globale des providers (flottant 0–1) |

[Documentation complète de la configuration](docs/configuration.md) — [Formats d'export](docs/export.md)


# Lancer le script

## Natif

```shell
php manager.php export
```

Options disponibles :

```shell
php manager.php export --skip-generation  # Exporte sans régénérer l'EPG
php manager.php export --keep-cache       # Conserve le cache après la génération
php manager.php fetch-channel <channel-id> <date> <provider> <output_path>
php manager.php update-default-logos
php manager.php help
```

## Docker

```bash
docker run -v ./var/export:/app/var/export -v ./config/:/app/config xmltvfr
```


# Sortie

## Logs

Les logs sont stockés dans `var/logs/` au format JSON.

## Fichiers XMLTV

Les fichiers sont générés dans `var/export/` selon les `export_handlers` configurés (`.xml`, `.gz`, `.zip`, ou tout autre format via `CommandLineExport`).


# Ajouter des services

Il est possible d'ajouter des providers personnalisés en créant une classe dans `src/Component/Provider/` qui étend `AbstractProvider`.

```php
public function constructEPG(string $channel, string $date): Channel|bool
{
    parent::constructEPG($channel, $date);
    foreach ($results as $result) {
        $program = $this->channelObj->addProgram(strtotime($result['start']), strtotime($result['end']));
        $program->addTitle($result['title']);
        $program->addDescription($result['synopsis']);
    }
    return $this->channelObj;
}
```

[Documentation complète pour ajouter un provider](docs/providers.md)
