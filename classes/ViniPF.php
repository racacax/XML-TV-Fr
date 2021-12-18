<?php
require_once 'Provider.php';
require_once 'Utils.php';
class ViniPF extends AbstractProvider implements Provider
{

    public static function getPriority()
    {
        return 0.4;
    }

    public function __construct()
    {
        parent::__construct("channels_per_provider/channels_vinipf.json");
    }

    function constructEPG($channel, $date)
    {
        parent::constructEPG($channel, $date);
        if(!in_array($channel,$this->CHANNELS_KEY))
            return false;
        $dateDebut = '{"dateDebut":"2021-12-18T21:00:00-10:00"}';
        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_URL, 'https://programme-tv.vini.pf/programmesJSON');
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch3, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0");
        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 1);
        $res3 = curl_exec($ch3);
        curl_close($ch3);
        $res2 = json_decode($res3,true);
        if(!isset($res2['timeSlices']))
            return false;
        $res3 = json_decode($res3, true);
        $json = $res3["timeSlices"];
        $count = 0;
        foreach ($json as $section) {
            foreach ($section["contents"] as $section2) {
                $count++;
                $program = $this->channelObj->addProgram($section2["startTime"], $section2["endTime"]);
                $program->addTitle($section2["title"]);
                $program->addDesc("Aucune description");
                $program->addCategory("Inconnu");
                $program->setIcon($section2["URLImage"]);
            }
        }
        $this->channelObj->save();
        if($count < 2)
            return false;
        return true;
    }
}