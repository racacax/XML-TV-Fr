<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;


use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;

class PlutoTV extends AbstractProvider implements ProviderInterface {

    private $sessionToken;

    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct(ResourcePath::getInstance()->getChannelPath('channels_plutotv.json'), $priority ?? 0.10);
        $this->getSessionToken();
    }

    private function getSessionToken() {
        $json = @json_decode($this->getContentFromURL('https://boot.pluto.tv/v4/start?appName=web&appVersion=5.107.0&deviceVersion=96.0.0&deviceModel=web&deviceMake=firefox&deviceType=web&clientID=245658c-6556-25563-8be6-586586353fgv&clientModelNumber=1.0.0&channelSlug=walker-texas-ranger-fr&serverSideAds=true&constraints='), true);
        if(isset($json['sessionToken']))
            $this->sessionToken = $json['sessionToken'];
    }


    public function constructEPG(string $channel, string $date) {
        parent::constructEPG($channel, $date);
        if(!$this->channelExists($channel) || !isset($this->sessionToken))
            return false;

        $channelId = $this->getChannelsList()[$channel];
        $headers = array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:96.0) Gecko/20100101 Firefox/96.0',
            'Authorization: Bearer '.$this->sessionToken);
        $count = 6;
        for($i=0; $i<$count; $i++) {
            Logger::updateLine(" ".round($i*100/$count, 2)." %");
            $hour = strval($i*4);
            if($i < 3 )
                $hour = "0".$hour;
            $epg = @json_decode($this->getContentFromURL("https://service-channels.clusters.pluto.tv/v2/guide/timelines?start={$date}T$hour:00:00.000Z&channelIds=$channelId&duration=240", $headers), true);
            if (!isset($epg['data'][0]['timelines']))
                return false;
            foreach ($epg['data'][0]['timelines'] as $timeline) {
                $programObj = $this->channelObj->addProgram(strtotime($timeline['start']), strtotime($timeline['stop']));
                $programObj->addTitle($timeline['title'] ?? '');
                if (isset($timeline['episode'])) {
                    $programObj->addSubtitle("Saison " . ($timeline['episode']['season'] ?? '1') . ' Episode ' . ($timeline['episode']['number'] ?? '1'));

                    $programObj->addCategory($timeline['episode']['genre'] ?? 'Inconnu');
                    $programObj->addCategory($timeline['episode']['subGenre'] ?? null);
                    $programObj->setIcon($timeline['episode']['series']['featuredImage']['path'] ?? $timeline['episode']['poster']['path'] ?? null);
                    $programObj->setRating($timeline['episode']['rating'] ?? null);
                    $programObj->setEpisodeNum(($timeline['episode']['season'] ?? '1'), ($timeline['episode']['number'] ?? '1'));
                    $programObj->addDesc(@$timeline['episode']['description'] ?? null);

                }
            }
        }
        return $this->channelObj;
    }
}