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
 * @version 0.1 : 16/02/2020
 */
class Orange extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_orange.json'), $priority ?? 0.95);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $channel_id = $this->channelsList[$channel];

        $response = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
        $json = json_decode($response, true);
        if (preg_match('(Invalid request)', $response) || preg_match('(504 Gateway Time-out)', $response) || !isset($json)) {
            return false;
        }
        if (isset($json['code'])) {
            return false;
        }
        foreach ($json as $val) {
            switch (@$val['csa']) {
                case '2':
                    $csa = '-10';

                    break;
                case '3':
                    $csa = '-12';

                    break;
                case '4':
                    $csa = '-16';

                    break;
                case '5':
                    $csa = '-18';

                    break;
                default:
                    $csa = 'Tout public';

                    break;
            }
            $program = new Program($val['diffusionDate'], $val['diffusionDate']+$val['duration']);
            $program->addDesc($val['synopsis']);
            $program->addCategory($val['genre']);
            $program->addCategory($val['genreDetailed']);
            $program->setIcon((!empty($val['covers']) ? ''.end($val['covers'])['url'] : ''));
            $program->setRating($csa);
            if (!isset($val['season'])) {
                $program->addTitle($val['title']);
            } else {
                if ($val['season']['number'] =='') {
                    $val['season']['number'] ='1';
                }
                if ($val['episodeNumber'] =='') {
                    $val['episodeNumber'] ='1';
                }
                $program->addTitle($val['season']['serie']['title']);
                $program->setEpisodeNum($val['season']['number'], $val['episodeNumber']);
                $program->addSubtitle($val['title']);
            }

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->channelsList[$channel->getId()];

        return  'https://rp-live.orange.fr/live-webapp/v3/applications/STB4PC/programs?'.http_build_query([
            'period' => $date->format('Y-m-d'),
            'epgIds' => $channelId,
            'mco' => 'OFR'
        ]);
    }
}
