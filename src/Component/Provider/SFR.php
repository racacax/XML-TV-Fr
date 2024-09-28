<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

// Original script by lazel from https://github.com/lazel/XML-TV-Fr/blob/master/classes/SFR.php
class SFR extends AbstractProvider implements ProviderInterface
{
    private ProviderCache $jsonPerDay;

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_sfr.json'), $priority ?? 0.85);
        $this->jsonPerDay = new ProviderCache('sfrCache');
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $channelId = $this->getChannelsList()[$channel];
        $arrayPerDay = $this->jsonPerDay->getArray();
        if (!isset($arrayPerDay[$date])) {
            $content = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
            $json = json_decode($content, true);
            $this->jsonPerDay->setArrayKey($date, $json);
        } else {
            $json = $arrayPerDay[$date];
        }

        if ($json === false) {
            return false;
        }
        $programs = @$json['epg'];

        if (empty($programs[$channelId])) {
            return false;
        }


        foreach ($programs[$channelId] as $program) {
            if (isset($program['moralityLevel'])) {
                $csa = match ($program['moralityLevel']) {
                    '2' => '-10',
                    '3' => '-12',
                    '4' => '-16',
                    '5' => '-18',
                    default => 'Tout public',
                };
            } else {
                $csa = 'Tout public';
            }
            $programObj = new Program($program['startDate'] / 1000, $program['endDate'] / 1000);
            $programObj->addTitle($program['title'] ?? '');
            $programObj->addSubtitle(@$program['subTitle']);
            $programObj->setEpisodeNum(@$program['seasonNumber'], @$program['episodeNumber']);
            $programObj->addDesc(@$program['description']);
            $programObj->addCategory(@$program['genre']);
            $programObj->setIcon(@$program['images'][0]['url']);
            $programObj->setRating($csa);

            $channelObj->addProgram($programObj);
        }

        return $channelObj;
    }


    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        return 'https://static-cdn.tv.sfr.net/data/epg/gen8/guide_web_' . $date->format('Ymd') . '.json';
    }
}
