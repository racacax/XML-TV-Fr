<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\TracePlus;
use racacax\XmlTv\Component\ProviderCache;

class TracePlusTest extends TestCase
{
    /**
     * @var Client|\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;

    public function setUp(): void
    {
        parent::setUp();
        ProviderCache::clearCache();
        $this->client = $this->createMock(Client::class);
    }

    public function tearDown(): void
    {
        ProviderCache::clearCache();
        parent::tearDown();
    }

    public function testLogosAreMatchedThroughEpgSlugWithASingleRequest(): void
    {
        $response = ['data' => ['allChannel' => ['items' => [
            ['epg' => 'http://filesapp.trace.tv/files/epg/export/TRACE_IVOIRE/epg.xml', 'logo' => ['url' => 'https://media.example/ivoire.png']],
            ['epg' => 'https://filesapp.trace.tv/files/epg/export/TRACE_TOCA/epg.xml', 'logo' => ['url' => 'https://media.example/toca.jpg']],
            ['epg' => 'https://filesapp.trace.tv/files/epg/export/TRACE_NAIJA/epg.xml', 'logo' => null],
        ]]]];
        $this->client
            ->expects($this->once())
            ->method('post')
            ->with(
                'https://graphql.umbraco.io',
                $this->callback(fn (array $options) => $options['headers']['umb-project-alias'] === 'trace-backoffice' && str_contains($options['body'], 'allChannel'))
            )
            ->willReturn(new Response(200, [], json_encode($response)));

        $provider = new TracePlus($this->client, 1);

        $this->assertSame('https://media.example/toca.jpg', $provider->getLogo('TraceToca.fr'));
        $this->assertSame('https://media.example/ivoire.png', $provider->getLogo('Trace.ci'));
        $this->assertNull($provider->getLogo('TraceNaija.af'));
        $this->assertNull($provider->getLogo('TraceChristmas.af'));
    }

    public function testLogoIsNullWhenApiFails(): void
    {
        $this->client->method('post')->willReturn(new Response(500, [], ''));

        $provider = new TracePlus($this->client, 1);

        $this->assertNull($provider->getLogo('TraceToca.fr'));
    }

    public function testLogoOfUnknownChannelThrows(): void
    {
        $provider = new TracePlus($this->client, 1);

        $this->expectException(\Exception::class);
        $provider->getLogo('Unknown.fr');
    }
}
