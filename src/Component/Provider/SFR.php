<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

// Original script by lazel from https://github.com/lazel/XML-TV-Fr/blob/master/classes/SFR.php
class SFR extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        $file = $extraParam['provider'] ?? 'sfr';
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_'.$file.'.json'), $priority ?? 0.85);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $channelId = $this->getChannelsList()[$channel];
        $selectedDate = new \DateTimeImmutable($date);
        $contentDayBefore = $this->getContentFromURL($this->generateUrl($channelObj, $selectedDate->modify('-1 day')));
        $content = $this->getContentFromURL($this->generateUrl($channelObj, $selectedDate));
        $jsonDayBefore = json_decode($contentDayBefore, true);
        $json = json_decode($content, true);

        if ($json === false) {
            return false;
        }
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $programsDayBefore = @$jsonDayBefore['epg'];
        $programs = @$json['epg'];

        $programsForChannel = $programs[$channelId];
        if (empty($programsForChannel)) {
            return false;
        }

        if (!empty($programsDayBefore[$channelId])) {
            $programsForChannel = array_merge($programsDayBefore[$channelId], $programsForChannel);
        }


        foreach ($programsForChannel as $program) {
            $startDate = new \DateTimeImmutable('@'.($program['startDate'] / 1000));
            if ($startDate < $minDate) {
                continue;
            } elseif ($startDate > $maxDate) {
                break;
            }
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
