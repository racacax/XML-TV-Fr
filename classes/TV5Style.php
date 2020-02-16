<?php
/*
 * @author : Racacax
 * @version : 0.1 - 16/02-2020
 */
require_once 'Provider.php';
require_once 'Utils.php';
class TV5Style implements Provider
{
    private $XML_PATH;

    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
    }

    public static function getPriority()
    {
        return 0.12;
    }

    function constructEPG($channel, $date)
    {
        if ($channel != "TV5Style")
            return false;
        if(file_exists("epg/tv5style_".date('Y-m-d')))
        {
            $res1 = file_get_contents("epg/tv5style_".date('Y-m-d'));
            $res1 = json_decode($res1, true);
            if(!isset($res1))
                return false;
        } else {
            $tableau = array();
            $url = 'https://asia.tv5monde.com/Style?lang=fr-FR';
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, $url);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch1, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0");
            $res1 = html_entity_decode(curl_exec($ch1), ENT_QUOTES);
            curl_close($ch1);
            $res1 = str_replace('&apos;', "'", $res1);
            $res1 = explode('var scheduleData = ', $res1)[1];
            $res1 = explode(' ; var', $res1)[0];
            $res2 = $res1;
            $res1 = json_decode($res1, true);

            if(!isset($res1))
                return false;
            file_put_contents("epg/tv5style_".date('Y-m-d'),$res2);
        }
        unset($res1[count($res1) - 1]);
        if(file_exists(Utils::generateFilePath($this->XML_PATH,$channel,$date)))
            unlink(Utils::generateFilePath($this->XML_PATH,$channel,$date));
        $fp = fopen(Utils::generateFilePath($this->XML_PATH,$channel,$date), "a");
        $success = false;
        foreach ($res1 as $tvsty) {
            if($date == date('Y-m-d', (strtotime($tvsty["ServerBroadcastTime"] . '+08:00'))))
            {
                fputs($fp, '<programme start="' . date('YmdHis O', (strtotime($tvsty["ServerBroadcastTime"] . '+08:00'))) . '" stop="' . date('YmdHis O', strtotime($tvsty["ServerBroadcastEndTime"] . '+08:00')) . '" channel="TV5Style">
	<title lang="fr">' . htmlspecialchars($tvsty['Title'], ENT_XML1) . '</title>
	<desc lang="fr">' . htmlspecialchars($tvsty['Description'], ENT_XML1) . '</desc>
	<category lang="fr">' . htmlspecialchars($tvsty['Category'], ENT_XML1) . '</category>
</programme>
');
                $success = true;
            }
        }
        fclose($fp);
        return $success;
    }
}