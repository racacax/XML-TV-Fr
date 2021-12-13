<?php
/*
 * @version 0.4.0
 * @author racacax
 * @date 12/12/2021
 */

$CONFIG = array( # /!\ Default configuration. Edit your config in config.json
    "days"=>8, # Number of days XML TV Fr will try to get EPG
    "output_path"=>"./xmltv", # Where xmltv files are stored
    "time_limit"=> 0, # time limit for the EPG grab (0 = unlimited)
    "memory_limit"=> -1, # memory limit for the EPG grab (-1 = unlimited)
    "cache_max_days"=>8, # after how many days do we clear cache (0 = no cache)
    "delete_raw_xml" => false, # delete xmltv.xml after EPG grab (if you want to provide only compressed XMLTV)
    "enable_gz" => true, # enable gz compression for the XMLTV
    "enable_zip" => true, # enable zip compression for the XMLTV,
    "xml_cache_days" => 5 # How many days old XML are stored
);


echo "\e[36m[CHARGEMENT] \e[39mChargement du fichier de config\n";
if(!file_exists('config.json') & file_exists('config_example.json')) {
    echo "\e[36m[CHARGEMENT] \e[33mFichier config.json absent, copie de config_example.json\e[39m\n";
    copy('config_example.json', 'config.json');
}

echo "\e[36m[CHARGEMENT] \e[39mListe des paramètres : ";
$json = json_decode(file_get_contents('config.json'),true);
foreach ($json as $key => $value) {
    $CONFIG[$key] = $value;
    echo "\e[95m($key) \e[39m=> \e[33m$value\e[39m, ";
}
define('CONFIG', $CONFIG);
echo "\n";

date_default_timezone_set('Europe/Paris');
set_time_limit(CONFIG["time_limit"]);
ini_set('memory_limit', CONFIG["memory_limit"]); // modify for resolve error Line173 : memory limit GZencode _ Ludis 20200729

if ( ! function_exists('glob_recursive'))
{
    // Does not support flag GLOB_BRACE
    function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
        {
            $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }
}

function compare_classe($a,$b)
{
    if(class_exists($a) && class_exists($b))
    {
        if(call_user_func($a. "::getPriority") > call_user_func($b. "::getPriority"))
            return -1;
        return 1;
    } else {
        return 0;
    }
}

$classes = glob('classes/*.php');
$NON_PROVIDER_CLASES = ["Provider", "Utils", "Program", "Channel", "AbstractProvider"];
$classes_priotity = array();
define('XML_PATH',"channels/");
define('CLASS_PREFIX',"EPG_");
echo "\e[36m[CHARGEMENT] \e[39mOrganisation des classes de Provider \n";
foreach($classes as $classe) {
    require_once $classe;
    $class_name = explode('/',explode('.php',$classe)[0]);
    $class_name = $class_name[count($class_name)-1];
    if(class_exists($class_name) && !in_array($class_name, $NON_PROVIDER_CLASES))
    {
        if(method_exists(new $class_name(),'getPriority' ) && method_exists(new $class_name(),'constructEPG' ))
            $classes_priotity[] = $class_name;
    }
}

usort($classes_priotity,"compare_classe");
if(!file_exists('channels.json'))
{
    if(!file_exists('channels_example.json')) {
        echo "\e[31m[ERREUR] \e[39mchannels.json manquant";
    } else {
        copy('channels_example.json', 'channels.json');
    }
}

Utils::getChannelsEPG($classes_priotity);

Utils::clearOldXML();

Utils::moveOldXML();

Utils::clearXMLCache();

Utils::generateXML();

Utils::clearEPGCache();

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

