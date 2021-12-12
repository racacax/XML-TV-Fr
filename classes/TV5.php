<?php
/*
 * @author Racacax
 * @version 0.1 : 05/09/2021
 */
require_once 'Provider.php';
require_once 'Utils.php';
class TV5 implements Provider
{
    private $XML_PATH;
    private static $CHANNELS_LIST;
    private static $CHANNELS_KEY;

    public static function getPriority()
    {
        return 0.6;
    }
    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
        if(!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_tv5.json"))
        {
            self::$CHANNELS_LIST  = json_decode(file_get_contents("channels_per_provider/channels_tv5.json"), true);
            self::$CHANNELS_KEY = array_keys(self::$CHANNELS_LIST);
        }
    }

    public function constructEPG($channel,$date)
    {
        $xml_save = Utils::generateFilePath($this->XML_PATH, $channel, $date);
        if (file_exists($xml_save))
            unlink($xml_save);

        if (!in_array($channel, self::$CHANNELS_KEY))
            return false;
        $channel_id = self::$CHANNELS_LIST[$channel];


        $start = date('Y-m-d', strtotime($date))."T00:00:00";
        $end = date('Y-m-d', strtotime($date) + 86400)."T00:00:00";
        $url = 'https://bo-apac.tv5monde.com/tvschedule/full?start='.$start.'&end='.$end.'&key='.$channel_id.'&timezone=Europe/Paris&language=EN';
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, $url);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch1, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0");
        $res1 = curl_exec($ch1);
        curl_close($ch1);
        $json = json_decode($res1,true);
        if(!@isset($json['data'][0]))
        {
            return false;
        }
        $channel_obj = new Channel($channel, $xml_save);
        foreach($json["data"] as $val)
        {
            $program = $channel_obj->addProgram(strtotime($val['utcstart']."+00:00"), strtotime($val['utcend']."+00:00"));
            $program->addTitle($val["title"]);
            $program->addDesc((!empty($val["description"])) ? $val["description"] : 'Pas de description');
            $program->addCategory($val["category"]);
            $program->setIcon(!empty($val["image"])?''.$val["image"]:'');
            if(isset($val["season"])) {
                if($val["season"] =="") { $val["season"] ='1';} if($val["episode"] =="") { $val["episode"] ='1';}
                $program->addSubtitle($val["episode_name"]);
                $program->setEpisodeNum($val["season"], $val["episode"]);
            }


        }
        $channel_obj->save();
        return true;
    }


}
