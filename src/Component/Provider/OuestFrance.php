<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * Ouest-France TV guide (https://programmetv.ouest-france.fr/chaines/programme-tv-france-2-4/?date=YYYY-MM-DD).
 * Channels use the same ids as Orange and Telerama (the id is the end of the slug of the channel).
 *
 * The page of a day only gives the time (HH:MM) of the programs, from the early morning to the early morning of the
 * next day: the first program belongs to the date of the page, and the date changes every time the time goes back.
 * The pages of the day before and of the day are therefore read to get the whole Paris day. The title of the programs
 * is truncated when it is too long ("Affaire conclue, tout le monde a quel..."): the page of the program is read to
 * get the full one. Season and episode are found in the url of the program.
 */
class OuestFrance extends AbstractProvider implements ProviderInterface
{
    private const URL = 'https://programmetv.ouest-france.fr/chaines/%s/?date=%s';
    // Without these headers, the website denies access
    private const HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:137.0) Gecko/20100101 Firefox/137.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'fr-FR,fr;q=0.9',
        'Accept-Encoding' => 'gzip, deflate',
    ];
    private bool $enableDetails;

    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        $this->enableDetails = $extraParam['ouestfrance_enable_details'] ?? true;
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_ouestfrance.json'), $priority ?? 0.3);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $programs = $this->getPrograms((string) $this->channelsList[$channel], $date);
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($programs as $index => $data) {
            if ($data['start'] < $minDate) {
                continue;
            } elseif ($data['start'] > $maxDate) {
                break;
            }
            $end = isset($data['duration']) ? $data['start']->setTimestamp($data['start']->getTimestamp() + $data['duration'] * 60) : null;
            $next = $programs[$index + 1]['start'] ?? null;
            if ($next !== null && ($end === null || $next < $end)) {
                $end = $next;
            }
            if ($end === null || $end <= $data['start']) {
                continue;
            }
            $program = new Program($data['start'], $end);
            $this->addDetails($program, $data);
            $channelObj->addProgram($program);
        }

        return $channelObj->getProgramCount() > 0 ? $channelObj : false;
    }

    /**
     * @return array<int, array{start: \DateTimeImmutable, title: string, genre: ?string, duration: ?int, url: string, icon: ?string}>
     */
    private function getPrograms(string $slug, string $date): array
    {
        $programs = [];
        $previousEnd = null;
        $dayBefore = (new \DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
        foreach ([$dayBefore, $date] as $day) {
            $content = $this->getContentFromURL(sprintf(self::URL, $slug, $day), self::HEADERS);
            $pageDate = new \DateTimeImmutable($day, new \DateTimeZone('Europe/Paris'));
            $previousMinutes = null;
            foreach (array_slice(preg_split('/<li\s+class="programme/', $content) ?: [], 1) as $block) {
                if (!preg_match('/class="horaire[^"]*">(\d{1,2}):(\d{2})</', $block, $time)) {
                    continue;
                }
                $minutes = (int) $time[1] * 60 + (int) $time[2];
                // The time goes back by more than an hour when the date changes (it goes back by less when the clocks change)
                if ($previousMinutes !== null && $minutes < $previousMinutes - 60) {
                    $pageDate = $pageDate->modify('+1 day');
                }
                $previousMinutes = $minutes;
                preg_match('/class="nom"><strong>(.*?)<\/strong>/s', $block, $title);
                preg_match('/<a href="([^"]*)"/', $block, $url);
                preg_match('/class="infos[^"]*">(.*?)<\/p>/s', $block, $infos);
                preg_match('/data-oflazyload=\'\{"src":"(.*?)"\}\'/', $block, $icon);
                [$genre, $duration] = $this->parseInfos($infos[1] ?? '');
                $start = $this->resolveRepeatedHour($pageDate->setTime((int) $time[1], (int) $time[2]), $previousEnd);
                // A program is on the page of the day before and on the one of the day only when the pages overlap
                $previousEnd = $duration !== null ? $start->setTimestamp($start->getTimestamp() + $duration * 60) : null;
                $programs[$start->getTimestamp()] = [
                    'start' => $start,
                    'title' => trim($title[1] ?? ''),
                    'genre' => $genre,
                    'duration' => $duration,
                    'url' => $url[1] ?? '',
                    'icon' => $icon[1] ?? null,
                ];
            }
        }
        ksort($programs);

        return array_values($programs);
    }

    /**
     * @return array{?string, ?int} Genre and duration in minutes ("Film documentaire • 2h15")
     */
    private function parseInfos(string $infos): array
    {
        $parts = array_map('trim', explode('•', strip_tags($infos)));
        $duration = null;
        if (preg_match('/^(\d+)h(\d{2})$/', (string) end($parts), $matches)) {
            $duration = (int) $matches[1] * 60 + (int) $matches[2];
            array_pop($parts);
        }

        return [$parts[0] !== '' ? $parts[0] : null, $duration];
    }

    private function addDetails(Program $program, array $data): void
    {
        $title = $data['title'];
        $details = null;
        // Full title of the truncated ones, even if details are disabled
        $isTruncated = str_ends_with($title, '...');
        if (($this->enableDetails || $isTruncated) && $data['url'] !== '') {
            $details = $this->getContentFromURL($data['url'], self::HEADERS);
            if ($isTruncated && preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $details, $fullTitle) && trim(strip_tags($fullTitle[1])) !== '') {
                $title = trim(strip_tags($fullTitle[1]));
            }
        }
        $program->addTitle($title);
        $program->addCategory($data['genre']);
        $program->addIcon($data['icon']);
        if (preg_match('/\/s(\d+)e(\d+)-[^\/]*\/?$/', $data['url'], $episode)) {
            $program->setEpisodeNum($episode[1], $episode[2]);
        }
        if ($this->enableDetails && $details) {
            $this->addDetailsFromPage($program, $details);
        }
    }

    private function addDetailsFromPage(Program $program, string $page): void
    {
        if (preg_match('/<div class="su-article[^"]*content-summary">(.*?)<\/div>/s', $page, $summary)) {
            $program->addDesc(trim(strip_tags($summary[1])));
        }
        if (preg_match('/<p><strong>Année de production<\/strong>\s*(\d{4})/', $page, $year)) {
            $program->setDate($year[1]);
        }
        foreach (['De' => 'director', 'Avec' => 'actor'] as $label => $type) {
            if (preg_match('/<p><strong>' . $label . '<\/strong>(.*?)<\/p>/s', $page, $people)) {
                foreach (explode(',', strip_tags($people[1])) as $name) {
                    $program->addCredit(trim($name), $type);
                }
            }
        }
    }
}
