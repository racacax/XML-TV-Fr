<?php
require_once 'Provider.php';
require_once 'Utils.php';
class TVHebdo implements Provider
{
    private $XML_PATH;
    private static $TMP_PATH = "epg/tv_hebdo_";
    private static $CHANNELS_LIST;
    private static $CHANNELS_KEY;

    public static function getPriority()
    {
        return 0.2;
    }

    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
        if (!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_tvhebdo.json")) {
            self::$CHANNELS_LIST = json_decode(file_get_contents("channels_per_provider/channels_tvhebdo.json"), true);
            self::$CHANNELS_KEY = array_keys(self::$CHANNELS_LIST);
        }
    }

    function constructEPG($channel, $date)
    {
        $old_zone = date_default_timezone_get();
        date_default_timezone_set('America/Montreal');
        if(!in_array($channel,self::$CHANNELS_KEY))
        {
            date_default_timezone_set($old_zone);
            return false;
        }
        if(!file_exists(self::$TMP_PATH . $channel."_".$date.'.json'))
        {
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, 'http://www.ekamali.com/index.php?q='.base64_encode('http://www.tvhebdo.com/horaire-tele/'.self::$CHANNELS_LIST[$channel].'/date/'.$date).'&hl=3ed');
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch1, CURLOPT_REFERER, 'http://www.ekamali.com/index.php');
            curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0');
//curl_setopt($ch1, CURLOPT_PROXY, '66.70.255.195:3128');
            $res1 = html_entity_decode(curl_exec($ch1),ENT_QUOTES);
            curl_close($ch1);
            @$res1 = explode('Mes<br>alertes courriel',$res1)[1];
            preg_match_all('/class="heure"\>(.*?)\<\/td\>/',$res1,$time);
            preg_match_all('/class="titre"\>(.*?)"\>(.*?)\<\/a\>/',$res1,$titre);
            $t8 = json_encode($time);
            $t9 = json_encode($titre);
            $t8 = $t8.'|||||||||||||||||||||||'.$t9;
            if(strlen($t8)>100)
            {
                file_put_contents(self::$TMP_PATH . $channel."_".$date.'.json',$t8);
            } else {
                date_default_timezone_set($old_zone);
                return false; }
        } else {
            $t8 = file_get_contents(self::$TMP_PATH . $channel."_".$date.'.json');
            $time = explode('|||||||||||||||||||||||',$t8)[0];
            $time = json_decode($time,true);
            $titre = explode('|||||||||||||||||||||||',$t8)[1];
            $titre = json_decode($titre,true);
        }

        for($j=0;$j<count($titre[2]);$j++)
        {
            $prgm[] = date('YmdHis O',strtotime($date.' '.$time[1][$j])).' || '.$titre[2][$j];
        }

        for($j=0;$j<count($prgm)-1;$j++)
        {
            $now = explode(' || ',$prgm[$j]);
            $after = explode(' || ',$prgm[$j+1])[0];
            $fp = fopen(Utils::generateFilePath($this->XML_PATH,$channel,$date),"a");
            $genre = 'Inconnu';
            $id = self::$CHANNELS_LIST[$channel];
            if($id == "rds/RDS" || $id == "rds2/RDS2" || $id == "ris/RDSI" || $id == "tvas/TVASP" || $id == "tvs2/TVS2") { $genre = 'Sport'; }
            fputs($fp,'<programme start="'.$now[0].'" stop="'.$after.'" channel="'.$channel.'">
	<title lang="fr">'.htmlspecialchars($now[1],ENT_XML1).'</title>
	<desc lang="fr">Aucune description</desc>
	<category lang="fr">'.$genre.'</category>
</programme>
');
            fclose( $fp );
        }
        date_default_timezone_set($old_zone);
        return true;
    }
}