<?php
require_once 'Provider.php';
require_once 'Utils.php';
class PlayTV implements Provider
{
    private $XML_PATH;
    private static $TMP_PATH = "epg/playtv/";
    private static $CHANNELS_LIST;
    private static $CHANNELS_KEY;

    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
        if(!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_playtv.json"))
        {
            self::$CHANNELS_LIST  = json_decode(file_get_contents("channels_per_provider/channels_playtv.json"), true);
            self::$CHANNELS_KEY = array_keys(self::$CHANNELS_LIST);
        }
    }

    public static function getPriority()
    {
        return 0.9;
    }
    public function constructEPG($channel,$date)
    {
        if(!in_array($channel,self::$CHANNELS_KEY))
            return false;
        if (!file_exists(self::$TMP_PATH.'/playtv-' . $channel .'_'.$date)) {
            $url = 'http://m.playtv.fr/api/programmes/?channel_id=' . self::$CHANNELS_LIST[$channel] . '&date=' . $date . '&preset=daily';
            $ch1 = curl_init();
            curl_setopt($ch1, CURLOPT_URL, $url);
            curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch1, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0");
            $res1 = curl_exec($ch1);
            curl_close($ch1);
            $res1 = str_replace('assets\/images\/tv-default.svg', 'http://img.src.ca/ouglo/emission/480x270/findesemissions.jpg', $res1);
            $res2 = json_decode($res1, true);
            if(!isset($res2[0]))
                return false;
            $b = count($res2) - 1;
            $start = $res2[$b]["start"];
            $end = $res2[$b]["end"];
            if (date('H', $start) > 10 && date('H', $end) >= 0 && $b > 3) {
                file_put_contents(self::$TMP_PATH.'/playtv-' . $channel .'_'.$date, $res1);
            } else {
                return false;
            }
        } else {
            $res1 = file_get_contents(self::$TMP_PATH.'/playtv-' . $channel .'_'.$date);
        }
        $res1 = json_decode($res1, true);
        $xml_save = Utils::generateFilePath($this->XML_PATH,$channel,$date);
        if(file_exists( $xml_save))
            unlink( $xml_save);
        foreach ($res1 as $val) {
                $ns = '';
                $season = '';
                $de = '';
                if (isset($val["program"]["episode"])) {
                    if ($val["program"]["season"] == "") {
                        $val["program"]["season"] = '1';
                    }
                    $de = ' : ';
                    $season = 'Saison ' . $val["program"]["season"] . ' Episode ' . $val["program"]["episode"];
                    $ns = chr(10) . '	<episode-num system="xmltv_ns">' . ($val["program"]["season"] - 1) . '.' . ($val["program"]["episode"] - 1) . '.</episode-num>';
                }
                $csa = 'TP';
                if ($val["program"]["csa_id"] == "2") {
                    $csa = '-10';
                }
                if ($val["program"]["csa_id"] == "3") {
                    $csa = '-12';
                }
                if ($val["program"]["csa_id"] == "4") {
                    $csa = '-16';
                }
                if ($val["program"]["csa_id"] == "5") {
                    $csa = '-18';
                }
                $subtitle = '';
                if ($val["program"]["subtitle"]) {
                    $season = $season . $de . $val["program"]["subtitle"];
                    $subtitle = chr(10) . '	<sub-title lang="fr">' . htmlspecialchars($val["program"]["subtitle"], ENT_XML1) . '</sub-title>';
                }
                $subcat = '';
                if (strlen($season) > 2) {
                    $season = $season . chr(10);
                }
                if ($val["program"]["subgender"]) {
                    $subcat = chr(10) . '	<category lang="fr">' . htmlspecialchars($val["program"]["subgender"], ENT_XML1) . '</category>';
                }
                $fp = fopen($xml_save, "a");
                fputs($fp, '<programme start="' . date('YmdHis O', $val["start"]) . '" stop="' . date('YmdHis O', $val["end"]) . '" channel="' . $channel . '">
	<title lang="fr">' . htmlspecialchars($val["program"]["title"], ENT_XML1) . '</title>' . $subtitle . '
	<desc lang="fr">' . htmlspecialchars($season . $val["program"]["summary_long"], ENT_XML1) . '</desc>
	<category lang="fr">' . htmlspecialchars($val["program"]["gender"], ENT_XML1) . '</category>' . $subcat . '
	<icon src="' . htmlspecialchars($val["program"]["images"]["xlarge"], ENT_XML1) . '" />
	<year>' . htmlspecialchars($val["program"]["year"], ENT_XML1) . '</year>
	<rating system="csa">
      <value>' . $csa . '</value>
    </rating>' . $ns . '
</programme>
');
                fclose($fp);
        }
        return true;
    }

}