<?php
require_once 'Provider.php';
require_once 'Utils.php';
class TeleZ extends AbstractProvider implements Provider
{
    private static $cache_per_day = array(); // TeleZ sends all channels data for the day. No need to request for every channel

    public function __construct()
    {
        parent::__construct("channels_per_provider/channels_telez.json", 0.5);
    }

    function constructEPG($channel, $date)
    {
        parent::constructEPG($channel, $date);
        if(!$this->channelExists($channel))
            return false;

        $channelId = $this->getChannelsList()[$channel];
        if(!isset(self::$cache_per_day[md5($date)])) {
            $res3 = $this->getContentFromURL("https://api.telez.fr/schedule?full_day=1&date=$date");
            $json = json_decode($res3, true);
            self::$cache_per_day[md5($date)] = $json;
        }
        $array = self::$cache_per_day[md5($date)];
        foreach ($array['data'] as $c) {
            if($c['channel']['id'] == $channelId) {
                foreach ($c['programs'] as $program) {
                    $start = strtotime($program['onTime']);
                    $programObj = $this->channelObj->addProgram($start, $start + 60 * $program['duration']);
                    $programObj->setIcon($program['image']['url']);
                    $programObj->addDesc($program['synopsis']);
                    $programObj->addCategory(@$program['category']['name']);
                    $programObj->addCategory(@$program['showType']['name']);
                    $programObj->addTitle($program['title']);
                }
            }
        }
        return $this->channelObj->save();
    }
}