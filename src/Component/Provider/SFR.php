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
    private $jsonPerDay;

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_sfr.json'), $priority ?? 0.85);
        $this->jsonPerDay = [];
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $channelId = $this->getChannelsList()[$channel];

        if (!isset($this->jsonPerDay[$date])) {
            $content = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
            $json = json_decode($content, true);
            $this->jsonPerDay[$date] = $json;
        } else {
            $json = $this->jsonPerDay[$date];
        }

        if ($json === false) {
            return false;
        }
        $programs = @$json['epg'];

        if (!isset($programs[$channelId]) || empty($programs[$channelId])) {
            return false;
        }


        foreach ($programs[$channelId] as $program) {
            if (isset($program['moralityLevel'])) {
                switch ($program['moralityLevel']) {
                    case '2':
                        $csa = '-10';

                        break;
                    case '3':
                        $csa = '-12';

                        break;
                    case '4':
                        $csa = '-16';

                        break;
                    case '5':
                        $csa = '-18';

                        break;
                    default:
                        $csa = 'Tout public';

                        break;
                }
            } else {
                $csa = 'Tout public';
            }
            $programObj = new Program($program['startDate'] / 1000, $program['endDate'] / 1000);
            $programObj->addTitle($program['title'] ?? '');
            $programObj->addSubtitle($program['subTitle'] ?? @$program['eventName']);
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
