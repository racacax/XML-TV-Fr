<?php
/*
 * @version 1.0.0
 * @author racacax
 * @date 18/12/2021
 */

chdir(__DIR__);
require_once "classes/Utils.php";
define('XMLTVFR_SILENT', false);
loadConfig();

date_default_timezone_set('Europe/Paris');
set_time_limit(XMLTVFR_CONFIG["time_limit"]);
ini_set('memory_limit', XMLTVFR_CONFIG["memory_limit"]); // modify for resolve error Line173 : memory limit GZencode _ Ludis 20200729


if(!file_exists('channels.json'))
{
    if(!file_exists('channels_example.json')) {
        echo "\e[31m[ERREUR] \e[39mchannels.json manquant";
    } else {
        copy('channels_example.json', 'channels.json');
    }
}
foreach(XMLTVFR_CONFIG['guides_to_generate'] as $guide) {
    $xmlFile = $guide["filename"];
    $channelsFile = $guide['channels'];
    getChannelsEPG(getClasses(), $channelsFile);

    clearOldXML();

    moveOldXML($xmlFile);

    clearXMLCache();

    generateXML($channelsFile, $xmlFile);

    if (validateXML($xmlFile)) {
        reformatXML($xmlFile);

        if (XMLTVFR_CONFIG["enable_gz"]) {
            gzCompressXML($xmlFile);
        }

        if (XMLTVFR_CONFIG["enable_zip"]) {
            zipCompressXML($xmlFile);
        }
        if (XMLTVFR_CONFIG["enable_xz"]) {
            xzCompressXML($xmlFile);
        }


        if (XMLTVFR_CONFIG["delete_raw_xml"]) {
            echo "\e[34m[EXPORT] \e[39mSuppression du fichier XML brut ($xmlFile)\n";
            unlink(XMLTVFR_CONFIG["output_path"] . "/$xmlFile");
        }

    }
}

