<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * Le Télégramme TV guide (Libreserviceweb, https://telegramme.libreserviceweb.fr/television/programmes-chaine.html).
 * One request gives all the programs of a channel for a day, with their summary, cast, ... Channels are defined by their id and
 * the id of the list they belong to (299: TNT, 302: box/satellite/cable).
 *
 * A day goes from 06:00 to about 06:00 on the next day and only the time (HH:MM) of the programs is given: the first program
 * belongs to the date of the page and the date changes every time the time goes back. The pages of the day before and of the day
 * are therefore read to get the whole Paris day. The end of a program is the start of the next one, or its start plus its
 * duration when the guide gives one (films) and it comes first.
 */
class LeTelegramme extends AbstractProvider implements ProviderInterface
{
    private const BASE_URL = 'https://telegramme.libreserviceweb.fr';
    private const SOURCE = 'Le Télégramme';
    private const CREDITS = ['De' => 'director', 'avec' => 'actor', 'Avec' => 'actor', 'Présenté par' => 'presenter'];

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_letelegramme.json'), $priority ?? 0.4);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $programs = $this->getPrograms($this->channelsList[$channel], $date);
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($programs as $index => $data) {
            if ($data['start'] < $minDate) {
                continue;
            } elseif ($data['start'] > $maxDate) {
                break;
            }
            $end = $programs[$index + 1]['start'] ?? null;
            if ($data['duration'] !== null) {
                $endFromDuration = $data['start']->setTimestamp($data['start']->getTimestamp() + $data['duration'] * 60);
                $end = $end === null || $endFromDuration < $end ? $endFromDuration : $end;
            }
            if ($end === null || $end <= $data['start']) {
                continue;
            }
            $program = new Program($data['start'], $end);
            $this->addDetails($program, $data['html']);
            $channelObj->addProgram($program);
        }

        return $channelObj->getProgramCount() > 0 ? $channelObj : false;
    }

    /**
     * @param array{id: int, list: int} $channel
     * @return array<int, array{start: \DateTimeImmutable, duration: ?int, html: string}>
     */
    private function getPrograms(array $channel, string $date): array
    {
        $programs = [];
        $previousEnd = null;
        $dayBefore = (new \DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
        foreach ([$dayBefore, $date] as $day) {
            $content = $this->getContentFromURL($this->generateUrl($channel, $day));
            $pageDate = new \DateTimeImmutable($day, new \DateTimeZone('Europe/Paris'));
            $previousMinutes = null;
            foreach (array_slice(preg_split('/<li class="np-program-infos">/', $content) ?: [], 1) as $block) {
                if (!preg_match('/np-television-program-schedule">\s*(\d{1,2}):(\d{2})/', $block, $time)) {
                    continue;
                }
                $minutes = (int) $time[1] * 60 + (int) $time[2];
                // The time goes back by more than an hour when the date changes (it goes back by less when the clocks change)
                if ($previousMinutes !== null && $minutes < $previousMinutes - 60) {
                    $pageDate = $pageDate->modify('+1 day');
                }
                $previousMinutes = $minutes;
                $start = $this->resolveRepeatedHour($pageDate->setTime((int) $time[1], (int) $time[2]), $previousEnd);
                $duration = $this->parseDuration($block);
                $previousEnd = $duration !== null ? $start->setTimestamp($start->getTimestamp() + $duration * 60) : null;
                $programs[$start->getTimestamp()] = ['start' => $start, 'duration' => $duration, 'html' => $block];
            }
        }
        ksort($programs);

        return array_values($programs);
    }

    private function parseDuration(string $block): ?int
    {
        if (!preg_match('/np-tv-program-channel-technical-infos">\s*(?:(\d+)h)?\s*(?:(\d+)\s*mn)?\s*</', $block, $matches) || ($matches[1] ?? '') . ($matches[2] ?? '') === '') {
            return null;
        }

        return (int) ($matches[1] ?? 0) * 60 + (int) ($matches[2] ?? 0);
    }

    private function addDetails(Program $program, string $block): void
    {
        preg_match('/np-television-program-title">\s*<a [^>]*>(.*?)<\/a>/s', $block, $title);
        $program->addTitle($this->cleanText(preg_replace('/<span class="np-event-note".*?<\/span><\/span>/s', '', $title[1] ?? '')));
        if (preg_match('/np-television-program-sub-title">(.*?)<\/div>/s', $block, $subTitle)) {
            $program->addSubTitle($this->cleanText($subTitle[1]));
        }
        if (preg_match('/np-tv-program-grid-channel-tv-program-type-\d+" title="(.*?)"/', $block, $type)) {
            $program->addCategory(mb_strtoupper(mb_substr($type[1], 0, 1)) . mb_strtolower(mb_substr($type[1], 1)));
        }
        if (preg_match('/np-channel-program-synopsis"><div>(.*?)<\/div>/s', $block, $infos)) {
            $this->addInfos($program, $this->cleanText($infos[1]));
        }
        if (preg_match('/np-television-profile-description-castings">(.*?)<\/div>/s', $block, $castings)) {
            $this->addCredits($program, $castings[1]);
        }
        if (preg_match('/<span class="np-bold">Résumé :<\/span>(.*?)<\/div>/s', $block, $summary)) {
            $program->addDesc($this->cleanText($summary[1]));
        }
        if (preg_match('/<span class="np-bold">Notre opinion :<\/span>(.*?)<\/div>/s', $block, $opinion)) {
            $program->addReview($this->cleanText($opinion[1]), self::SOURCE);
        }
        $hearts = substr_count($block, 'glyphicon-heart"');
        if ($hearts > 0) {
            $program->addStarRating($hearts, 5, self::SOURCE);
        }
        if (preg_match('/np-programs-prime-program-image"><figure><img src="(\/[^"]*)"/', $block, $image)) {
            $program->addIcon(self::BASE_URL . $image[1]);
        }
    }

    /**
     * "2014. Comédie dramatique France, 1h40" : year, genres, countries and duration. Genres and countries are only
     * separated by a space, the countries are the ones found at the end of the list.
     */
    private function addInfos(Program $program, string $infos): void
    {
        if (preg_match('/^(\d{4})\.\s*(.*)$/u', $infos, $matches)) {
            $program->setDate($matches[1]);
            $infos = $matches[2];
        }
        $parts = array_map('trim', explode(',', $infos));
        if (preg_match('/^\d+h(\d+)?$/', (string) end($parts))) {
            array_pop($parts);
        }
        // The first country is at the end of the last genre, the following ones are separated by commas
        foreach ($parts as $index => $part) {
            foreach (self::getCountryNames() as $country) {
                if (str_ends_with($part, ' ' . $country) || $part === $country) {
                    $genres = array_slice($parts, 0, $index);
                    $beforeCountry = trim(substr($part, 0, -strlen($country)));
                    if ($beforeCountry !== '') {
                        $genres[] = $beforeCountry;
                    }
                    foreach ($genres as $genre) {
                        $program->addCategory($genre);
                    }
                    $program->setCountry(implode(' - ', array_merge([$country], array_slice($parts, $index + 1))));

                    return;
                }
            }
        }
        foreach ($parts as $genre) {
            $program->addCategory($genre);
        }
    }

    private function addCredits(Program $program, string $html): void
    {
        // The role is after a space (entities are already decoded: a non-breaking space is the character U+00A0)
        $role = '(?:(?:&nbsp;|\x{A0}|\s)*\(.*?\))?';
        $pattern = '/(Présenté par|De|avec|Avec)((?:\s*<a [^>]*>.*?<\/a>' . $role . '\s*,?)+)/su';
        if (!preg_match_all($pattern, $html, $groups, PREG_SET_ORDER)) {
            return;
        }
        foreach ($groups as $group) {
            preg_match_all('/<a [^>]*>(.*?)<\/a>(?:(?:&nbsp;|\x{A0}|\s)*\((.*?)\))?/su', $group[2], $people, PREG_SET_ORDER);
            foreach ($people as $person) {
                $program->addCredit($this->cleanText($person[1]), self::CREDITS[$group[1]], !empty($person[2]) ? $this->cleanText($person[2]) : null);
            }
        }
    }

    private function cleanText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    /**
     * @return string[] Names of the countries in French, the longest first
     */
    private static function getCountryNames(): array
    {
        static $names = null;
        if ($names === null) {
            $names = [];
            foreach (\ResourceBundle::create('fr', 'ICUDATA-region') ?->get('Countries') ?? [] as $code => $name) {
                if (!is_numeric($code) && strlen((string) $code) === 2) {
                    $names[] = $name;
                }
            }
            // Names used by the website, which differ from the usual ones
            array_push($names, 'Etats-Unis', 'Grande-Bretagne', 'Afrique du Sud', 'Corée du Sud', 'URSS');
            usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));
        }

        return $names;
    }

    /**
     * @param array{id: int, list: int} $channel
     */
    public function generateUrl(array $channel, string $date): string
    {
        return self::BASE_URL . '/television/programmes-chaine.html?' . http_build_query([
            'np_television_fronttvprogramschannelfiltertype' => [
                'date' => $date,
                'tvChannelListId' => $channel['list'],
                'tvChannelId' => $channel['id'],
            ],
        ]);
    }
}
