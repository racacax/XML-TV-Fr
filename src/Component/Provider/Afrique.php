<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;


use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;

class Afrique extends AbstractProvider implements ProviderInterface
{

    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct(ResourcePath::getInstance()->getChannelPath('channels_afrique.json'), $priority ?? 0.2);
    }

    public function constructEPG(string $channel, string $date)
    {
        parent::constructEPG($channel, $date);
        if(!$this->channelExists($channel))
            return false;
        $day = (strtotime($date) - strtotime(date('Y-m-d')))/86400;
        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_URL, 'https://service.canal-overseas.com/ott-frontend/vector/83001/channel/' . $this->channelsList[$channel] . '/events?filter.day=' . $day);
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
        return $this->channelObj;
    }
}