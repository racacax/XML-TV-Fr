<?php
/*
 * @author Racacax
 * @version 0.1 : 16/02/2020
 */
require_once 'Provider.php';
require_once 'Utils.php';
class Orange implements Provider
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
        if(!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_orange.json"))
        {
            self::$CHANNELS_LIST  = json_decode(file_get_contents("channels_per_provider/channels_orange.json"), true);
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


        if(!file_exists(self::$TMP_PATH.'Orange'.base64_encode($channel).$date.'.json'))
        {
            $url = 'https://rp-live.orange.fr/live-webapp/v3/applications/STB4PC/programs?period='.$date.'&epgIds='.$channel_id.'&mco=OFR';
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, $url);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch1, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0");
            $res1 = curl_exec($ch1);
            curl_close($ch1);
            $json = json_decode($res1,true);
            if(!preg_match('(Invalid request)',$res1) && !preg_match('(504 Gateway Time-out)',$res1) && isset($json))
            {
                file_put_contents(self::$TMP_PATH.'Orange'.base64_encode($channel).$date.'.json',$res1);
            }
        } else { $res1 = file_get_contents(self::$TMP_PATH.'Orange'.base64_encode($channel).$date.'.json');
            $json = json_decode($res1,true);}
        $fp = fopen($xml_save,"a");
        foreach($json as $val)
        {
            if($val["csa"] == "1") { $csa = 'TP'; } if($val["csa"] == "2") { $csa = '-10'; } if($val["csa"] == "3") { $csa = '-12'; } if($val["csa"] == "4") { $csa = '-16'; } if($val["csa"] == "5") { $csa = '-18'; }

            if(!isset($val["season"]))
            {
                fputs($fp,'<programme start="'.date('YmdHis O',$val["diffusionDate"]).'" stop="'.date('YmdHis O',$val["diffusionDate"]+$val["duration"]).'" channel="'.$channel.'">
	<title lang="fr">'.htmlspecialchars($val["title"],ENT_XML1).'</title>
	<desc lang="fr">'.htmlspecialchars($val["synopsis"],ENT_XML1).'</desc>
	<category lang="fr">'.htmlspecialchars($val["genre"],ENT_XML1).'</category>
	<category lang="fr">'.htmlspecialchars($val["genreDetailed"],ENT_XML1).'</category>
	<icon src="'.(!empty($val["covers"])?''.htmlspecialchars(end($val["covers"])["url"],ENT_XML1):'').'" />
	<rating system="csa">
      <value>'.htmlspecialchars($csa,ENT_XML1).'</value>
    </rating>
</programme>
');
            } else {
                if($val["season"]["number"] =="") { $val["season"]["number"] ='1';} if($val["episodeNumber"] =="") { $val["episodeNumber"] ='1';}
                fputs($fp,'<programme start="'.date('YmdHis O',$val["diffusionDate"]).'" stop="'.date('YmdHis O',$val["diffusionDate"]+$val["duration"]).'" channel="'.$channel.'">
	<title lang="fr">'.htmlspecialchars($val["season"]["serie"]["title"],ENT_XML1).'</title>
	<sub-title lang="fr">'.htmlspecialchars($val["title"],ENT_XML1).'</sub-title>
	<episode-num system="xmltv_ns">'.($val["season"]["number"]-1).'.'.($val["episodeNumber"]-1).'.</episode-num>
	<desc lang="fr">'.htmlspecialchars($val["synopsis"],ENT_XML1).'</desc>
	<category lang="fr">'.htmlspecialchars($val["genre"],ENT_XML1).'</category>
	<category lang="fr">'.htmlspecialchars($val["genreDetailed"],ENT_XML1).'</category>
	<icon src="'.(!empty($val["covers"])?''.htmlspecialchars(end($val["covers"])["url"],ENT_XML1):'').'" />
	<rating system="csa">
      <value>'.htmlspecialchars($csa,ENT_XML1).'</value>
    </rating>
</programme>
');
            }


        }
        fclose( $fp );
        return true;
    }


}
