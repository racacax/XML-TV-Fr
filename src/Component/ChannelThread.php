<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use Amp\Sync\Channel;
use racacax\XmlTv\ValueObject\EPGDate;
use racacax\XmlTv\ValueObject\EPGEnum;
use racacax\XmlTv\ValueObject\DummyChannel;

use function Amp\async;
use function Amp\delay;
use function Amp\Parallel\Worker\getWorker;

class ChannelThread
{
    protected ?string $channel;
    protected ?string $provider = null;
    protected ?array $info;
    protected ?array $failedProviders;
    protected ?array $datesGathered;
    protected ?array $extraParams;
    protected ChannelsManager $manager;
    protected Generator $generator;
    protected string $status;
    protected string $date;
    protected bool $isRunning;
    protected bool $hasStarted;

    public function __construct(ChannelsManager $manager, Generator $generator)
    {
        $this->manager = $manager;
        $this->generator = $generator;
        $this->isRunning = false;
        $this->hasStarted = false;
    }

    public function setChannel(array $channelInfo): void
    {
        $this->hasStarted = false;
        $this->status = "\e[35mDémarrage...\e[39m";
        $this->channel = $channelInfo['key'];
        $this->info = $channelInfo['info'];
        $this->failedProviders = $channelInfo['failedProviders'];
        $this->datesGathered = $channelInfo['datesGathered'];
        $this->extraParams = $channelInfo['extraParams'];
    }

    public function __toString()
    {
        if (!$this->hasStarted || !$this->isRunning) {
            return Utils::colorize('En pause...', 'yellow').' '.TerminalIcon::pause();
        }
        $str = $this->getChannel().' - '.$this->getDate().' - '.$this->getProvider();
        $status = $this->getStatus();
        if (isset($status)) {
            $str .= ' '.$status;
        }

        return $str.' '.TerminalIcon::spinner();
    }


    /**
     * @return ProviderInterface[]
     */
    protected function getRemainingProviders(): array
    {
        $providers = $this->generator->getProviders($this->info['priority'] ?? []);
        $providers = array_filter($providers, fn ($provider) => $provider->channelExists($this->channel));
        if (count($this->failedProviders) > 0) {
            $failedProviderNames = $this->failedProviders;
            $providers = array_filter($providers, function ($provider) use ($failedProviderNames) {
                $providerName = Utils::extractProviderName($provider);

                return !in_array($providerName, $failedProviderNames);
            });
        }

        return $providers;
    }

    private function getLastMessage(Channel $workerChannel): ?string
    {
        try {
            return $workerChannel->receive();
        } catch (\Throwable $_) {
            return null;
        }
    }

    protected function getProviderResult(string $providerName, string $date): string
    {
        $worker = getWorker();
        $task = new ProviderTask($providerName, $date, $this->channel, $this->extraParams);
        $execution = $worker->submit($task);
        $future = $execution->getFuture();
        $workerChannel = $execution->getChannel();
        while (!$future->isComplete()) {
            $lastMessage = $this->getLastMessage($workerChannel);
            if (!empty($lastMessage)) {
                $this->status = Utils::colorize($lastMessage, 'magenta');
            }
            delay(0);
        }
        $this->manager->removeChannelFromProvider($providerName, $this->channel);

        return $execution->await();
    }

    /**
     * Get data for selected provider for selected channel and selected date
     * @param string $providerName
     * @param ProviderInterface $provider
     * @param string $date
     * @param string $cacheKey
     * @return array
     * @throws \Random\RandomException
     */
    protected function getDataFromProvider(string $providerName, ProviderInterface $provider, string $date, string $cacheKey): array
    {
        $cache = $this->generator->getCache();
        flush();
        $providerResult = $this->getProviderResult($providerName, $date);
        if ($providerResult == 'false') {
            $this->failedProviders[] = $providerName;
            Logger::addChannelFailedProvider($this->channel, $date, get_class($provider));

            return ['success' => false];
        } else {
            [$startTimes, $endTimes] = Utils::getStartAndEndDatesFromXMLString($providerResult);
            $state = $provider->getChannelStateFromTimes($startTimes, $endTimes, $this->generator->getConfigurator());
            /**
             * If we retrieve partial data. We check if existing cache (if any) is worse or better than those data.
             * If cache is better, we consider that current provider failed to gather data
             */
            if ($state == EPGEnum::$PARTIAL_CACHE) {
                if (($cache->getState($cacheKey) > EPGEnum::$EXPIRED_CACHE)) {
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

    /**
     * Gather channel information for selected day.
     * Will look for cache file and browse providers in order
     * @param EPGDate $epgDate
     * @return array Information about status (success, cache, partial, provider and if gathering has been skipped)
     */
    protected function gatherData(EPGDate $epgDate): array
    {
        $date = $epgDate->getFormattedDate();
        $cache = $this->generator->getCache();
        $cacheKey = sprintf('%s_%s.xml', $this->channel, $date);
        $currentResult = ['success' => false];
        $cacheState = $cache->getState($cacheKey);
        if ($epgDate->getCachePolicy() == EPGDate::$CACHE_ONLY || ($cacheState == EPGEnum::$FULL_CACHE && $epgDate->getCachePolicy() == EPGDate::$CACHE_FIRST)) {
            if ($cacheState == EPGEnum::$NO_CACHE) {
                return ['success' => false,  'isCache' => false, 'skipped' => false];
            } else {
                $providerName = $cache->getProviderName($cacheKey);

                return ['success' => true, 'provider' => $providerName, 'isCache' => true, 'isPartial' => $cacheState == EPGEnum::$PARTIAL_CACHE, 'skipped' => false];
            }
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

                $result = $this->getDataFromProvider($providerName, $provider, $date, $cacheKey);
                if ($result['success']) {
                    $currentResult = $result;
                }
                if (!@$currentResult['isPartial'] && $result['success']) {
                    return $currentResult;
                }
            }
        }

        return $currentResult;
    }

    /**
     * @param array $result
     * @param string $cacheKey
     * @return string Colorized string like: OK - ProviderName, OK (Cache) - ProviderName, HS, ...
     * Note: Only the status is colorized, provider name isn't
     */
    protected function getStatusString(array $result, string $cacheKey): string
    {
        $cache = $this->generator->getCache();
        $providerName = '';
        $emoji = TerminalIcon::success();
        if ($result['success']) {
            $providerName = ' - '.$result['provider'];
            $statusString = 'OK';
            $color = 'green';
            if (@$result['isPartial']) {
                $statusString .= ' (Partial)';
                $color = 'yellow';
            }
            if (@$result['isCache']) {
                $providerName = ' - '.$cache->getProviderName($cacheKey);
                $statusString .= ' (Cache)';
                $color = 'light yellow';
            }
        } else {
            if ($cache->getState($cacheKey)) {
                $providerName = ' - '.$cache->getProviderName($cacheKey);
                $statusString = 'OK (Forced Cache)';
                $color = 'yellow';
            } else {
                $emoji = TerminalIcon::error();
                $statusString = 'HS';
                $color = 'red';
            }
        }

        return Utils::colorize($statusString, $color).$providerName.' '.$emoji;
    }

    /**
     * Run thread for current channel.
     * Will go through all dates remaining to gather and all providers each day (depending on cache)
     * @return void
     * @throws \Exception
     */
    protected function run(): void
    {
        $cache = $this->generator->getCache();
        $epgDates = $this->generator->getConfigurator()->getEpgDates();
        $total = count($epgDates);
        $epgDates = array_diff($epgDates, $this->datesGathered);
        $progress = $total - count($epgDates);
        foreach ($epgDates as $epgDate) {
            $date = $epgDate->getFormattedDate();
            Logger::addChannelEntry($this->channel, $date);
            $progress++;
            $this->date = $date." ($progress/$total)";
            $cacheKey = sprintf('%s_%s.xml', $this->channel, $date);

            $result = $this->gatherData($epgDate);
            if (@$result['skipped']) {
                $this->manager->addChannel($this->channel, $this->failedProviders, $this->datesGathered);

                return;
            }
            $statusString = $this->getStatusString($result, $cacheKey);
            $this->addEvent($date, $statusString);
            if ($result['success']) {
                Logger::setChannelSuccessfulProvider($this->channel, $date, $result['provider'], $result['isCache']);
            } elseif ($cache->getState($cacheKey)) {
                $providerName = $cache->getProviderName($cacheKey);
                Logger::setChannelSuccessfulProvider($this->channel, $date, $providerName.' - Forced', true);
            } elseif ($this->generator->getConfigurator()->isEnableDummy()) {
                $cache->store($cacheKey, $this->generator->getFormatter()->formatChannel(new DummyChannel($this->channel, $date), null));
            }
            $this->datesGathered[] = $epgDate;
            $this->failedProviders = [];
        }

        $this->manager->incrChannelsDone();
    }

    protected function addEvent(string $date, string $statusInfo): void
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
