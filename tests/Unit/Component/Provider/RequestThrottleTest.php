<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\AbstractProvider;
use racacax\XmlTv\Component\ProviderCache;

class RequestThrottleTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        ProviderCache::clearCache();
    }

    public function tearDown(): void
    {
        ProviderCache::clearCache();
        parent::tearDown();
    }

    public function testRequestsAreSpacedButCacheHitsAreNot(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->exactly(2))->method('get')->willReturnCallback(fn () => new Response(200, [], 'content'));
        $provider = new class ($client, 0.3) extends AbstractProvider {
            public function __construct(Client $client, float $interval)
            {
                $this->minRequestInterval = $interval;
                parent::__construct($client, 'unknown.json', 0.5);
            }

            public function fetch(string $url): string
            {
                return $this->getContentFromURL($url);
            }
        };

        $provider->fetch('https://example.com/1');
        $afterFirst = microtime(true);
        $provider->fetch('https://example.com/2');
        $afterSecond = microtime(true);
        // Cached: no request, no delay
        $provider->fetch('https://example.com/2');
        $afterCached = microtime(true);

        $this->assertGreaterThanOrEqual(0.28, $afterSecond - $afterFirst);
        $this->assertLessThan(0.1, $afterCached - $afterSecond);
    }
}
