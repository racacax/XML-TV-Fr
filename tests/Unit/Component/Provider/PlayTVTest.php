<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\PlayTV;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;

class PlayTVTest extends TestCase
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

    /**
     * @dataProvider mediaKeyProvider
     */
    public function testProgramInformation(string $mediaKey): void
    {
        $attrs = [
            'texts' => ['long' => 'Le Limousin, dans les années 1970.', 'short' => 'Résumé'],
            'images' => ['large' => [['url' => 'https://images.example/large.jpg']]],
            'production_year' => 2015,
            'nationalities' => ['FR', 'GB'],
            'episode' => 4, 'season' => 2,
        ];
        $json = ['data' => [[
            'title' => 'La belle saison', 'start_at' => '2026-07-15T10:00:00.000000Z', 'end_at' => '2026-07-15T11:40:00.000000Z',
            'certification' => '12', 'attributes' => ['Rediffusion', 'Inédit', 'Audiodescription', 'Direct', 'En 4/3'],
            'media' => ['path' => [['category' => 'film']], $mediaKey => $attrs],
        ], [
            'title' => 'Sans information', 'start_at' => '2026-07-15T12:00:00.000000Z', 'end_at' => '2026-07-15T13:00:00.000000Z',
            'certification' => 'TP', 'media' => ['path' => [['category' => 'film']]],
        ]]];
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn(new Response(200, [], json_encode($json)));

        $provider = new PlayTV($client, 1);
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('TF1.fr', '2026-07-15'), $provider);

        $this->assertStringContainsString('<desc lang="fr">Le Limousin, dans les années 1970.</desc>', $xml);
        $this->assertStringContainsString('<icon src="https://images.example/large.jpg"/>', $xml);
        $this->assertStringContainsString('<episode-num system="xmltv_ns">1.3.</episode-num>', $xml);
        $this->assertStringContainsString('<date>2015</date>', $xml);
        $this->assertStringContainsString('<country>France - Royaume-Uni</country>', $xml);
        $this->assertStringContainsString('<value>-12</value>', $xml);
        $this->assertStringContainsString('<previously-shown/>', $xml);
        $this->assertStringContainsString('<premiere/>', $xml);
        $this->assertStringContainsString('<audio-described/>', $xml);
        $this->assertStringContainsString('<keyword>direct</keyword>', $xml);
        $this->assertSame(1, substr_count($xml, '<date>'));
        $this->assertSame(1, substr_count($xml, '<rating'));
    }

    public function mediaKeyProvider(): array
    {
        return ['current name of the key' => ['plurimedia_attrs'], 'former name of the key' => ['attrs']];
    }
}
