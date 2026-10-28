<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\Proximus;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;

class ProximusTest extends TestCase
{
    private const DATE = '2026-01-15';

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

    public function testProgramsOfTheChannelAreConverted(): void
    {
        $provider = $this->createProvider($this->createClient($requests));

        $channelObj = $provider->constructEPG('TF1.fr', self::DATE);

        // The program of the day before and the one of the next day are ignored
        $this->assertSame(1, $channelObj->getProgramCount());
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        $this->assertStringContainsString('<programme start="20260115210000 +0100" stop="20260115223000 +0100" channel="TF1.fr">', $xml);
        $this->assertStringContainsString('<title lang="fr">Film de TF1</title>', $xml);
        $this->assertStringContainsString('<sub-title lang="fr">Le titre de l\'épisode</sub-title>', $xml);
        $this->assertStringContainsString('<director>Jane Doe</director>', $xml);
        $this->assertStringContainsString('<actor>John Doe</actor>', $xml);
        $this->assertStringContainsString('<date>2024</date>', $xml);
        $this->assertStringContainsString('<country>France - Belgique</country>', $xml);
        $this->assertStringContainsString('<episode-num system="xmltv_ns">1.4.</episode-num>', $xml);
        $this->assertStringContainsString('<value>-12</value>', $xml);
        $this->assertStringContainsString('<subtitles type="teletext"/>', $xml);
        $this->assertStringContainsString('poster/EPG/abc.jpg', $xml);
        $this->assertStringNotContainsString('Film de la veille', $xml);
        $this->assertStringNotContainsString('Film du lendemain', $xml);
    }

    public function testChannelsOfTheSameGroupShareTheSameRequest(): void
    {
        $provider = $this->createProvider($this->createClient($requests));

        $provider->constructEPG('TF1.fr', self::DATE);
        $channelObj = $provider->constructEPG('France2.fr', self::DATE);

        $this->assertCount(1, $requests);
        $this->assertSame(1, $channelObj->getProgramCount());
        $this->assertStringContainsString('filter_channelIds=1005%2C1006', $requests[0]);
        $this->assertStringContainsString('filter_startTime=' . (new \DateTimeImmutable(self::DATE, new \DateTimeZone('Europe/Paris')))->getTimestamp() * 1000, $requests[0]);
    }

    public function testChannelWithoutProgramIsNotReturned(): void
    {
        $provider = $this->createProvider($this->createClient($requests));

        $this->assertFalse($provider->constructEPG('Arte.fr', self::DATE));
        $this->assertFalse($provider->constructEPG('Unknown.fr', self::DATE));
    }

    private function createProvider(Client $client): Proximus
    {
        return new class ($client) extends Proximus {
            public function __construct(Client $client)
            {
                $this->channelsList = ['TF1.fr' => 1005, 'France2.fr' => 1006, 'Arte.fr' => 1020];
                parent::__construct($client, 1);
            }
        };
    }

    private function createClient(?array &$requests): Client
    {
        $requests = [];
        $program = function (string $title, string $start, string $end, array $extra = []) {
            $ts = fn (string $d) => (new \DateTimeImmutable($d, new \DateTimeZone('Europe/Paris')))->getTimestamp() * 1000;

            return ['id' => '1', 'layout' => 'CONTENT_ITEM', 'metadata' => $extra + [
                'title' => $title,
                'airingStartTime' => $ts($start),
                'airingEndTime' => $ts($end),
            ]];
        };
        $body = json_encode(['resultCode' => 'OK', 'resultObj' => ['containers' => [
            ['id' => '1005', 'layout' => 'CHANNEL_ITEM', 'containers' => [
                $program('Film de la veille', '2026-01-14 23:00', '2026-01-15 00:30'),
                $program('Film de TF1', '2026-01-15 21:00', '2026-01-15 22:30', [
                    'episodeTitle' => 'Le titre de l\'épisode',
                    'longDescription' => 'Description',
                    'subcategory' => 'Movies',
                    'genres' => ['Drama'],
                    'directors' => ['Jane Doe'],
                    'actors' => ['John Doe'],
                    'year' => 2024,
                    'country' => 'fr-be',
                    'season' => 2,
                    'episodeNumber' => 5,
                    'pcLevel' => 12,
                    'extendedMetadata' => ['supportForHearingImpaired' => true, 'images' => ['landscapePoster' => 'abc.jpg']],
                ]),
                $program('Film du lendemain', '2026-01-16 01:00', '2026-01-16 02:00'),
            ]],
            ['id' => '1006', 'layout' => 'CHANNEL_ITEM', 'containers' => [$program('Film de France 2', '2026-01-15 20:00', '2026-01-15 21:00')]],
        ]]]);
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturnCallback(function (string $url) use ($body, &$requests) {
            $requests[] = $url;

            return new Response(200, [], $body);
        });

        return $client;
    }
}
