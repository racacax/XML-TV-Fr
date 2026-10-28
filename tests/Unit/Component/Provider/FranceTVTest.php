<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\FranceTV;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\ValueObject\Channel;

class FranceTVTest extends TestCase
{
    /**
     * @var Client|\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;
    /**
     * @var string[]
     */
    private array $requestedUrls = [];

    public function setUp(): void
    {
        parent::setUp();
        ProviderCache::clearCache();
        $this->client = $this->createMock(Client::class);
        $this->requestedUrls = [];
    }

    public function tearDown(): void
    {
        ProviderCache::clearCache();
        parent::tearDown();
    }

    public function testProgramsOfTheParisDayAreGatheredFromTheTwoPages(): void
    {
        $this->mockApi();

        $provider = new FranceTV($this->client, 1);
        $channelObj = $provider->constructEPG('France2.fr', '2026-07-15');

        $this->assertInstanceOf(Channel::class, $channelObj);
        $this->assertSame(
            ['https://www.france.tv/api/epg/videos/?date=2026-07-14&channel=france-2', 'https://www.france.tv/api/epg/videos/?date=2026-07-15&channel=france-2'],
            $this->requestedUrls
        );
        // Before the day (21:00), after it (the day after) and the duplicate are ignored
        $this->assertSame(5, $channelObj->getProgramCount());
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        // 00:30 in Paris comes from the page of the day before
        $this->assertStringContainsString('<programme start="20260715003000 +0200" stop="20260715013000 +0200"', $xml);
        // End: start + duration, limited to the start of the next program
        $this->assertStringContainsString('<programme start="20260715013000 +0200" stop="20260715014500 +0200"', $xml);
        $this->assertStringContainsString('<programme start="20260715060000 +0200" stop="20260715063000 +0200"', $xml);
        $this->assertStringContainsString('<programme start="20260715063000 +0200" stop="20260715073000 +0200"', $xml);
        $this->assertSame(1, substr_count($xml, 'start="20260715063000 +0200"'));
    }

    public function testTitlesAndInformation(): void
    {
        $this->mockApi();

        $provider = new FranceTV($this->client, 1);
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('France2.fr', '2026-07-15'), $provider);

        // Generic titles are replaced by the show found in the url, or in the tracking data
        $this->assertStringContainsString('<title lang="fr">Tout le monde veut prendre sa place</title>', $xml);
        $this->assertStringContainsString('<sub-title lang="fr">Émission du mardi 14 juillet 2026</sub-title>', $xml);
        $this->assertStringContainsString('<title lang="fr">Des chiffres et des lettres</title>', $xml);
        // Specific titles are kept
        $this->assertStringContainsString('<title lang="fr">Nosferatu</title>', $xml);
        $this->assertStringNotContainsString('<sub-title lang="fr">Nosferatu', $xml);
        // Season and episode
        $this->assertStringContainsString('<title lang="fr">Un si grand soleil</title>', $xml);
        $this->assertStringContainsString('<sub-title lang="fr">Épisode du jeudi 21 août 2025</sub-title>', $xml);
        $this->assertStringContainsString('<episode-num system="xmltv_ns">6.1730.</episode-num>', $xml);
        $this->assertStringContainsString('<desc lang="fr">Un quiz de culture générale</desc>', $xml);
        $this->assertStringContainsString('<category lang="fr">Divertissement</category>', $xml);
        $this->assertStringContainsString('<icon src="https://medias.example/expanded.jpg"/>', $xml);
        $this->assertStringContainsString('<subtitles type="teletext"/>', $xml);
        $this->assertStringContainsString('<audio-described/>', $xml);
        $this->assertStringContainsString('<value>Tout public</value>', $xml);
        $this->assertStringContainsString('<value>-12</value>', $xml);
    }

    public function testConsecutiveDaysShareTheirPages(): void
    {
        $this->mockApi();

        $provider = new FranceTV($this->client, 1);
        $provider->constructEPG('France2.fr', '2026-07-15');
        $provider->constructEPG('France2.fr', '2026-07-16');

        $this->assertCount(3, $this->requestedUrls);
    }

    public function testNoProgramOrUnknownChannelReturnsFalse(): void
    {
        $this->mockApi();
        $provider = new FranceTV($this->client, 1);

        $this->assertFalse($provider->constructEPG('France2.fr', '2026-12-31'));
        $this->assertFalse($provider->constructEPG('Unknown.fr', '2026-07-15'));
    }

    private function video(int $id, string $isoDate, string $duration, string $title, array $extra = [], array $tracking = []): array
    {
        return [
            'content' => $extra + [
                'id' => $id, 'title' => $title, 'isoDate' => $isoDate, 'isoDuration' => $duration, 'url' => '',
                'category' => ['id' => 'info', 'label' => 'Info'], 'csa' => 'TP', 'description' => '', 'icons' => [],
            ],
            'tracking' => $tracking,
        ];
    }

    private function mockApi(): void
    {
        $dayBefore = [
            $this->video(1, '2026-07-14T21:00:00+02:00', 'PT0H30M0S', 'Avant la journée'),
            $this->video(2, '2026-07-15T00:30:00+02:00', 'PT1H0M0S', 'Programme de la nuit'),
        ];
        $day = [
            $this->video(3, '2026-07-15T01:30:00+02:00', 'PT2H0M0S', 'Nosferatu', ['csa' => '12']),
            $this->video(4, '2026-07-15T01:45:00+02:00', 'PT0H30M0S', 'Émission du mardi 14 juillet 2026', ['url' => '/france-2/tout-le-monde-veut-prendre-sa-place/4-emission-du-mardi.html', 'description' => 'Un quiz de culture générale', 'category' => ['id' => 'entertainment', 'label' => 'Divertissement'], 'images' => ['base' => ['x2' => 'https://medias.example/base.jpg'], 'expanded' => ['x1' => 'https://medias.example/expanded.jpg']], 'icons' => [['id' => 'st'], ['id' => 'ad'], ['id' => 'vm']]]),
            $this->video(5, '2026-07-15T06:00:00+02:00', 'PT0H30M0S', 'S7 E1731 - Épisode du jeudi 21 août 2025', ['url' => '/france-3/un-si-grand-soleil/un-si-grand-soleil-saison-7/5-episode.html']),
            $this->video(6, '2026-07-15T06:30:00+02:00', 'PT1H0M0S', 'émission du 03 septembre 1976', [], ['program' => 'des_chiffres_et_des_lettres|des_chiffres_et_des_lettres']),
            $this->video(7, '2026-07-15T06:30:00+02:00', 'PT1H0M0S', 'émission du 03 septembre 1976', [], ['program' => 'des_chiffres_et_des_lettres']),
            $this->video(8, '2026-07-16T00:30:00+02:00', 'PT1H0M0S', 'Le lendemain'),
        ];
        $this->client->method('get')->willReturnCallback(function (string $url) use ($dayBefore, $day) {
            $this->requestedUrls[] = $url;
            $content = match (true) {
                str_contains($url, 'date=2026-07-14') => $dayBefore,
                str_contains($url, 'date=2026-07-15') => $day,
                default => [],
            };

            return new Response(200, [], json_encode($content));
        });
    }
}
