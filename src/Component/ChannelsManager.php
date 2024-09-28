<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class ChannelsManager
{
    private array $channels;
    private array $channelsInfo;
    private Generator $generator;
    private array $providersUsed;
    private array $providersFailedByChannel;
    private array $datesGatheredByChannel;
    private int $channelsCount;
    private int $channelsDone;

    public function __construct(array $channels, Generator $generator)
    {
        $this->channelsCount = count($channels);
        $this->channelsDone = 0;
        $this->channelsInfo = $channels;
        $this->generator = $generator;
        $this->channels = array_keys($channels);
        $this->providersUsed = [];
        $this->providersFailedByChannel = [];
    }

    public function incrChannelsDone(): void
    {
        $this->channelsDone++;
    }
    public function getStatus(): string
    {
        return $this->channelsDone.' / '.$this->channelsCount;
    }
    public function removeChannelFromProvider(string $provider, string $channel): void
    {
        if (isset($this->providersUsed[$provider])) {
            if (($key = array_search($channel, $this->providersUsed[$provider])) !== false) {
                unset($this->providersUsed[$provider][$key]);
            }
        }
    }

    public function hasRemainingChannels(): bool
    {
        return count($this->channels) > 0;
    }

    //TODO : Add limit in config
    public function canUseProvider(string $provider): bool
    {
        return !isset($this->providersUsed[$provider]) || count($this->providersUsed[$provider]) == 0;
    }

    public function addChannelToProvider(string $provider, string $channel): void
    {
        if (!isset($this->providersUsed[$provider])) {
            $this->providersUsed[$provider] = [];
        }
        $this->providersUsed[$provider][] = $channel;
    }

    public function hasAnyRemainingChannel(): bool
    {
        return count($this->channels) > 0;
    }

    public function addChannel(string $channel, array $providersFailed, array $datesGathered): void
    {
        $this->channels[] = $channel;
        $this->providersFailedByChannel[$channel] = $providersFailed;
        $this->datesGatheredByChannel[$channel] = $datesGathered;
    }

    private function isChannelAvailable(string $key): bool
    {
        $providers = $this->generator->getProviders($this->info['priority'] ?? []);
        $f = $this->providersFailedByChannel[$key] ?? [];
        if (count($f) > 0) {
            $failedProviders = $this->generator->getProviders($f);
        } else {
            $failedProviders = [];
        }
        foreach ($providers as $provider) {
            if (in_array($provider, $failedProviders)) {
                continue;
            }
            $providerClass = Utils::extractProviderName($provider);
            if (!$provider->channelExists($key)) {
                continue;
            } elseif (!$this->canUseProvider($providerClass)) {
                return false;
            } else {
                return true;
            }
        }

        return true;
    }

    public function shiftChannel(): array
    {
        $maxLoop = count($this->channels);
        $key = null;
        for ($i = 0; $i < $maxLoop; $i++) {
            $tmpKey = array_shift($this->channels);
            if ($this->isChannelAvailable($tmpKey)) {
                $key = $tmpKey;

                break;
            } else {
                $this->addChannel(
                    $tmpKey,
                    $this->providersFailedByChannel[$tmpKey] ?? [],
                    $this->datesGatheredByChannel[$tmpKey] ?? []
                );
            }
        }
        if (!isset($key)) {
            return [];
        }

        return [
            'key' => $key, 'info' => $this->channelsInfo[$key],
            'failedProviders' => $this->providersFailedByChannel[$key] ?? [],
            'datesGathered' => $this->datesGatheredByChannel[$key] ?? [],
            'extraParams' => $this->generator->getExtraParams()
        ];
    }
}
