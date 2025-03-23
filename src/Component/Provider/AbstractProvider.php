<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\ValueObject\EPGEnum;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ChannelFactory;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\ProcessCache;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\Channel;

abstract class AbstractProvider
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array<string, string|number|array<string, string|number>>
     */
    protected $channelsList = [];

    /**
     * @var array<string, float>
     */
    protected static $priority;

    protected string $status;

    public function __construct(Client $client, string $jsonPath, float $priority)
    {
        if (empty($this->channelsList) && file_exists($jsonPath)) {
            $list = json_decode(
                file_get_contents($jsonPath) ?: '{}',
                true
            );
            $this->channelsList = is_array($list) ? $list : [];
        }

        //todo: to improve
        self::$priority[static::class] = $priority;
        $this->client = $client;
        $this->status = '';
    }

    public static function getPriority(): float
    {
        return self::$priority[static::class];
    }

    public function setStatus(string $status)
    {
        $this->status = $status;
        if (defined('CHANNEL_PROCESS')) {
            (new ProcessCache('status'))->save(CHANNEL_PROCESS, $this->status);
        } else {
            Logger::updateLine(' '.$status);
        }
    }

    /**
     * @return Channel|false
     */
    public function constructEPG(string $channel, string $date): Channel|bool
    {
        return ChannelFactory::createChannel($channel);
    }

    /**
     * @return array<string, string|number|array<string, string|number>>
     */
    public function getChannelsList(): array
    {
        return $this->channelsList;
    }

    public function channelExists(string $channel): bool
    {
        return isset($this->channelsList[$channel]);
    }

    public static function getMinMaxDate(string $date): array
    {
        $minStart = new \DateTimeImmutable($date, new \DateTimeZone('Europe/Paris'));
        $maxStart = $minStart->modify('+1 day')->modify('-1 second');

        return [$minStart, $maxStart];
    }

    /**
     * @param string $url
     * @param array<string, string> $headers
     * @return string
     */
    protected function getContentFromURL(string $url, array $headers = [], bool $ignoreCache = false): string
    {
        if (empty($headers['User-Agent'])) {
            $headers['User-Agent'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:95.0) Gecko/20100101 Firefox/95.0';
        }
        $cache = new ProviderCache(md5($url.json_encode($headers)));
        if (!$ignoreCache) {
            $content = $cache->getContent();
            if (!empty($content)) {
                return $content;
            }
        }

        try {
            $response = $this->client->get(
                $url,
                [
                    'headers' => $headers,
                    'connect_timeout' => 1,
                    'timeout' => 20
                ]
            );
        } catch (\Throwable $e) {
            // Hep to debug
            // dump($e);
            // No error accepted
            return '';
        }
        $content = $response->getBody()->getContents();
        $cache->setContent($content);

        return $content;
    }


    public function getChannelStateFromTimes(array $startTimes, array $endTimes, Configurator $config): int
    {
        if (count($startTimes) == 0) {
            return EPGEnum::$NO_CACHE;
        }
        if (max($endTimes) - min($startTimes) > $config->getMinTimeRange()) {
            return EPGEnum::$FULL_CACHE;
        } else {
            return EPGEnum::$PARTIAL_CACHE;
        }
    }
}
