<?php

namespace racacax\XmlTv\Component;

use Exception;
use racacax\XmlTv\ValueObject\DummyChannel;

class SingleThreadedGenerator extends Generator
{
    /**
     * @throws Exception
     */
    protected function generateEpg(): void
    {
        foreach ($this->guides as $guide) {
            $channels = json_decode(file_get_contents($guide['channels']), true);
            Logger::log(sprintf("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes (%s - %d chaines)\n", $guide['channels'], count($channels)));


            $countChannel = 0;
            foreach ($channels as $channelKey => $channelInfo) {
                $countChannel++;
                $providers = $this->getProviders($channelInfo['priority'] ?? []);
                foreach ($this->listDate as $date) {
                    Logger::addChannelEntry($guide['channels'], $channelKey, $date);
                    $cacheKey = sprintf('%s_%s.xml', $channelKey, $date);
                    Logger::log(sprintf("\e[95m[EPG GRAB] \e[39m%s (%d/%d) : %s", $channelKey, $countChannel, count($channels), $date));

                    if ($this->cache->getState($cacheKey) == CacheFile::$FULL_CACHE) {
                        Logger::log(" | \e[33mOK \e[39m- From Cache " . chr(10));
                        Logger::setChannelSuccessfulProvider($guide['channels'], $channelKey, $date, 'Cache', true);

                        continue;
                    }
                    $channelFound = false;
                    foreach ($providers as $provider) {
                        $old_zone = date_default_timezone_get();
                        if (!$provider->channelExists($channelKey)) {
                            continue;
                        }

                        try {
                            $channel = @$provider->constructEPG($channelKey, $date);
                        } catch (\Throwable $e) {
                            $channel = false;
                        }
                        date_default_timezone_set($old_zone);
                        if ($channel === false || $channel->getProgramCount() === 0) {
                            Logger::addChannelFailedProvider($guide['channels'], $channelKey, $date, get_class($provider));

                            continue;
                        }

                        $channelFound = true;
                        Logger::setChannelSuccessfulProvider($guide['channels'], $channelKey, $date, get_class($provider));
                        $this->cache->store($cacheKey, $this->formatter->formatChannel($channel, $provider));
                        Logger::log(" | \e[32mOK\e[39m - " . Utils::extractProviderName($provider) . chr(10));

                        break;
                    }

                    if (!$channelFound) {
                        if ($this->cache->getState($cacheKey)) {
                            Logger::setChannelSuccessfulProvider($guide['channels'], $channelKey, $date, 'Forced Cache', true);
                            Logger::log(" | \e[33mOK \e[39m- From Cache (Forced)" . chr(10));
                        } else {
                            if ($this->createEpgIfNotFound) {
                                $this->cache->store($cacheKey, $this->formatter->formatChannel(new DummyChannel($channelKey, $date), null));
                            }
                            Logger::log(" | \e[31mHS\e[39m" . chr(10));
                        }

                    }
                }
            }
            Logger::log("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes terminée...\n");
        }
    }
}
