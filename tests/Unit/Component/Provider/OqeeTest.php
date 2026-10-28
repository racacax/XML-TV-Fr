<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\Oqee;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;

class OqeeTest extends TestCase
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

    public function testYearAndSubtitlesAreRetrieved(): void
    {
        $client = $this->createMock(Client::class);
        // One program at the start of every window of 6 hours requested by the provider
        $client->method('get')->willReturnCallback(function (string $url) {
            $start = (int) substr($url, strrpos($url, '/') + 1);
            $live = [
                'title' => 'Un film ' . $start, 'description' => 'Description', 'category' => 'Film', 'sub_category' => 'Comédie',
                'start' => $start, 'end' => $start + 3600, 'parental_rating' => 12, 'audio_description' => false,
                'year' => $start % 2 === 0 ? 1995 : null, 'subtitle_teletext' => true,
            ];

            return new Response(200, [], json_encode(['success' => true, 'result' => ['entries' => [['type' => 'live', 'pictures' => [], 'live' => $live]]]]));
        });

        $provider = new Oqee($client, 1);
        $channelObj = $provider->constructEPG('TF1.fr', '2026-07-15');
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);

        $this->assertGreaterThan(0, $channelObj->getProgramCount());
        $this->assertStringContainsString('<date>1995</date>', $xml);
        $this->assertStringContainsString('<subtitles type="teletext"/>', $xml);
        // Programs without year do not get an empty date
        $this->assertStringNotContainsString('<date></date>', $xml);
    }
}
