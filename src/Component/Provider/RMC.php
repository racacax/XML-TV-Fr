<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\ValueObject\Channel;

class RMC extends SFR implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, $priority ?? 0.85, ['provider' => 'rmc']);
    }


    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        return 'https://static-cdn.tv.sfr.net/data/epg/bfmrmc/guide_web_' . $date->format('Ymd') . '.json';
    }
}
