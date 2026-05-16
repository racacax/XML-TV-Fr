<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use racacax\XmlTv\Component\ChannelFactory;
use racacax\XmlTv\Component\Exception\NetworkConnectionException;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\EPGEnum;

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
    private ?\Amp\Sync\Channel $workerChannel;

    // Todo: Add logo priority
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
        $this->workerChannel = null;
    }

    public function setWorkerChannel(\Amp\Sync\Channel $channel): void
    {
        $this->workerChannel = $channel;
    }

    public static function getPriority(): float
    {
        return self::$priority[static::class];
    }

    public function getInstancePriority(): float
    {
        return static::getPriority();
    }

    public function setStatus(string $status): void
    {
        $this->workerChannel?->send($status);
    }

    /**
     * @return Channel|false
     */
    public function constructEPG(string $channel, string $date): Channel|bool
    {
        return ChannelFactory::createChannel($channel);
    }

    public function getLogo(string $channel): ?string
    {
        if (!$this->channelExists($channel)) {
            throw new \Exception("Channel $channel does not exist in this provider");
        }

        return null;
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
    protected function getContentFromURL(string $url, array $headers = [], bool $ignoreCache = false, bool $decodeEntities = true): string
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

        @mkdir(dirname($cache->getLockPath()), 0777, true);
        $fp = fopen($cache->getLockPath(), 'c');
        if ($fp !== false) {
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                $this->setStatus(Utils::colorize('Cache en attente...', 'yellow'));
                flock($fp, LOCK_EX);
            }
            // Another thread may have fetched and cached while we were waiting for the lock
            if (!$ignoreCache) {
                $content = $cache->getContent();
                if (!empty($content)) {
                    flock($fp, LOCK_UN);
                    fclose($fp);

                    return $content;
                }
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
        } catch (ConnectException $e) {
            if ($fp !== false) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            throw new NetworkConnectionException('Connection failed: '.$e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            if ($fp !== false) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            return '';
        }
        $content = $decodeEntities ? html_entity_decode($response->getBody()->getContents(), ENT_QUOTES) : $response->getBody()->getContents();
        $cache->setContent($content);

        if ($fp !== false) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $content;
    }


    public function getChannelStateFromTimes(array $startTimes, array $endTimes, Configurator $config): int
    {
        if (count($startTimes) == 0) {
            return EPGEnum::$NO_CACHE;
        }

        if (Utils::getTimeSpanFromStartAndEndTimes($startTimes, $endTimes) > $config->getMinEndTime()) {
            return EPGEnum::$FULL_CACHE;
        } else {
            return EPGEnum::$PARTIAL_CACHE;
        }
    }

    public function __toString()
    {
        return get_class($this);
    }
}
