<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class TV5Global extends AbstractProvider implements ProviderInterface
{
    private bool $enableDetails;
    private static array $HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:146.0) Gecko/20100101 Firefox/146.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'fr-FR,fr-CA;q=0.8,en;q=0.5,en-US;q=0.3',
        'Accept-Encoding' => 'gzip, deflate, br, zstd',
        'Upgrade-Insecure-Requests' => '1',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Sec-GPC' => '1',
        'Sec-Fetch-User' => '?1',
        'Priority' => 'u=0, i'
    ];
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tv5global.json'), $priority ?? 0.6);

        if (isset($extraParam['tv5global_enable_details'])) {
            $this->enableDetails = $extraParam['tv5global_enable_details'];
        } else {
            $this->enableDetails = true;
        }
    }

    private function getContentOrRetry(string $url, string $rootDomain): string
    {
        $endUrl = '';
        for ($i = 0; $i < 5; $i++) {
            $content = $this->getContentFromURL($url.$endUrl, array_merge(self::$HEADERS, ['Host' => $rootDomain]), true);
            if (!str_contains($content, '502 Bad Gateway')) {
                return $content;
            }
            $try = $i + 2;
            $this->setStatus(Utils::colorize("Erreur 502, essai n°$try...", 'red'));
            $endUrl = $endUrl.'&';
            sleep(5);
        }

        return '';
    }
    private function addDetails(Program $program, string $url): void
    {
        try {
            $content = $this->getContentFromURL($url, self::$HEADERS);
            preg_match('/class="field-label-inline">Saison<\/span>.*?<span>(.*?)<\/span>/s', $content, $season);
            preg_match('/class="field__label">Épisode<\/div>.*?<div class="field__item">(.*?)<\/div>/s', $content, $episode);
            preg_match('/field--type-text-with-summary.*?">(.*?)<\/div>/s', $content, $summary);

            if ($episode[1]) {
                $program->setEpisodeNum(intval($season[1] ?? '1'), intval($episode[1]));
            }
            if (!str_contains($summary[1], 'googletag')) {
                $program->addDesc(strip_tags($summary[1]));
            }
        } catch (\Throwable) {
            // We allow failures on fetching details
            return;
        }
    }
    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);

        if (!$this->channelExists($channel)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $dateObj = new \DateTimeImmutable($date);
        $content = $this->getContentOrRetry($this->generateUrl($channelObj, $dateObj), $this->getRootDomain($channelObj));

        // Renaming container class containing all programs
        $dayBefore = $dateObj->modify('-1 day')->format('Y-m-d');
        $dayAfter = $dateObj->modify('+1 day')->format('Y-m-d');
        $content = str_replace("jour-$dayBefore", 'PROGRAM_SPLIT', $content);
        $content = str_replace("jour-$date", 'PROGRAM_SPLIT', $content);
        $content = str_replace("jour-$dayAfter", 'PROGRAM_SPLIT', $content);
        $programs = explode('PROGRAM_SPLIT', $content);

        $count = count($programs);

        for ($i = 1; $i < $count - 1; $i++) {
            $percent = round($i * 100 / $count, 2) . ' %';
            $this->setStatus($percent);
            $p = $programs[$i];
            preg_match('/datetime="(.*?)"/s', $p, $startTime);
            preg_match('/datetime="(.*?)"/s', $programs[$i + 1], $endTime);
            preg_match('/field-categorie.*?field-content">(.*?)<\/div>/s', $p, $genre);
            preg_match('/field-title.*?field-content">(.*?)<\/span>/s', $p, $titleOrSubtitle);
            preg_match('/field-serie.*?field-content">(.*?)<\/span>/s', $p, $title);
            preg_match('/data-src="(.*?)"/', $p, $image);
            preg_match('/href="(.*?)"/', $p, $href);
            $startTimeObj = new \DateTime('@'.strtotime($startTime[1]));
            $endTimeObj = new \DateTime('@'.strtotime($endTime[1]));

            if ($startTimeObj < $minDate) {
                continue;
            } elseif ($startTimeObj > $maxDate) {
                return $channelObj;
            }
            $program = new Program($startTimeObj, $endTimeObj);
            if ($title[1]) {
                $program->addTitle($title[1]);
                $program->addSubTitle($titleOrSubtitle[1] ?? 'Aucun sous-titre');
            } else {
                $program->addTitle($titleOrSubtitle[1] ?? 'Aucun titre');
            }
            if ($this->enableDetails && $href[1]) {
                $this->addDetails($program, 'https://'.$this->getRootDomain($channelObj).$href[1]);
            }
            $program->addCategory($genre[1] ?? 'Inconnu');
            if (!empty($image[1])) {
                $program->setIcon('https://'.$this->getRootDomain($channelObj).$image[1]);
            }
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    private function getRootDomain(Channel $channel): string
    {
        $channel_id = $this->channelsList[$channel->getId()];

        return $channel_id.'.tv5monde.com';
    }

    public function generateUrl(Channel $channel, $date): string
    {
        return 'https://'.$this->getRootDomain($channel).'/tv-guide?day=' . $date->format('Y-m-d');
    }
}
