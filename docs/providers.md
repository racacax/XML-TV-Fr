# Ajouter un provider

Il est possible d'ajouter des providers personnalisés en plus de ceux fournis par XML TV Fr.

## Structure minimale

Créez une classe dans `src/Component/Provider/` qui étend `AbstractProvider` et implémente `ProviderInterface`. Le nom de la classe doit correspondre exactement au nom du fichier.

```php
<?php

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\StaticComponent\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;

class MonProvider extends AbstractProvider
{
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_monprovider.json'), $priority ?? 0.5);
    }

    public static function getStaticPriority(): float
    {
        return 0.5;
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        parent::constructEPG($channel, $date);

        $data = json_decode($this->getContentFromURL('https://monapi.fr/epg/'.$channel.'/'.$date), true);

        if (empty($data)) {
            return false;
        }

        foreach ($data as $item) {
            $program = $this->channelObj->addProgram(strtotime($item['start']), strtotime($item['end']));
            $program->addTitle($item['title']);           // langue par défaut : "fr"
            $program->addDescription($item['synopsis']);
            $program->addIcon($item['image']);
            $program->addCategory($item['genre']);
            $program->addSubTitle($item['episode_title']);
        }

        return $this->channelObj;
    }
}
```

## Méthodes disponibles

### Requêtes HTTP

```php
$this->getContentFromURL(string $url, array $headers = [], bool $post = false): string
```

Récupère le contenu d'une URL avec mise en cache HTTP automatique (dossier `var/provider_cache/`). La clé de cache est un MD5 de l'URL et des headers.

### Ajout de programmes

```php
$program = $this->channelObj->addProgram(int $start, int $end): Program
```

Timestamps Unix. Retourne un objet `Program` sur lequel on peut appeler :

| Méthode | Description |
|---------|-------------|
| `addTitle(string $title, string $lang = 'fr')` | Titre du programme |
| `addSubTitle(string $subtitle, string $lang = 'fr')` | Titre de l'épisode |
| `addDescription(string $desc, string $lang = 'fr')` | Synopsis |
| `addCategory(string $category, string $lang = 'fr')` | Genre / catégorie |
| `addIcon(string $url)` | URL de l'image |
| `addDirector(string $name)` | Réalisateur |
| `addActor(string $name, string $role = '')` | Acteur |
| `addEpisodeNum(string $num, string $system = 'xmltv_ns')` | Numéro d'épisode |
| `addRating(string $value, string $system = 'CSA')` | Classification |
| `setNew(bool $new = true)` | Marque comme inédit |

## Priorité

La priorité est un flottant entre 0 et 1. Plus elle est élevée, plus le provider est appelé en priorité. Valeurs de référence :

| Provider | Priorité |
|----------|----------|
| Telerama | 0.8 |
| MyCanal | 0.7 |
| Orange | 0.6 |
| SFR | 0.5 |

La priorité peut être surchargée globalement via `priority_orders` dans `config/config.json`, ou par chaine via le champ `priority` dans `channels.json`. Voir [configuration](./configuration.md#priorités-des-providers-priority_orders).

## Fichier de chaines

Créez `resources/channel_config/channels_monprovider.json` listant les chaines supportées par votre provider. Le format est un objet JSON dont les clés sont les IDs de chaines :

```json
{
  "France2.fr": "france-2",
  "TF1.fr": "tf1"
}
```

La valeur associée à chaque clé est l'identifiant interne utilisé par votre provider (ID API, slug, etc.). Vous pouvez y stocker un objet si plusieurs informations sont nécessaires :

```json
{
  "France2.fr": {"id": "france-2", "region": "fr"}
}
```

## Paramètres supplémentaires (`extraParam`)

Le constructeur reçoit un tableau `$extraParam` qui contient les valeurs de `extra_params` issues de `config/config.json`. Cela permet d'exposer des options de configuration sans modifier le code :

```php
public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
{
    $this->enableDetails = $extraParam['monprovider_enable_details'] ?? true;
    parent::__construct(...);
}
```

L'utilisateur peut alors configurer ce comportement dans son `config/config.json` :

```json
"extra_params": {
  "monprovider_enable_details": false
}
```

Documentez vos paramètres dans [docs/configuration.md](./configuration.md#paramètres-supplémentaires-extra_params).
