<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class PlutoTV extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_plutotv.json'), $priority ?? 0.10);
    }

    private function getSessionToken(): string
    {
        $content = $this->getContentFromURL('https://boot.pluto.tv/v4/start?appName=web&appVersion=5.107.0&deviceVersion=96.0.0&deviceModel=web&deviceMake=firefox&deviceType=web&clientID=245658c-6556-25563-8be6-586586353fgv&clientModelNumber=1.0.0&channelSlug=walker-texas-ranger-fr&serverSideAds=true&constraints=');
        $json = @json_decode($content, true);

        return $json['sessionToken'] ?? '';
    }


    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        $sessionToken = $this->getSessionToken();
        if (!$this->channelExists($channel) || empty($sessionToken)) {
            return false;
        }

        $headers = ['Authorization' => 'Bearer '.$sessionToken];
        $count = 6;
        for ($i=0; $i<$count; $i++) {
            Logger::updateLine(' '.round($i*100/$count, 2).' %');
            $hour = strval($i*4);
            if ($i < 3) {
                $hour = '0'.$hour;
            }
            $content = $this->getContentFromURL(
                sprintf($this->generateUrl($channelObj, new \DateTimeImmutable($date)), $hour),
                $headers
            );

            $epg = @json_decode($content, true);
            if (!isset($epg['data'][0]['timelines'])) {
                return false;
            }
            foreach ($epg['data'][0]['timelines'] as $timeline) {
                $programObj = new Program(strtotime($timeline['start']), strtotime($timeline['stop']));
                $programObj->addTitle($timeline['title'] ?? '');
                if (isset($timeline['episode'])) {
                    $programObj->addSubtitle('Saison ' . ($timeline['episode']['season'] ?? '1') . ' Episode ' . ($timeline['episode']['number'] ?? '1'));

                    $programObj->addCategory($timeline['episode']['genre'] ?? 'Inconnu');
                    $programObj->addCategory($timeline['episode']['subGenre'] ?? null);
                    $programObj->setIcon($timeline['episode']['series']['featuredImage']['path'] ?? $timeline['episode']['poster']['path'] ?? null);
                    $programObj->setRating($timeline['episode']['rating'] ?? null);
                    $programObj->setEpisodeNum(($timeline['episode']['season'] ?? '1'), ($timeline['episode']['number'] ?? '1'));
                    $programObj->addDesc(@$timeline['episode']['description'] ?? null);
                }
                $channelObj->addProgram($programObj);
            }
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->channelsList[$channel->getId()];
        $dateStr = $date->format('Y-m-d');

        return "https://service-channels.clusters.pluto.tv/v2/guide/timelines?start={$dateStr}T%s:00:00.000Z&channelIds=$channelId&duration=240";
    }
}