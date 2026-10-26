<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use DateTimeImmutable;
use Exception;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Tele7Jours extends AbstractProvider implements ProviderInterface
{
    private static array $DAYS = ['Mon' => 'lundi', 'Tue' => 'mardi', 'Wed' => 'mercredi', 'Thu' => 'jeudi', 'Fri' => 'vendredi', 'Sat' => 'samedi', 'Sun' => 'dimanche'];
    private bool $enableDetails;
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tele7jours.json'), $priority ?? 0.6);

        if (isset($extraParam['tele7jours_enable_details'])) {
            $this->enableDetails = $extraParam['tele7jours_enable_details'];
        } else {
            $this->enableDetails = true;
        }
    }

    private function getDayLabel(DateTimeImmutable $date): string
    {
        $date = $date->setTime(0, 0, 0);
        $dateYmd = $date->format('Y-m-d');
        $today = (new DateTimeImmutable())->setTime(0, 0, 0);
        $weekAfter = $today->modify('+6 days');
        $yesterday = $today->modify('-1 day');
        if ($date < $yesterday) {
            throw new Exception('Date is too early !');
        } elseif ($dateYmd == $yesterday->format('Y-m-d')) {
            return 'hier';
        } elseif ($dateYmd == $today->format('Y-m-d')) {
            return '';
        } else {
            $dateDay = @self::$DAYS[$date->format('D')];
            if (empty($dateDay)) {
                throw new Exception('Invalid day date !');
            }
            if ($date > $weekAfter) {
                $dateDay .= 'prochain';
            }

            return $dateDay;
        }
    }
    private function parseProgram(DateTimeImmutable $startDateObj, ?int $duration, string $p): ?Program
    {
        preg_match('/<div class="tvgrid-broadcast__details-title">(.*?)<\/div>/s', $p, $title);
        preg_match('/<div class="tvgrid-broadcast__details-season">(.*?)<\/div>/s', $p, $subtitleDetails);
        preg_match('/<div class="tvgrid-broadcast__subdetails">(.*?)<\/div>/s', $p, $subDetails);
        // The poster is a protocol-relative <img src> (older markups used a srcset)
        preg_match('/<div class="tvgrid-broadcast__poster">(.*?)<\/picture>/s', $p, $poster);
        preg_match('/srcset="(.*?)"/s', $poster[1] ?? '', $imgs);
        preg_match('/<img[^>]*?\ssrc="(.*?)"/s', $poster[1] ?? '', $posterSrc);
        preg_match('/href="(.*?)"/s', $p, $url);
        $subDetailsSplit = explode('|', $subDetails[1]);
        $isPremiere = in_array('Inédit', array_map('trim', $subDetailsSplit), true);
        if ($duration === null) {
            // Without a duration, the end of the program is unknown
            return null;
        }
        $endTimeObj = $startDateObj->setTimestamp($startDateObj->getTimestamp() + $duration * 60);
        $programObj = new Program($startDateObj, $endTimeObj);
        if ($isPremiere) {
            $programObj->setPremiere();
        }
        $programObj->addTitle(trim(strip_tags($title[1])));
        $programObj->addCategory(trim(end($subDetailsSplit)));
        if (@$subtitleDetails[1]) {
            $subtitleDetailsSplit = explode('|', $subtitleDetails[1]);
            $subtitleItems = [];
            foreach ($subtitleDetailsSplit as $subtitleDetail) {
                $subtitleDetail = trim($subtitleDetail);
                if (preg_match('/^(?:S(\d+)\s*)?E(\d+)$/i', $subtitleDetail, $matches)) {
                    $season  = $matches[1] ? (int) $matches[1] : 1;
                    $episode = (int) $matches[2];
                    $programObj->setEpisodeNum($season, $episode);
                } else {
                    $subtitleItems[] = $subtitleDetail;
                }
            }
            if ($subtitleItems) {
                $subtitle = join(' | ', $subtitleItems);
                $programObj->addSubTitle($subtitle);
            }
        }
        if (!empty($imgs[1])) {
            $imgs = explode(',', $imgs[1]);
            $img = explode(' ', trim(end($imgs)))[0];
            $programObj->addIcon($img);
        } elseif (!empty($posterSrc[1])) {
            $programObj->addIcon(str_starts_with($posterSrc[1], '//') ? 'https:' . $posterSrc[1] : $posterSrc[1]);
        }

        if ($this->enableDetails && isset($url[1])) {
            // Links of the grid are relative
            $this->addDetails($programObj, str_starts_with($url[1], '/') ? 'https://www.programme-television.org' . $url[1] : $url[1]);
        }

        return $programObj;
    }

    private function getTagName(string $castingTag): string
    {
        return match($castingTag) {
            'Réalisateur' => 'director',
            'Producteur' => 'producer',
            default => 'guest'
        };
    }
    public function addDetails(Program $programObj, string $url): void
    {
        $content = $this->getContentFromURL($url);
        preg_match('/<p class="program-details__summary-text">(.*?)<\/p>/s', $content, $details);
        preg_match_all('/<li class="casting__item">.*?<p class="casting__name">(.*?)<\/p>.*?<span class="casting__role">(.*?)<\/span>.*?<\/li>/s', $content, $casting);
        if ($casting[1]) {
            for ($i = 0; $i < count($casting[1]); $i++) {
                $tag = $this->getTagName($casting[2][$i]);
                if ($tag == 'guest') {
                    $actorName = $casting[1][$i];
                    $filmName = $casting[2][$i];
                    $programObj->addCredit("$actorName ($filmName)", $tag);
                } else {
                    $programObj->addCredit($casting[1][$i], $tag);
                }
            }
        }
        if (!empty($details[1])) {
            $programObj->addDesc(strip_tags($details[1]));
        }
        // "France - Belgique  - 2015": countries, then year
        preg_match('/<h2 class="program-details__info-title">Production<\/h2>\s*<span class="program-details__info-text">(.*?)<\/span>/s', $content, $production);
        if (!empty($production[1])) {
            $parts = array_filter(array_map('trim', explode('-', strip_tags($production[1]))));
            if (preg_match('/^\d{4}$/', (string) end($parts))) {
                $programObj->setDate((string) array_pop($parts));
            }
            if (!empty($parts)) {
                $programObj->setCountry(implode(' - ', $parts));
            }
        }
    }
    /**
     * The website only gives the time of the programs. The grid of a day goes from about 05:00 to about 05:00 on the next day:
     * the date changes every time the time goes back, and the night of a day is at the end of the grid of the day before.
     * @return array<int, array{start: DateTimeImmutable, duration: ?int, html: string}>
     */
    private function getEntries(Channel $channelObj, DateTimeImmutable $day): array
    {
        $entries = [];
        $previousEnd = null;
        foreach ([$day->modify('-1 day'), $day] as $pageDate) {
            try {
                $dayLabel = $this->getDayLabel($pageDate);
            } catch (Exception $e) {
                // The grid of the day before is not available for the oldest days
                if ($pageDate == $day) {
                    throw $e;
                }

                continue;
            }
            $content = $this->getContentFromURL($this->generateUrl($channelObj, $dayLabel));
            $previousMinutes = null;
            foreach (array_slice(explode('class="tvgrid-broadcast__item', $content), 1) as $block) {
                if (!preg_match('/<div class="tvgrid-broadcast__details-time">\s*(\d{1,2})[h:](\d{2})\s*<\/div>/s', $block, $time)) {
                    continue;
                }
                $minutes = (int) $time[1] * 60 + (int) $time[2];
                // The time goes back by more than an hour when the date changes (it goes back by less when the clocks change)
                if ($previousMinutes !== null && $minutes < $previousMinutes - 60) {
                    $pageDate = $pageDate->modify('+1 day');
                }
                $previousMinutes = $minutes;
                $start = $this->resolveRepeatedHour(
                    new DateTimeImmutable($pageDate->format('Y-m-d') . sprintf(' %02d:%02d', $time[1], $time[2]), new \DateTimeZone('Europe/Paris')),
                    $previousEnd
                );
                $duration = $this->getDuration($block);
                $previousEnd = $duration !== null ? $start->setTimestamp($start->getTimestamp() + $duration * 60) : null;
                $entries[$start->getTimestamp()] = ['start' => $start, 'duration' => $duration, 'html' => $block];
            }
        }
        ksort($entries);

        return array_values($entries);
    }

    /**
     * The website writes durations as "41 mn" or "41 mins"
     */
    private function getDuration(string $block): ?int
    {
        if (!preg_match('/<div class="tvgrid-broadcast__subdetails">(.*?)<\/div>/s', $block, $subDetails)) {
            return null;
        }
        foreach (explode('|', $subDetails[1]) as $subDetail) {
            if (preg_match('/^\s*(\d+)\s*(?:mn|mins?)\s*$/', $subDetail, $duration)) {
                return (int) $duration[1];
            }
        }

        return null;
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        // Details are only requested for the programs of the day
        $entries = array_values(array_filter(
            $this->getEntries($channelObj, $minDate),
            fn (array $entry) => $entry['start'] >= $minDate && $entry['start'] <= $maxDate
        ));

        $count = count($entries);
        foreach ($entries as $index => $entry) {
            $this->setStatus(round($index * 100 / $count, 2) . ' %');
            $programObj = $this->parseProgram($entry['start'], $entry['duration'], $entry['html']);
            if ($programObj !== null) {
                $channelObj->addProgram($programObj);
            }
        }

        return $channelObj->getProgramCount() > 0 ? $channelObj : false;
    }

    public function generateUrl(Channel $channel, string $dayLabel): string
    {
        $channelId = $this->channelsList[$channel->getId()];

        return "https://www.programme-television.org/tv/chaines/$channelId/$dayLabel";
    }

}
