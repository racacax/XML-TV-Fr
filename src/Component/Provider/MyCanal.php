<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

// Edited by lazel from https://github.com/lazel/XML-TV-Fr/blob/master/classes/MyCanal.php
class MyCanal extends AbstractProvider implements ProviderInterface
{
    private static $apiKey;
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_mycanal.json'), $priority ?? 0.7);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        self::$apiKey = $this->channelsList[$channel]['apiKey']; // different apiKey depending on countries
        //@todo: add cache (next PR?)
        $url1 = $this->generateUrl($channelObj, $datetime = new \DateTimeImmutable($date));
        $url2 = $this->generateUrl($channelObj, $datetime->modify('+1 days'));
        /**
         * @var Response[]
         */
        try {
            $response = Utils::all([
                '1' => $this->client->getAsync($url1),
                '2' => $this->client->getAsync($url2)
            ])->wait();
        } catch(\Throwable $t) {
            return false;
        }
        $json = json_decode((string)$response['1']->getBody(), true);
        $json2 = json_decode((string)$response['2']->getBody(), true);

        if (!isset($json['timeSlices']) || empty($json['timeSlices'])) {
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
            Logger::updateLine(' ' . round($index * 100 / $count, 2) . ' %');
            $promises[$program['onClick']['URLPage']] = $this->client->getAsync($program['onClick']['URLPage']);
        }
        try {
            $response = Utils::all($promises)->wait();
        } catch(\Throwable $t) {
            return false;
        }

        foreach ($all as $index => $program) {
            Logger::updateLine(' '.round($index*100/$count, 2).' %');
            $detail = json_decode((string)$response[$program['onClick']['URLPage']]->getBody(), true);

            $startTime = $program['startTime'] / 1000;

            $parentalRating = $detail['episodes']['contents'][0]['parentalRatings'][0]['value'] ?? @$detail['detail']['informations']['parentalRatings'][0]['value'];

            switch ($parentalRating) {
                case '2':
                    $csa = '-10';

                    break;
                case '3':
                    $csa = '-12';

                    break;
                case '4':
                    $csa = '-16';

                    break;
                case '5':
                    $csa = '-18';

                    break;
                default:
                    $csa = 'Tout public';

                    break;
            }

            $icon = $detail['episodes']['contents'][0]['URLImage'] ?? @$detail['detail']['informations']['URLImage'];
            $icon = str_replace(['{resolutionXY}', '{imageQualityPercentage}'], ['640x360', '80'], $icon ?? '');

            $programs[$startTime] = [
                'startTime'     => $startTime,
                'channel'       => $channel,
                'title'         => $detail['tracking']['dataLayer']['content_title'],
                'subTitle'      => @$detail['episodes']['contents'][0]['subtitle'],
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
        $day = ($date->getTimestamp() - strtotime(date('Y-m-d'))) / 86400;

        return  'https://hodor.canalplus.pro/api/v2/mycanal/channels/' . self::$apiKey . '/' . $channelId . '/broadcasts/day/'. $day;
    }
}
