<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Oqee extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_oqee.json'), $priority ?? 0.5);
    }

    private function getCustomMatchTitle(string $currentTitle, string $desc): string
    {
        /**
         * Ligue 1+ channels have the match only in description.
         * It has a consistent description we can use to append it to title.
         */
        preg_match('/opposant (.*?) et (.*?)\./s', $desc, $teams);
        if ($teams[1] && $teams[2]) {
            return "$currentTitle | $teams[1] / $teams[2]";
        }

        return $currentTitle;
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $dateObj = new \DateTimeImmutable($date.' 00:00 +00:00');
        $timestamps = array_map(function ($hour) use ($dateObj) { return $dateObj->modify("$hour hours"); }, ['-6', '+0', '+6', '+12', '+18', '+24']);

        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($timestamps as $times) {
            $json = json_decode($this->getContentFromURL($this->generateUrl($channelObj, $times)), true);
            if (empty($json['result']['entries'])) {
                return false;
            }

            foreach ($json['result']['entries'] as $entry) {
                $startDate = new \DateTimeImmutable('@'.$entry['live']['start']);
                if ($startDate < $minDate) {
                    continue;
                } elseif ($startDate > $maxDate) {
                    return $channelObj;
                }
                $program = Program::withTimestamp($entry['live']['start'], $entry['live']['end']);
                $title = $entry['live']['title'];
                $desc = @$entry['live']['description'] ?? 'Aucune description';
                if (str_starts_with($channel, 'Ligue1Plus')) {
                    $title = $this->getCustomMatchTitle($title, $desc);
                }
                $program->addTitle($title);
                $program->addSubtitle(@$entry['live']['sub_title']);
                $program->addDesc($desc);
                $program->addCategory(@$entry['live']['category']);
                $program->addCategory(@$entry['live']['sub_category']);
                $icon = str_replace('h%d', 'h1080', @$entry['pictures']['main'] ?? '');
                $program->setIcon($icon);
                $program->setRating('-'. $entry['live']['parental_rating']);
                $channelObj->addProgram($program);
            }
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, $date): string
    {
        return 'https://api.oqee.net/api/v1/epg/by_channel/'. $this->channelsList[$channel->getId()] .'/'.$date->getTimestamp();
    }
}
