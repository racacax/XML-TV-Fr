<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * Pickx (Proximus) anonymous API. The channels list gives numeric ids (the old "UID..." ids are in `BEPexternalId`).
 *
 * The guide of several channels is requested at once: channels are grouped by CHUNK_SIZE following the order of
 * their ids in the channels file, so that every channel of a group sends the same request for a given day (HTTP cache).
 */
class Proximus extends AbstractProvider implements ProviderInterface
{
    private const API_URL = 'https://api.prod-ntvbe.tvapi-web.pickx.be/AGL/1.5/A/FRA/WEB/PXS/TRAY/EPG';
    private const IMAGE_URL = 'https://experience-cache.cdi.streaming.proximustv.be/posterserver/poster/EPG/';
    private const REGION_ID = 7;
    private const TECHNICAL_PACKAGES = '136,142,154,157,193,196,214,217,223,226,232';
    private const CHUNK_SIZE = 20;
    private const HEADERS = [
        'Accept' => 'application/json',
        'Origin' => 'https://tv.pickx.be',
        'Referer' => 'https://tv.pickx.be/',
    ];

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_proximus.json'), $priority ?? 0.59);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $channelId = (int) $this->channelsList[$channel];
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $json = json_decode($this->getContentFromURL($this->generateUrl($channelId, $minDate), self::HEADERS, false, false), true);

        $programs = [];
        foreach ($json['resultObj']['containers'] ?? [] as $container) {
            if ((int) ($container['id'] ?? 0) === $channelId) {
                $programs = $container['containers'] ?? [];

                break;
            }
        }

        foreach ($programs as $program) {
            $metadata = $program['metadata'] ?? [];
            if (empty($metadata['airingStartTime']) || empty($metadata['airingEndTime']) || empty($metadata['title'])) {
                continue;
            }
            $start = intdiv((int) $metadata['airingStartTime'], 1000);
            // The request covers a larger period than the day (programs are returned as soon as they overlap it)
            if ($start < $minDate->getTimestamp() || $start > $maxDate->getTimestamp()) {
                continue;
            }
            $channelObj->addProgram($this->createProgram($metadata, $start, intdiv((int) $metadata['airingEndTime'], 1000)));
        }

        return $channelObj->getProgramCount() > 0 ? $channelObj : false;
    }

    private function createProgram(array $metadata, int $start, int $end): Program
    {
        $extended = $metadata['extendedMetadata'] ?? [];
        $program = Program::withTimestamp($start, $end);
        $program->addTitle(trim($metadata['title']));
        $program->addSubTitle($metadata['episodeTitle'] ?? null);
        $program->addDesc($metadata['longDescription'] ?? $metadata['shortDescription'] ?? null);
        $program->addCategory($metadata['subcategory'] ?? null);
        foreach ($metadata['genres'] ?? [] as $genre) {
            $program->addCategory($genre);
        }
        foreach ($metadata['directors'] ?? [] as $director) {
            $program->addCredit($director, 'director');
        }
        foreach ($metadata['actors'] ?? [] as $actor) {
            $program->addCredit($actor, 'actor');
        }
        if (!empty($metadata['year'])) {
            $program->setDate((string) $metadata['year']);
        }
        $country = $this->formatCountry((string) ($metadata['country'] ?? ''));
        if ($country !== '') {
            $program->setCountry($country);
        }
        if (!empty($metadata['episodeNumber'])) {
            // For daily programs, the season is the year
            $season = (int) ($metadata['season'] ?? 0);
            $program->setEpisodeNum($season > 0 && $season < 1900 ? $season : null, (int) $metadata['episodeNumber']);
        }
        if (!empty($extended['supportForVisuallyImpaired'])) {
            $program->setAudioDescribed();
        }
        if (!empty($extended['supportForHearingImpaired'])) {
            $program->addSubtitles('teletext');
        }
        $image = $extended['images']['landscapePoster'] ?? $metadata['pictureUrl'] ?? null;
        if (!empty($image)) {
            $program->addIcon(self::IMAGE_URL . $image);
        }
        $program->setRating(match ((int) ($metadata['pcLevel'] ?? 0)) {
            6 => '-6',
            10 => '-10',
            12 => '-12',
            16 => '-16',
            18 => '-18',
            default => 'Tout public',
        });

        return $program;
    }

    /**
     * "fr-be" => "France - Belgique"
     */
    private function formatCountry(string $codes): string
    {
        $names = [];
        foreach (array_filter(explode('-', $codes)) as $code) {
            // Unknown codes are returned as is
            $names[] = \Locale::getDisplayRegion('-' . strtoupper($code), 'fr');
        }

        return implode(' - ', $names);
    }

    /**
     * Same URL for every channel of the same group (see class comment) and every program of the day.
     */
    public function generateUrl(int $channelId, \DateTimeImmutable $date): string
    {
        $ids = array_values(array_unique(array_map('intval', $this->channelsList)));
        sort($ids);
        $group = array_slice($ids, (int) (intdiv((int) array_search($channelId, $ids, true), self::CHUNK_SIZE) * self::CHUNK_SIZE), self::CHUNK_SIZE);

        return self::API_URL . '?' . http_build_query([
            'filter_regionId' => self::REGION_ID,
            'filter_technicalPackages' => self::TECHNICAL_PACKAGES,
            'sortOrder' => 'asc',
            'filter_startTime' => $date->getTimestamp() * 1000,
            'filter_endTime' => $date->modify('+1 day')->getTimestamp() * 1000,
            'filter_channelIds' => implode(',', $group),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
