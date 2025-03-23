<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
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
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tebeosud.json'), $priority ?? 0.2);
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $res1 = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
        $res1 = str_replace('"', "'", $res1);
        preg_match_all("/<p class='hour-program'>(.*?)<\/p>/s", $res1, $hours);
        preg_match_all("/<span class='video-card-date'>(.*?)<\/span>/s", $res1, $titles);
        preg_match_all("/<div class='program-card-content'> <img .*?src='(.*?)'.*?>/s", $res1, $images);
        preg_match_all("/<p class='programm-card-duree'>.*?<span>(.*?)<\/span>.*?<\/p>/s", $res1, $durations);
        if (count($titles[1]) == 0) {
            return false;
        }
        for ($i = 0; $i < count($titles[1]); $i++) {
            $start = strtotime($date.' '.$hours[1][$i]);
            if ($i == count($titles) - 1) {
                $end = $start + 3600;
            } elseif (isset($hours[1][$i + 1])) {
                $end = strtotime($date.' '.$hours[1][$i + 1]);
            }
            if (!isset($end)) {
                continue;
            }
            $program = new Program($start, $end);
            $program->addTitle(trim($titles[1][$i]));
            $program->addDesc('Aucune description');
            $program->setIcon($images[1][$i]);
            $program->addCategory('Inconnu');

            $channelObj->addProgram($program);
        }

        $channelObj->orderProgram();

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {

        return 'https://www.tebeo.bzh/programme/'.($date->format('d-m-Y')).'/';
    }
}
