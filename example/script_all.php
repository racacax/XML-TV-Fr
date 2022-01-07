<?php

require_once 'vendor/autoload.php';

use racacax\XmlTv\Component\Generator;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Configurator;

if(!file_exists('var/config.json')) {
    copy('resources/config/default_config.json', 'var/config.json');
}
if(!file_exists('var/channels.json')) {
    copy('resources/config/default_channels.json', 'var/channels.json');
}

//dd('move debugFile', 'move cache folder', 'change cache format', 'channel factory', 'clean cache', 'move tool folder', 'move output folder');

Logger::setLogLevel('debug');
$configurator = Configurator::initFromConfigFile(
    'var/config.json'
);
$generator = $configurator->getGenerator();
$generator->generateEpg();
$generator->exportEpg();

//$generator    = new Generator($configurator);

/*$generator->addChannels([
    'TF1.fr'=> [],
    'M6.fr'=> [],
]);*/

date_default_timezone_set('Europe/Paris');

dd($configurator);


