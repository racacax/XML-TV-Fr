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
$params = array_slice($argv, 2);
if(!in_array("--skip-generation", $params)) {
    $generator->generate();
}
$generator->exportEpg($configurator->getOutputPath());
if(!in_array('--keep-cache', $params)) {
    $generator->clearCache($configurator->getCachePhysicalTTL());
}

//Logger::clearLog();
