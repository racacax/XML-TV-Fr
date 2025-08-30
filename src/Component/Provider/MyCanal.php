<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

// Edited by lazel from https://github.com/lazel/XML-TV-Fr/blob/master/classes/MyCanal.php
class MyCanal extends AbstractProvider implements ProviderInterface
{
    protected static array $apiKey = [];
    protected string $region = 'fr';
    protected bool $enableDetails;
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        if (isset($extraParam['mycanal_enable_details'])) {
            $this->enableDetails = $extraParam['mycanal_enable_details'];
        } else {
            $this->enableDetails = true;
        }
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_mycanal.json'), $priority ?? 0.7);
    }

    protected function getApiKey()
    {
        if (!isset(self::$apiKey[$this->region])) {
            $result = $this->getContentFromURL('https://www.canalplus.com/' . $this->region . '/programme-tv/');
            $token = @explode('"', explode('"token":"', $result)[1])[0];
            if (empty($token)) {
                throw new \Exception('Impossible to retrieve MyCanal API Key');
            }
            self::$apiKey[$this->region] = $token;
        }

        return self::$apiKey[$this->region];
    }

    private function getProgramList(Channel $channelObj, string $date): array
    {
        $programList = [];
        $lastIndex = -1;
        [$minStart, $maxStart] = $this->getMinMaxDate($date);
        $startDate = (new \DateTimeImmutable($date))->modify('-1 day');
        while (true) {
            $this->setStatus('Délimitation des programmes');
            $url = $this->generateUrl($channelObj, $startDate);
            $json = @json_decode($this->getContentFromURL($url), true);
            if (empty($json['timeSlices'])) {
                return $programList;
            }
            $programsSummary = [];
            foreach ($json['timeSlices'] as $section) {
                $programsSummary = array_merge($programsSummary, $section['contents']);
            }
            if (count($programsSummary) == 0) {
                return $programList;
            }
            foreach ($programsSummary as $program) {
                $startTime = new DateTimeImmutable('@'.($program['startTime'] / 1000));
                if ($lastIndex >= 0) {
                    $programList[$lastIndex]['endTime'] = $startTime;
                }
                if ($startTime >= $minStart && $startTime <= $maxStart) {
                    $lastIndex++;
                    $programList[] = [
                        'startTime'     => $startTime,
                        'title'         => $program['title'],
                        'subTitle'      => @$program['subtitle'] ?? null,
                        'URLPage'       => $program['onClick']['URLPage']
                    ];
                } elseif ($startTime > $maxStart) {
                    return $programList;
                }
            }
            $startDate = $startDate->modify('+1 day');
        }
    }

    private function fetchDetails(array &$programList): void
    {
        $count = count($programList);
        $promises = [];
        foreach ($programList as $index => $program) {
            $percent = round($index * 100 / $count, 2) . ' %';
            $this->setStatus($percent);
            $url = $program['URLPage'];
            $promises[] = $this->client->getAsync($url)->then(function ($response) use (&$programList, $index) {
                $detail = json_decode($response->getBody()->getContents(), true);
                $programList[$index]['title'] = @$detail['detail']['informations']['title'] ?? $programList[$index]['title'] ?? 'Aucun titre';
                $programList[$index]['subTitle'] = @$detail['episodes']['contents'][0]['subtitle'] ?? $programList[$index]['subTitle'];
                $programList[$index]['description'] = @$detail['episodes']['contents'][0]['summary'] ?? @$detail['detail']['informations']['summary'];
                $programList[$index]['season'] = @$detail['detail']['selectedEpisode']['seasonNumber'];
                $programList[$index]['episode'] = @$detail['detail']['selectedEpisode']['episodeNumber'];
                $programList[$index]['genre'] = @$detail['tracking']['dataLayer']['genre'];
                $programList[$index]['genreDetailed'] = @$detail['tracking']['dataLayer']['subgenre'];

                $icon = $detail['episodes']['contents'][0]['URLImage'] ?? @$detail['detail']['informations']['URLImage'];
                $icon = str_replace(['{resolutionXY}', '{imageQualityPercentage}'], ['640x360', '80'], $icon ?? '');
                $programList[$index]['icon'] = $icon;
                $programList[$index]['year'] = @$detail['detail']['informations']['productionYear'];
                $parentalRating = $detail['episodes']['contents'][0]['parentalRatings'][0]['value'] ?? @$detail['detail']['informations']['parentalRatings'][0]['value'];
                $csa = match ($parentalRating) {
                    '2' => '-10',
                    '3' => '-12',
                    '4' => '-16',
                    '5' => '-18',
                    default => 'Tout public',
                };
                $programList[$index]['csa'] = $csa;
            });
            usleep(100000); # To avoid rate limit
        }

        try {
            Utils::all($promises)->wait();
        } catch (\Throwable $t) {
            ##return false; We allow failures on details
        }
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $this->region = $this->channelsList[$channel]['region'];

        $programList = $this->getProgramList($channelObj, $date);

        if ($this->enableDetails) {
            $this->fetchDetails($programList);
        }

        foreach ($programList as $program) {
            $programObj = new Program($program['startTime'], $program['endTime']);
            $programObj->addTitle($program['title']);
            $programObj->addSubtitle(@$program['subTitle']);
            $programObj->addDesc(@$program['description']);
            $programObj->setEpisodeNum(@$program['season'], @$program['episode']);
            $programObj->addCategory(@$program['genre']);
            $programObj->addCategory(@$program['genreDetailed']);
            $programObj->setIcon(@$program['icon']);
            $programObj->setRating(@$program['csa']);
            $channelObj->addProgram($programObj);
        }

        return $channelObj;
    }


    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->channelsList[$channel->getId()]['id'];
        $day = round(($date->getTimestamp() - strtotime(date('Y-m-d'))) / 86400);

        return 'https://hodor.canalplus.pro/api/v2/mycanal/channels/' . $this->getApiKey() . '/' . $channelId . '/broadcasts/day/'. $day;
    }
}
