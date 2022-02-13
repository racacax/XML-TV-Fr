<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Afrique extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_afrique.json'), $priority ?? 0.2);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $url = $this->generateUrl($channelObj, new \DateTimeImmutable($date));
        $content = json_decode($this->getContentFromURL($url), true);
        if (!isset($content['timeSlices'])) {
            return false;
        }
        $json = $content['timeSlices'];
        $count = 0;
        foreach ($json as $section) {
            foreach ($section['contents'] as $section2) {
                $count++;
                $program = new Program($section2['startTime'], $section2['endTime']);
                $program->addTitle($section2['title']);
                $program->addDesc('Aucune description');
                $program->addCategory('Inconnu');
                $program->setIcon($section2['URLImage']);

                $channelObj->addProgram($program);
            }
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $day = ($date->getTimestamp() - strtotime(date('Y-m-d')))/86400;

        return 'https://service.canal-overseas.com/ott-frontend/vector/83001/channel/' . $this->channelsList[$channel->getId()] . '/events?filter.day=' . $day;
    }
}
