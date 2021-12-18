<?php
require_once 'Provider.php';
require_once 'Utils.php';
class ViniPF extends AbstractProvider implements Provider
{
    private static $cache_per_day = array(); // ViniPF send all channels data for two hours. No need to request for every channel
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
        $debut = strtotime($date); // to be synchronized with Paris timezone to avoid overlaping on french channels if multiple providers
        date_default_timezone_set("Pacific/Tahiti");
        for($i=0; $i <12; $i++) {
            $dateDebut = '{"dateDebut":"'.date('c',$debut + 3600*2*$i).'"}';
            if(!isset(self::$cache_per_day[md5($dateDebut)])) {
                $ch3 = curl_init();
                curl_setopt($ch3, CURLOPT_URL, 'https://programme-tv.vini.pf/programmesJSON');
                curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch3, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0");
                curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 0);
                curl_setopt($ch3, CURLOPT_POST, 1);
                curl_setopt($ch3, CURLOPT_POSTFIELDS, $dateDebut);
                $res3 = curl_exec($ch3);
                curl_close($ch3);
                $json = json_decode($res3, true);
                self::$cache_per_day[md5($dateDebut)] = $json;
            }
            $array = self::$cache_per_day[md5($dateDebut)];
            foreach($array["programmes"] as $viniChannel) {
                if($viniChannel["nid"] == $this->CHANNELS_LIST[$channel]) {
                    foreach($viniChannel["programmes"] as $programme) {
                        $program = $this->channelObj->addProgram($programme["timestampDeb"], $programme['timestampFin']);
                        $program->addTitle($programme['titreP']);
                        $program->addSubtitle($programme['legendeP']);
                        $program->addDesc($programme["desc"]);
                        $program->setIcon($programme['srcP']);
                        $program->addCategory($programme["categorieP"]);
                    }
                }
            }
        }
        return $this->channelObj->save();
    }
}