<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\ValueObject\DummyChannel;

use function Amp\async;
use function Amp\delay;

class ChannelThread
{
    private ?string $channel;
    private ?string $provider = null;
    private ?array $info;
    private ?array $failedProviders;
    private ?array $datesGathered;
    private ?array $extraParams;
    private ChannelsManager $manager;
    private Generator $generator;
    private string $status;
    private string $date;
    private bool $isRunning;
    private bool $hasStarted;
    private string $generatorId;
    private string $channelsFile;

    public function __construct(ChannelsManager $manager, Generator $generator, string $generatorId, string $channelsFile)
    {
        $this->manager = $manager;
        $this->generator = $generator;
        $this->isRunning = false;
        $this->hasStarted = false;
        $this->generatorId = $generatorId;
        $this->channelsFile = $channelsFile;
    }

    public function setChannel(array $channelInfo): void
    {
        $this->hasStarted = false;
        $this->status = "\e[35mDémarrage...e[39m";
        $this->channel = $channelInfo['key'];
        $this->info = $channelInfo['info'];
        $this->failedProviders = $channelInfo['failedProviders'];
        $this->datesGathered = $channelInfo['datesGathered'];
        $this->extraParams = $channelInfo['extraParams'];
    }

    public function __toString()
    {
        if (!$this->hasStarted || !$this->isRunning) {
            return Utils::colorize('En pause...', 'yellow');
        }
        $str = $this->getChannel().' - '.$this->getDate().' - '.$this->getProvider();
        $status = $this->getStatus();
        if (isset($status)) {
            $str .= ' '.$status;
        }

        return $str;
    }

    private function getChannelInfo(): string
    {
        return json_encode(['key' => $this->channel, 'info' => $this->info, 'extraParams' => $this->extraParams]);
    }

    private function run(): void
    {
        $cacheInstance = new ProcessCache('cache');
        $statusInstance = new ProcessCache('status');
        $providers = $this->generator->getProviders($this->info['priority'] ?? []);
        if (count($this->failedProviders) > 0) {
            $failedProviders = $this->generator->getProviders($this->failedProviders);
        } else {
            $failedProviders = [];
        }
        // TODO : providers failed diff
        $cache = $this->generator->getCache();
        $dates = $this->generator->getListDate();
        $total = count($dates);
        $dates = array_diff($dates, $this->datesGathered);
        $progress = $total - count($dates);
        foreach ($dates as $date) {
            Logger::addChannelEntry($this->channelsFile, $this->channel, $date);
            $progress++;
            $this->date = $date." ($progress/$total)";
            $cacheKey = sprintf('%s_%s.xml', $this->channel, $date);

            if ($cache->getState($cacheKey) == CacheFile::$FULL_CACHE) {
                Logger::setChannelSuccessfulProvider($this->channelsFile, $this->channel, $date, 'Cache', true);

                continue;
            }
            $channelFound = false;
            foreach ($providers as $provider) {
                if (in_array($provider, $failedProviders)) {
                    continue;
                }
                $providerClass = Utils::extractProviderName($provider);
                if (!$provider->channelExists($this->channel)) {
                    continue;
                } elseif (!$this->manager->canUseProvider($providerClass)) {
                    $this->manager->addChannel($this->channel, $this->failedProviders, $this->datesGathered);

                    return;
                } else {
                    $this->manager->addChannelToProvider($providerClass, $this->channel);
                    $this->provider = $providerClass;
                    $this->hasStarted = true;
                }
                $this->status = Utils::colorize('En cours...', 'magenta');


                flush();
                $bytes = random_bytes(10);
                $fileName = bin2hex($bytes);
                $cmd = Utils::getThreadCommand($providerClass, $date, $this->getChannelInfo(), $fileName, $this->generatorId);
                Utils::startCmd($cmd);
                $channel = 'false';
                while (true) {
                    if (!$cacheInstance->exists($fileName)) {
                        if ($statusInstance->exists($fileName)) {
                            $this->status = Utils::colorize($statusInstance->pop($fileName), 'magenta');
                        }
                        delay(0.001);
                    } else {
                        while ($cacheInstance->exists($fileName.'.lock')) {
                            delay(0.001);
                        }
                        $channel = $cacheInstance->pop($fileName);
                        $this->manager->removeChannelFromProvider($providerClass, $this->channel);

                        break;
                    }
                }


                if ($channel == 'false') {
                    $this->failedProviders[] = get_class($provider);
                    Logger::addChannelFailedProvider($this->channelsFile, $this->channel, $date, get_class($provider));

                    continue;
                }

                $channelFound = true;
                Logger::setChannelSuccessfulProvider($this->channelsFile, $this->channel, $date, get_class($provider));
                $cache->store($cacheKey, $channel);

                break;
            }

            if (!$channelFound) {
                if ($cache->getState($cacheKey)) {
                    Logger::setChannelSuccessfulProvider($this->channelsFile, $this->channel, $date, 'Forced Cache', true);
                } elseif ($this->generator->createEpgIfNotFound()) {
                    $cache->store($cacheKey, $this->generator->getFormatter()->formatChannel(new DummyChannel($this->channel, $date), null));
                }
            }
            $this->datesGathered[] = $date;
            $this->failedProviders = [];
        }
        $this->manager->incrChannelsDone();
    }

    public function start(): void
    {
        if (!$this->isRunning) {
            $this->isRunning = true;
            $fn = function () {
                $this->run();
                $this->isRunning = false;
            };
            async($fn);
        }
    }
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    public function getChannel(): ?string
    {
        if (isset($this->channel)) {
            return $this->channel;
        }

        return null;
    }

    public function getStatus(): ?string
    {
        if (isset($this->status)) {
            return $this->status;
        }

        return null;
    }

    public function getDate(): ?string
    {
        if (isset($this->date)) {
            return $this->date;
        }

        return null;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }
}
