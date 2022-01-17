<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;

class Proximus extends AbstractProvider implements ProviderInterface
{
    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct(ResourcePath::getInstance()->getChannelPath('channels_proximus.json'), $priority ?? 0.59);
    }

    public function constructEPG(string $channel, string $date)
    {
        parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $channelId = $this->getChannelsList()[$channel];
        $timestamp = strtotime($date);


        $get = $this->getContentFromURL("https://api.proximusmwc.be/v2/graphql?query=query%20(%24language%3A%20String!%2C%20%24startTime%3A%20Int!%2C%20%24endTime%3A%20Int!%2C%20%24options%3A%20SchedulesByIntervalOptions)%20{%0A%20%20schedulesByInterval(language%3A%20%24language%2C%20startTime%3A%20%24startTime%2C%20endTime%3A%20%24endTime%2C%20options%3A%20%24options)%20{%0A%20%20%20%20trailId%0A%20%20%20%20accessLevel%0A%20%20%20%20programReferenceNumber%0A%20%20%20%20channelId%0A%20%20%20%20channel%0A%20%20%20%20title%0A%20%20%20%20startTime%0A%20%20%20%20endTime%0A%20%20%20%20timePeriod%0A%20%20%20%20image%20{%0A%20%20%20%20%20%20key%0A%20%20%20%20%20%20url%0A%20%20%20%20%20%20__typename%0A%20%20%20%20}%0A%20%20%20%20imageOnErrorHandler%0A%20%20%20%20parentalRating%0A%20%20%20%20detailUrl%0A%20%20%20%20ottBlackListed%0A%20%20%20%20cloudRecordable%0A%20%20%20%20grouped%0A%20%20%20%20description%0A%20%20%20%20shortDescription%0A%20%20%20%20category%0A%20%20%20%20translatedCategory%0A%20%20%20%20categoryId%0A%20%20%20%20formattedStartTime%0A%20%20%20%20formattedEndTime%0A%20%20%20%20subCategory%0A%20%20%20%20scheduleTimeFormat%0A%20%20%20%20links%20{%0A%20%20%20%20%20%20episodeNumber%0A%20%20%20%20%20%20id%0A%20%20%20%20%20%20seasonId%0A%20%20%20%20%20%20seasonName%0A%20%20%20%20%20%20seriesId%0A%20%20%20%20%20%20seriesTitle%0A%20%20%20%20%20%20title%0A%20%20%20%20%20%20type%0A%20%20%20%20%20%20__typename%0A%20%20%20%20}%0A%20%20%20%20seriesId%0A%20%20%20%20__typename%0A%20%20}%0A}%0A&variables={%22endTime%22%3A".($timestamp+86400)."%2C%22language%22%3A%22fr%22%2C%22options%22%3A{%22channelIds%22%3A[%22$channelId%22]}%2C%22startTime%22%3A$timestamp}");

        $json = json_decode($get, true);

        $programs = @$json['data']['schedulesByInterval'];

        if (!isset($programs) || empty($programs)) {
            return false;
        }


        foreach ($programs as $program) {
            if (isset($program['parentalRating'])) {
                switch ($program['parentalRating']) {
                    case '10': $csa = '-10'; break;
                    case '12': $csa = '-12'; break;
                    case '16': $csa = '-16'; break;
                    case '18': $csa = '-18'; break;
                    default: $csa = 'Tout public';  break;
                }
            } else {
                $csa = 'Tout public';
            }
            $programObj = $this->channelObj->addProgram($program['startTime'], $program['endTime']);
            $programObj->addTitle($program['title'] ?? '');
            $programObj->addDesc(@$program['description']);
            $programObj->addCategory(@$program['category']);
            $programObj->addCategory(@$program['subcategory']);
            $programObj->setIcon(@$program['images'][0]['url']);
            $programObj->setRating($csa);
        }

        return $this->channelObj;
    }
}
