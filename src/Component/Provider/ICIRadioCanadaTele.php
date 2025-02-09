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
 * @version 0.1 : 18/12/2021
 */
class ICIRadioCanadaTele extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_iciradiocanada.json'), $priority ?? 0.65);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $json = json_decode($this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date))), true);
        if (!isset($json['data']['broadcasts'])) {
            return false;
        }
        foreach ($json['data']['broadcasts'] as $broadcast) {
            $program = new Program(strtotime($broadcast['startsAt']), strtotime($broadcast['endsAt']));
            $program->addCategory($broadcast['subtheme']);
            $program->setIcon(str_replace('{0}', '635', str_replace('{1}', '16x9', @$broadcast['pircture']['url'] ?? '')));
            $program->addTitle($broadcast['title']);
            $program->addSubtitle($broadcast['subtitle']);

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];

        return sprintf(
            'https://services.radio-canada.ca/neuro/sphere/v1/tele/schedule/%s?regionId=%s',
            $date->format('Y-m-d'),
            $channel_id
        );
    }
}
