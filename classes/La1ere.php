<?php
require_once 'Provider.php';
require_once 'Utils.php';
class La1ere extends AbstractProvider implements Provider
{

    public static function getPriority()
    {
        return 0.3;
    }

    public function __construct()
    {
        parent::__construct("channels_per_provider/channels_1ere.json");
    }

    function constructEPG($channel, $date)
    {
        parent::constructEPG($channel, $date);
        if($date != date('Y-m-d')) {
            return false;
        }
        $old_zone = date_default_timezone_get();
        if(!in_array($channel,$this->CHANNELS_KEY))
        {
            return false;
        }
        date_default_timezone_set($this->CHANNELS_LIST[$channel]["timezone"]);
        $channel_id = $this->CHANNELS_LIST[$channel]['id'];
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, "https://la1ere.francetvinfo.fr/$channel_id/emissions");
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0');
        $res1 = html_entity_decode(curl_exec($ch1),ENT_QUOTES);
        curl_close($ch1);
        $days = explode('<div class="guide">', $res1);
        $infos = [];
        unset($days[0]);
        array_values($days);
        foreach($days as $key => $day) {
            $programs = explode('</li>', $day);
            foreach($programs as $program) {
                preg_match('/\<span class=\"program-hour\".*?\>(.*?)\<\/span\>/',$program, $hour);
                preg_match('/\<span class=\"program-name\".*?\>(.*?)\<\/span\>/',$program, $name);
                preg_match('/\<div class=\"subtitle\".*?\>(.*?)\<\/div\>/',$program, $subtitle);
                if(isset($name[1])) {
                    $infos[] = array(
                        "hour" => date('YmdHis O', strtotime(date('Ymd', strtotime("now") + 86400 * $key) . ' ' . str_replace('H',':',$hour[1]))),
                        "title" => $name[1],
                        "subtitle" => @$subtitle[1]
                    );
                }
            }
        }
        for($i=0; $i<count($infos)-1; $i++) {
            $program = $this->channelObj->addProgram(strtotime($infos[$i]["hour"]), strtotime($infos[$i+1]["hour"]));
            if(strlen($infos[$i+1]["subtitle"])>0) {
                $program->addSubtitle($infos[$i+1]["subtitle"]);
            }
            $program->addTitle($infos[$i]["title"]);
            $program->addCategory("Inconnu");
        }
        $this->channelObj->save();
        date_default_timezone_set($old_zone);
        return true;
    }
}