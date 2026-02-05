<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use DateTimeImmutable;
use GuzzleHttp\Client;
use Normalizer;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class LInternaute extends AbstractProvider implements ProviderInterface
{
    private static array $DAYS = ['Mon' => 'lundi', 'Tue' => 'mardi', 'Wed' => 'mercredi', 'Thu' => 'jeudi', 'Fri' => 'vendredi', 'Sat' => 'samedi', 'Sun' => 'dimanche'];
    private static array $MONTHS = [1 => 'janvier', 2 => 'fevrier', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet',8 => 'aout', 9 => 'septembre',10 => 'octobre', 11 => 'novembre',12 => 'decembre'];
    private bool $enableDetails;
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_linternaute.json'), $priority ?? 0.45);

        if (isset($extraParam['linternaute_enable_details'])) {
            $this->enableDetails = $extraParam['linternaute_enable_details'];
        } else {
            $this->enableDetails = true;
        }
    }

    private function getDayLabel(DateTimeImmutable $date): string
    {
        $date = $date->setTime(0, 0, 0);
        if ($date->format('Y-m-d') === date('Y-m-d')) {
            return '';
        }
        $month = self::$MONTHS[intval($date->format('m'))];
        $day = self::$DAYS[$date->format('D')];
        $dayNumber = $date->format('d');
        $year = $date->format('Y');

        return "-$day-$dayNumber-$month-$year";
    }
    private function parseProgram(string $date, string $p): Program
    {
        preg_match('/<div class="grid_col bu_tvprogram_logo">.*?<div>(.*?)<\/div>.*?<div>(.*?)<\/div>.*?<\/div>/s', $p, $times);
        preg_match('/href="(.*?)"/s', $p, $href);
        preg_match('/<span class="bu_tvprogram_typo5">(.*?)<\/span>/s', $p, $desc);
        preg_match('/<span class="bu_tvprogram_typo2">(.*?)<\/span>/s', $p, $title);
        preg_match('/<span class="bu_tvprogram_typo3">(.*?)<\/span>/s', $p, $subtitle);
        preg_match('/<span class="bu_tvprogram_typo4">(.*?)<\/span>/s', $p, $category);
        preg_match('/src="(.*?)"/s', $p, $img);
        $startTime = str_replace('h', ':', $times[1]);
        $endTime = str_replace('h', ':', $times[2]);
        $startDateObj = new DateTimeImmutable($date.' '.trim($startTime));
        $endTimeObj = new DateTimeImmutable($date.' '.trim($endTime));
        if ($endTimeObj < $startDateObj) {
            $endTimeObj = $endTimeObj->modify('+1 day');
        }

        $programObj = new Program($startDateObj, $endTimeObj);
        $programObj->addTitle(trim(strip_tags($title[1])));
        if (@$subtitle[1]) {
            $programObj->addSubtitle(trim(strip_tags($subtitle[1])));
        }
        $categorySplited = explode('-', $category[1]);
        $programObj->addCategory(trim($categorySplited[1]));

        if (isset($img[1])) {
            $programObj->addIcon($img[1]);
        }

        if ($this->enableDetails && $href[1]) {
            $this->addDetails($programObj, $href[1]);
        }
        if (count($programObj->getChildren('desc')) == 0) {
            // Add summary only if details didn't load
            $programObj->addDesc(trim(strip_tags($desc[1])));
        }

        return $programObj;
    }

    private function getTagName(string $castingTag): string | null
    {
        $castingTag = Normalizer::normalize($castingTag, Normalizer::FORM_C);

        return match($castingTag) {
            'Réalisateur' => 'director',
            'Producteur' => 'producer',
            'Scénariste' => 'writer',
            'Acteurs' => 'actor',
            'Présentateur' => 'presenter',
            'Avec' => 'actor',
            'Réalisé par' => 'director',
            default => null
        };
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
        $programs = explode('class="bu_tvprogram_grid__line grid_row"', $content);
        unset($programs[0]);
        $count = count($programs);
        for ($i = 1; $i <= $count; $i++) {
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

        return "https://www.linternaute.com/television/programme-$channelId$dayLabel/";
    }

    private function addCredits(Program $programObj, array $credits): void
    {
        for ($i = 0; $i < count($credits[0]); $i++) {
            $creditType = trim($credits[1][$i]);
            $creditsSplited = explode(',', $credits[2][$i]);
            $tag = $this->getTagName($creditType);
            if ($tag) {
                foreach ($creditsSplited as $credit) {
                    $programObj->addCredit(trim(strip_tags($credit)), $tag);
                }
            }
        }
    }
    private function addDetails(Program $programObj, string $link): void
    {
        try {
            if (!str_contains($link, 'https://')) {
                $link = 'https://www.linternaute.com'.$link;
            }
            $content = $this->getContentFromURL($link);

            preg_match('/<div id="top" class="bu_ccmeditor">(.*?)<\/div>/s', $content, $desc);
            if ($desc[1]) {
                $programObj->addDesc(trim(strip_tags($desc[1])));
            } else {
                preg_match('/<p><strong><a id="synopsis" name="synopsis">Synopsis <\/a>- <\/strong>(.*?)<\/p>/s', $content, $desc);
                if ($desc[1]) {
                    $programObj->addDesc(trim(strip_tags($desc[1])));
                }
            }


            preg_match('/<span class="app_stars__note">.*?<span>.*?<span>(.*?)<\/span>/s', $content, $preciseNote);
            if (isset($preciseNote[1])) {
                $stars = floatval($preciseNote[1]);
            } else {
                $stars = count(explode('fill="#FC0"', $content)) - 1;
            }
            if ($stars > 0) {
                $programObj->addStarRating($stars, 5);
            }

            preg_match_all('/<div class="grid_line gutter grid--norwd">.*?<div class="grid_left w25">(.*?)<\/div>.*?<div class="grid_last">.*?<b>(.*?)<\/b>.*?<\/div>.*?<\/div>/s', $content, $credits);
            $this->addCredits($programObj, $credits);

            preg_match_all('/<dl>.*?<dd>(.*?):<\/dd>.*?<dt>(.*?)<\/dt>.*?<\/dl>/s', $content, $credits);
            $this->addCredits($programObj, $credits);

            preg_match('/<span class="bu_tvprogram_broadcasting_pegi">(.*?)<\/span>/s', $content, $csa);
            if (isset($csa[1])) {
                $programObj->setRating(-intval($csa[1]));
            }

            preg_match('/episode_navigation_locator--season".*?>Saison (\d+)<\/a>/s', $content, $season);
            preg_match('/bu_tvprogram_episode_navigation_locator--mobile.*?EP(\d+)<\/span>/s', $content, $episode);

            if (isset($episode[1])) {
                $programObj->setEpisodeNum($season[1], $episode[1]);
            }


        } catch (\Throwable) {
            // we allow failures on details
        }
    }

}
