<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\SRGSSR;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\ValueObject\Channel;

class SRGSSRTest extends TestCase
{
    private const RTS_ONE = '143932a79bb5a123a646b68b1d1188d7ae493e5b';
    private const RTS_TWO = 'd7dfff28deee44e1d3c49a3d37d36d492b29671b';

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

    public function testProgramsOfTheDayWithTheirInformation(): void
    {
        $this->mockApi();

        $provider = new SRGSSR($this->client, 1);
        $channelObj = $provider->constructEPG('RTSUn.ch', '2026-07-15');

        $this->assertInstanceOf(Channel::class, $channelObj);
        // The program that starts the day after is ignored
        $this->assertSame(2, $channelObj->getProgramCount());
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        // Offsets are given by the API and are converted
        $this->assertStringContainsString('<programme start="20260715010000 +0200" stop="20260715023000 +0200" channel="RTSUn.ch">', $xml);
        $this->assertStringContainsString('<title lang="fr">Pauvres créatures</title>', $xml);
        $this->assertStringContainsString('<desc lang="fr">Bella est une jeune femme</desc>', $xml);
        $this->assertStringContainsString('<category lang="fr">Fiction</category>', $xml);
        $this->assertStringContainsString('<date>2023</date>', $xml);
        $this->assertStringContainsString('<country>Etats-Unis</country>', $xml);
        $this->assertStringContainsString('<icon src="https://img.example/film.jpg"/>', $xml);
        $this->assertSame(1, preg_match('#<credits>\s*<director>Yórgos Lánthimos</director>\s*<actor>Emma Stone</actor>\s*<presenter>Une présentatrice</presenter>\s*</credits>#', $xml));
        $this->assertStringContainsString('<rating system="SRG">', $xml);
        $this->assertStringContainsString('<value>Rouge</value>', $xml);
        $this->assertStringContainsString('<subtitles type="teletext"/>', $xml);
        $this->assertStringContainsString('<audio-described/>', $xml);
        $this->assertStringContainsString('<previously-shown/>', $xml);
        $this->assertStringContainsString('<keyword>direct</keyword>', $xml);
        $this->assertStringContainsString('<keyword>langue des signes</keyword>', $xml);
        // Series: season and episode, description of the show as a fallback, fallback image ignored
        $this->assertStringContainsString('<episode-num system="xmltv_ns">12.15.</episode-num>', $xml);
        $this->assertStringContainsString('<desc lang="fr">Description de la série</desc>', $xml);
        $this->assertStringNotContainsString('fallback.png', $xml);
        $this->assertStringNotContainsString('Kamera', $xml);
    }

    public function testChannelsOfTheSameBusinessUnitShareTheRequest(): void
    {
        $this->mockApi();

        $provider = new SRGSSR($this->client, 1);

        $this->assertNotFalse($provider->constructEPG('RTSUn.ch', '2026-07-15'));
        $this->assertNotFalse($provider->constructEPG('RTSDeux.ch', '2026-07-15'));
        $this->assertSame(['https://il.srgssr.ch/integrationlayer/2.0/rts/programGuide/tv/byDate/2026-07-15'], $this->requestedUrls);
    }

    public function testTextsAreInTheLanguageOfTheBusinessUnit(): void
    {
        $guide = ['programGuide' => [['channel' => ['id' => 'la1'], 'programList' => [
            ['title' => 'Telegiornale', 'startTime' => '2026-07-15T20:00:00+02:00', 'endTime' => '2026-07-15T20:30:00+02:00', 'genre' => 'Informazione', 'imageIsFallbackUrl' => true],
        ]]]];
        $this->client->method('get')->willReturn(new Response(200, [], json_encode($guide)));

        $provider = new SRGSSR($this->client, 1);
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('RSI1.ch', '2026-07-15'), $provider);

        $this->assertStringContainsString('<title lang="it">Telegiornale</title>', $xml);
        $this->assertStringContainsString('<category lang="it">Informazione</category>', $xml);
    }

    public function testNoProgramOrApiFailureReturnsFalse(): void
    {
        $this->mockApi();
        $provider = new SRGSSR($this->client, 1);

        $this->assertFalse($provider->constructEPG('RTSUn.ch', '2026-12-31'));
        $this->assertFalse($provider->constructEPG('Unknown.fr', '2026-07-15'));
    }

    private function mockApi(): void
    {
        $guide = ['programGuide' => [
            ['channel' => ['id' => self::RTS_ONE], 'programList' => [
                [
                    'title' => 'Pauvres créatures', 'startTime' => '2026-07-15T01:00:00+02:00', 'endTime' => '2026-07-15T02:30:00+02:00',
                    'description' => 'Bella est une jeune femme', 'genre' => 'Fiction', 'productionYear' => 2023,
                    'productionCountry' => 'Etats-Unis', 'imageUrl' => 'https://img.example/film.jpg', 'imageIsFallbackUrl' => false,
                    'creditList' => [
                        ['realName' => 'Yórgos Lánthimos', 'role' => 'Un film de'],
                        ['realName' => 'Emma Stone', 'role' => 'Avec'],
                        ['realName' => 'Une présentatrice', 'role' => 'Présentation'],
                        ['realName' => 'Un caméraman', 'role' => 'Kamera'],
                    ],
                    'youthProtectionColor' => 'RED', 'subtitlesAvailable' => true, 'hasVisualDescription' => true,
                    'hasSignLanguage' => true, 'isLive' => true, 'isRepetition' => true,
                ],
                [
                    'title' => 'Une série', 'subtitle' => 'Épisode', 'startTime' => '2026-07-15T23:00:00+02:00', 'endTime' => '2026-07-16T00:10:00+02:00',
                    'show' => ['description' => 'Description de la série'], 'seasonNumber' => 13, 'episodeNumber' => 16,
                    'imageUrl' => 'https://img.example/fallback.png', 'imageIsFallbackUrl' => true,
                ],
                ['title' => 'Demain', 'startTime' => '2026-07-16T00:30:00+02:00', 'endTime' => '2026-07-16T01:30:00+02:00'],
            ]],
            ['channel' => ['id' => self::RTS_TWO], 'programList' => [
                ['title' => 'Autre chaîne', 'startTime' => '2026-07-15T10:00:00+02:00', 'endTime' => '2026-07-15T11:00:00+02:00'],
            ]],
        ]];
        $this->client->method('get')->willReturnCallback(function (string $url) use ($guide) {
            $this->requestedUrls[] = $url;

            return new Response(str_ends_with($url, '2026-07-15') ? 200 : 404, [], str_ends_with($url, '2026-07-15') ? json_encode($guide) : '');
        });
    }
}
