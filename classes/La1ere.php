<?php
require_once 'Provider.php';
require_once 'Utils.php';
class La1ere implements Provider
{
    private $XML_PATH;
    private static $CHANNELS_LIST;
    private static $CHANNELS_KEY;

    public static function getPriority()
    {
        return 0.3;
    }

    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
        if (!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_1ere.json")) {
            self::$CHANNELS_LIST = json_decode(file_get_contents("channels_per_provider/channels_1ere.json"), true);
            self::$CHANNELS_KEY = array_keys(self::$CHANNELS_LIST);
        }
    }

    function constructEPG($channel, $date)
    {
        if($date != date('Y-m-d')) {
            return false;
        }
        $old_zone = date_default_timezone_get();
        if(!in_array($channel,self::$CHANNELS_KEY))
        {
            return false;
        }
        date_default_timezone_set(self::$CHANNELS_LIST[$channel]["timezone"]);
        $channel_id = self::$CHANNELS_LIST[$channel]['id'];
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, "https://la1ere.francetvinfo.fr/$channel_id/emissions");
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0');
        $res1 = html_entity_decode(curl_exec($ch1),ENT_QUOTES);
        curl_close($ch1);
        $days = explode('<div class="guide">', $res1);
        $infos = [];
        unset($days[0]);
        array_values($days);
        foreach($days as $key => $day) {
            $programs = explode('</li>', $day);
            foreach($programs as $program) {
                preg_match('/\<span class=\"program-hour\".*?\>(.*?)\<\/span\>/',$program, $hour);
                preg_match('/\<span class=\"program-name\".*?\>(.*?)\<\/span\>/',$program, $name);
                preg_match('/\<div class=\"subtitle\".*?\>(.*?)\<\/div\>/',$program, $subtitle);
                if(isset($name[1])) {
                    $infos[] = array(
                        "hour" => date('YmdHis O', strtotime(date('Ymd', strtotime("now") + 86400 * $key) . ' ' . str_replace('H',':',$hour[1]))),
                        "title" => $name[1],
                        "subtitle" => @$subtitle[1]
                    );
                }
            }
        }
        for($i=0; $i<count($infos)-1; $i++) {
            $fp = fopen(Utils::generateFilePath($this->XML_PATH,$channel,$date),"a");
            if(strlen($infos[$i+1]["subtitle"])>0) {
                $subtitle = '<sub-title lang="fr">'.$infos[$i+1]["subtitle"].'</sub-title>';
            } else {
                $subtitle = '';
            }
            fputs($fp,'<programme start="'.$infos[$i]["hour"].'" stop="'.$infos[$i+1]["hour"].'" channel="'.$channel.'">
	<title lang="fr">'.htmlspecialchars($infos[$i]["title"],ENT_XML1).'</title>
	'.$subtitle.'
	<desc lang="fr">Aucune description</desc>
	<category lang="fr">Inconnu</category>
</programme>
');

            fclose( $fp );
        }
        date_default_timezone_set($old_zone);
        return true;
    }
}