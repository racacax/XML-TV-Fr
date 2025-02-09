<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\StaticComponent\ChannelInformation;

abstract class Generator
{
    /**
     * @var array
     */
    protected array $extraParams;

    /**
     * @var array
     */
    protected array $listDate = [];
    /**
     * @var bool
     */
    protected bool $createEpgIfNotFound;
    /**
     * @var XmlExporter
     */
    protected XmlExporter $exporter;
    /**
     * @var XmlFormatter
     */
    protected XmlFormatter $formatter;
    /**
     * @var CacheFile
     */
    protected CacheFile $cache;
    /**
     * @var int
     */
    protected int $nbThreads;

    public function __construct(\DateTimeImmutable $start, \DateTimeImmutable $stop, bool $createEpgIfNotFound, int $nbThreads, array $extraParams)
    {
        $this->createEpgIfNotFound = $createEpgIfNotFound;
        $this->extraParams = $extraParams;
        $this->nbThreads = $nbThreads;
        $current = new \DateTime();
        $current->setTimestamp($start->getTimestamp());
        while ($current <= $stop) {
            $this->listDate[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }
    }


    public array $guides;
    /**
     * @var ProviderInterface[] list of all provider
     */
    protected array $providers;

    public function addGuides(array $guidesAsArray): void
    {
        $this->guides = $guidesAsArray;
    }

    /**
     * @param ProviderInterface[] $providers
     */
    public function setProviders(array $providers): void
    {
        $this->providers = $providers;
    }

    /**
     * @return ProviderInterface[]
     */
    public function getProviders(array $list = []): array
    {
        if (empty($list)) {
            return $this->providers;
        }

        return array_filter(
            $this->providers,
            function (ProviderInterface $provider) use ($list) {
                return
                    in_array(Utils::extractProviderName($provider), $list, true) ||
                    in_array(get_class($provider), $list, true);
            }
        );
    }

    public function getExtraParams(): array
    {
        return $this->extraParams;
    }

    abstract protected function generateEpg(): void;

    public function generate(): void
    {
        ProviderCache::clearCache();
        $this->generateEpg();
        ProviderCache::clearCache();
        Logger::save();
    }

    public function getCache(): CacheFile
    {
        return $this->cache;
    }

    public function createEpgIfNotFound(): bool
    {
        return $this->createEpgIfNotFound;
    }

    public function getFormatter(): XmlFormatter
    {
        return $this->formatter;
    }

    public function getListDate(): array
    {
        return $this->listDate;
    }

    /**
     * @throws \Exception
     */
    public function exportEpg(string $exportPath): void
    {
        @mkdir($exportPath, 0777, true);

        foreach ($this->guides as $guide) {
            $channels = json_decode(file_get_contents($guide['channels']), true);
            $defaultInfo = ChannelInformation::getInstance();
            $this->exporter->startExport($exportPath . $guide['filename']);
            $listCacheKey = [];
            $listAliases = [];
            foreach ($channels as $channelKey => $channelInfo) {
                $icon = $channelInfo['icon'] ?? $defaultInfo->getDefaultIcon($channelKey);
                $name = $channelInfo['name'] ?? $defaultInfo->getDefaultName($channelKey) ?? $channelKey;
                $alias = $channelInfo['alias'] ?? $channelKey;
                if ($alias != $channelKey) {
                    $listAliases[$channelKey] = $alias;
                }
                $this->exporter->addChannel($alias, $name, $icon);
                $listCacheKey = array_merge($listCacheKey, array_map(
                    function (string $date) use ($channelKey) {
                        return sprintf('%s_%s.xml', $channelKey, $date);
                    },
                    $this->listDate
                ));
            }
            foreach ($listCacheKey as $keyCache) {
                if (!$this->cache->getState($keyCache)) {
                    continue;
                }
                $cache = $this->cache->get($keyCache);
                $channelId = explode('_', $keyCache)[0];
                if (array_key_exists($channelId, $listAliases)) {
                    $cache = str_replace('channel="' . $channelId . '"', 'channel="' . $listAliases[$channelId] . '"', $cache);
                }

                try {
                    $this->exporter->addProgramsAsString(
                        $cache
                    );
                } catch (\Throwable $e) {
                    $this->cache->clear($keyCache);
                }
            }
            $this->exporter->stopExport();
        }
    }

    public function setExporter(XmlExporter $exporter): void
    {
        $this->exporter = $exporter;
        $this->formatter = $exporter->getFormatter();
    }


    public function setCache(CacheFile $cache): void
    {
        $this->cache = $cache;
    }

    public function clearCache(int $maxCacheDay): void
    {
        $this->cache->clearCache($maxCacheDay);
    }
}
