<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use DateTime;
use DateTimeImmutable;
use racacax\XmlTv\ValueObject\EPGEnum;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.1 : 22/02/2025
 */
class LEquipeLive extends AbstractProvider implements ProviderInterface
{
    private ProviderCache $cache;
    private array $DAYS = ['Lun' => 'Mon', 'Mar' => 'Tue', 'Mer', 'Wed', 'Jeu' => 'Thu', 'Ven' => 'Fri', 'Sam' => 'Sat', 'Dim' => 'Sun'];
    private array $daysDate;
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_lequipelive.json'), $priority ?? 0.1);
        $this->cache = new ProviderCache('lequipeLive');
        $this->daysDate = $this->getDaysDate();
    }
    private function getDaysDate()
    {
        $arr = [];
        $date = new DateTime(date('Y-m-d'));
        for ($i = 1; $i <= 7; $i++) {
            $date->modify('+1 day');
            $arr[$date->format('D')] = $date->format('Y-m-d');
        }

        return $arr;
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $content = $this->cache->getContent();
        $channelId = $this->getChannelsList()[$channel];
        $dateObj = new \DateTimeImmutable($date);
        if (!$content) {
            $content = $this->getContentFromURL($this->generateUrl($channelObj, $dateObj));
        }
        $zone = @explode('class="CarouselWidget__headerTitle"', @explode('alt="À suivre en direct"', $content)[1])[0];
        $items = explode('class="CarouselWidget__item"', $zone);
        unset($items[0]);
        $channelItems = [];
        foreach ($items as $item) {
            $item = $this->parseItem($item);
            if ($item['id'] != $channelId) {
                continue;
            }
            $channelItems[] = $item;
        }
        $minTime = $dateObj->getTimestamp();
        $maxTime = $dateObj->modify('+1 day')->getTimestamp();
        for ($i = 0; $i < count($channelItems); $i++) {
            if ($channelItems[$i]['time'] >= $minTime && $channelItems[$i]['time'] <= $maxTime) {
                $endTime = min(@$channelItems[$i + 1]['time'] ?? PHP_INT_MAX, $channelItems[$i]['time'] + 7200);
                $program = new Program($channelItems[$i]['time'], $endTime);
                $program->addTitle($channelItems[$i]['title']);
                $program->addSubtitle($channelItems[$i]['subtitle']);
                $program->addCategory('Sports');
                $program->setIcon($channelItems[$i]['img']);
                $channelObj->addProgram($program);
            }
        }

        return $channelObj;
    }

    public function getChannelStateFromTimes(array $startTimes, array $endTimes, Configurator $config): int
    {
        if (count($startTimes) > 0) {
            return EPGEnum::$FULL_CACHE;
        }

        return EPGEnum::$NO_CACHE;
    }

    private function formatTime(string $time): int
    {
        $time = str_replace('h', ':', $time);
        if (str_ends_with($time, ':')) {
            $time .= '00';
        }
        $time = explode('.', $time);
        if (count($time) == 1) {
            return strtotime(date('Y-m-d').' '.$time[0]);
        }
        $enDay = $this->DAYS[$time[0]];
        $date = $this->daysDate[$enDay];

        return strtotime($date.' '.$time[1]);
    }

    private function parseItem(string $item): array
    {
        preg_match("/<h2 class=\"ColeaderWidget__title\".*?>(.*?)<\/h2>/s", $item, $idMatch);
        $idMatch = trim($idMatch[1]);
        preg_match("/<div.*?class=\"ArticleTags__item\".*?>(.*?)<\/div>/s", $item, $title);
        $title = trim($title[1]);
        preg_match("/<p class=\"ColeaderWidget__subtitle\".*?>(.*?)<\/p>/s", $item, $subtitle);
        $subtitle = trim($subtitle[1]);
        preg_match("/<span class=\"ColeaderLabels__text\".*?>(.*?)<\/span>/s", $item, $time);
        $time = trim($time[1]);
        preg_match('/src="(.*?)"/s', $item, $img);
        $img = $img[1];

        return ['id' => $idMatch, 'title' => $title, 'subtitle' => $subtitle, 'time' => $this->formatTime($time), 'img' => $img];
    }

    public function generateUrl(Channel $channel, DateTimeImmutable $date): string
    {
        return 'https://www.lequipe.fr/tv/';
    }
}
