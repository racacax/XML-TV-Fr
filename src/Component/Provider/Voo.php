<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;

class Voo extends AbstractProvider implements ProviderInterface
{
    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct(ResourcePath::getInstance()->getChannelPath("channels_voo.json"), $priority ?? 0.85);
    }

    public function constructEPG(string $channel, string $date)
    {
        parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $date_start = date('Y-m-d', strtotime($date)).'T00:00:00Z';
        $date_end = date('Y-m-d', strtotime($date) + 86400).'T02:00:00Z';
        //$end = strtotime($date);
        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_URL, 'https://publisher.voomotion.be/traxis/web/Channel/' . $this->channelsList[$channel] . '/Events/Filter/AvailabilityEnd%3C=' . $date_end . '%26%26AvailabilityStart%3E=' .$date_start.'/Sort/AvailabilityStart/Props/IsAvailable,Products,AvailabilityEnd,AvailabilityStart,ChannelId,AspectRatio,DurationInSeconds,Titles,Channels?output=json&Language=fr&Method=PUT');
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
        foreach ($json["Events"]["Event"] as $event) {
            $start = strtotime($event["AvailabilityStart"]);
            $end = strtotime($event["AvailabilityEnd"]);
            $program = $this->channelObj->addProgram($start, $end);
            $program->addTitle($event["Titles"]["Title"][0]["Name"]);
            $program->addDesc(@$event["Titles"]["Title"][0]["LongSynopsis"]);
            $program->addCategory(@$event["Titles"]["Title"][0]["Genres"]["Genre"][0]["Value"]);
            $program->setIcon(@$event["Titles"]["Title"][0]["Pictures"]["Picture"][0]["Value"]);
        }
        return $this->channelObj;
    }
}
