<?php
/*
 * @author Racacax
 * @version 0.1 : 05/09/2021
 */
require_once 'Provider.php';
require_once 'Utils.php';
class TV5 extends AbstractProvider implements Provider
{

    public static function getPriority()
    {
        return 0.6;
    }
    public function __construct()
    {
        parent::__construct("channels_per_provider/channels_tv5.json");
    }

    public function constructEPG($channel,$date)
    {
        parent::constructEPG($channel, $date);

        if (!$this->channelExists($channel))
            return false;
        $channel_id = $this->channelsList[$channel];


        $start = date('Y-m-d', strtotime($date))."T00:00:00";
        $end = date('Y-m-d', strtotime($date . ' + 1 days'))."T00:00:00";
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
        if(!@isset($json['data'][0]))
        {
            return false;
        }
        foreach($json["data"] as $val)
        {
            $program = $this->channelObj->addProgram(strtotime($val['utcstart']."+00:00"), strtotime($val['utcend']."+00:00"));
            $program->addTitle($val["title"]);
            $program->addDesc((!empty($val["description"])) ? $val["description"] : 'Pas de description');
            $program->addCategory($val["category"]);
            $program->setIcon(!empty($val["image"])?''.$val["image"]:'');
            if(isset($val["season"])) {
                if($val["season"] =="") { $val["season"] ='1';} if($val["episode"] =="") { $val["episode"] ='1';}
                $program->addSubtitle($val["episode_name"]);
                $program->setEpisodeNum($val["season"], $val["episode"]);
            }


        }
        return $this->channelObj->save();
    }


}
