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

//dd('move debugFile', 'move cache folder', 'change cache format', 'channel factory');

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




/*
 * @version 1.0.0
 * @author racacax
 * @date 18/12/2021
 */

chdir(__DIR__);
require_once "classes/Utils.php";
define('SILENT', false);
loadConfig();



foreach(CONFIG['guides_to_generate'] as $guide) {
    $xmlFile = $guide["filename"];
    $channelsFile = $guide['channels'];
    getChannelsEPG(getClasses(), $channelsFile);

    clearOldXML();

    moveOldXML($xmlFile);

    clearXMLCache();

    generateXML($channelsFile, $xmlFile);

    if (validateXML($xmlFile)) {
        reformatXML($xmlFile);

        if (CONFIG["enable_gz"]) {
            gzCompressXML($xmlFile);
        }

        if (CONFIG["enable_zip"]) {
            zipCompressXML($xmlFile);
        }
        if (CONFIG["enable_xz"]) {
            xzCompressXML($xmlFile);
        }


        if (CONFIG["delete_raw_xml"]) {
            echo "\e[34m[EXPORT] \e[39mSuppression du fichier XML brut ($xmlFile)\n";
            unlink(CONFIG["output_path"] . "/$xmlFile");
        }

    }
}

