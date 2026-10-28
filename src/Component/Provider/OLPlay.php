<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * OLPlay (Olympique Lyonnais) API, in two steps:
 *  - EpgTile/FilterProgramTiles returns the schedule (id, start and end of every program) of all channels
 *  - Tile/GetTiles returns the details (description, image, flags, ...) of the requested programs
 *
 * Both requests are independent from the channel and the requested day: they always cover the same period
 * (see getPeriod()), so that they are only sent once (HTTP cache) whatever the number of days and channels.
 */
class OLPlay extends AbstractProvider implements ProviderInterface
{
    private const API_URL = 'https://api.app.olplay.ol.fr/';
    private const PLATFORM = 'www';
    private const HEADERS = [
        'Content-Type' => 'application/json;charset=utf-8',
        'X-Api-Camel-Case' => 'true',
        'X-Api-Date-Format' => 'iso',
        'Referer' => 'https://olplay.ol.fr',
    ];
    private const DAYS_BEFORE = 2;
    private const DAYS_AFTER = 8;

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_olplay.json'), $priority ?? 0.7);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $schedule = $this->getSchedule((string) $this->channelsList[$channel]);
        $dayEntries = [];
        foreach ($schedule['entries'] as $entry) {
            $start = new \DateTimeImmutable($entry['from']);
            if ($start >= $minDate && $start <= $maxDate) {
                $dayEntries[] = $entry;
            }
        }
        if (empty($dayEntries)) {
            return false;
        }

        $tiles = $this->getTiles($schedule['ids']);
        if (empty($tiles)) {
            return false;
        }

        foreach ($dayEntries as $entry) {
            $tile = $tiles[$entry['id']] ?? null;
            if (empty($tile['title'])) {
                continue;
            }
            $program = new Program(new \DateTimeImmutable($entry['from']), new \DateTimeImmutable($entry['to']));
            $this->addDetails($program, $tile);
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    private function addDetails(Program $program, array $tile): void
    {
        $program->addTitle(trim($tile['title']));
        $program->addSubTitle($this->cleanText($tile['subTitle'] ?? null));
        $program->addDesc($this->cleanText($tile['description'] ?? $tile['shortDescription'] ?? $tile['tinyDescription'] ?? null));
        if (!empty($tile['date'])) {
            $program->setDate((string) $tile['date']);
        }
        foreach ($tile['categories'] ?? [] as $category) {
            $program->addCategory($category['name'] ?? null);
        }
        foreach ($tile['people'] ?? [] as $person) {
            $name = $person['fullName'] ?? trim(($person['firstName'] ?? '') . ' ' . ($person['lastName'] ?? ''));
            $program->addCredit($name, $person['roleCodename'] ?? 'guest');
        }
        if (!empty($tile['countries'][0]['name'])) {
            $program->setCountry($tile['countries'][0]['name']);
        }
        foreach ($tile['images'] ?? [] as $image) {
            if (($image['role'] ?? null) === 'icon') {
                $program->addIcon($image['url'] ?? null);

                break;
            }
        }
        if (!empty($tile['isLive']) || !empty($tile['isLiveEvent'])) {
            $program->addKeyword('direct');
        }
        if (!empty($tile['isPreviouslyShown'])) {
            $program->setPreviouslyShown();
        }
        if (!empty($tile['isAdultContent'])) {
            $program->setRating('-18');
        }
        if (!empty($tile['hasSubtitles'])) {
            $program->addSubtitles('teletext');
        }
        if (!empty($tile['hasAudioDescription'])) {
            $program->setAudioDescribed();
        }
        if (!empty($tile['hasSignLanguage'])) {
            $program->addKeyword('langue des signes');
        }
    }

    /**
     * @return array{entries: array<int, array{from: string, to: string, id: string}>, ids: string[]}
     */
    private function getSchedule(string $channelCodename): array
    {
        [$from, $to] = $this->getPeriod();
        $response = $this->post('v1/EpgTile/FilterProgramTiles', [
            'from' => $from->format('c'),
            'to' => $to->format('c'),
        ]);
        $ids = [];
        foreach ($response['programs'] ?? [] as $entries) {
            foreach ($entries as $entry) {
                $ids[] = $entry['id'];
            }
        }

        return ['entries' => $response['programs'][$channelCodename] ?? [], 'ids' => $ids];
    }

    /**
     * @param string[] $ids
     * @return array<string, array>
     */
    private function getTiles(array $ids): array
    {
        $response = $this->post('v2/Tile/GetTiles', [
            'requestedTiles' => array_map(fn (string $id) => ['id' => $id], $ids),
        ]);
        $tiles = [];
        foreach ($response['tiles'] ?? [] as $tile) {
            $tiles[$tile['id']] = $tile;
        }

        return $tiles;
    }

    private function post(string $endpoint, array $payload): array
    {
        $body = json_encode(['platformCodename' => self::PLATFORM] + $payload);
        $response = json_decode($this->getContentFromURL(self::API_URL . $endpoint, self::HEADERS, false, false, $body), true);

        return !empty($response['result']['success']) ? $response : [];
    }

    /**
     * @return \DateTimeImmutable[]
     */
    private function getPeriod(): array
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));

        return [
            $today->modify('-' . self::DAYS_BEFORE . ' days'),
            $today->modify('+' . self::DAYS_AFTER . ' days'),
        ];
    }

    private function cleanText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $text = preg_replace('#</(div|p)>|<br\s*/?>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = str_replace("\u{A0}", ' ', $text);
        $text = preg_replace(['/[ \t]+/', '/\s*\R\s*/'], [' ', "\n"], $text);

        return trim($text);
    }
}
