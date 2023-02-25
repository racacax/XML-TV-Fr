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
 * @version 0.1 : 25/02/2023
 */
class TV5Global extends AbstractProvider implements ProviderInterface
{
    private static $cache = array();
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tv5global.json'), $priority ?? 0.6);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);

        if (!$this->channelExists($channel)) {
            return false;
        }
        if(!isset(self::$cache[$channel])) {
            $content = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
            preg_match_all("/class=\"grid-item-time\" data-startDateTime=\"(.*?)\" data-endDateTime=\"(.*?)\" data-time-zone=\"(.*?)\".*?<h2 class=\"grid-item-title\">.*?\">(.*?)<\/a>.*?<p class=\"grid-item-categ\">(.*?)<\/p>/s", $content, $parsedData);
            self::$cache[$channel] = $parsedData;
        }
        $dateTS = strtotime($date);
        for($i=0; $i < count(self::$cache[$channel][0]); $i++) {
            date_default_timezone_set(self::$cache[$channel][3][$i]);
            $dateEnd = strtotime(self::$cache[$channel][2][$i]);
            $dateStart = strtotime(self::$cache[$channel][1][$i]);
            if($dateEnd >= $dateTS && $dateStart <= $dateTS + 86400) {
                $program = new Program($dateStart, $dateEnd);
                $program->addTitle(self::$cache[$channel][4][$i]);
                $program->addDesc('Pas de description'); // TODO. Add possibility to get description
                $program->addCategory(self::$cache[$channel][5][$i]);
                $channelObj->addProgram($program);
            }
        }
        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];

        return 'https://www.tv5monde.com/programmes/fr/'.$channel_id.'/';
    }
}
