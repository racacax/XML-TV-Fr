<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\Channel;

interface ProviderInterface
{
    /**
     * @return Channel|false
     */
    public function constructEPG(string $channel, string $date): Channel|bool;

    public static function getPriority(): float;
    public function channelExists(string $channel): bool;

    public function getChannelsList(): array;
    public function getChannelStateFromTimes(array $startTimes, array $endTimes, Configurator $config): int;
    public static function getMinMaxDate(string $date): array;
}
