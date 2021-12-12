<?php
require_once 'Provider.php';
require_once 'Utils.php';
class Afrique implements Provider
{
    private $XML_PATH;
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
        $xml_save = Utils::generateFilePath($this->XML_PATH,$channel,$date);
        if(file_exists($xml_save))
            unlink($xml_save);
        $res3 = json_decode($res3, true);
        $json = $res3["timeSlices"];
        $count = 0;
        $channel_obj = new Channel($channel, $xml_save);
        foreach ($json as $section) {
            foreach ($section["contents"] as $section2) {
                $count++;
                $program = $channel_obj->addProgram($section2["startTime"], $section2["endTime"]);
                $program->addTitle($section2["title"]);
                $program->addDesc("Aucune description");
                $program->addCategory("Inconnu");
                $program->setIcon($section2["URLImage"]);
            }
        }
        $channel_obj->save();
        if($count < 2)
            return false;
        return true;
    }
}