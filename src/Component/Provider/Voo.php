<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Voo extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_voo.json'), $priority ?? 0.85);
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);

        try {
            $response = $this->client->post(
                $this->generateUrl($channelObj, $minDate),
                [
                    'body' => '<SubQueryOptions><QueryOption path="Titles">/Props/Name,Pictures,ShortSynopsis,LongSynopsis,Genres,Events,SeriesCount,SeriesCollection</QueryOption><QueryOption path="Titles/Events">/Props/IsAvailable</QueryOption><QueryOption path="Products">/Props/ListPrice,OfferPrice,CouponCount,Name,EntitlementState,IsAvailable</QueryOption><QueryOption path="Channels">/Props/Products</QueryOption><QueryOption path="Channels/Products">/Filter/EntitlementEnd>2018-01-27T14:40:43Z/Props/EntitlementEnd,EntitlementState</QueryOption></SubQueryOptions>'
                ]
            );
        } catch (ConnectException $e) {
            return false;
        }
        $json = json_decode($response->getBody()->getContents(), true);
        if (!isset($json['Events']['Event'])) {
            return false;
        }
        foreach ($json['Events']['Event'] as $event) {
            $start = strtotime($event['AvailabilityStart']);
            $startDate = new \DateTimeImmutable('@'.$start);
            if ($startDate < $minDate) {
                continue;
            } elseif ($startDate > $maxDate) {
                break;
            }
            $end = strtotime($event['AvailabilityEnd']);
            $program = new Program($start, $end);
            $program->addTitle($event['Titles']['Title'][0]['Name']);
            $program->addDesc(@$event['Titles']['Title'][0]['LongSynopsis']);
            $program->addCategory(@$event['Titles']['Title'][0]['Genres']['Genre'][0]['Value']);
            $program->setIcon(@$event['Titles']['Title'][0]['Pictures']['Picture'][0]['Value']);

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }


    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $date_start = $date->format('Y-m-d\TH:i:s\Z');
        $date_end = $date->modify('+2 days')->format('Y-m-d\TH:i:s\Z');

        return 'https://publisher.voomotion.be/traxis/web/Channel/' . $this->channelsList[$channel->getId()] . '/Events/Filter/AvailabilityEnd%3C=' . $date_end . '%26%26AvailabilityStart%3E=' .$date_start.'/Sort/AvailabilityStart/Props/IsAvailable,Products,AvailabilityEnd,AvailabilityStart,ChannelId,AspectRatio,DurationInSeconds,Titles,Channels?output=json&Language=fr&Method=PUT';
    }
}
