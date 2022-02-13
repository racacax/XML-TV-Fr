<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Proximus extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_proximus.json'), $priority ?? 0.59);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $get = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));

        $json = json_decode($get, true);

        $programs = @$json['data']['schedulesByInterval'];

        if (empty($programs)) {
            return false;
        }


        foreach ($programs as $program) {
            if (isset($program['parentalRating'])) {
                switch ($program['parentalRating']) {
                    case '10':
                        $csa = '-10';

                        break;
                    case '12':
                        $csa = '-12';

                        break;
                    case '16':
                        $csa = '-16';

                        break;
                    case '18':
                        $csa = '-18';

                        break;
                    default:
                        $csa = 'Tout public';

                        break;
                }
            } else {
                $csa = 'Tout public';
            }
            $programObj = new Program($program['startTime'], $program['endTime']);
            $programObj->addTitle($program['title'] ?? '');
            $programObj->addDesc(@$program['description']);
            $programObj->addCategory(@$program['category']);
            $programObj->addCategory(@$program['subcategory']);
            $programObj->setIcon(@$program['images'][0]['url']);
            $programObj->setRating($csa);

            $channelObj->addProgram($programObj);
        }

        return $channelObj;
    }
    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->getChannelsList()[$channel->getId()];

        $query = <<<'GRAPHQL'
query ($language: String!, $startTime: Int!, $endTime: Int!, $options: SchedulesByIntervalOptions) {
    schedulesByInterval(language: $language, startTime: $startTime, endTime: $endTime, options: $options) {
        trailId
        accessLevel
        programReferenceNumber
        channelId
        channel
        title
        startTime
        endTime
        timePeriod
        image {
        key
        url
        __typename
        }
        imageOnErrorHandler
        parentalRating
        detailUrl
        ottBlackListed
        cloudRecordable
        grouped
        description
        shortDescription
        category
        translatedCategory
        categoryId
        formattedStartTime
        formattedEndTime
        subCategory
        scheduleTimeFormat
        links {
        episodeNumber
        id
        seasonId
        seasonName
        seriesId
        seriesTitle
        title
        type
        __typename
        }
        seriesId
        __typename
    }
}
GRAPHQL;

        $variables = json_encode([
            'startTime'=> $date->getTimestamp(),
            'endTime'=> $date->modify('+1 days')->getTimestamp(),
            'language'=> 'fr',
            'options'=> ['channelIds' => [$channelId]]
        ]);

        return  'https://api.proximusmwc.be/v2/graphql?'.http_build_query(['query'=>$query, 'variables'=>$variables]);
    }
}
