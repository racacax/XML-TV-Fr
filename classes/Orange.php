<?php
/*
 * @author Racacax
 * @version 0.1 : 16/02/2020
 */
require_once 'Provider.php';
require_once 'Utils.php';
class Orange extends AbstractProvider implements Provider
{
    public function __construct()
    {
        parent::__construct("channels_per_provider/channels_orange.json", 0.95);
    }

    public function constructEPG($channel,$date)
    {
        parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel))
            return false;
        $channel_id = $this->channelsList[$channel];


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
        if(preg_match('(Invalid request)',$res1) || preg_match('(504 Gateway Time-out)',$res1) || !isset($json))
        {
            return false;
        }
        if(isset($json['code']))
            return false;
        foreach($json as $val)
        {
            switch(@$val["csa"]) {
                case '2': $csa = '-10'; break;
                case '3': $csa = '-12'; break;
                case '4': $csa = '-16'; break;
                case '5': $csa = '-18'; break;
                default: $csa = 'Tout public';  break;
            }
            $program = $this->channelObj->addProgram($val["diffusionDate"], $val["diffusionDate"]+$val["duration"]);
            $program->addDesc($val["synopsis"]);
            $program->addCategory($val["genre"]);
            $program->addCategory($val["genreDetailed"]);
            $program->setIcon((!empty($val["covers"])?''.end($val["covers"])["url"]:''));
            $program->setRating($csa);
            if(!isset($val["season"]))
            {
                $program->addTitle($val["title"]);
            } else {
                if($val["season"]["number"] =="") { $val["season"]["number"] ='1';} if($val["episodeNumber"] =="") { $val["episodeNumber"] ='1';}
                $program->addTitle($val["season"]["serie"]["title"]);
                $program->setEpisodeNum($val["season"]["number"], $val["episodeNumber"]);
                $program->addSubtitle($val["title"]);
            }


        }
        return $this->channelObj->save();
    }


}
