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

//dd('move debugFile', 'move cache folder', 'change cache format', 'channel factory', 'clean cache', 'move tool folder', 'move output folder');

Logger::setLogLevel('debug');
Logger::setLogFolder('var/logs/');
$configurator = Configurator::initFromConfigFile(
    'config/config.json'
);
$generator = $configurator->getGenerator();
$generator->generateEpg();
$generator->exportEpg($configurator->getOutputPath());
$generator->clearCache($configurator->getCacheMaxDays());

//Logger::clearLog();
date_default_timezone_set('Europe/Paris');



