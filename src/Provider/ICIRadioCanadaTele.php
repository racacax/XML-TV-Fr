<?php
declare(strict_types=1);

namespace racacax\XmlTv\Provider;

use racacax\XmlTv\Component\AbstractProvider;
use racacax\XmlTv\Component\ProviderInterface;

/*
 * @author Racacax
 * @version 0.1 : 18/12/2021
 */
class ICIRadioCanadaTele extends AbstractProvider implements ProviderInterface
{

    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct("resources/channel_config/channels_iciradiocanada.json", 0.6);
    }

    public function constructEPG($channel,$date)
    {
        parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel))
            return false;
        $channel_id = $this->channelsList[$channel];


        $url = "https://services.radio-canada.ca/neuro/sphere/v1/tele/schedule/$date?regionId=$channel_id";
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, $url);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch1, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0");
        $res1 = curl_exec($ch1);
        curl_close($ch1);
        $json = @json_decode($res1,true);
        if(!isset($json["data"]["broadcasts"]))
            return false;
        foreach($json["data"]["broadcasts"] as $broadcast)
        {
            $program = $this->channelObj->addProgram(strtotime($broadcast["startsAt"]), strtotime($broadcast["endsAt"]));
            $program->addCategory($broadcast["subtheme"]);
            $program->setIcon(str_replace('{0}', "635", str_replace('{1}', '16x9',@$broadcast["pircture"]["url"])));
            $program->addTitle($broadcast["title"]);
            $program->addSubtitle($broadcast["subtitle"]);

        }
        return $this->channelObj;
    }


}
