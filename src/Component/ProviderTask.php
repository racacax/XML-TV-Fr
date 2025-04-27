<?php

namespace racacax\XmlTv\Component;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use racacax\XmlTv\Configurator;

/**
 * Async thread gathering epg data for a channel on a specific day for a specific provider
 * Returns formatted XML if success
 * Returns false if not success
 */
class ProviderTask implements Task
{
    public function __construct(
        private string $providerName,
        private string $date,
        private string $channelId,
        private ?array $extraParams
    ) {
    }

    public function run(Channel $channel, Cancellation $cancellation): string
    {
        $client = Configurator::getDefaultClient();
        $providerClass = Utils::getProvider($this->providerName);
        $provider = new $providerClass($client, null, $this->extraParams);
        $provider->setWorkerChannel($channel);

        return @Utils::getChannelDataFromProvider($provider, $this->channelId, $this->date);
    }
}
