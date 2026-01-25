<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ChannelFactory;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.1 : 07/12/2023
 */
class PlayTV extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_playtv.json'), $priority ?? 0.45);

    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = ChannelFactory::createChannel($channel);
        if (!$this->channelExists($channel)) {
            return false;
        }
        [$minDate] = $this->getMinMaxDate($date);
        $response = $this->getContentFromURL($this->generateUrl($channelObj, $minDate));
        $json = json_decode($response, true);
        if (empty($json['data'])) {
            return false;
        }
        foreach ($json['data'] as $val) {
            $program = Program::withTimestamp(strtotime($val['start_at']), strtotime($val['end_at']));

            $attrs = $val['media']['attrs'] ?? [];
            $category = $val['media']['path'][0]['category'] ?? 'Inconnu';
            $category[0] = strtoupper($category[0]);
            $program->addCategory($category);
            $program->addDesc($attrs['texts']['long'] ?? $attrs['texts']['short'] ?? 'Aucune description');
            $program->addTitle($val['title']);
            if (isset($val['subtitle'])) {
                $program->addSubTitle($val['subtitle']);
            }
            if (isset($attrs['episode'])) {
                $program->setEpisodeNum($attrs['season'] ?? '1', $attrs['episode']);
            }
            $images = $attrs['images'] ?? [];
            $image = $images['large'][0]['url'] ?? $images['thumbnail'][0]['url'] ?? null;
            if (isset($image)) {
                $program->addIcon($image);
            }

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->channelsList[$channel->getId()];
        $startDate = $date->setTimezone(new \DateTimeZone('UTC'));
        $endDate = $startDate->modify('+1 day');

        return  'https://api.playtv.fr/broadcasts?'.http_build_query([
            'include' => 'media',
            'filter[channel_id]' => $channelId,
            'filter[airing_between]' => $startDate->format("Y-m-d\TH:i:s\Z").','.$endDate->format("Y-m-d\TH:i:s\Z")
        ]);
    }
}
