<?php
function echoSilent($string) {
    if(!SILENT)
        echo $string;
}
class Utils
{
    public static function generateFilePath($channel,$date)
    {
        return XML_PATH.$channel."_".$date.".xml";
    }

    public static function reformatXML() {
        echoSilent("\e[34m[EXPORT] \e[39mReformatage du XML...\n");
        $domxml = new DOMDocument('1.0');
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        /* @var $xml SimpleXMLElement */
        $domxml->loadXML(file_get_contents(CONFIG['output_path']."/xmltv.xml"));
        $domxml->save(CONFIG['output_path']."/xmltv.xml");
    }
    public static function validateXML() {
        echoSilent("\e[34m[EXPORT] \e[39mValidation du fichier XML...\n");
        @$xml = XMLReader::open(CONFIG['output_path']."/xmltv.xml");

        $xml->setParserProperty(XMLReader::VALIDATE, true);

        if($xml->isValid())
        {
            echoSilent("\e[34m[EXPORT] \e[32mXML valide\e[39m\n");
            return true;
        } else {
            echoSilent("\e[34m[EXPORT] \e[31mXML non valide\e[39m\n");
            return false;
        }
    }

    public static function gzCompressXML() {
        echoSilent("\e[34m[EXPORT] \e[39mCompression du XMLTV en GZ...\n");
        $got = file_get_contents(CONFIG['output_path'].'/xmltv.xml');
        $got1 = gzencode($got,true);
        file_put_contents(CONFIG['output_path'].'/xmltv.xml.gz',$got1);
        echoSilent("\e[34m[EXPORT] \e[39mGZ : \e[32mOK\e[39m\n");
    }

    public static function zipCompressXML() {
        echoSilent("\e[34m[EXPORT] \e[39mCompression du XMLTV en ZIP...\n");
        $zip = new ZipArchive();
        $filename = CONFIG['output_path']."/xmltv.zip";

        if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
            echoSilent("\e[34m[EXPORT] \e[39mZIP : \e[31mHS\e[39m\n");
        } else {
            echoSilent("\e[34m[EXPORT] \e[39mZIP : \e[32mOK\e[39m\n");
        }
        $zip->addFile(CONFIG['output_path']."/xmltv.xml", "xmltv.xml");
        $zip->close();

    }

    public static function getChannelsEPG($classes_priotity) {
        echoSilent("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes\n");
        $logs = array('channels'=>array(), 'xml'=>array(),'failed_providers'=>array());
        $channels = json_decode(file_get_contents('channels.json'),true);
        $channels_key = array_keys($channels);
        foreach($channels_key as $channel)
        {
            if(isset($channels[$channel]["priority"]) && count($channels[$channel]["priority"]) > 0)
            {
                $priority = $channels[$channel]['priority'];
            } else {
                $priority = $classes_priotity;
            }
            for($i=-1;$i<CONFIG["days"];$i++)
            {
                $date = date('Y-m-d',time()+86400*$i);
                echoSilent("\e[95m[EPG GRAB] \e[39m".$channel." : ".$date);
                $file = Utils::generateFilePath($channel,$date);
                if(!file_exists($file)) {
                    $success = false;
                    foreach ($priority as $classe) {
                        if(!class_exists($classe))
                            break;
                        if(!isset(${CLASS_PREFIX.$classe}))
                            ${CLASS_PREFIX.$classe} = new $classe(XML_PATH);
                        if(${CLASS_PREFIX.$classe}->constructEPG($channel,$date))
                        {
                            $logs["channels"][$date][$channel]['success'] = true;
                            echoSilent(" | \e[32mOK\e[39m - ".$classe.chr(10));
                            $logs["channels"][$date][$channel]['provider'] = $classe;
                            $logs["channels"][$date][$channel]['cache'] = false;
                            break;
                        }
                        $logs["channels"][$date][$channel]['failed_providers'][] = $classe;
                        $logs["channels"][$date][$channel]['success'] = false;
                        $logs["failed_providers"][$classe] = true;
                    }
                    if(!$logs["channels"][$date][$channel]['success'])
                        echoSilent(" | \e[31mHS\e[39m".chr(10));
                } else {
                    $provider = self::getProviderFromComment($file);
                    $logs["channels"][$date][$channel]['provider'] = $provider;
                    echoSilent(" | \e[33mOK \e[39m- $provider (Cache)".chr(10));
                    $logs["channels"][$date][$channel]['success'] = true;
                    $logs["channels"][$date][$channel]['cache'] = true;

                }
            }
        }
        echoSilent("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes terminée...\n");
        $log_path = 'logs/logs'.date('YmdHis').'.json';
        echoSilent("\e[36m[LOGS] \e[39m Export des logs vers $log_path\n");
        file_put_contents($log_path,json_encode($logs));
    }

    public static function clearOldXML() {
        $xmltv = glob(CONFIG['output_path'].'/xmltv*');
        foreach($xmltv as $file)
        {
            if(time()-filemtime($file) >= 86400*CONFIG["xml_cache_days"])
                unlink($file);

        }
    }

    public static function getProviderFromComment($file) {
        return @trim(explode('-->', explode('<!--', file_get_contents($file))[1])[0]);
    }

    public static function moveOldXML() {
        foreach(["xml","zip","xml.gz"] as $ext) {

            if(file_exists(CONFIG['output_path']."/xmltv.$ext"))
            {
                rename(CONFIG['output_path']."/xmltv.$ext",CONFIG['output_path']."/xmltv_".date('Y-m-d_H-i-s',filemtime(CONFIG['output_path']."/xmltv.$ext")).".$ext");
            }
        }
    }

    public static function clearXMLCache() {
        $files = glob(XML_PATH.'*');
        foreach($files as $file){
            if(time()-filemtime($file) >= 86400 * CONFIG['cache_max_days'])
                unlink($file);
        }
    }


    public static function generateXML() {

        echoSilent("\e[34m[EXPORT] \e[39mGénération du XML...\n");
        $channels = json_decode(file_get_contents('channels.json'),true);
        $filepath = CONFIG['output_path']."/xmltv.xml";
        $out = fopen($filepath, "w");
        fwrite($out,'<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE tv SYSTEM "xmltv.dtd">

<tv source-info-url="https://github.com/racacax/XML-TV-Fr" source-info-name="XML TV Fr" generator-info-name="XML TV Fr" generator-info-url="https://github.com/racacax/XML-TV-Fr">
  ');
        foreach($channels as $key => $channel)
        {
            @$icon = $channel['icon'];
            @$name = $channel['name'];
            if(!isset($name))
                $name = $key;
            fwrite($out,'<channel id="'.$key.'">
    <display-name>'.htmlspecialchars($name, ENT_XML1).'</display-name>
    <icon src="'.htmlspecialchars($icon, ENT_XML1).'" />
  </channel>'.chr(10));
        }
        $files = glob(XML_PATH.'*');
        foreach($files as $file){
            $in = fopen($file, "r");
            while ($line = fgets($in)){
                fwrite($out, $line);
            }
            fclose($in);
        }
        fwrite($out,'</tv>');
        fclose($out);

        echoSilent("\e[34m[EXPORT] \e[39mGénération du XML terminée\n");
    }

    public static function loadConfig() {
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


        echoSilent("\e[36m[CHARGEMENT] \e[39mChargement du fichier de config\n");
        if(!file_exists('config.json') & file_exists('config_example.json')) {
            echoSilent("\e[36m[CHARGEMENT] \e[33mFichier config.json absent, copie de config_example.json\e[39m\n");
            copy('config_example.json', 'config.json');
        }

        echoSilent("\e[36m[CHARGEMENT] \e[39mListe des paramètres : ");
        $json = json_decode(file_get_contents('config.json'),true);
        foreach ($json as $key => $value) {
            $CONFIG[$key] = $value;
            echoSilent("\e[95m($key) \e[39m=> \e[33m$value\e[39m, ");
        }
        define('CONFIG', $CONFIG);

        define('NON_PROVIDER_CLASES',["Provider", "Utils", "Program", "Channel", "AbstractProvider"]);
        define('XML_PATH',"channels/");
        define('CLASS_PREFIX',"EPG_");
        echoSilent("\n");
    }

    public static function getClasses() {
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
        $classes_priotity = array();
        echoSilent("\e[36m[CHARGEMENT] \e[39mOrganisation des classes de Provider \n");
        foreach($classes as $classe) {
            require_once $classe;
            $class_name = explode('/',explode('.php',$classe)[0]);
            $class_name = $class_name[count($class_name)-1];
            if(class_exists($class_name) && !in_array($class_name, NON_PROVIDER_CLASES))
            {
                if(method_exists(new $class_name(),'getPriority' ) && method_exists(new $class_name(),'constructEPG' ))
                    $classes_priotity[] = $class_name;
            }
        }

        usort($classes_priotity,"compare_classe");
        return $classes_priotity;
    }


}