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

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        [$minDate, $maxDate] = $this->getMinMaxDate($date);

        $json = json_decode($this->getContentFromURL($this->generateUrl($channelObj, $minDate, $maxDate)), true);

        if (empty($json['channel'][0]['event'])) {
            return false;
        }


        foreach ($json['channel'][0]['event'] as $program) {
            $genre = @$program['programInfo']['genre'][0];
            $subGenre = @$program['programInfo']['subGenre'][0];

            if (isset($program['parentalGuidance'])) {
                $csa = explode('.', $program['parentalGuidance']);

                $csa = match ((int)end($csa)) {
                    2 => '-10',
                    3 => '-12',
                    4 => '-16',
                    5 => '-18',
                    default => 'Tout public',
                };
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
            $startTime = strtotime($program['startTime']);
            $startDate = new \DateTimeImmutable('@' . $startTime);
            if ($startDate < $minDate) {
                continue;
            } elseif ($startDate > $maxDate) {
                return $channelObj;
            }
            $programObj = Program::withTimestamp($startTime, strtotime($program['endTime']));
            if (isset($program['programInfo']['character'])) {
                foreach ($program['programInfo']['character'] as $intervenant) {
                    $programObj->addCredit($intervenant['firstName'] . ' ' . $intervenant['lastName'], $this->getCreditType($intervenant['function']));
                }
            }
            $programObj->addTitle($program['programInfo']['longTitle']);
            $programObj->addSubTitle(@$program['programInfo']['secondaryTitle']);
            $programObj->addDesc(@$program['programInfo']['longSummary'] ?? @$program['programInfo']['shortSummary']);
            $programObj->setEpisodeNum(@$program['programInfo']['seriesInfo']['seasonNumber'], @$program['programInfo']['seriesInfo']['episodeNumber']);
            $programObj->addCategory($genre);
            $programObj->addCategory($subGenre);
            $programObj->addIcon(isset($program['media'][0]['url']) ? 'https://img.bouygtel.fr' . $program['media'][0]['url'] : null);
            $programObj->setRating($csa);
            if (isset($program['programInfo']['countryOfOrigin'])) {
                $programObj->setCountry($program['programInfo']['countryOfOrigin'], 'fr');
            }
            if (isset($program['programInfo']['productionDate'])) {
                $programObj->setDate(strval($program['programInfo']['productionDate']));
            }
            $channelObj->addProgram($programObj);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $minDate, \DateTimeImmutable $maxDate): string
    {
        $param = [
            'profile' => 'detailed',
            'epgChannelNumber' => $this->channelsList[$channel->getId()],
            'eventCount' => 9999,
            'startTime' => $minDate->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'endTime' => $maxDate->setTimezone(new \DateTimeZone('UTC'))->modify('+12 hours')->format('Y-m-d\TH:i:s\Z')
        ];

        return 'https://epg.cms.pfs.bouyguesbox.fr/cms/sne/live/epg/events.json?' . http_build_query($param);
    }

    private function getCreditType(string $type): string
    {
        switch ($type) {
            case 'Acteur':
                $type = 'actor';

                break;
            case 'Réalisateur':
                $type = 'director';

                break;
            case 'Scénariste':
                $type = 'writer';

                break;
            case 'Producteur':
                $type = 'producer';

                break;
            case 'Musique':
                $type = 'composer';

                break;
            case 'Créateur':
                $type = 'editor';

                break;
            case 'Présentateur vedette':
            case 'Autre présentateur':
                $type = 'presenter';

                break;
            case 'Commentateur':
                $type = 'commentator';

                break;
            case 'Origine Scénario':
            case 'Scénario':
                $type = 'adapter';

                break;
        }

        return $type;
    }
}
