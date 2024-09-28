<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use DateTimeImmutable;
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

    /**
     * This function will help on test generation
     *
     * @param Channel $channel
     * @param DateTimeImmutable $date
     * @return string
     */
    public function generateUrl(Channel $channel, DateTimeImmutable $date): string;
}
