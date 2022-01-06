<?php
class MainProgram {
    public static $customTextDisplayed = false;
    public static $currentOutput = null;
    public static $dummyEPG = "";
    public static $filesQueue = [];
}
function echoSilent($string) {
    if(MainProgram::$customTextDisplayed) {
        MainProgram::$customTextDisplayed = false;
        echoSilent("\r".MainProgram::$currentOutput);
    }
    MainProgram::$currentOutput = $string;
    if(!XMLTVFR_SILENT)
        echo $string;
}
function displayTextOnCurrentLine($str) {
    MainProgram::$customTextDisplayed = true;
    if(!XMLTVFR_SILENT)
        echo "\r".MainProgram::$currentOutput.$str;
}
function generateFilePath($channel,$date)
{
    return XMLTVFR_XML_PATH.$channel."_".$date.".xml";
}

function reformatXML($file) {
    echoSilent("\e[34m[EXPORT] \e[39mReformatage du XML... ($file)\n");
    $domxml = new DOMDocument('1.0');
    $domxml->preserveWhiteSpace = false;
    $domxml->formatOutput = true;
    /* @var $xml SimpleXMLElement */
    $domxml->loadXML(file_get_contents(XMLTVFR_CONFIG['output_path']."/$file"));
    $domxml->save(XMLTVFR_CONFIG['output_path']."/$file");
}
function validateXML($file) {
    echoSilent("\e[34m[EXPORT] \e[39mValidation du fichier XML...\n");
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_file(XMLTVFR_CONFIG['output_path'] . '/'.$file);
    if($xml === false) {
        echoSilent("\e[34m[EXPORT] \e[31mXML non valide\e[39m ($file)\n");
        foreach (libxml_get_errors() as $error) {
            echo "\t", $error->message;
        }
        libxml_clear_errors();
        return false;
    } else {
        echoSilent("\e[34m[EXPORT] \e[32mXML valide\e[39m ($file)\n");
        return true;
    }
}

function gzCompressXML($file) {
    echoSilent("\e[34m[EXPORT] \e[39mCompression du XMLTV en GZ... ($file)\n");
    $got = file_get_contents(XMLTVFR_CONFIG['output_path']."/$file");
    $got1 = gzencode($got,true);
    file_put_contents(XMLTVFR_CONFIG['output_path']."/$file.gz",$got1);
    echoSilent("\e[34m[EXPORT] \e[39mGZ : \e[32mOK\e[39m ($file)\n");
}
function xzCompressXML($file) {
    if(!isset(XMLTVFR_CONFIG['7zip_path'])) {
        echoSilent("\e[34m[EXPORT] \e[31mImpossible d'exporter en XZ (chemin de 7zip non défini)\e[39m ($file)\n");
    }
    echoSilent("\e[34m[EXPORT] \e[39mCompression du XMLTV en XZ... ($file)\n");
    $filenameSplited = explode('.', $file)[0];
    $filename = XMLTVFR_CONFIG['output_path']."/$filenameSplited.xz";
    echoSilent("\e[34m[EXPORT] \e[39mRéponse de 7zip : ".exec('"'.XMLTVFR_CONFIG['7zip_path'].'" a -t7z "'.$filename.'" "'.XMLTVFR_CONFIG['output_path']."/$file".'"'));
}

function zipCompressXML($file) {
    echoSilent("\e[34m[EXPORT] \e[39mCompression du XMLTV en ZIP... ($file)\n");
    $zip = new ZipArchive();
    $filenameSplited = explode('.', $file)[0];
    $filename = XMLTVFR_CONFIG['output_path']."/$filenameSplited.zip";

    if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
        echoSilent("\e[34m[EXPORT] \e[39mZIP : \e[31mHS\e[39m ($file)\n");
    } else {
        echoSilent("\e[34m[EXPORT] \e[39mZIP : \e[32mOK\e[39m ($file)\n");
    }
    $zip->addFile(XMLTVFR_CONFIG['output_path']."/$file", "xmltv.xml");
    $zip->close();

}

function getChannelsEPG($classes_priotity, $file) {
    echoSilent("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes ($file)\n");
    $logs = array('channels'=>array(), 'xml'=>array(),'failed_providers'=>array());
    $channels = json_decode(file_get_contents($file),true);
    MainProgram::$filesQueue = [];
    $channelsKeys = array_keys($channels);
    foreach($channelsKeys as $channel)
    {
        if(isset($channels[$channel]["priority"]) && count($channels[$channel]["priority"]) > 0)
        {
            $priority = $channels[$channel]['priority'];
        } else {
            $priority = $classes_priotity;
        }
        for($i=-1;$i<XMLTVFR_CONFIG["days"];$i++)
        {
            $date = date('Y-m-d',time()+86400*$i);
            echoSilent("\e[95m[EPG GRAB] \e[39m".$channel." : ".$date);
            $file = generateFilePath($channel,$date);
            if($date == date('Y-m-d') && XMLTVFR_CONFIG['force_todays_grab'] && (strtotime("now") - @intval(filemtime($file))) > 42800)
                @unlink($file);
            if(!file_exists($file)) {
                $success = false;
                foreach ($priority as $classe) {
                    if(!class_exists($classe))
                        break;
                    if(!isset(${XMLTVFR_CLASS_PREFIX.$classe}))
                        ${XMLTVFR_CLASS_PREFIX.$classe} = new $classe(XMLTVFR_XML_PATH);
                    $old_zone = date_default_timezone_get();
                    if(${XMLTVFR_CLASS_PREFIX.$classe}->constructEPG($channel,$date))
                    {
                        $logs["channels"][$date][$channel]['success'] = true;
                        echoSilent(" | \e[32mOK\e[39m - ".$classe.chr(10));
                        $logs["channels"][$date][$channel]['provider'] = $classe;
                        $logs["channels"][$date][$channel]['cache'] = false;
                        date_default_timezone_set($old_zone);
                        MainProgram::$filesQueue[] = $file;
                        break;
                    } else {
                        date_default_timezone_set($old_zone);
                    }
                    if(XMLTVFR_CONFIG['enable_dummy'])
                        MainProgram::$dummyEPG .= createDummyEPG($channel, $date);
                    $logs["channels"][$date][$channel]['failed_providers'][] = $classe;
                    $logs["channels"][$date][$channel]['success'] = false;
                    $logs["failed_providers"][$classe] = true;
                }
                if(!$logs["channels"][$date][$channel]['success'])
                    echoSilent(" | \e[31mHS\e[39m".chr(10));
            } else {
                $provider = getProviderFromComment($file);
                $logs["channels"][$date][$channel]['provider'] = $provider;
                echoSilent(" | \e[33mOK \e[39m- $provider (Cache)".chr(10));
                $logs["channels"][$date][$channel]['success'] = true;
                $logs["channels"][$date][$channel]['cache'] = true;
                MainProgram::$filesQueue[] = $file;

            }
        }
    }
    echoSilent("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes terminée...\n");
    $log_path = 'logs/logs'.date('YmdHis').'.json';
    echoSilent("\e[36m[LOGS] \e[39m Export des logs vers $log_path\n");
    file_put_contents($log_path,json_encode($logs));
}

function clearOldXML() {
    $xmltv = glob(XMLTVFR_CONFIG['output_path'].'/xmltv*');
    foreach($xmltv as $file)
    {
        if(time()-filemtime($file) >= 86400*XMLTVFR_CONFIG["xml_cache_days"])
            unlink($file);

    }
}

function getProviderFromComment($file) {
    return @trim(explode('-->', explode('<!--', file_get_contents($file))[1])[0]);
}

function moveOldXML($xmlFile) {
    $splitedFile = explode('.', $xmlFile)[0];
    foreach(["xz", "xml","zip","xml.gz"] as $ext) {

        if(file_exists(XMLTVFR_CONFIG['output_path']."/$splitedFile.$ext"))
        {
            rename(XMLTVFR_CONFIG['output_path']."/$splitedFile.$ext",XMLTVFR_CONFIG['output_path']."/{$splitedFile}_".date('Y-m-d_H-i-s',filemtime(XMLTVFR_CONFIG['output_path']."/$splitedFile.$ext")).".$ext");
        }
    }
}

function clearXMLCache() {
    $files = glob(XMLTVFR_XML_PATH.'*');
    foreach($files as $file){
        if(time()-filemtime($file) >= 86400 * XMLTVFR_CONFIG['cache_max_days'])
            unlink($file);
    }
}

function getDefaultChannelsInfos() {
    return json_decode(file_get_contents('resources/default_channels_infos.json'),true);

}

function generateXML($channelsFile, $xmlFile) {

    echoSilent("\e[34m[EXPORT] \e[39mGénération du XML... ($xmlFile)\n");
    $channels = json_decode(file_get_contents($channelsFile),true);
    $defaultChannelsInfos = getDefaultChannelsInfos();
    $filepath = XMLTVFR_CONFIG['output_path']."/$xmlFile";
    $out = fopen($filepath, "w");
    fwrite($out,'<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE tv SYSTEM "xmltv.dtd">
<!-- Generated with XML TV Fr v'.XMLTVFR_VERSION.' -->
<tv source-info-url="https://github.com/racacax/XML-TV-Fr" source-info-name="XML TV Fr" generator-info-name="XML TV Fr" generator-info-url="https://github.com/racacax/XML-TV-Fr">
  ');
    foreach($channels as $key => $channel)
    {
        @$icon = $channel['icon'];
        if(empty($icon))
            $icon = @$defaultChannelsInfos[$key]['icon'];
        @$name = $channel['name'];
        if(empty($name))
            $name = @$defaultChannelsInfos[$key]['name'];
        if(!isset($name))
            $name = $key;
        fwrite($out,'<channel id="'.$key.'">
    <display-name>'.stringAsXML($name).'</display-name>
    '.(!(empty($icon)) ? '<icon src="'.stringAsXML($icon).'" />' : '').'
  </channel>'.chr(10));
    }
    $files = MainProgram::$filesQueue;
    foreach($files as $file){
        $in = fopen($file, "r");
        while ($line = fgets($in)){
            fwrite($out, $line);
        }
        fclose($in);
    }
    fwrite($out, MainProgram::$dummyEPG);
    fwrite($out,'</tv>');
    fclose($out);

    echoSilent("\e[34m[EXPORT] \e[39mGénération du XML terminée ($xmlFile)\n");
}

function loadConfig() {
    $CONFIG = array( # /!\ Default configuration. Edit your config in config.json
        "days"=>8, # Number of days XML TV Fr will try to get EPG
        "output_path"=>"./xmltv", # Where xmltv files are stored
        "time_limit"=> 0, # time limit for the EPG grab (0 = unlimited)
        "memory_limit"=> -1, # memory limit for the EPG grab (-1 = unlimited)
        "cache_max_days"=>8, # after how many days do we clear cache (0 = no cache)
        "delete_raw_xml" => false, # delete xmltv.xml after EPG grab (if you want to provide only compressed XMLTV)
        "enable_gz" => true, # enable gz compression for the XMLTV
        "enable_zip" => true, # enable zip compression for the XMLTV,
        "enable_xz" => false, # enable XZ compression for the XMLTV (need 7zip),
        "xml_cache_days" => 5, # How many days old XML are stored
        "enable_dummy" => false, # Add a dummy EPG if channel not found
        "custom_priority_orders" => [], # Add a custom priority order for a provider globally,
        "guides_to_generate" => [array("channels"=>"./channels.json", "filename"=>"xmltv.xml")], # list of xmltv to generate
        "7zip_path"=> null, # path of 7zip binary,
        "force_todays_grab"=>false # ignore cache for today
    );


    echoSilent("\e[36m[CHARGEMENT] \e[39mChargement du fichier de config\n");
    if(!file_exists('config.json') & file_exists('config_example.json')) {
        echoSilent("\e[36m[CHARGEMENT] \e[33mFichier config.json absent, copie de config_example.json\e[39m\n");
        copy('config_example.json', 'config.json');
    }

    echoSilent("\e[36m[CHARGEMENT] \e[39mListe des paramètres : ");
    $json = json_decode(file_get_contents('config.json'),true);
    foreach ($json as $key => $value) {
        $CONFIG[$key] = $value;
        if(is_array($value)) {
            $value = json_encode($value);
        }
        echoSilent("\e[95m($key) \e[39m=> \e[33m$value\e[39m, ");
    }
    define('XMLTVFR_CONFIG', $CONFIG);

    define('XMLTVFR_NON_PROVIDER_CLASES',["Provider", "Utils", "Program", "Channel", "AbstractProvider"]);
    define('XMLTVFR_XML_PATH',"channels/");
    define('XMLTVFR_CLASS_PREFIX',"EPG_");
    define('XMLTVFR_VERSION', "1.5.1");
    echoSilent("\n");
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

function getClasses() {
    $classes = glob('classes/*.php');
    $classes_priotity = array();
    echoSilent("\e[36m[CHARGEMENT] \e[39mOrganisation des classes de Provider \n");
    foreach($classes as $classe) {
        require_once $classe;
        $class_name = explode('/',explode('.php',$classe)[0]);
        $class_name = $class_name[count($class_name)-1];
        if(class_exists($class_name) && !in_array($class_name, XMLTVFR_NON_PROVIDER_CLASES))
        {
            if(method_exists(new $class_name(),'getPriority' ) && method_exists(new $class_name(),'constructEPG' ))
                $classes_priotity[] = $class_name;
        }
    }

    usort($classes_priotity,"compare_classe");
    return $classes_priotity;
}


function stringAsXML($string) {
    return str_replace('"','&quot;',htmlspecialchars($string, ENT_XML1));
}

function createDummyEPG($channel, $date) {
    $channelObj = new Channel($channel, $date, "Dummy");
    for($i=0; $i<12; $i++) {
        $time = strtotime($date)+$i*2*3600;
        $program = $channelObj->addProgram($time, $time + 2 * 3600);
        $program->addTitle("Aucun programme");
    }
    return $channelObj->toString();
}