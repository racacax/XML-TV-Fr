<?php

namespace racacax\XmlTv\Component;

use Exception;
use racacax\XmlTv\ValueObject\DummyChannel;
use racacax\XmlTv\ValueObject\EPGEnum;

class SingleThreadedGenerator extends Generator
{
    /**
     * @throws Exception
     */
    protected function generateEpg(): void
    {
        foreach ($this->guides as $guide) {
            $channels = Utils::getChannelsFromGuide($guide);
            Logger::log(sprintf("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes (%s - %d chaines)\n", json_encode($guide['channels']), count($channels)));


            $countChannel = 0;
            foreach ($channels as $channelKey => $channelInfo) {
                $countChannel++;
                $providers = $this->getProviders($channelInfo['priority'] ?? []);
                foreach ($this->listDate as $date) {
                    Logger::addChannelEntry($guide['filename'], $channelKey, $date);
                    $cacheKey = sprintf('%s_%s.xml', $channelKey, $date);
                    Logger::log(sprintf("\e[95m[EPG GRAB] \e[39m%s (%d/%d) : %s", $channelKey, $countChannel, count($channels), $date));

                    if ($this->cache->getState($cacheKey) == EPGEnum::$FULL_CACHE) {
                        $providerName = $this->cache->getProviderName($cacheKey);
                        Logger::log(" | \e[33mOK \e[39m- $providerName (Cache) " . chr(10));
                        Logger::setChannelSuccessfulProvider($guide['filename'], $channelKey, $date, $providerName, true);

                        continue;
                    }
                    $channelFound = false;

                    $chosenChannel = null;
                    $chosenChannelState = EPGEnum::$FULL_CACHE;
                    $chosenChannelProvider = null;
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
                            Logger::addChannelFailedProvider($guide['filename'], $channelKey, $date, get_class($provider));

                            continue;
                        }
                        $channelFound = true;
                        $state = $provider->getChannelStateFromTimes($channel->getStartTimes(), $channel->getEndTimes(), $this->configurator);
                        if ($state == EPGEnum::$PARTIAL_CACHE && ($chosenChannel == null || $chosenChannel->getLatestStartDate() < $channel->getLatestStartDate())) {
                            $chosenChannel = $channel;
                            $chosenChannelState = EPGEnum::$PARTIAL_CACHE;
                            $chosenChannelProvider = $provider;
                        } elseif ($state == EPGEnum::$FULL_CACHE) {
                            $chosenChannel = $channel;
                            $chosenChannelState = EPGEnum::$FULL_CACHE;
                            $chosenChannelProvider = $provider;

                            break;
                        }
                    }
                    if ($channelFound) {
                        if ($chosenChannelState === EPGEnum::$PARTIAL_CACHE) {
                            if ($this->cache->getState($cacheKey) <= EPGEnum::$PARTIAL_CACHE) {
                                Logger::log(" | \e[32mOK\e[39m - " . Utils::extractProviderName($chosenChannelProvider).' (Partial)' . chr(10));
                                Logger::setChannelSuccessfulProvider($guide['filename'], $channelKey, $date, get_class($chosenChannelProvider).' (Partial)');
                                $this->cache->store($cacheKey, $this->formatter->formatChannel($chosenChannel, $chosenChannelProvider));
                            } else {
                                $channelFound = false;
                            }
                        } elseif ($chosenChannelState === EPGEnum::$FULL_CACHE) {
                            Logger::log(" | \e[32mOK\e[39m - " . Utils::extractProviderName($chosenChannelProvider) . chr(10));
                            Logger::setChannelSuccessfulProvider($guide['filename'], $channelKey, $date, get_class($chosenChannelProvider));
                            $this->cache->store($cacheKey, $this->formatter->formatChannel($chosenChannel, $chosenChannelProvider));
                        }
                    }

                    if (!$channelFound) {
                        if ($this->cache->getState($cacheKey)) {
                            $providerName = $this->cache->getProviderName($cacheKey);
                            Logger::setChannelSuccessfulProvider($guide['filename'], $channelKey, $date, "$providerName - Forced", true);
                            Logger::log(" | \e[33mOK \e[39m- $providerName (Forced Cache)" . chr(10));
                        } else {
                            if ($this->configurator->isEnableDummy()) {
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
