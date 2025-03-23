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
 * @version 0.1 : 16/02/2020
 *
 * Update 01/2022
 * TimeZone: Europe/Paris
 * Unit Test : tests/Unit/Component/Provider/OrangeTest.php
 */
class Orange extends AbstractProvider implements ProviderInterface
{
    /**
     * @var \DateTimeZone
     */
    private $timezone;

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_orange.json'), $priority ?? 0.95);

        $this->timezone = new \DateTimeZone('Europe/Paris');
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = ChannelFactory::createChannel($channel);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $startDate = new \DateTimeImmutable($date);

        $responseDayBefore = $this->getContentFromURL($this->generateUrl($channelObj, $startDate->modify('-1 day')));
        $response = $this->getContentFromURL($this->generateUrl($channelObj, $startDate));
        if (false !== strpos($response, 'Invalid request') || false !== strpos($response, '504 Gateway Time-out')) {
            return false;
        }
        $jsonDayBefore = @json_decode($responseDayBefore, true);
        $json = @json_decode($response, true);
        if (empty($json) || isset($json['code'])) {
            return false;
        }
        if (!empty($jsonDayBefore)) {
            $json = array_merge($jsonDayBefore, $json);
        }
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($json as $val) {
            if (empty($val['diffusionDate']) || empty($val['duration'])) {
                continue;
            }
            $begin = (new \DateTimeImmutable('@'.$val['diffusionDate']))->setTimezone($this->timezone);
            if ($begin < $minDate) {
                continue;
            } elseif ($begin > $maxDate) {
                break;
            }
            $program = new Program($begin, $begin->modify(sprintf('+%d seconds', $val['duration'])));

            $program->addDesc($val['synopsis']);
            $program->addCategory($val['genre']);
            $program->addCategory($val['genreDetailed']);
            $program->setIcon((!empty($val['covers']) ? ''.end($val['covers'])['url'] : ''));
            $program->setRating($this->convertCSACodeToString(@$val['csa']));
            if (!isset($val['season'])) {
                $program->addTitle($val['title']);
            } else {
                if ($val['season']['number'] == '') {
                    $val['season']['number'] = '1';
                }
                if (empty($val['episodeNumber'])) {
                    $val['episodeNumber'] = '1';
                }
                $program->addTitle($val['season']['serie']['title']);
                $program->setEpisodeNum($val['season']['number'], $val['episodeNumber']);
                $program->addSubtitle($val['title']);
            }

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    private function convertCSACodeToString(int $csa): string
    {
        switch ($csa) {
            case '2':
                return '-10';
            case '3':
                return '-12';
            case '4':
                return '-16';
            case '5':
                return '-18';
            default:
                return 'Tout public';
        }
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->channelsList[$channel->getId()];

        return  'https://rp-ott-mediation-tv.woopic.com/api-gw/live/v3/applications/PC/programs?'.http_build_query([
            'period' => $date->format('Y-m-d'),
            'epgIds' => $channelId,
            'mco' => 'OFR'
        ]);
    }
}
