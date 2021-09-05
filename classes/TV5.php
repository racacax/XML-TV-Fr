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
    private static $TMP_PATH = "epg/";
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


        if(!file_exists(self::$TMP_PATH.'TV5'.base64_encode($channel).$date.'.json'))
        {
            $start = date('Y-m-d')."T00:00:00";
            $end = date('Y-m-d', strtotime("+1 day"))."T00:00:00";
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
            if(@isset($json['data'][0]))
            {
                file_put_contents(self::$TMP_PATH.'TV5'.base64_encode($channel).$date.'.json',$res1);
            }
        } else { $res1 = file_get_contents(self::$TMP_PATH.'TV5'.base64_encode($channel).$date.'.json');
            $json = json_decode($res1,true);}
        $fp = fopen($xml_save,"a");
        foreach($json["data"] as $val)
        {

            if(!isset($val["season"]))
            {
                fputs($fp,'<programme start="'.date('YmdHis O',strtotime($val['utcstart']."+00:00")).'" stop="'.date('YmdHis O',strtotime($val['utcend']."+00:00")).'" channel="'.$channel.'">
	<title lang="fr">'.htmlspecialchars($val["title"],ENT_XML1).'</title>
	<desc lang="fr">'.htmlspecialchars($val["description"],ENT_XML1).'</desc>
	<category lang="fr">'.htmlspecialchars($val["category"],ENT_XML1).'</category>
	<icon src="'.(!empty($val["image"])?''.htmlspecialchars($val["image"],ENT_XML1):'').'" />
</programme>
');
            } else {
                if($val["season"] =="") { $val["season"] ='1';} if($val["episode"] =="") { $val["episode"] ='1';}
                fputs($fp,'<programme start="'.date('YmdHis O',strtotime($val['utcstart']."+00:00")).'" stop="'.date('YmdHis O',strtotime($val['utcend']."+00:00")).'" channel="'.$channel.'">
	<title lang="fr">'.htmlspecialchars($val["title"],ENT_XML1).'</title>
	<sub-title lang="fr">'.htmlspecialchars($val["episode_name"],ENT_XML1).'</sub-title>
	<episode-num system="xmltv_ns">'.@($val["season"]-1).'.'.@($val["episode"]-1).'.</episode-num>
	<desc lang="fr">'.htmlspecialchars($val["description"],ENT_XML1).'</desc>
	<category lang="fr">'.htmlspecialchars($val["category"],ENT_XML1).'</category>
	<icon src="'.(!empty($val["image"])?''.htmlspecialchars($val["image"],ENT_XML1):'').'" />
</programme>
');
            }


        }
        fclose( $fp );
        return true;
    }


}
