<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use \Exception;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Teleboy extends AbstractProvider implements ProviderInterface
{
    private static $API_KEY = '';

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_teleboy.json'), $priority ?? 0.4);
    }


    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $content = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)),
            ["x-teleboy-apikey" => $this->getAPIKey()]);
        $json = json_decode($content, true);
        if(empty($json["data"]["items"])) {
            return false;
        }
        foreach($json["data"]["items"] as $item) {
            $programObj = new Program(strtotime($item["begin"]), strtotime($item['end']));
            $programObj->addTitle($item['title']);
            if(!empty($item['subtitle'])) {
                $programObj->addSubtitle($item['subtitle']);
            }
            $programObj->addDesc(@$item['short_description'] ?? "Aucune description");
            $programObj->addCategory(@$item['genre']['name_fr'] ?? "Inconnu");
            if(!empty($item['primary_image'])) {
                $programObj->setIcon($item['primary_image']['base_path'].'raw/'.$item['primary_image']['hash'].".jpg");
            }
            $channelObj->addProgram($programObj);
        }



        return $channelObj;
    }

    public function getAPIKey() {
        if(self::$API_KEY == "") {
            $content = $this->getContentFromURL("https://www.teleboy.ch/fr/");
            $key = explode("'", explode('tvapiKey:', $content)[1])[1];
            if(empty($key)) {
                throw new Exception("API Error");
            }
            self::$API_KEY = $key;
        }
        return self::$API_KEY;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $date_start = $date->format('Y-m-d+00:00:00');
        $date_end = $date->format('Y-m-d+23:59:59');
        $channelId = $this->channelsList[$channel->getId()];
        return "https://api.teleboy.ch/epg/broadcasts?begin=${date_start}&end=${date_end}&expand=flags,primary_image,genre,short_description&limit=9999&skip=0&sort=station&station=${channelId}";
    }
}
