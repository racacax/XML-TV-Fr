<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

// Edited by lazel from https://github.com/lazel/XML-TV-Fr/blob/master/classes/MyCanal.php
class MyCanal extends AbstractProvider implements ProviderInterface
{
    protected static array $apiKey = [];
    protected string $region = 'fr';
    protected mixed $proxy = null;
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        if(isset($extraParam['mycanal_proxy'])) {
            $this->proxy = $extraParam['mycanal_proxy'];
        }
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_mycanal.json'), $priority ?? 0.7);
    }

    protected function getApiKey()
    {
        if(!isset(self::$apiKey[$this->region])) {
            $result = $this->getContentFromURL('https://www.canalplus.com/' . $this->region . '/programme-tv/');
            $token = @explode('"', explode('"token":"', $result)[1])[0];
            if(empty($token)) {
                throw new \Exception('Impossible to retrieve MyCanal API Key');
            }
            self::$apiKey[$this->region] = $token;
        }

        return self::$apiKey[$this->region];
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $this->region = $this->channelsList[$channel]['region'];
        //@todo: add cache (next PR?)
        $url1 = $this->generateUrl($channelObj, $datetime = new \DateTimeImmutable($date));
        $url2 = $this->generateUrl($channelObj, $datetime->modify('+1 days'));

        try {
            /**
             * @var Response[]
             */
            $response = Utils::all([
                '1' => $this->client->getAsync($url1),
                '2' => $this->client->getAsync($url2)
            ])->wait();
        } catch (\Throwable $t) {
            return false;
        }
        $json = json_decode((string)$response['1']->getBody(), true);
        $json2 = json_decode((string)$response['2']->getBody(), true);

        if (empty($json['timeSlices'])) {
            return false;
        }

        $all = [];
        foreach ($json['timeSlices'] as $section) {
            $all = array_merge($all, $section['contents']);
        }

        if (@$nd = $json2['timeSlices'][0]['contents'][0]) {
            $all[] = $nd;
        }

        $programs = [];
        $lastTime = 0;
        $count = count($all);
        $begin = microtime(true);
        $promises = [];
        foreach ($all as $index => $program) {
            $percent = round($index * 100 / $count, 2) . ' %';
            $this->setStatus($percent);
            $url = $program['onClick']['URLPage'];
            if(!is_null($this->proxy)) {
                $url = $this->proxy[0].urlencode(base64_encode($url)).$this->proxy[1];
            }

            $promises[$program['onClick']['URLPage']] = $this->client->getAsync($url);
            usleep(100000); # To avoid rate limit
        }

        try {
            $response = Utils::all($promises)->wait();
        } catch (\Throwable $t) {
            ##return false; We allow failures on details
        }

        foreach ($all as $index => $program) {
            $responseBody = null;
            if(!is_null($response[$program['onClick']['URLPage']])) {
                $responseBody = $response[$program['onClick']['URLPage']]->getBody();
            }
            if(!is_null($responseBody)) {
                $detail = json_decode((string)$responseBody, true);
            } else {
                $detail = [];
            }

            $startTime = $program['startTime'] / 1000;

            $parentalRating = $detail['episodes']['contents'][0]['parentalRatings'][0]['value'] ?? @$detail['detail']['informations']['parentalRatings'][0]['value'];

            $csa = match ($parentalRating) {
                '2' => '-10',
                '3' => '-12',
                '4' => '-16',
                '5' => '-18',
                default => 'Tout public',
            };

            $icon = $detail['episodes']['contents'][0]['URLImage'] ?? @$detail['detail']['informations']['URLImage'];
            $icon = str_replace(['{resolutionXY}', '{imageQualityPercentage}'], ['640x360', '80'], $icon ?? '');
            $programs[$startTime] = [
                'startTime'     => $startTime,
                'channel'       => $channel,
                'title'         => $detail['tracking']['dataLayer']['content_title'] ?? $program['title'],
                'subTitle'      => @$detail['episodes']['contents'][0]['subtitle'] ?? $program['subtitle'] ?? null,
                'description'   => $detail['episodes']['contents'][0]['summary'] ?? @$detail['detail']['informations']['summary'],
                'season'        => @$detail['detail']['selectedEpisode']['seasonNumber'],
                'episode'       => @$detail['detail']['selectedEpisode']['episodeNumber'],
                'genre'         => $detail['tracking']['dataLayer']['genre'],
                'genreDetailed' => $detail['tracking']['dataLayer']['subgenre'],
                'icon'          => $icon,
                'year'          => @$detail['detail']['informations']['productionYear'],
                'csa'           => $csa
            ];

            if ($lastTime > 0) {
                $lastProgram = $programs[$lastTime];
                $programObj = new Program($lastProgram['startTime'], $startTime);
                $programObj->addTitle($lastProgram['title']);
                $programObj->addSubtitle($lastProgram['subTitle']);
                $programObj->addDesc($lastProgram['description']);
                $programObj->setEpisodeNum($lastProgram['season'], $lastProgram['episode']);
                $programObj->addCategory($lastProgram['genre']);
                $programObj->addCategory($lastProgram['genreDetailed']);
                $programObj->setIcon($lastProgram['icon']);
                $programObj->setYear($lastProgram['year']);
                $programObj->setRating($lastProgram['csa']);

                $channelObj->addProgram($programObj);
            }

            $lastTime = $startTime;
        }

        return $channelObj;
    }


    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->channelsList[$channel->getId()]['id'];
        $day = round(($date->getTimestamp() - strtotime(date('Y-m-d'))) / 86400);

        $url = 'https://hodor.canalplus.pro/api/v2/mycanal/channels/' . $this->getApiKey() . '/' . $channelId . '/broadcasts/day/'. $day;
        if(!is_null($this->proxy)) {
            $url = $this->proxy[0].urlencode(base64_encode($url)).$this->proxy[1];
        }

        return $url;
    }
}
