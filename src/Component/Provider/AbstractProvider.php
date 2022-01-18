<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ChannelFactory;

abstract class AbstractProvider
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $channelsList = [];

    protected static $priority;

    public function __construct(Client $client, string $jsonPath, float $priority)
    {
        if (empty($this->channelsList) && file_exists($jsonPath)) {
            $this->channelsList = json_decode(file_get_contents($jsonPath), true);
        }
        //todo: to improve
        self::$priority[static::class] = $priority;
        $this->client = $client;
    }

    public static function getPriority(): float
    {
        return self::$priority[static::class];
    }

    public function constructEPG(string $channel, string $date)
    {
        return ChannelFactory::createChannel($channel);
    }

    /**
     * @return array
     */
    public function getChannelsList(): array
    {
        return $this->channelsList;
    }

    public function channelExists(string $channel): bool
    {
        return isset($this->channelsList[$channel]);
    }

    protected function getContentFromURL($url, array $headers = []): string
    {
        if (empty($headers['User-Agent'])) {
            $headers['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0';
        }
        $response = $this->client->get(
            $url,
            ['headers'=> $headers]
        );

        return $response->getBody()->getContents();
    }
}
