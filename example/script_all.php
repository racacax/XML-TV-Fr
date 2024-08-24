<?php

require_once 'vendor/autoload.php';

use racacax\XmlTv\Component\Generator;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Configurator;

if(!file_exists('config/config.json')) {
    copy('resources/config/default_config.json', 'config/config.json');
}
if(!file_exists('config/channels.json')) {
    copy('resources/config/default_channels.json', 'config/channels.json');
}

Logger::setLogLevel('debug');
Logger::setLogFolder('var/logs/');
$configurator = Configurator::initFromConfigFile(
    'config/config.json'
);
$generator = $configurator->getGenerator();
date_default_timezone_set('Europe/Paris');
if(!isset($argv[1]) || $argv[1] !== "skip-generate") {
    $generator->generateEpg();
}
$generator->exportEpg($configurator->getOutputPath());
$generator->clearCache($configurator->getCacheMaxDays());

//Logger::clearLog();
