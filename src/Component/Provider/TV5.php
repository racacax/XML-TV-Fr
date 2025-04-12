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
 * @version 0.1 : 05/09/2021
 */

class TV5 extends AbstractProvider implements ProviderInterface
{
    private static $HEADERS = ['Host' => 'bo-apac.tv5monde.com',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/png,image/svg+xml,*/*;q=0.8',
        'Accept-Language' => 'fr-FR,fr-CA;q=0.8,en;q=0.5,en-US;q=0.3',
        'Accept-Encoding' => 'gzip, deflate, br, zstd',
        'DNT' => '1',
        'Sec-GPC' => '1',
        'Connection' => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Sec-Fetch-User' => '?1',
        'Priority' => 'u=0, i'];

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tv5.json'), $priority ?? 0.6);
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);

        if (!$this->channelExists($channel)) {
            return false;
        }
        $content = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)), self::$HEADERS);
        $json = json_decode($content, true);
        if (!@isset($json['data'][0])) {
            return false;
        }
        foreach ($json['data'] as $val) {
            $program = Program::withTimestamp(strtotime($val['utcstart'] . '+00:00'), strtotime($val['utcend'] . '+00:00'));
            $program->addTitle($val['title']);
            $program->addDesc((!empty($val['description'])) ? $val['description'] : 'Pas de description');
            $program->addCategory($val['category']);
            $program->setIcon(!empty($val['image']) ? '' . $val['image'] : '');
            if (isset($val['season'])) {
                if ($val['season'] == '') {
                    $val['season'] = '1';
                }
                if ($val['episode'] == '') {
                    $val['episode'] = '1';
                }
                $program->addSubtitle($val['episode_name']);
                $program->setEpisodeNum($val['season'], $val['episode']);
            }
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];
        $start = $date->format('Y-m-d\T00:00:00');
        $end = $date->modify('+1 days')->format('Y-m-d\T00:00:00');

        return 'https://bo-apac.tv5monde.com/tvschedule/full?start=' . $start . '&end=' . $end . '&key=' . $channel_id . '&timezone=Europe/Paris&language=EN';
    }
}
