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

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);

        if (!$this->channelExists($channel)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $dateObj = new \DateTime($date);
        $dateObj->modify('-1 day');
        for ($i = 0; $i < 3; $i++) {
            $content = html_entity_decode($this->getContentFromURL($this->generateUrl($channelObj, $dateObj), [
                'Host' => 'www.tv5monde.com',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:127.0) Gecko/20100101 Firefox/127.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'fr-FR,fr-CA;q=0.8,en;q=0.5,en-US;q=0.3',
                'DNT' => '1',
                'Sec-GPC' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Priority' => 'u=1'
            ]));
            $programs = explode('<li class="hourly-list', $content);
            unset($programs[0]);
            $timezone = explode('<div class="filter-country select">', $content)[1];
            $timezone = explode('</li>', explode('class="active"', $timezone)[1])[0];
            $timezone = explode('"', explode('selected-timezone=', $timezone)[1])[0];
            date_default_timezone_set($timezone);
            foreach ($programs as $p) {
                preg_match('/<p class="time-start">(.*?)<\/p>/s', $p, $startTime);
                preg_match('/<p class="time-duration">(.*?)<\/p>/s', $p, $duration);
                preg_match('/<p class="program-type">(.*?)<\/p>/s', $p, $genre);
                preg_match('/<h4 class="program-title">(.*?)<\/h4>/s', $p, $title);
                preg_match('/<p class="program-summary">(.*?)<\/p>/s', $p, $summary);
                preg_match('/src="(.*?)"/', $p, $image);
                $startDate = strtotime($dateObj->format('Y-m-d') . ' ' . $startTime[1]);
                if (str_contains($duration[1], 'mn')) {
                    $duration[1] = intval(explode('mn', $duration[1])[0]) * 60;
                } else {
                    $duration[1] = explode('h', $duration[1]);
                    $duration[1] = intval($duration[1][0]) * 3600 + intval($duration[1][1]) * 60;
                }
                $endDate = $startDate + $duration[1];
                $summary[1] = str_replace('<br />', '', $summary[1] ?? '');
                $startDateObj = new \DateTime('@'.$startDate);
                if ($startDateObj < $minDate) {
                    continue;
                } elseif ($startDateObj > $maxDate) {
                    return $channelObj;
                }
                $program = new Program($startDate, $endDate);
                $program->addTitle($title[1] ?? 'Aucun titre');
                $program->addDesc($summary[1]);
                $program->addCategory($genre[1] ?? 'Inconnu');
                if (!empty($image)) {
                    $program->setIcon($image[1]);
                }
                $channelObj->addProgram($program);
            }
            $dateObj->modify('+1 day');
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];

        return 'https://www.tv5monde.com/tv/programmes/' . $channel_id . '?day=' . $date->format('Y-m-d');
    }
}
