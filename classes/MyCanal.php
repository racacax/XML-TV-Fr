<?php
require_once 'Provider.php';
require_once 'Utils.php';
class MyCanal implements Provider
{
    private $XML_PATH;
    private static $CHANNELS_LIST;
    private static $CHANNELS_KEY;
    private static $PREVIOUS_SEGMENTS;

    public static function getPriority()
    {
        return 0.2;
    }

    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
        if (!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_mycanal.json")) {
            self::$CHANNELS_LIST = json_decode(file_get_contents("channels_per_provider/channels_mycanal.json"), true);
            self::$CHANNELS_KEY = array_keys(self::$CHANNELS_LIST);
        }
        if(!isset(self::$PREVIOUS_SEGMENTS)) {
            self::$PREVIOUS_SEGMENTS = array();
        }
    }

    function constructEPG($channel, $date)
    {
        if(!in_array($channel,self::$CHANNELS_KEY))
            return false;
        $day = (strtotime($date) - strtotime(date('Y-m-d')))/86400;
        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_URL, 'https://hodor.canalplus.pro/api/v2/mycanal/channels/b4c8b468c73dff714ba07307b8266833/'.self::$CHANNELS_LIST[$channel].'/broadcasts/day/'.($day));
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch3, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0");
        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 1);
        $res3 = curl_exec($ch3);
        curl_close($ch3);
        $res3 = str_replace('{resolutionXY}', '300x200', $res3);
        $res3 = str_replace('{imageQualityPercentage}', '80', $res3);
        $res2 = json_decode($res3,true);
        if(!isset($res2['timeSlices']))
            return false;
        $xml_save = Utils::generateFilePath($this->XML_PATH,$channel,$date);
        if(file_exists($xml_save))
            unlink($xml_save);
        $res3 = json_decode($res3, true);
        $json = $res3["timeSlices"];
        if(isset(self::$PREVIOUS_SEGMENTS[$channel]))
            $previous = self::$PREVIOUS_SEGMENTS[$channel];
        $count = 0;
        $channel_obj = new Channel($channel, $xml_save);
        foreach ($json as $section) {
            foreach ($section["contents"] as $section2) {
                if(isset($previous)) {
                    $program = $channel_obj->addProgram($previous["startTime"] / 1000, $section2["startTime"] / 1000);
                    $program->addTitle($previous["title"]." - ".@$previous["subtitle"]);
                    $program->addDesc("Aucune description");
                    $program->addCategory("Inconnu");
                    $program->setIcon(@$previous["URLImage"]);
                }
                $count ++;
                $previous = $section2;
            }
        }
        if(isset($previous))
            self::$PREVIOUS_SEGMENTS[$channel] = $previous;
        $channel_obj->save();
        if($count < 2)
            return false;
        return true;
    }
}