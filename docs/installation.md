# Installation

## Natif

### Prérequis

PHP >=8.0 avec les extensions suivantes :
- curl
- zip
- mbstring
- xml
- json
- dom
- simplexml
- xmlreader
- libxml
- pcntl
- posix
- intl

Ainsi que [Composer](https://getcomposer.org/).

### Installation

```bash
git clone https://github.com/racacax/XML-TV-Fr.git
cd XML-TV-Fr
composer install
cp resources/config/default_config.json config/config.json
cp resources/config/default_channels.json config/channels.json
```

### Mise à jour

```bash
git pull
composer install
```

## Docker

### Construire l'image

```bash
docker build -t xmltvfr .
```

Cette commande doit être relancée après chaque mise à jour de XML TV Fr.

### Lancer l'export

```bash
docker run -v ./var/export:/app/var/export -v ./config/:/app/config xmltvfr
```

Remplacez `./var/export` par le dossier de sortie souhaité.

### Passer des arguments

```bash
docker run -v ./var/export:/app/var/export -v ./config/:/app/config xmltvfr php manager.php export --keep-cache
```

### Makefile (développement)

```bash
make drun ARGS="php manager.php export --skip-generation"
```
