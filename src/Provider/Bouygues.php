<?php

declare(strict_types=1);

namespace racacax\XmlTv\Provider;

use racacax\XmlTv\Component\AbstractProvider;
use racacax\XmlTv\Component\ProviderInterface;

// Original script by lazel on https://github.com/lazel/XML-TV-Fr/blob/master/classes/Bouygues.php
class Bouygues extends AbstractProvider implements ProviderInterface {


    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct('resources/channel_config/channels_bouygues.json',$priority ?? 0.9);
    }

    public function constructEPG($channel, $date) {
        parent::constructEPG($channel, $date);
        if(!$this->channelExists($channel))
            return false;

        $channelId = $this->getChannelsList()[$channel];

        $date_start = $date . 'T04:00:00Z';
        $date_end = date('Y-m-d', strtotime($date . ' + 1 days')) . 'T03:59:59Z';

        $curl = curl_init('http://epg.cms.pfs.bouyguesbox.fr/cms/sne/live/epg/events.json?profile=detailed&epgChannelNumber=' . $channelId . '&eventCount=999&startTime='.$date_start.'&endTime='.$date_end);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $get = curl_exec($curl);
        curl_close($curl);

        $json = json_decode($get, true);

        if(!isset($json['channel'][0]['event']) || empty($json['channel'][0]['event'])) return false;


        foreach($json['channel'][0]['event'] as $program) {
            $genre = @$program['programInfo']['genre'][0];
            $subGenre = @$program['programInfo']['subGenre'][0];

            if(isset($program['parentalGuidance'])) {
                $csa = explode('.', $program['parentalGuidance']);

                switch((int)end($csa)) {
                    case 2: $csa = '-10'; break;
                    case 3: $csa = '-12'; break;
                    case 4: $csa = '-16'; break;
                    case 5: $csa = '-18'; break;
                    default: $csa = 'Tout public';  break;
                }
            } else $csa = 'Tout public';

            if(!is_null($genre) && !is_null($subGenre) && $genre == $subGenre) {
                if(isset($program['programInfo']['genre'][1])) $genre = $program['programInfo']['genre'][1];
                else $subGenre = null;
            }
            $programObj = $this->channelObj->addProgram(strtotime($program['startTime']), strtotime($program['endTime']));
            $programObj->addTitle($program['programInfo']['longTitle']);
            $programObj->addSubtitle(@$program['programInfo']['secondaryTitle']);
            $programObj->addDesc(@$program['programInfo']['longSummary'] ?? @$program['programInfo']['shortSummary']);
            $programObj->setEpisodeNum(@$program['programInfo']['seriesInfo']['seasonNumber'], @$program['programInfo']['seriesInfo']['episodeNumber']);
            $programObj->addCategory($genre);
            $programObj->addCategory($subGenre);
            $programObj->setIcon(isset($program['media'][0]['url']) ? 'https://img.bouygtel.fr' . $program['media'][0]['url'] : null);
            $programObj->setRating($csa);
            $programObj->setYear(@$program['programInfo']['productionDate']);
        }

        return $this->channelObj;
    }
}