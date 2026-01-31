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
    private function parseProgram(string $date, string $p): Program
    {
        preg_match('/<div class="tvgrid-broadcast__details-time">(.*?)<\/div>/s', $p, $startTime);
        preg_match('/<div class="tvgrid-broadcast__details-title">(.*?)<\/div>/s', $p, $title);
        preg_match('/<div class="tvgrid-broadcast__details-season">(.*?)<\/div>/s', $p, $subtitleDetails);
        preg_match('/<div class="tvgrid-broadcast__subdetails">(.*?)<\/div>/s', $p, $subDetails);
        preg_match('/srcset="(.*?)"/s', $p, $imgs);
        preg_match('/href="(.*?)"/s', $p, $url);
        $startTime[1] = str_replace('h', ':', $startTime[1]);
        $startDateObj = new DateTimeImmutable($date.' '.$startTime[1]);
        $subDetailsSplit = explode('|', $subDetails[1]);
        $endTimeObj = null;
        $isPremiere = false;
        foreach ($subDetailsSplit as $subDetail) {
            $subDetail = trim($subDetail);
            if (str_ends_with($subDetail, 'mn')) {
                $subDetail = str_replace('mn', ' minutes', $subDetail);
                $endTimeObj = $startDateObj->modify("+$subDetail");
            } elseif ($subDetail == 'Inédit') {
                $isPremiere = true;
            }
        }
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
        if ($imgs[1]) {
            $imgs = explode(',', $imgs[1]);
            $img = explode(' ', trim(end($imgs)))[0];
            $programObj->addIcon($img);
        }

        if ($this->enableDetails && $url[1]) {
            $this->addDetails($programObj, $url[1]);
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
        if ($details[1]) {
            $programObj->addDesc(strip_tags($details[1]));
        }
    }
    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        [$minDate, $_] = $this->getMinMaxDate($date);
        $dayLabel = $this->getDayLabel($minDate);
        $content = $this->getContentFromURL($this->generateUrl($channelObj, $dayLabel));
        $programs = explode('class="tvgrid-broadcast__item', $content);

        $count = count($programs);
        for ($i = 1; $i < $count; $i++) {
            $percent = round($i * 100 / $count, 2) . ' %';
            $this->setStatus($percent);
            $p = $programs[$i];
            $programObj = $this->parseProgram($date, $p);
            $channelObj->addProgram($programObj);

        }


        return $channelObj;
    }

    public function generateUrl(Channel $channel, string $dayLabel): string
    {
        $channelId = $this->channelsList[$channel->getId()];

        return "https://www.programme-television.org/tv/chaines/$channelId/$dayLabel";
    }

}
