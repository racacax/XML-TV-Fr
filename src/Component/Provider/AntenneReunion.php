<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * Antenne Réunion API (https://www.antennereunion.fr/epg).
 *
 * The EPG endpoint returns, in a single request, the programs of every requested channel over a period given by
 * a start (midnight) and a number of hours. The request is independent from the channel and the requested day: it always
 * covers the same period (see getBody()), so that it is only sent once (HTTP cache) whatever the number of days and channels.
 */
class AntenneReunion extends AbstractProvider implements ProviderInterface
{
    private const API_URL = 'https://proxies.antennereunion-production.eu-west-3.alphanetworks.tv/proxy/';
    private const HEADERS = [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'X-AN-WebService-IdentityKey' => 'HadCogoctandsxEDEsdufOcagidOad',
        'Referer' => 'https://www.antennereunion.fr/',
    ];
    private const DAYS_BEFORE = 2;
    private const DAYS_AFTER = 8;
    private const SYNOPSIS_KEYS = ['AD_SYNO600C', 'AD_SYNO400C', 'AD_SYNO350C', 'AD_SYNO150C'];
    private const IGNORED_ORIGINAL_TYPES = ['autre', 'xy', 'programme'];

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_antennereunion.json'), $priority ?? 0.5);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $response = json_decode($this->getContentFromURL(self::API_URL . 'epg', self::HEADERS, false, false, $this->getBody()), true);
        $events = $response['result']['epg']['chan_' . $this->channelsList[$channel]] ?? [];
        if (empty($response['status']) || empty($events)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($events as $event) {
            $start = new \DateTimeImmutable('@' . $event['startutc']);
            if ($start < $minDate) {
                continue;
            } elseif ($start > $maxDate) {
                break;
            }
            if ($event['endutc'] <= $event['startutc'] || empty($event['title'])) {
                continue;
            }
            $program = Program::withTimestamp($event['startutc'], $event['endutc']);
            $this->addDetails($program, $event, json_decode($event['direct_metadata'] ?? '', true) ?: []);
            $channelObj->addProgram($program);
        }

        return $channelObj->getProgramCount() > 0 ? $channelObj : false;
    }

    public function getLogo(string $channel): ?string
    {
        parent::getLogo($channel);

        return self::API_URL . 'imgdata?' . http_build_query([
            'type' => '100',
            'objectId' => $this->channelsList[$channel],
            'languageId' => 'fr',
        ]);
    }

    private function addDetails(Program $program, array $event, array $metadata): void
    {
        $program->addTitle(trim($event['title']));
        $program->addDesc($this->getSynopsis($event, $metadata));
        $program->addCategory($event['genre'] ?? null);
        $originalType = $metadata['AD_ORIGTYPE'] ?? null;
        if ($originalType !== null
            && !in_array(strtolower($originalType), self::IGNORED_ORIGINAL_TYPES, true)
            && strcasecmp($originalType, $event['genre'] ?? '') !== 0) {
            $program->addCategory($originalType);
        }
        $program->addIcon(self::API_URL . 'imgdata?' . http_build_query([
            'type' => 'broadcast_epg',
            'objectId' => $event['id_epg'],
            'languageId' => 'fr',
        ]));
        if (!empty($metadata['TS_PRODYEAR'])) {
            $program->setDate((string) $metadata['TS_PRODYEAR']);
        }
        $program->setRating($event['rating'] > 0 ? '-' . $event['rating'] : 'Tout public');
        if (isset($metadata['AD_SERIESEP'])) {
            $program->setEpisodeNum($metadata['AD_SERIESEA'] ?? null, $metadata['AD_SERIESEP']);
        }
        foreach (['actor' => 'AD_LIACTORS', 'director' => 'AD_LIREALIS', 'presenter' => 'AD_LIPRESEN'] as $type => $key) {
            foreach ($metadata[$key] ?? [] as $name) {
                if ($name !== 'N/A') {
                    $program->addCredit($name, $type);
                }
            }
        }
        if (!empty($metadata['CA_PREMIERE'])) {
            $program->setPremiere();
        }
        if (!empty($metadata['CA_LIVEBCST'])) {
            $program->addKeyword('direct');
        }
        if (!empty($metadata['CA_RETRBCST'])) {
            $program->setPreviouslyShown();
        }
    }

    /**
     * The synopsis of the event is truncated: the longest of the synopsis available in the metadata is used instead.
     */
    private function getSynopsis(array $event, array $metadata): ?string
    {
        $synopsis = null;
        foreach ([$event['synopsis'] ?? null, ...array_map(fn ($key) => $metadata[$key] ?? null, self::SYNOPSIS_KEYS)] as $candidate) {
            $candidate = trim(str_replace('\\"', '"', (string) $candidate));
            if (strlen($candidate) > strlen((string) $synopsis)) {
                $synopsis = $candidate;
            }
        }

        return $synopsis;
    }

    private function getBody(): string
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));

        return http_build_query([
            'languageId' => 'fr',
            'midnight' => $today->modify('-' . self::DAYS_BEFORE . ' days')->getTimestamp(),
            'startHour' => 0,
            'endHour' => 24 * (self::DAYS_BEFORE + self::DAYS_AFTER),
            'channelIds' => json_encode(array_values(array_unique($this->channelsList))),
        ]);
    }
}
