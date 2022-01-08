<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\StaticComponent\ChannelInformation;
use racacax\XmlTv\ValueObject\DummyChannel;

class Generator
{
    private $listDate = [];
    /**
     * @var bool
     */
    private $createEpgIfNotFound;
    /**
     * @var XmlExporter
     */
    private $exporter;
    /**
     * @var XmlFormatter
     */
    private $formatter;
    /**
     * @var CacheFile
     */
    private $cache;

    public function __construct(\DateTimeImmutable $start, \DateTimeImmutable $stop, bool $createEpgIfNotFound)
    {
        $this->createEpgIfNotFound = $createEpgIfNotFound;
        $current = new \DateTime();
        $current->setTimestamp($start->getTimestamp());
        while ($current <= $stop) {
            $this->listDate[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

    }


    public $guides;
    /**
     * @var ProviderInterface[] list of all provider
     */
    private $providers;

    public function addGuides(array $guidesAsArray)
    {
        $this->guides = $guidesAsArray;
    }

    /**
     * @param ProviderInterface[] $providers
     */
    public function setProviders(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @return ProviderInterface[]
     */
    public function getProviders(array $list): array
    {
       if(empty($list)) {
           return $this->providers;
       }
       return array_filter(
           $this->providers,
           function(ProviderInterface $provider) use ($list) {
               return
                   in_array(Utils::extractProviderName($provider), $list, true) ||
                   in_array(get_class($provider), $list, true)
               ;
           }
       );
    }

    public function generateEpg()
    {
        foreach ($this->guides as $guide){
            $channels = json_decode(file_get_contents($guide['channels']),true);
            Logger::log(sprintf("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes (%s - %d chaines)\n", $guide['channels'], count($channels)));


            $logs = array('channels'=>array(), 'xml'=>array(),'failed_providers'=>array());

            foreach($channels as $channelKey => $channelInfo) {
                $providers = $this->getProviders($channelInfo["priority"] ?? []);
                foreach($this->listDate as $date) {
                    $cacheKey = sprintf("%s_%s.xml", $channelKey, $date);
                    if(!isset($logs["channels"][$date][$channelKey])){
                        $logs["channels"][$date][$channelKey] = [
                            'success' => false,
                            'provider' => null,
                            'cache'=> false,
                            'failed_providers' => [],
                        ];
                    }
                    Logger::log(sprintf("\e[95m[EPG GRAB] \e[39m%s : %s", $channelKey, $date));

                    if ($this->cache->has($cacheKey)){
                        Logger::log(" | \e[33mOK \e[39m- From Cache ".chr(10));
                        continue;
                    }
                    $channelFound = false;
                    foreach ($providers as $provider) {
                        $old_zone = date_default_timezone_get();
                        $channel = $provider->constructEPG($channelKey, $date);
                        date_default_timezone_set($old_zone);
                        if ($channel === false || $channel->getProgramCount()>0){
                            $logs['channels'][$date][$channelKey]['failed_providers'][] = get_class($provider);
                            $logs['failed_providers'][get_class($provider)] = true;
                            continue;
                        }

                        $channelFound = true;
                        $logs['channels'][$date][$channelKey] = [
                            'success' => true,
                            'provider' => get_class($provider),
                            'cache'=> false,
                        ];
                        $this->cache->store($cacheKey, $this->formatter->formatChannel($channel, $provider));
                        Logger::log(" | \e[32mOK\e[39m - ".Utils::extractProviderName($provider).chr(10));
                        break ;
                    }

                    if(!$channelFound) {
                        if ($this->createEpgIfNotFound) {
                            $this->cache->store($cacheKey, $this->formatter->formatChannel(new DummyChannel($channelKey, $date), null));
                        }
                        Logger::log(" | \e[31mHS\e[39m".chr(10));
                    }
                }
            }
            Logger::log("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes terminée...\n");
            Logger::debug(json_encode($logs));
        }
    }
    public function exportEpg(string $exportPath)
    {
        @mkdir($exportPath, 0777, true);

        foreach ($this->guides as $guide){
            $channels = json_decode(file_get_contents($guide['channels']),true);
            $defaultInfo = ChannelInformation::getInstance();
            $this->exporter->startExport($exportPath . $guide['filename']);
            $listCacheKey = [];
            foreach($channels as $channelKey => $channelInfo) {
                $icon = $channelInfo['icon'] ?? $defaultInfo->getDefaultIcon($channelKey);
                $name = $channelInfo['name'] ?? $defaultInfo->getDefaultName($channelKey) ?? $channelKey;
                $this->exporter->addChannel($channelKey, $name, $icon);
                $listCacheKey = array_merge($listCacheKey, array_map(
                    function(string $date) use ($channelKey) {
                        return sprintf("%s_%s.xml", $channelKey, $date);
                    },
                    $this->listDate
                ));
            }
            foreach($listCacheKey as $keyCache) {
                $this->exporter->addProgramsAsString(
                    $this->cache->get($keyCache)
                );
            }
            $this->exporter->stopExport();
        }
    }

    public function setExporter(XmlExporter $exporter)
    {
        $this->exporter = $exporter;
        $this->formatter = $exporter->getFormatter();
    }


    public function setCache(CacheFile $cache)
    {
        $this->cache = $cache;
    }

    public function clearCache(int $maxCacheDay)
    {
        $this->cache->clearCache($maxCacheDay);
    }
}