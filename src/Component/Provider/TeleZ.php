<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class TeleZ extends AbstractProvider implements ProviderInterface
{
    private static $cache_per_day = []; // TeleZ sends all channels data for the day. No need to request for every channel

    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_telez.json'), $priority ?? 0.5);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $url = $this->generateUrl($channelObj, new \DateTimeImmutable($date));
        $channelId = $this->getChannelsList()[$channel];
        if (!isset(self::$cache_per_day[$url])) {
            $res3 = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
            $json = json_decode($res3, true);
            self::$cache_per_day[$url] = $json;
        }
        $array = self::$cache_per_day[$url];
        foreach ($array['data'] as $c) {
            if ($c['channel']['id'] == $channelId) {
                foreach ($c['programs'] as $program) {
                    $start = strtotime($program['onTime']);
                    $programObj = new Program($start, $start + 60 * $program['duration']);
                    $programObj->setIcon($program['image']['url']);
                    $programObj->addDesc($program['synopsis']);
                    $programObj->addCategory(@$program['category']['name']);
                    $programObj->addCategory(@$program['showType']['name']);
                    $programObj->addTitle($program['title']);

                    $channelObj->addProgram($programObj);
                }
            }
        }

        return $channelObj;
    }


    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        return 'https://api.telez.fr/schedule?full_day=1&date='.$date->format('Y-m-d');
    }
}
