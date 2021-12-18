<?php
/*
 * @version 1.0.0
 * @author racacax
 * @date 18/12/2021
 */

chdir(__DIR__);
require_once "classes/Utils.php";
define('SILENT', false);
loadConfig();

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

getChannelsEPG(getClasses());

clearOldXML();

moveOldXML();

clearXMLCache();

generateXML();

if(validateXML()) {
    reformatXML();

    if (CONFIG["enable_gz"]) {
        gzCompressXML();
    }

    if (CONFIG["enable_zip"]) {
        zipCompressXML();
    }

    if (CONFIG["delete_raw_xml"]) {
        echo "\e[34m[EXPORT] \e[39mSuppression du fichier XML brut\n";
        unlink(CONFIG["output_path"] . "/xmltv.xml");
    }
}

