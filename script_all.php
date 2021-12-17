<?php
/*
 * @version 0.4.0
 * @author racacax
 * @date 16/12/2021
 */

chdir(__DIR__);
require_once "classes/Utils.php";
define('SILENT', false);
Utils::loadConfig();

date_default_timezone_set('Europe/Paris');
set_time_limit(CONFIG["time_limit"]);
ini_set('memory_limit', CONFIG["memory_limit"]); // modify for resolve error Line173 : memory limit GZencode _ Ludis 20200729


if(!file_exists('channels.json'))
{
    if(!file_exists('channels_example.json')) {
        echo "\e[31m[ERREUR] \e[39mchannels.json manquant";
    } else {
        copy('channels_example.json', 'channels.json');
    }
}

Utils::getChannelsEPG(Utils::getClasses());

Utils::clearOldXML();

Utils::moveOldXML();

Utils::clearXMLCache();

Utils::generateXML();

if(Utils::validateXML()) {
    Utils::reformatXML();

    if (CONFIG["enable_gz"]) {
        Utils::gzCompressXML();
    }

    if (CONFIG["enable_zip"]) {
        Utils::zipCompressXML();
    }

    if (CONFIG["delete_raw_xml"]) {
        echo "\e[34m[EXPORT] \e[39mSuppression du fichier XML brut\n";
        unlink(CONFIG["output_path"] . "/xmltv.xml");
    }
}

