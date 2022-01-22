<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TooManyRedirectsException;
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
            $headers['User-Agent'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:95.0) Gecko/20100101 Firefox/95.0';
        }
        try {
            $response = $this->client->get(
                $url,
                [
                    'headers'=> $headers,
                    'connect_timeout' => 1
                ]
            );
        } catch (\Exception $e) {
            // Hep to debug
            // dump($e);
            // No error accepted
            return '';
        }


        return $response->getBody()->getContents();
    }
}
