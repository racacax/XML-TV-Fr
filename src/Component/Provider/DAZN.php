<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class DAZN extends AbstractProvider implements ProviderInterface
{
    private static $WEEKS_TIMESTAMP = [0, 1724012100, 1724616900, 1725221700, 1726417800, 1726961400, 1727566200, 1728171000, 1729380600, 1729985400, 1730593800, 1731198600, 1732408200, 1733013000, 1733617800, 1734222600, 1736037000, 1736641800, 1737246600, 1737851400, 1738456200, 1739061000, 1739665800, 1740270600, 1740875400, 1741480200, 1742085000, 1743294600, 1743895800, 1744500600, 1745105400, 1745710200, 1746315000, 1746919800];
    private static $MATCH_DURATION = 7200;
    private $jsonPerDay;

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_dazn.json'), $priority ?? 0.1);
        $this->jsonPerDay = [];
    }

    private function getProgramTitle(array $match)
    {
        $title = $match['home']['clubIdentity']['officialName'] . ' - ' . $match['away']['clubIdentity']['officialName'];
        $isDAZN = false;
        $name = ';';
        foreach ($match['broadcasters']['local'] as $broadcaster) {
            $name = $broadcaster['name']['fr-FR'];

            if ($broadcaster['code'] == 'DAZ') {
                $isDAZN = true;

                break;
            }
        }
        if (!$isDAZN) {
            $title .= " (A suivre sur $name)";
        }

        return $title;
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $startCurrentDate = strtotime($date);
        $endCurrentDate = $startCurrentDate + 86400;
        $channelId = $this->getChannelsList()[$channel];
        $url = $this->generateUrl($channelObj, new \DateTimeImmutable($date));
        if (!isset($this->jsonPerDay[$url])) {
            $this->jsonPerDay[$url] = json_decode($this->getContentFromURL($url), true);
        }
        $json = $this->jsonPerDay[$url];
        if (count($json['matches']) == 0) {
            return false;
        }
        $week = $this->getWeek(new \DateTimeImmutable($date));

        if ($channelId == 'multiplex') {
            $this->generateMultiplex($channelObj, $json['matches'], $startCurrentDate, $endCurrentDate, $week);
        } else {
            $match = $json['matches'][$channelId - 1];
            $start = strtotime($match['date']);
            $end = strtotime($match['date']) + self::$MATCH_DURATION;
            $entries = [['startTime' => $startCurrentDate, 'endTime' => min($start, $endCurrentDate), 'prefix' => 'Prochain match : '],
                ['startTime' => $start, 'endTime' => $end, 'prefix' => ''],
                ['startTime' => max($end, $startCurrentDate), 'endTime' => $endCurrentDate, 'prefix' => 'Match précédent : ']];
            foreach ($entries as $entry) {
                if($entry['startTime'] > $endCurrentDate || $entry['endTime'] < $startCurrentDate) {
                    continue;
                }
                $program = new Program($entry['startTime'], $entry['endTime']);
                $program->addCategory('Football');
                $program->addDesc("Ligue 1 - Semaine ${week}");
                $program->addTitle($entry['prefix'] . $this->getProgramTitle($match));
                $channelObj->addProgram($program);
            }
        }

        return $channelObj;
    }

    private function getWeek(\DateTimeImmutable $date): int
    {
        $timestamp = $date->getTimestamp();
        $value = 0;
        foreach (array_reverse(array_flip(self::$WEEKS_TIMESTAMP), true) as $weekTimestamp => $week) {
            if ($timestamp >= $weekTimestamp) {
                $value = $week;

                break;
            }
        }

        return $value + 1;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $week = $this->getWeek($date);

        return "https://ma-api.ligue1.fr/championship-matches/championship/1/game-week/$week?season=2024";
    }

    private function generateMultiplex(Channel $channel, array $matches, int $startCurrentDate, int $endCurrentDate, int $week)
    {
        $groupedMatches = [];
        $lastMatch = null;
        $lastEndTime = $startCurrentDate;
        foreach ($matches as $match) {
            $startTime = strtotime($match['date']);
            $endTime = $startTime + self::$MATCH_DURATION;
            if(is_null($lastMatch) || $lastMatch['endTime'] <= $startTime) {
                $lastMatch = ['startTime' => $startTime, 'endTime' => $endTime, 'matches' => [['startTime' => $startTime, 'title' => $this->getProgramTitle($match)]]];
            } else {
                $lastMatch['endTime'] = $endTime;
                $lastMatch['matches'][] = ['startTime' => $startTime, 'title' => $this->getProgramTitle($match)];
                array_pop($groupedMatches);
            }
            $groupedMatches[] = $lastMatch;
        }
        foreach($groupedMatches as $groupedMatch) {
            $start = $groupedMatch['startTime'];
            $end = $groupedMatch['endTime'];
            if($end < $startCurrentDate) {
                continue;
            }
            $titles = array_map(function ($match) { return $match['title']; }, $groupedMatch['matches']);
            if(count($titles) == 1) {
                $title = $titles[0];
            } else {
                $title = 'Multiplex';
            }
            $endTime = min($start, $endCurrentDate);
            $entries = [['startTime' => $lastEndTime, 'endTime' => $endTime, 'value' => 'Match à venir'],
                ['startTime' => $start, 'endTime' => $end, 'value' => $title]];

            foreach ($entries as $entry) {
                if($entry['startTime'] > $endCurrentDate || $entry['endTime'] < $startCurrentDate || $entry['startTime']  === $entry['endTime']) {
                    continue;
                }
                $lastEndTime = $entry['endTime'];
                $program = new Program($entry['startTime'], $entry['endTime']);
                $program->addCategory('Football');
                $program->addDesc("Ligue 1 - Semaine $week :\n".implode("\n", $titles));
                $program->addTitle($entry['value']);
                $channel->addProgram($program);
            }
        }

    }
}
