<?php
require_once 'Provider.php';
require_once 'Utils.php';
class Afrique implements Provider
{
    private $XML_PATH;
    private static $TMP_PATH = "epg/afrique/";
    private static $CHANNELS_LIST;
    private static $CHANNELS_KEY;

    public static function getPriority()
    {
        return 0.2;
    }

    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
        if (!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_afrique.json")) {
            self::$CHANNELS_LIST = json_decode(file_get_contents("channels_per_provider/channels_afrique.json"), true);
            self::$CHANNELS_KEY = array_keys(self::$CHANNELS_LIST);
        }
    }

    function constructEPG($channel, $date)
    {
        if(!in_array($channel,self::$CHANNELS_KEY))
            return false;
        $day = (strtotime($date) - strtotime(date('Y-m-d')))/86400;
        if (!file_exists(self::$TMP_PATH . $channel."_".$date.'.json')) {
            $ch3 = curl_init();
            curl_setopt($ch3, CURLOPT_URL, 'https://service.canal-overseas.com/ott-frontend/vector/83001/channel/' . self::$CHANNELS_LIST[$channel] . '/events?filter.day=' . $day);
            curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch3, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0");
            curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 1);
            $res3 = curl_exec($ch3);
            curl_close($ch3);
            $res2 = json_decode($res3,true);
            if(!isset($res2['timeSlices']))
                return false;
            file_put_contents(self::$TMP_PATH . $channel."_".$date.'.json', $res3);
        } else {
            $res3 = file_get_contents(self::$TMP_PATH . $channel."_".$date.'.json');
        }
        $xml_save = Utils::generateFilePath($this->XML_PATH,$channel,$date);
        if(file_exists($xml_save))
            unlink($xml_save);
        $res3 = json_decode($res3, true);
        $json = $res3["timeSlices"];
        $count = 0;
        foreach ($json as $section) {
            foreach ($section["contents"] as $section2) {
                $count++;
                $fp = fopen($xml_save, "a");
                fputs($fp, '<programme start="' . date('YmdHis O', ($section2["startTime"])) . '" stop="' . date('YmdHis O', $section2["endTime"]) . '" channel="' . $channel . '">
	<title lang="fr">' . htmlspecialchars($section2["title"], ENT_XML1) . '</title>
	<desc lang="fr">Aucune description</desc>
	<category lang="fr">Inconnu</category>
	<icon src="' . htmlspecialchars($section2["URLImage"], ENT_XML1) . '" />
</programme>
');
                fclose($fp);
            }
        }
        if($count < 2)
            return false;
        return true;
    }
}