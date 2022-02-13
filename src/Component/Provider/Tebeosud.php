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

/*
 * Update 02/2022
 * Type: Scraper
 * TimeZone: Europe/Paris
 * SubHttpCall: Async
 */
class Tebeosud extends AbstractProvider implements ProviderInterface
{
    /**
     * @var \DateTimeZone
     */
    private $timezone;

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tebeosud.json'), $priority ?? 0.2);

        $this->timezone = new \DateTimeZone('Europe/Paris');
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        if ($date != date('Y-m-d')) {
            return false;
        }

        $res1 = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));

        $firstPartBegin = strpos($res1, '<span class="rouge">Programme</span>');
        if ($firstPartBegin === false) {
            return false;
        }
        $firstPartEnd = $secondPartBegin = strpos($res1, '<h3 class="grid_16 titre">');
        $secondPartEnd = strpos($res1, '<!-- Fin liste des programmes -->');

        $part1 = substr($res1, $firstPartBegin, $firstPartEnd - $firstPartBegin);
        $part2 = substr($res1, $secondPartBegin, $secondPartEnd - $secondPartBegin);


        $firstDay = $this->getStartDate($part1);
        $date = (new \DateTimeImmutable($firstDay))->setTimezone($this->timezone);
        //construct guide
        $guide = [];
        $previous = null;
        foreach ([$part1, $part2] as $index => $content) {
            $programDay = $date->modify("+$index days");
            $listProgram = explode('<tr>', $content);
            if (0 === $index) {
                array_shift($listProgram);
            }
            foreach ($listProgram as $programContent) {
                $matches = [];
                $re = '/class="date"><a href="(.*)">([\d:]+)[\s\S]*nom"><a href="(.*)">(.*)<\/a>/m';
                preg_match($re, $programContent, $matches, PREG_OFFSET_CAPTURE, 0);
                if (empty($matches)) {
                    continue;
                }
                $url = $matches[1][0] ?? $matches[3][0];
                if (strpos($url, 'http') !== 0) {
                    $url = 'https:'.$url;
                }
                [$hour, $min] = explode(':', $matches[2][0]);
                $begin = $programDay->setTime(intval($hour), intval($min), 0);
                $guide[$begin->format('c')] = [
                    'url' => $url,
                    'begin' => $begin,
                    'title' => $matches[4][0],
                ];
                if (null !== $previous) {
                    $guide[$previous->format('c')]['end'] = $begin;
                }
                $previous = $begin;
            }
        }
        $request = [];
        // Convert data to Program
        foreach ($guide as $dataProgram) {
            if (empty($dataProgram['end'])) {
                continue;
            }
            $request[] = $this->client
                ->getAsync($dataProgram['url'])
                ->then(function (Response $response) use ($dataProgram, $channelObj) {
                    $content = $response->getBody()->getContents();
                    preg_match('/"description" content="([^"]*)/m', $content, $desc);
                    preg_match('/meta property="og:image" content="(.*?)"/', $content, $img);

                    $urlImage = $img[1] ?? '';
                    if (strpos($urlImage, 'http') !== 0) {
                        $urlImage = 'https:'.$urlImage;
                    }
                    $program = new Program($dataProgram['begin'], $dataProgram['end']);
                    $program->addTitle($dataProgram['title']);
                    $program->addDesc(trim($desc[1] ?? ''));
                    $program->setIcon($urlImage);
                    //$program->addCategory($program['genre']);

                    $channelObj->addProgram($program);
                });
        }
        Utils::all($request)->wait();

        $channelObj->orderProgram();

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];

        return "https://www.tebe$channel_id.bzh/le-programme";
    }

    public function getStartDate(string $content): string
    {
        $matches = [];
        $re = '/<h2>[\w]+\s+([\d]+)/';
        preg_match($re, $content, $matches, PREG_OFFSET_CAPTURE, 0);
        if (!isset($matches[1][0])) {
            return '';
        }
        $day = intval($matches[1][0]);
        $currentDay = intval(date('d'));

        if ($day === $currentDay) {
            $startDate = date('Y-m-d');
        } elseif ($day === intval(date('d', strtotime('-1 days')))) {
            $startDate = date('Y-m-d', strtotime('-1 days'));
        } else {
            return '';
        }

        return $startDate;
    }
}
