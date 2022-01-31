<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

// Original script by lazel on https://github.com/lazel/XML-TV-Fr/blob/master/classes/Bouygues.php
class Bouygues extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_bouygues.json'), $priority ?? 0.9);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $json = json_decode($this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date))), true);
        if (!isset($json['channel'][0]['event']) || empty($json['channel'][0]['event'])) {
            return false;
        }


        foreach ($json['channel'][0]['event'] as $program) {
            $genre = @$program['programInfo']['genre'][0];
            $subGenre = @$program['programInfo']['subGenre'][0];

            if (isset($program['parentalGuidance'])) {
                $csa = explode('.', $program['parentalGuidance']);

                switch ((int)end($csa)) {
                    case 2:
                        $csa = '-10';

                        break;
                    case 3:
                        $csa = '-12';

                        break;
                    case 4:
                        $csa = '-16';

                        break;
                    case 5:
                        $csa = '-18';

                        break;
                    default:
                        $csa = 'Tout public';

                        break;
                }
            } else {
                $csa = 'Tout public';
            }

            if (!is_null($genre) && !is_null($subGenre) && $genre == $subGenre) {
                if (isset($program['programInfo']['genre'][1])) {
                    $genre = $program['programInfo']['genre'][1];
                } else {
                    $subGenre = null;
                }
            }
            $programObj = new Program(strtotime($program['startTime']), strtotime($program['endTime']));
            $programObj->addTitle($program['programInfo']['longTitle']);
            $programObj->addSubtitle(@$program['programInfo']['secondaryTitle']);
            $programObj->addDesc(@$program['programInfo']['longSummary'] ?? @$program['programInfo']['shortSummary']);
            $programObj->setEpisodeNum(@$program['programInfo']['seriesInfo']['seasonNumber'], @$program['programInfo']['seriesInfo']['episodeNumber']);
            $programObj->addCategory($genre);
            $programObj->addCategory($subGenre);
            $programObj->setIcon(isset($program['media'][0]['url']) ? 'https://img.bouygtel.fr' . $program['media'][0]['url'] : null);
            $programObj->setRating($csa);
            $programObj->setYear(@$program['programInfo']['productionDate']);
            $channelObj->addProgram($programObj);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $param = [
            'profile'=>'detailed',
            'epgChannelNumber'=> $this->channelsList[$channel->getId()],
            'eventCount'=>999,
            'startTime'=>$date->format('Y-m-d\T04:00:00\Z'),
            'endTime'=>$date->modify('+1 days')->format('Y-m-d\T03:59:59\Z')
        ];

        return 'http://epg.cms.pfs.bouyguesbox.fr/cms/sne/live/epg/events.json?' . http_build_query($param);
    }
}
