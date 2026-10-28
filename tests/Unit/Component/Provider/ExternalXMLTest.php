<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\ExternalXML;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\ValueObject\Channel;

class ExternalXMLTest extends TestCase
{
    private const FIRST_URL = 'https://i.mjh.nz/Plex/fr.xml.gz';

    /**
     * @var Client|\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;
    /**
     * @var array<int, array{string, array}>
     */
    private array $requests = [];

    public function setUp(): void
    {
        parent::setUp();
        ProviderCache::clearCache();
        $this->client = $this->createMock(Client::class);
        $this->requests = [];
    }

    public function tearDown(): void
    {
        ProviderCache::clearCache();
        parent::tearDown();
    }

    public function testProgramsAreCopiedWithAllTheirInformation(): void
    {
        $this->mockXml([self::FIRST_URL => $this->xml('main-id')]);

        $provider = $this->createProvider();
        $channelObj = $provider->constructEPG('SonyOneFavoris.fr', '2026-07-15');

        $this->assertInstanceOf(Channel::class, $channelObj);
        // Other channel, other day and invalid program (end before start) are ignored
        $this->assertSame(2, $channelObj->getProgramCount());
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        $this->assertStringContainsString('<programme start="20260715020000 +0200" stop="20260715030000 +0200" pdc-start="202607150200" channel="SonyOneFavoris.fr">', $xml);
        $this->assertStringContainsString('<title lang="fr">Starsky &amp; Hutch</title>', $xml);
        $this->assertStringContainsString('<sub-title lang="fr">Cover Girl</sub-title>', $xml);
        $this->assertStringContainsString('<desc lang="fr">Une "description"</desc>', str_replace('&quot;', '"', $xml));
        $this->assertStringContainsString('<icon src="https://img.example/a.jpg" width="300" height="200"/>', $xml);
        $this->assertStringContainsString('<episode-num system="xmltv_ns">3.78.</episode-num>', $xml);
        $this->assertStringContainsString('<episode-num system="onscreen">S4E79</episode-num>', $xml);
        $this->assertStringContainsString('<date>1977</date>', $xml);
        $this->assertStringContainsString('<premiere/>', $xml);
        $this->assertStringContainsString('<previously-shown start="20250101000000"/>', $xml);
        $this->assertStringContainsString('<audio-described/>', $xml);
        $this->assertStringContainsString('<subtitles type="teletext">', $xml);
        $this->assertStringContainsString('<language>fr</language>', $xml);
        $this->assertStringContainsString('<rating system="CSA">', $xml);
        $this->assertStringContainsString('<value>-12</value>', $xml);
        $this->assertStringContainsString('<star-rating system="IMDB">', $xml);
        $this->assertSame(1, preg_match('#<credits>\s*<director>Dir</director>\s*<actor role="Starsky">Paul</actor>\s*<actor>David</actor>\s*<presenter>Pres</presenter>\s*</credits>#', $xml));
        // Empty elements without attribute are ignored
        $this->assertStringNotContainsString('<category', $xml);
        $this->assertSame(1, substr_count($xml, '<credits>'));
        $this->assertSame(1, substr_count($xml, '<video>'));
        $this->assertStringNotContainsString('<video/>', $xml);
        $this->assertStringContainsString('<new/>', $xml);
        // Second program uses dates without offset and ISO 8601 dates
        $this->assertStringContainsString('<programme start="20260715030000 +0200" stop="20260715040000 +0200"', $xml);
    }

    public function testChannelsShareTheSameDownload(): void
    {
        $this->mockXml([self::FIRST_URL => $this->xml('main-id')]);

        $provider = $this->createProvider();

        $this->assertNotFalse($provider->constructEPG('SonyOneFavoris.fr', '2026-07-15'));
        $this->assertNotFalse($provider->constructEPG('SonyOneFavoris.fr', '2026-07-16'));
        $this->assertNotFalse($provider->constructEPG('SonyOneHitsAction.fr', '2026-07-15'));
        $this->assertCount(1, $this->requests);
    }

    public function testGzipSourceAndHeaders(): void
    {
        $this->mockXml([self::FIRST_URL => gzencode($this->xml('main-id'))]);

        $provider = $this->createProvider();

        $this->assertSame(2, $provider->constructEPG('SonyOneFavoris.fr', '2026-07-15')->getProgramCount());
        $this->assertSame('test', $this->requests[0][1]['headers']['X-Test']);
    }

    public function testNextSourceIsUsedWhenTheFirstOneHasNoProgram(): void
    {
        $this->mockXml([
            self::FIRST_URL => $this->xml('main-id'),
            'https://example.com/other.xml' => '<tv><programme start="20260716100000 +0100" stop="20260716110000 +0100" channel="x"><title>Autre</title></programme></tv>',
        ]);

        $provider = $this->createProvider();
        $channelObj = $provider->constructEPG('FallbackChannel.fr', '2026-07-16');

        $this->assertSame(1, $channelObj->getProgramCount());
        $this->assertStringContainsString('<title>Autre</title>', (new XmlFormatter())->formatChannel($channelObj, $provider));
        $this->assertCount(2, $this->requests);
    }

    public function testTimezoneOfSourceIsUsedForDatesWithoutOffset(): void
    {
        $this->mockXml(['https://example.com/local.xml' => '<tv><programme start="20260715120000" stop="20260715130000" channel="x"><title>Local</title></programme></tv>']);

        $provider = $this->createProvider();
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('LocalChannel.fr', '2026-07-15'), $provider);

        // 12:00 in New York (UTC-4 in July) is 18:00 in Paris (UTC+2)
        $this->assertStringContainsString('start="20260715180000 +0200" stop="20260715190000 +0200"', $xml);
    }

    public function testDatePlaceholdersAndOffsetOfUrl(): void
    {
        $xml = '<tv><programme start="20260714230000 +0000" stop="20260715010000 +0000" channel="x"><title>Night</title></programme></tv>';
        $this->mockXml(['https://example.com/20260714.xml' => $xml]);

        $provider = $this->createProvider();

        $this->assertNotFalse($provider->constructEPG('DatedChannel.fr', '2026-07-15'));
        $this->assertSame('https://example.com/20260714.xml', $this->requests[0][0]);
        // Each day has its own file
        $provider->constructEPG('DatedChannel.fr', '2026-07-16');
        $this->assertSame('https://example.com/20260715.xml', $this->requests[1][0]);
    }

    public function testNoProgramOrUnavailableSourceReturnsFalse(): void
    {
        $this->mockXml([self::FIRST_URL => $this->xml('main-id')]);

        $provider = $this->createProvider();

        $this->assertFalse($provider->constructEPG('SonyOneFavoris.fr', '2026-12-31'));
        $this->assertFalse($provider->constructEPG('Unreachable.fr', '2026-07-15'));
        $this->assertFalse($provider->constructEPG('Unknown.fr', '2026-07-15'));
    }

    public function testLogoIsTheIconOfTheChannel(): void
    {
        $this->mockXml([self::FIRST_URL => $this->xml('main-id')]);

        $provider = $this->createProvider();

        $this->assertSame('https://img.example/logo.png', $provider->getLogo('SonyOneFavoris.fr'));
        $this->assertNull($provider->getLogo('NoLogo.fr'));
        $this->expectException(\Exception::class);
        $provider->getLogo('Unknown.fr');
    }

    /**
     * @param array<string, string> $files Content returned for each url
     */
    private function mockXml(array $files): void
    {
        $this->client->method('get')->willReturnCallback(function (string $url, array $options) use ($files) {
            $this->requests[] = [$url, $options];

            return new Response(isset($files[$url]) ? 200 : 404, [], $files[$url] ?? '');
        });
    }

    private function createProvider(): ExternalXML
    {
        $config = [
            'SonyOneFavoris.fr' => [['url' => self::FIRST_URL, 'id' => 'main-id', 'headers' => ['X-Test' => 'test']]],
            'SonyOneHitsAction.fr' => ['url' => self::FIRST_URL, 'id' => 'main-id', 'headers' => ['X-Test' => 'test']],
            'FallbackChannel.fr' => [['url' => self::FIRST_URL, 'id' => 'unknown-id'], ['url' => 'https://example.com/other.xml', 'id' => 'x']],
            'NoLogo.fr' => [['url' => self::FIRST_URL, 'id' => 'other-id', 'headers' => ['X-Test' => 'test']]],
            'LocalChannel.fr' => [['url' => 'https://example.com/local.xml', 'id' => 'x', 'timezone' => 'America/New_York']],
            'DatedChannel.fr' => [['url' => 'https://example.com/$year$month$day.xml', 'id' => 'x', 'date_offset' => -1]],
            'Unreachable.fr' => [['url' => 'https://example.com/404.xml', 'id' => 'x'], ['id' => 'missing-url']],
        ];

        // Channels of the provider are normally read from channels_externalxml.json
        return new class ($this->client, $config) extends ExternalXML {
            public function __construct(Client $client, array $config)
            {
                $this->channelsList = $config;
                parent::__construct($client, 1);
            }
        };
    }

    private function xml(string $id): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<tv>
  <channel id="$id"><display-name>Favoris</display-name><icon src="https://img.example/logo.png"/></channel>
  <channel id="other-id"><display-name>Sans logo</display-name></channel>
  <programme start="20260715000000 +0000" stop="20260715010000 +0000" pdc-start="202607150200" channel="$id">
    <title lang="fr">Starsky &amp; Hutch</title>
    <sub-title lang="fr">Cover Girl</sub-title>
    <desc lang="fr">Une "description"</desc>
    <credits><actor role="Starsky">Paul</actor><presenter>Pres</presenter><director>Dir</director><actor>David</actor></credits>
    <date>1977</date>
    <category lang="fr"></category>
    <icon src="https://img.example/a.jpg" width="300" height="200"/>
    <episode-num system="xmltv_ns">3.78.</episode-num>
    <episode-num system="onscreen">S4E79</episode-num>
    <video><present>yes</present></video>
    <video/>
    <previously-shown start="20250101000000"/>
    <premiere/>
    <new/>
    <language>fr</language>
    <subtitles type="teletext"><language>fr</language></subtitles>
    <rating system="CSA"><value>-12</value></rating>
    <star-rating system="IMDB"><value>7/10</value></star-rating>
    <audio-described/>
  </programme>
  <programme start="2026-07-15T01:00:00+00:00" stop="2026-07-15T02:00:00.000000+00:00" channel="$id">
    <title>Dates ISO</title>
    <credits/>
  </programme>
  <programme start="20260715040000 +0000" stop="20260715030000 +0000" channel="$id"><title>Fin avant le début</title></programme>
  <programme start="20260715050000 +0000" stop="20260715060000 +0000" channel="other-id"><title>Autre chaîne</title></programme>
  <programme start="20260716050000 +0000" stop="20260716060000 +0000" channel="$id"><title>Autre jour</title></programme>
</tv>
XML;
    }
}
