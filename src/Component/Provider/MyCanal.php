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
    private static array $HEADERS = ['Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
'Accept-Language'=>'fr-FR,fr-CA;q=0.9,en;q=0.8,en-US;q=0.7',
'Accept-Encoding'=>'gzip, deflate, br, zstd',
'Sec-GPC'=>'1',
'Connection'=>'keep-alive',
'Upgrade-Insecure-Requests'=>'1',
'Sec-Fetch-Dest'=>'document',
'Sec-Fetch-Mode'=>'navigate',
'Sec-Fetch-Site'=>'none',
'Sec-Fetch-User'=>'?1',
'Priority'=>'u=0, i'];
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
            $result = $this->getContentFromURL('https://www.canalplus.com/' . $this->region . '/programme-tv/', self::$HEADERS);
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
            $json = @json_decode($this->getContentFromURL($url, self::$HEADERS), true);
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
            $promises[] = $this->client->requestAsync('GET', $url, self::$HEADERS)->then(function ($response) use (&$programList, $index) {
                $detail = json_decode($response->getBody()->getContents(), true);
                print_r($detail);
                $programList[$index]['title'] = @$detail['detail']['informations']['title'] ?? $programList[$index]['title'] ?? 'Aucun titre';
                $programList[$index]['subTitle'] = @$detail['episodes']['contents'][0]['subtitle'] ?? $programList[$index]['subTitle'];
                $programList[$index]['description'] = @$detail['episodes']['contents'][0]['summary'] ?? @$detail['detail']['informations']['summary'];
                $programList[$index]['season'] = @$detail['detail']['selectedEpisode']['seasonNumber'];
                $programList[$index]['episode'] = @$detail['detail']['selectedEpisode']['episodeNumber'];
                $programList[$index]['genre'] = @$detail['tracking']['dataLayer']['genre'];
                $programList[$index]['genreDetailed'] = @$detail['tracking']['dataLayer']['subgenre'];
                $programList[$index]['closedCaptioning'] = @$detail['detail']['informations']['closedCaptioning'];
                $programList[$index]['reviews'] = @$detail['detail']['informations']['reviews'];
                $programList[$index]['productionYear'] = @$detail['detail']['informations']['productionYear'];

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
            $programObj->addSubTitle(@$program['subTitle']);
            $programObj->addDesc(@$program['description']);
            $programObj->setEpisodeNum(@$program['season'], @$program['episode']);
            $programObj->addCategory(@$program['genre']);
            $programObj->addCategory(@$program['genreDetailed']);
            $programObj->addIcon(@$program['icon']);
            $programObj->setRating(@$program['csa']);
            if (@$program['productionYear']) {
                $programObj->setDate(strval($program['productionYear']));
            }
            if (@$program['closedCaptioning']) {
                $programObj->addSubtitles('teletext');
            }
            foreach (($program['reviews'] ?? []) as $review) {
                if (@$review['review']) {
                    $programObj->addReview($review['review'], $review['name']);
                }
                if (@$review['stars']['value']) {
                    $programObj->addStarRating($review['stars']['value'], 5, $review['stars']['type']);
                }
            }
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

    public function getLogo(string $channel): ?string
    {
        parent::getLogo($channel);
        $channelInfo = $this->channelsList[$channel];
        $this->region = $channelInfo['region'];
        $url = "https://hodor.canalplus.pro/api/v2/mycanal/epgGrid/{$this->getApiKey()}/day/0?channelImageColor=white&discoverMode=true";
        $payload = json_decode($this->getContentFromURL($url, self::$HEADERS), true);
        foreach ($payload['channels'] as $channelData) {
            $spl = explode('/', explode('/broadcasts/day/', $channelData['URLChannelSchedule'])[0]);
            $id = end($spl);
            if ($id == strval($channelInfo['id'])) {
                if (isset($channelData['URLLogoChannel'])) {
                    return str_replace('{imageQualityPercentage}', '100', str_replace('{resolutionXY}', '640x480', $channelData['URLLogoChannel'])).'.png';
                }

                break;
            }
        }

        return null;
    }
}
