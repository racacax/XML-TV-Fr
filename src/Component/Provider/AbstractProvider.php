<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use racacax\XmlTv\Component\ChannelFactory;
use racacax\XmlTv\ValueObject\Channel;

abstract class AbstractProvider {

    /**
     * @var Channel|null
     */
    protected $channelObj;

    /**
     * @var array
     */
    protected $channelsList = [];

    protected static $priority;

    public function __construct(string $jsonPath, float $priority)
    {
        if (empty($this->channelsList) && file_exists($jsonPath)) {
            $this->channelsList = json_decode(file_get_contents($jsonPath), true);
        }
        //todo: to improve
        self::$priority[static::class] = $priority;
    }

    public static function getPriority(): float
    {
        return self::$priority[static::class];
    }

    public function constructEPG(string $channel, string $date)
    {
        $this->channelObj = ChannelFactory::createChannel($channel);

        return $this->channelObj;
    }

    /**
     * @return array
     */
    public function getChannelsList(): array
    {
        return $this->channelsList;
    }

    public function channelExists($channel): bool
    {
        return isset($this->getChannelsList()[$channel]);
    }

    protected function getContentFromURL($url, $headers = []): string
    {
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, $url);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
        if(!empty($headers)) {
            curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
        } else {
            curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0');
        }
        $return = curl_exec($ch1);
        if(is_string($return)) {
            $res1 = html_entity_decode($return, ENT_QUOTES);
        } else {
            $res1 ='';
        }
        curl_close($ch1);
        return $res1;
    }
}