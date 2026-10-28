<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * france.tv TV guide (https://www.france.tv/api/epg/videos/?date=YYYY-MM-DD&channel=...).
 *
 * The guide is made of the videos of a channel (replays and live) and not of its schedule. It is less accurate than
 * the guides of the operators: start times are the ones of the video, some programs are missing or overlap and there
 * is no end time. Therefore:
 *  - the end of a program is its start plus the duration of the video, limited to the start of the next program
 *  - the generic titles ("Émission du dimanche 25 octobre 2026") are replaced by the name of the show, found in
 *    the url of the video or in the tracking data, where it is only available as a slug (without accents)
 *
 * The page of a day (?date=D) lists the videos broadcast from 05:00 on that day to 05:00 on the next one: the pages of the
 * day before and of the day are needed to get the whole day (Paris time, dates come with their offset).
 */
class FranceTV extends AbstractProvider implements ProviderInterface
{
    private const API_URL = 'https://www.france.tv/api/epg/videos/?date=%s&channel=%s';
    private const GENERIC_TITLE = '/^(émission|edition|édition|épisode|episode)\b/iu';

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_francetv.json'), $priority ?? 0.2);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $videos = $this->getVideos((string) $this->channelsList[$channel], $date);
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($videos as $index => $video) {
            $start = $video['start'];
            if ($start < $minDate) {
                continue;
            } elseif ($start > $maxDate) {
                break;
            }
            $end = $video['start']->add($video['duration']);
            $next = $videos[$index + 1]['start'] ?? null;
            if ($next !== null && $next < $end) {
                $end = $next;
            }
            if ($end <= $start) {
                continue;
            }
            $program = new Program($start, $end);
            $this->addDetails($program, $video['content'], $video['tracking']);
            $channelObj->addProgram($program);
        }

        return $channelObj->getProgramCount() > 0 ? $channelObj : false;
    }

    /**
     * @return array<int, array{start: \DateTimeImmutable, duration: \DateInterval, content: array, tracking: array}> Videos of the day before and of the day, sorted by start, without duplicates
     */
    private function getVideos(string $apiChannel, string $date): array
    {
        $videos = [];
        $dayBefore = (new \DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
        foreach ([$dayBefore, $date] as $day) {
            $items = json_decode($this->getContentFromURL(sprintf(self::API_URL, $day, $apiChannel), [], false, false), true);
            foreach (is_array($items) ? $items : [] as $item) {
                $content = $item['content'] ?? [];
                if (empty($content['isoDate']) || empty($content['isoDuration'])) {
                    continue;
                }
                $start = new \DateTimeImmutable($content['isoDate']);
                // Some videos are listed twice at the same time
                $videos[$start->getTimestamp() . '-' . $content['id']] = [
                    'start' => $start,
                    'duration' => new \DateInterval($content['isoDuration']),
                    'content' => $content,
                    'tracking' => $item['tracking'] ?? [],
                ];
            }
        }
        uasort($videos, fn ($a, $b) => $a['start'] <=> $b['start']);

        // Videos starting at the same time are the same program
        $unique = [];
        foreach ($videos as $video) {
            $key = $video['start']->getTimestamp();
            if (!isset($unique[$key])) {
                $unique[$key] = $video;
            }
        }

        return array_values($unique);
    }

    private function addDetails(Program $program, array $content, array $tracking): void
    {
        [$title, $subTitle, $season, $episode] = $this->getTitles($content, $tracking);
        $program->addTitle($title);
        $program->addSubTitle($subTitle);
        $program->addDesc($content['description'] ?? null);
        $program->addCategory($content['category']['label'] ?? null);
        $program->addIcon($content['images']['expanded']['x1'] ?? $content['images']['base']['x2'] ?? null);
        if ($episode !== null) {
            $program->setEpisodeNum($season, $episode);
        }
        if (!empty($content['csa'])) {
            $program->setRating($content['csa'] === 'TP' ? 'Tout public' : '-' . $content['csa']);
        }
        foreach ($content['icons'] ?? [] as $icon) {
            if (($icon['id'] ?? null) === 'st') {
                $program->addSubtitles('teletext');
            } elseif (($icon['id'] ?? null) === 'ad') {
                $program->setAudioDescribed();
            }
        }
    }

    /**
     * @return array{string, ?string, ?string, ?string} Title, sub title, season and episode
     */
    private function getTitles(array $content, array $tracking): array
    {
        $title = trim($content['title']);
        $season = $episode = null;
        $hasEpisodeNumber = preg_match('/^S(\d+) E(\d+) - (.+)$/u', $title, $matches) === 1;
        if ($hasEpisodeNumber) {
            [, $season, $episode, $title] = $matches;
        }
        $isGeneric = preg_match(self::GENERIC_TITLE, $title) === 1;
        $show = $this->getShowName($content, $tracking);
        if ($show === null || (!$isGeneric && !$hasEpisodeNumber)) {
            return [$title, null, $season, $episode];
        }

        return [$show, $title, $season, $episode];
    }

    private function getShowName(array $content, array $tracking): ?string
    {
        // /channel/show/12345-title.html
        $segments = explode('/', trim($content['url'] ?? '', '/'));
        if (count($segments) >= 3 && preg_match('/^\d+-/', end($segments))) {
            $slug = $segments[1];
        } else {
            // Levels of the program are separated by |
            $slug = explode('|', $tracking['program'] ?? '')[0];
        }
        $name = trim(str_replace(['-', '_'], ' ', $slug));

        return $name === '' ? null : mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }
}
