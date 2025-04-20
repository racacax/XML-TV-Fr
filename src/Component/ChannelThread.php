<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\ValueObject\EPGEnum;
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


    /**
     * @return ProviderInterface[]
     */
    private function getRemainingProviders(): array
    {
        $providers = $this->generator->getProviders($this->info['priority'] ?? []);
        $providers = array_filter($providers, fn ($provider) => $provider->channelExists($this->channel));
        if (count($this->failedProviders) > 0) {
            $failedProviders = $this->generator->getProviders($this->failedProviders);
        } else {
            $failedProviders = [];
        }

        return array_diff($providers, $failedProviders);
    }

    private function waitForCompletion(string $fileName, string $providerName): void
    {
        $cacheInstance = new ProcessCache('cache');
        $statusInstance = new ProcessCache('status');
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
                $this->manager->removeChannelFromProvider($providerName, $this->channel);

                return;
            }
        }
    }

    private function getDataFromProvider(string $providerName, ProviderInterface $provider, string $date, string $cacheKey): array
    {
        $cache = $this->generator->getCache();
        $cacheInstance = new ProcessCache('cache');
        flush();
        $bytes = random_bytes(10);
        $fileName = bin2hex($bytes);
        $cmd = Utils::getThreadCommand($providerName, $date, $this->getChannelInfo(), $fileName, $this->generatorId);
        Utils::startCmd($cmd);

        $this->waitForCompletion($fileName, $providerName);
        $providerResult = $cacheInstance->pop($fileName);
        if ($providerResult == 'false') {
            $this->failedProviders[] = $providerName;
            Logger::addChannelFailedProvider($this->channelsFile, $this->channel, $date, get_class($provider));

            return ['success' => false];
        } else {
            [$startTimes, $endTimes] = Utils::getStartAndEndDatesFromXMLString($providerResult);
            $state = $provider->getChannelStateFromTimes($startTimes, $endTimes, $this->generator->getConfigurator());
            if ($state == EPGEnum::$PARTIAL_CACHE) {
                if (($cache->getState($cacheKey) != EPGEnum::$NO_CACHE)) {
                    $cacheContent = $cache->get($cacheKey);
                    [$cacheStartTimes, $_] = Utils::getStartAndEndDatesFromXMLString($cacheContent);
                    if (max($cacheStartTimes) > max($startTimes)) {
                        return ['success' => false];
                    }
                }
                $cache->store($cacheKey, $providerResult);

                return ['success' => true, 'provider' => $providerName, 'isCache' => false, 'skipped' => false, 'isPartial' => true];
            } else {
                $cache->store($cacheKey, $providerResult);

                return ['success' => true, 'provider' => $providerName, 'isCache' => false, 'skipped' => false, 'isPartial' => false];
            }
        }
    }
    private function gatherData(string $date): array
    {
        $cache = $this->generator->getCache();
        $cacheKey = sprintf('%s_%s.xml', $this->channel, $date);
        $currentResult = ['success' => false];
        if ($cache->getState($cacheKey) == EPGEnum::$FULL_CACHE) {
            $providerName = $cache->getProviderName($cacheKey);

            return ['success' => true, 'provider' => $providerName, 'isCache' => true, 'skipped' => false];
        } else {
            $providers = $this->getRemainingProviders();
            foreach ($providers as $provider) {
                $providerName = Utils::extractProviderName($provider);
                if (!$this->manager->canUseProvider($providerName)) {
                    return ['skipped' => true];
                } else {
                    $this->manager->addChannelToProvider($providerName, $this->channel);
                    $this->provider = $providerName;
                    $this->hasStarted = true;
                }
                $this->status = Utils::colorize('En cours...', 'magenta');

                $results = $this->getDataFromProvider($providerName, $provider, $date, $cacheKey);
                if ($results['success']) {
                    $currentResult = $results;
                }
                if (!@$currentResult['isPartial']) {
                    return $currentResult;
                }
            }
        }

        return $currentResult;
    }

    private function getStatusString(array $results, string $cacheKey): string
    {
        $cache = $this->generator->getCache();
        $providerName = '';
        if ($results['success']) {
            $providerName = ' - '.$results['provider'];
            $statusString = 'OK';
            $color = 'green';
            if (@$results['isPartial']) {
                $statusString .= ' (Partial)';
                $color = 'orange';
            }
            if (@$results['isCache']) {
                $providerName = ' - '.$cache->getProviderName($cacheKey);
                $statusString .= ' (Cache)';
                $color = 'yellow';
            }
        } else {
            if ($cache->getState($cacheKey)) {
                $providerName = ' - '.$cache->getProviderName($cacheKey);
                $statusString = 'OK (Forced Cache)';
                $color = 'orange';
            } else {
                $statusString = 'HS';
                $color = 'red';
            }
        }

        return Utils::colorize($statusString, $color).$providerName;
    }
    private function run(): void
    {
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

            $results = $this->gatherData($date);
            if (@$results['skipped']) {
                $this->manager->addChannel($this->channel, $this->failedProviders, $this->datesGathered);

                return;
            }
            $statusString = $this->getStatusString($results, $cacheKey);
            $this->addEvent($date, $statusString);
            if ($results['success']) {
                Logger::setChannelSuccessfulProvider($this->channelsFile, $this->channel, $date, $results['provider'], $results['isCache']);
            } elseif ($cache->getState($cacheKey)) {
                $providerName = $cache->getProviderName($cacheKey);
                Logger::setChannelSuccessfulProvider($this->channelsFile, $this->channel, $date, $providerName.' - Forced', true);
            } elseif ($this->generator->getConfigurator()->isEnableDummy()) {
                $cache->store($cacheKey, $this->generator->getFormatter()->formatChannel(new DummyChannel($this->channel, $date), null));
            }
            $this->datesGathered[] = $date;
            $this->failedProviders = [];
        }

        $this->manager->incrChannelsDone();
    }

    private function addEvent(string $date, string $statusInfo)
    {
        $this->manager->addEvent(($this->channel ?? '').' : '.$date.' | '.$statusInfo);
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
