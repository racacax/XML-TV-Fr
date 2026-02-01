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

class Tele2Semaines extends AbstractProvider implements ProviderInterface
{
    private static array $DAYS = ['Mon' => 'lundi', 'Tue' => 'mardi', 'Wed' => 'mercredi', 'Thu' => 'jeudi', 'Fri' => 'vendredi', 'Sat' => 'samedi', 'Sun' => 'dimanche'];

    private bool $enableDetails;

    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        if (isset($extraParam['tele2semaines_enable_details'])) {
            $this->enableDetails = $extraParam['tele2semaines_enable_details'];
        } else {
            $this->enableDetails = true;
        }
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tele2semaines.json'), $priority ?? 0.6);
    }

    /**
     * @throws Exception
     */
    private function getDayLabel(DateTimeImmutable $date): string
    {
        $date = $date->setTime(0, 0, 0);
        $dateYmd = $date->format('Y-m-d');
        $today = (new DateTimeImmutable())->setTime(0, 0, 0);
        $weekAfter = $today->modify('+6 days');
        if ($date < $today) {
            throw new Exception('Date is too early !');
        } elseif ($dateYmd == $today->format('Y-m-d')) {
            return '';
        } else {
            $dateDay = @self::$DAYS[$date->format('D')];
            if (empty($dateDay)) {
                throw new Exception('Invalid day date !');
            }
            if ($date > $weekAfter) {
                throw new Exception('Date is too late !');
            }

            return $dateDay;
        }
    }
    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $day = new \DateTimeImmutable($date);
        $dayAfter = $day->modify('+1 day');
        $content = html_entity_decode($this->getContentFromURL($this->generateUrl($day, $channelObj)), ENT_QUOTES);
        $contentDayAfter = $this->getContentFromURL($this->generateUrl($dayAfter, $channelObj));
        $programs = explode('class="broadcastCard"', $content);
        unset($programs[0]);

        preg_match('/class="broadcastCard-start" datetime="(.*?)"/s', $contentDayAfter, $endLastProgram);

        if (!$endLastProgram[1]) {
            return false;
        }
        $count = count($programs);
        for ($i = 1; $i <= $count; $i++) {
            $percent = round($i * 100 / $count, 2) . ' %';
            $this->setStatus($percent);
            preg_match('/class="broadcastCard-start" datetime="(.*?)"/s', $programs[$i], $start);
            $start = $start[1];
            if ($i == $count) {
                $end = $endLastProgram[1];
            } else {
                preg_match('/class="broadcastCard-start" datetime="(.*?)"/s', $programs[$i + 1], $end);
                $end = $end[1];
            }
            $programObj = new Program(new DateTimeImmutable($start), new DateTimeImmutable($end));

            preg_match('/href="(.*?)"/s', $programs[$i], $href);
            preg_match('/class="broadcastCard-format">(.*?)<\/p>/s', $programs[$i], $genre);
            preg_match('/<h2 class="broadcastCard-title">(.*?)<\/h2>/s', $programs[$i], $title);
            preg_match('/srcset="(.*?)"/s', $programs[$i], $src);
            preg_match('/<p class="broadcastCard-synopsis">(.*?)<\/p>/s', $programs[$i], $synopsis);
            preg_match('/aria-label="Note de (.*?) sur (.*?)"/s', $programs[$i], $note);

            $programObj->addTitle(trim(strip_tags($title[1])));
            $programObj->addDesc(trim(strip_tags($synopsis[1] ?? 'Aucune description')));
            $programObj->addCategory(trim(strip_tags($genre[1])));
            if (@$note[1]) {
                $programObj->addStarRating(intval($note[1]), intval($note[2]));
            }
            $src = str_replace('109x70', '1280x720', explode(' ', $src[1] ?? '')[0]);
            $programObj->addIcon($src);
            $channelObj->addProgram($programObj);
            if ($href[1] && $this->enableDetails) {
                $this->assignDetails($href[1], $programObj);
            }
        }

        return $channelObj;
    }

    private function getCreditRole(string $credit): string
    {
        return match($credit) {
            'Presentateur' => 'presenter',
            'Acteur' => 'actor',
            'Realisateur' => 'director',
            'Scénariste' => 'writer',
            'Musique' => 'composer',
            default => 'guest'
        };
    }
    private function assignDetails(string $href, Program $programObj): void
    {
        $content = html_entity_decode($this->getContentFromURL($href), ENT_QUOTES);
        $content = str_replace('<button class="overviewDetail-peopleShowMoreButton">Voir plus</button>', '', $content);
        preg_match_all('/<div class="overviewDetail-peopleList">.*?<span class="overviewDetail-title">(.*?): <\/span>(.*?)<\/div>/s', $content, $people);
        if ($people[1]) {
            for ($i = 1; $i < count($people[1]); $i++) {
                $tag = $this->getCreditRole($people[1][$i]);
                $peopleStripped = explode(',', strip_tags($people[2][$i]));
                foreach ($peopleStripped as $person) {
                    $programObj->addCredit(trim($person), $tag);
                }
            }
        }
        preg_match('/<div class="review-content">(.*?)<\/div>/s', $content, $review);
        if (isset($review[1])) {
            $programObj->addReview($review[1], 'Tele 2 Semaines');
        }
    }

    public function generateUrl(\DateTimeImmutable $date, Channel $channel): string
    {
        $dayLabel = $this->getDayLabel($date);
        $channelId = $this->channelsList[$channel->getId()];
        if (empty($dayLabel)) {
            return "https://www.programme.tv/chaine/$channelId/";
        } else {
            return "https://www.programme.tv/chaine/$dayLabel/$channelId/";
        }
    }
}
