<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;


use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;

// Original script by lazel from https://github.com/lazel/XML-TV-Fr/blob/master/classes/SFR.php
class SFR extends AbstractProvider implements ProviderInterface {

    private $jsonPerDay;

    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct(ResourcePath::getInstance()->getChannelPath('channels_sfr.json'), $priority ?? 0.85);
        $this->jsonPerDay = [];
    }

    public function constructEPG(string $channel, string $date) {
        parent::constructEPG($channel, $date);
        if(!$this->channelExists($channel))
            return false;

        $channelId = $this->getChannelsList()[$channel];

        if(!isset($this->jsonPerDay[$date])) {
            $curl = curl_init('https://static-cdn.tv.sfr.net/data/epg/gen8/guide_web_' . str_replace('-', '', $date) . '.json');
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0');
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            $get = curl_exec($curl);
            curl_close($curl);

            $json = json_decode($get, true);
            $this->jsonPerDay[$date] = $json;
        } else {
            $json = $this->jsonPerDay[$date];
        }

        $programs = @$json['epg'];

        if(!isset($programs[$channelId]) || empty($programs[$channelId])) return false;


        foreach($programs[$channelId] as $program) {
            if(isset($program['moralityLevel'])) {
                switch($program['moralityLevel']) {
                    case '2': $csa = '-10'; break;
                    case '3': $csa = '-12'; break;
                    case '4': $csa = '-16'; break;
                    case '5': $csa = '-18'; break;
                    default: $csa = 'Tout public';  break;
                }
            } else $csa = 'Tout public';
            $programObj = $this->channelObj->addProgram($program['startDate'] / 1000, $program['endDate'] / 1000);
            $programObj->addTitle($program['title'] ?? '');
            $programObj->addSubtitle(@$program['subTitle']);
            $programObj->setEpisodeNum(@$program['seasonNumber'], @$program['episodeNumber']);
            $programObj->addDesc(@$program['description']);
            $programObj->addCategory(@$program['genre']);
            $programObj->setIcon(@$program['images'][0]['url']);
            $programObj->setRating($csa);
        }

        return $this->channelObj;
    }
}