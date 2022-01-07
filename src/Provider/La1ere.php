<?php
declare(strict_types=1);

namespace racacax\XmlTv\Provider;

use racacax\XmlTv\Component\AbstractProvider;
use racacax\XmlTv\Component\ProviderInterface;

class La1ere extends AbstractProvider implements ProviderInterface
{

    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct("resources/channel_config/channels_1ere.json", $priority ?? 0.3);
    }

    function constructEPG($channel, $date)
    {
        parent::constructEPG($channel, $date);
        if($date != date('Y-m-d')) {
            return false;
        }
        if(!$this->channelExists($channel))
        {
            return false;
        }
        date_default_timezone_set($this->channelsList[$channel]["timezone"]);
        $channel_id = $this->channelsList[$channel]['id'];
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
        $days = array_values($days);
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
        return $this->channelObj;
    }
}