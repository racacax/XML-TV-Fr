<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.1 : 25/02/2023
 */
class TV5Global extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tv5global.json'), $priority ?? 0.6);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);

        if (!$this->channelExists($channel)) {
            return false;
        }
        $content = html_entity_decode($this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date))));
        $programs = explode('<li class="hourly-list', $content);
        unset($programs[0]);
        $timezone = explode('<div class="filter-country select">', $content)[1];
        $timezone = explode('<p>', $timezone)[1];
        $timezone = explode(" (", $timezone)[0];
        $timezone = explode(" ", $timezone);
        $timezone = end($timezone);
        foreach ($programs as $p) {
            preg_match('/<p class="time-start">(.*?)<\/p>/s', $p, $startTime);
            preg_match('/<p class="time-duration">(.*?)<\/p>/s', $p, $duration);
            preg_match('/<p class="program-type">(.*?)<\/p>/s', $p, $genre);
            preg_match('/<h4 class="program-title">(.*?)<\/h4>/s', $p, $title);
            preg_match('/<p class="program-summary">(.*?)<\/p>/s', $p, $summary);
            preg_match('/src="(.*?)"/', $p, $image);
            $startDate = strtotime($date . " " . $startTime[1] . $timezone);
            if (str_contains($duration[1], "mn")) {
                $duration[1] = intval(explode("mn", $duration[1])[0]) * 60;
            } else {
                $duration[1] = explode("h", $duration[1]);
                $duration[1] = intval($duration[1][0]) * 3600 + intval($duration[1][1]) * 60;
            }
            $endDate = $startDate + $duration[1];
            $summary[1] = str_replace('<br />', '', $summary[1] ?? "");
            $program = new Program($startDate, $endDate);
            $program->addTitle($title[1] ?? "Aucun titre");
            $program->addDesc($summary[1]);
            $program->addCategory($genre[1] ?? "Inconnu");
            if(!empty($image))
                $program->setIcon($image[1]);
            $channelObj->addProgram($program);
        }
        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];

        return 'https://www.tv5monde.com/tv/programmes/'.$channel_id.'?day='.$date->format("Y-m-d");
    }
}
