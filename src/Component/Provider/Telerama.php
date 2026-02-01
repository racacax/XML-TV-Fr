<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Telerama extends AbstractProvider implements ProviderInterface
{
    private static string $HOST = 'https://apps.telerama.fr/tlr/v1/free-android-phone/';
    private static string $USER_AGENT = 'TLR/4.11 (free; fr; ABTest 322) Android/13/33 (tablet; Galaxy Tab S6 Samsung Device)';
    private bool $enableDetails;

    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        if (isset($extraParam['telerama_enable_details'])) {
            $this->enableDetails = $extraParam['telerama_enable_details'];
        } else {
            $this->enableDetails = true;
        }
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_telerama.json'), $priority ?? 0.4);
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $channelId = $this->getChannelsList()[$channel];

        $contentDayBefore = $this->getContentFromURL($this->generateUrl((new \DateTimeImmutable($date))->modify('-1 day')), ['User-Agent: '.self::$USER_AGENT]);
        $content = $this->getContentFromURL($this->generateUrl(new \DateTimeImmutable($date)), ['User-Agent: '.self::$USER_AGENT]);
        $jsonDayBefore = json_decode($contentDayBefore, true);
        $json = json_decode($content, true);

        $channelPrograms = array_merge(@$jsonDayBefore['channels'][$channelId]['broadcasts'] ?? [], @$json['channels'][$channelId]['broadcasts'] ?? []);
        $count = count($channelPrograms);
        foreach ($channelPrograms as $index => $program) {
            $percent = round($index * 100 / $count, 2) . ' %';
            $this->setStatus($percent);
            [$minDate, $maxDate] = $this->getMinMaxDate($date);
            $programStartDate = new \DateTimeImmutable('@'.strtotime($program['start_date']));

            if ($programStartDate < $minDate) {
                continue;
            } elseif ($programStartDate > $maxDate) {
                return $channelObj;
            }
            $channelObj->addProgram($this->generateProgram($program));
        }

        return $channelObj;
    }

    private function generateProgram(array $program): Program
    {
        $programObj = Program::withTimestamp(strtotime($program['start_date']), strtotime($program['end_date']));
        $programObj->addTitle($program['title'] ?? 'Aucun titre');
        $programObj->addCategory(ucfirst(strip_tags($program['type'] ?? 'Aucune catégorie')));
        if ($program['is_inedit']) {
            $programObj->setPremiere();
        }
        $img = $program['illustration']['url'];
        if ($img) {
            $img = str_replace('{{height}}', '720', str_replace('{{width}}', '1280', $img));
            $programObj->addIcon($img);
        }
        foreach ([10,12,16,18] as $csaRating) {
            if (in_array("moins-de-$csaRating", $program['flags'] ?? [])) {
                $programObj->setRating($csaRating);
            }
        }
        if (in_array('audiodescription', $program['flags'] ?? [])) {
            $programObj->setAudioDescribed();
        }
        if (in_array('teletexte', $program['flags'] ?? [])) {
            $programObj->addSubtitles('teletext');
        }
        if ($this->enableDetails && !empty($program['deeplink'])) {
            $this->assignDetails($programObj, $program['deeplink']);
        }

        return $programObj;
    }

    private function getElementValue(string $content, string $element)
    {
        preg_match('/<p class="sheet__info-item-label">'.$element.'<\/p>.*?<p class="sheet__info-item-value">(.*?)<\/p>/', $content, $matches);

        return @$matches[1];
    }

    private function assignDetails(Program $programObj, string $deeplink)
    {

        $details = @json_decode($this->getContentFromURL(self::$HOST.str_replace('tlrm://', '', $deeplink), ['User-Agent: '.self::$USER_AGENT]), true);
        $content = @$details['templates']['raw_content']['content'];
        if ($content) {
            $season = $this->getElementValue($content, 'Saison');
            $episode = explode('/', $this->getElementValue($content, 'Épisode') ?? '')[0];
            if (!empty($season) || !empty($episode)) {
                $programObj->setEpisodeNum($season, $episode);
            }
            preg_match('/<p class="sheet__synopsis-content">(.*?)<\/p>/', $content, $synopsis);
            $synopsis = $synopsis[1];
            $programObj->addDesc($synopsis);
            preg_match('/<p class="article__page-subtitle">(.*?)<\/p>/', $content, $subtitle);
            $subtitle = @$subtitle[1];
            $programObj->addSubTitle($subtitle);
            $subtitle2 = $this->getElementValue($content, 'Titre de l’épisode');
            $programObj->addSubTitle($subtitle2);
            $scenario = $this->getElementValue($content, 'Scénario');
            $programObj->addCredit($scenario, 'writer');
            $director = $this->getElementValue($content, 'Réalisateur');
            $programObj->addCredit($director, 'director');
            $genre = $this->getElementValue($content, 'Genre');
            $presenter = $this->getElementValue($content, 'Présentateur');
            $programObj->addCredit($presenter, 'presenter');
            $programObj->addCategory(strip_tags($genre));
            preg_match_all('/<p class="sheet__info-item-label sheet__info-item-label--casting">(.*?)<\/p>.*?<p class="sheet__info-item-value">(.*?)<\/p>/', $content, $casting);
            for ($i = 0; $i < count($casting[0]); $i++) {
                $programObj->addCredit($casting[1][$i].' ('.$casting[2][$i].')', 'actor');
            }
        }
    }

    public function generateUrl(\DateTimeImmutable $date): string
    {
        $url = sprintf(
            'tv-program/grid?date=%s',
            $date->format('Y-m-d'),
        );

        return self::$HOST . $url;
    }
}
