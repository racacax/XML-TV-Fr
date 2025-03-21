<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Oqee extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_oqee.json'), $priority ?? 0.5);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $timestamps = array_map(function($hour) use ($date) { return strtotime("$date $hour:00:00 UTC"); }, [0, 6, 12, 18]);

        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        foreach ($timestamps as $times) {
            $json = json_decode($this->getContentFromURL($this->generateUrl($channelObj, $times)), true);
            if (empty($json['result']['entries'])) {
                return false;
            }

            foreach ($json['result']['entries'] as $entrie) {
                $program = new Program(date('YmdHis O', $entrie['live']['start']), date('YmdHis O', $entrie['live']['end']));
                $program->addTitle($entrie['live']['title']);
                $program->addSubtitle($entrie['live']['sub_title']);
                $program->addDesc($entrie['live']['description']);
                $program->addCategory($entrie['live']['category']);
                $program->addCategory($entrie['live']['sub_category']);
                $icon = str_replace("h%d", "h1080", $entrie['pictures']['main']);
                $program->setIcon($icon);
                $program->setRating("-". $entrie['live']['parental_rating']);
                $channelObj->addProgram($program);
            }
        }
        return $channelObj;
    }

    public function generateUrl(Channel $channel, $time): string
    {
        return 'https://api.oqee.net/api/v1/epg/by_channel/'. $this->channelsList[$channel->getId()] .'/'.$time;
    }
}
