<?php
require_once 'Provider.php';
require_once 'Utils.php';
class Tele7Jours implements Provider
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
        if(!isset(self::$CHANNELS_LIST) && file_exists("channels_per_provider/channels_tele7jours.json"))
        {
            self::$CHANNELS_LIST  = json_decode(file_get_contents("channels_per_provider/channels_tele7jours.json"), true);
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


        $pl = 0;
        $v = 0;
        for ($i = 1; $i <= 6; $i++) {
            if (!file_exists(self::$TMP_PATH . $channel . '_' . $date . "_t" . $i . ".json")) {
                $uu = curl_init("https://www.programme-television.org/grid/tranches/" . $channel_id . "_" . date('Ymd', strtotime($date)) . "_t" . $i . ".json");
                curl_setopt($uu, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
                curl_setopt($uu, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($uu, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($uu, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($uu, CURLOPT_FOLLOWLOCATION, true);
                $get = curl_exec($uu);
                curl_close($uu);
                $get = str_replace('$.la.t7.epg.grid.showDiffusions(', '', $get);
                $get = str_replace('127,101,', '', $get);
                $get = str_replace(');', '', $get);
                $get2 = $get;
                $get = json_decode($get, true);
                if (!isset($get))
                    return false;
                file_put_contents(self::$TMP_PATH . $channel . '_' . $date . "_t" . $i . ".json", $get2);

            } else {
                $get = file_get_contents(self::$TMP_PATH . $channel . '_' . $date . "_t" . $i . ".json");
                $get = json_decode($get, true);
            }

            $pop = 0;
            if(!isset($get["grille"]["aDiffusion"]))
                return false;
            foreach ($get["grille"]["aDiffusion"] as $val) {
                $h = $val["heureDif"];
                $h = str_replace('h', ':', $h);
                if ($h[0] . $h[1] < $v && $i == 6) {
                    $pl += 86400;
                }
                $v = $h[0] . $h[1];
                if (strlen($val["soustitre"]) > 2) {
                    $subtitle = chr(10) . '	<sub-title>' . htmlspecialchars($val["soustitre"], ENT_XML1) . '</sub-title>';
                } else {
                    $subtitle = '';
                }
                $tableau[] = (strtotime($date . ' ' . $h) + $pl) . ' || ' . $val["titre"] . ' || ' . $subtitle . ' || ' . $val["nature"] . ' || ' . $val["photo"] . ' || ' . $val["saison"] . ' || ' . $val["numEpi"];
                $tableau = array_values(array_unique($tableau));
                $pop++;
            }
        }


        for ($i2 = 0; $i2 < count($tableau) - 1; $i2++) {
            $o = explode(' || ', $tableau[$i2]);
            $o2 = explode(' || ', $tableau[$i2 + 1]);
            if ($o[5]) {
                if ($o[6] == "") {
                    $o[6] = '1';
                }
                $sai = chr(10) . '	<episode-num system="xmltv_ns">' . ($o[5] - 1) . '.' . ($o[6] - 1) . '.</episode-num>';
            } else {
                $sai = "";
            }
            $fp = fopen($xml_save, "a");
            fputs($fp, '<programme start="' . date('YmdHis O', $o[0]) . '" stop="' . date('YmdHis O', $o2[0]) . '" channel="' . $channel . '">
	<title lang="fr">' . htmlspecialchars($o[1], ENT_XML1) . '</title>' . $o[2] . '
	<desc lang="fr">Aucune description</desc>
	<category lang="fr">' . htmlspecialchars($o[3], ENT_XML1) . '</category>
	<icon src="' . htmlspecialchars($o[4], ENT_XML1) . '" />' . $sai . '
</programme>
');
            fclose($fp);
        }
        return true;
    }


}