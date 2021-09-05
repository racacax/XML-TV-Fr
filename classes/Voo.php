<?php
require_once 'Provider.php';
require_once 'Utils.php';
class Voo implements Provider
{

    private $XML_PATH;
    private static $TMP_PATH = "epg/voo/";
    private static $CHANNELS_LIST;
    private static $CHANNELS_KEY;

    public function __construct($XML_PATH)
    {
        $this->XML_PATH = $XML_PATH;
        if (!isset(self::$CHANNELS_LIST)&& file_exists("channels_per_provider/channels_voo.json")) {
            self::$CHANNELS_LIST = json_decode(file_get_contents("channels_per_provider/channels_voo.json"), true);
            self::$CHANNELS_KEY = array_keys(self::$CHANNELS_LIST);
        }
    }
    public static function getPriority()
    {
        return 0.85;
    }

    function constructEPG($channel, $date)
    {
        if(!in_array($channel,self::$CHANNELS_KEY))
            return false;
        $date_start = date('Y-m-d', strtotime($date)).'T00:00:00Z';
        $date_end = date('Y-m-d', strtotime($date) + 86400).'T02:00:00Z';
        $end = strtotime($date);
        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_URL, 'https://publisher.voomotion.be/traxis/web/Channel/' . self::$CHANNELS_LIST[$channel] . '/Events/Filter/AvailabilityEnd%3C=' . $date_end . '%26%26AvailabilityStart%3E=' .$date_start.'/Sort/AvailabilityStart/Props/IsAvailable,Products,AvailabilityEnd,AvailabilityStart,ChannelId,AspectRatio,DurationInSeconds,Titles,Channels?output=json&Language=fr&Method=PUT');
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch3, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0");
        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch3, CURLOPT_POST, 1);
        $str = '<SubQueryOptions><QueryOption path="Titles">/Props/Name,Pictures,ShortSynopsis,LongSynopsis,Genres,Events,SeriesCount,SeriesCollection</QueryOption><QueryOption path="Titles/Events">/Props/IsAvailable</QueryOption><QueryOption path="Products">/Props/ListPrice,OfferPrice,CouponCount,Name,EntitlementState,IsAvailable</QueryOption><QueryOption path="Channels">/Props/Products</QueryOption><QueryOption path="Channels/Products">/Filter/EntitlementEnd>2018-01-27T14:40:43Z/Props/EntitlementEnd,EntitlementState</QueryOption></SubQueryOptions>';
        curl_setopt($ch3, CURLOPT_POSTFIELDS, "" . $str . "");
        $res3 = curl_exec($ch3);
        curl_close($ch3);

        $json = json_decode($res3, true);
        if (!isset($json["Events"]["Event"])) {
            return false;
        }
        $xml_save = Utils::generateFilePath($this->XML_PATH,$channel,$date);
        if(file_exists( $xml_save))
            unlink( $xml_save);

        foreach ($json["Events"]["Event"] as $event) {
            $start = strtotime($event["AvailabilityStart"]);
            if ($start > $end + 1) {
                $fp = fopen($xml_save, "a");
                fputs($fp, '<programme start="' . date('YmdHis O', ($end)) . '" stop="' . date('YmdHis O', $start) . '" channel="' . $channel . '">
	<title lang="fr">Pas de programme</title>
	<desc lang="fr">Pas de programme</desc>
	<category lang="fr">Inconnu</category>
</programme>
');
                fclose($fp);
            }
            $end = strtotime($event["AvailabilityEnd"]);
            $fp = fopen($xml_save, "a");
            @fputs($fp, '<programme start="' . date('YmdHis O', ($start)) . '" stop="' . date('YmdHis O', $end) . '" channel="' . $channel . '">
	<title lang="fr">' . htmlspecialchars($event["Titles"]["Title"][0]["Name"], ENT_XML1) . '</title>
	<desc lang="fr">' . htmlspecialchars($event["Titles"]["Title"][0]["LongSynopsis"], ENT_XML1) . '</desc>
	<category lang="fr">' . htmlspecialchars($event["Titles"]["Title"][0]["Genres"]["Genre"][0]["Value"], ENT_XML1) . '</category>
	<icon src="' . htmlspecialchars($event["Titles"]["Title"][0]["Pictures"]["Picture"][0]["Value"], ENT_XML1) . '" />
</programme>
');
            fclose($fp);
        }
        return true;
    }

}
