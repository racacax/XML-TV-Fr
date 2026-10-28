<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\AntenneReunion;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\ValueObject\Channel;

class AntenneReunionTest extends TestCase
{
    /**
     * @var Client|\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;
    private string $today;
    private string $tomorrow;

    public function setUp(): void
    {
        parent::setUp();
        ProviderCache::clearCache();
        $this->client = $this->createMock(Client::class);
        $this->today = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $this->tomorrow = (new \DateTimeImmutable($this->today))->modify('+1 day')->format('Y-m-d');
    }

    public function tearDown(): void
    {
        ProviderCache::clearCache();
        parent::tearDown();
    }

    public function testProvider(): void
    {
        $this->mockApi();

        $provider = new AntenneReunion($this->client, 1);
        $channelObj = $provider->constructEPG('AntenneReunion.fr', $this->today);

        $this->assertInstanceOf(Channel::class, $channelObj);
        $this->assertSame(2, $channelObj->getProgramCount());
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        $this->assertStringContainsString('<title lang="fr">Ici tout commence</title>', $xml);
        $this->assertStringContainsString('<desc lang="fr">Synopsis complet "cité" bien plus long que la version tronquée.</desc>', str_replace('&quot;', '"', $xml));
        $this->assertStringContainsString('<category lang="fr">Série dramatique</category>', $xml);
        $this->assertStringContainsString('<category lang="fr">Série</category>', $xml);
        $this->assertStringContainsString('<date>2026</date>', $xml);
        $this->assertStringContainsString('<episode-num system="xmltv_ns">6.1534.</episode-num>', $xml);
        $this->assertStringContainsString('<actor>Elsa Lunghini</actor>', $xml);
        $this->assertStringContainsString('<director>Céline Guerraz</director>', $xml);
        $this->assertStringNotContainsString('N/A', $xml);
        $this->assertStringContainsString('<premiere/>', $xml);
        $this->assertStringContainsString('<previously-shown/>', $xml);
        $this->assertStringContainsString('objectId=57076', $xml);
        $this->assertStringContainsString('<value>-10</value>', $xml);
        $this->assertStringContainsString('<keyword>direct</keyword>', $xml);
        $this->assertStringContainsString('<title lang="fr">Météo</title>', $xml);
        $this->assertStringContainsString('<value>Tout public</value>', $xml);
        $this->assertStringContainsString('start="' . str_replace('-', '', $this->today) . '200000 +0', $xml);
    }

    public function testSeveralChannelsAndDaysShareTheSameRequest(): void
    {
        $this->mockApi();

        $provider = new AntenneReunion($this->client, 1);

        $this->assertSame(2, $provider->constructEPG('AntenneReunion.fr', $this->today)->getProgramCount());
        $this->assertSame(1, $provider->constructEPG('AntenneReunion.fr', $this->tomorrow)->getProgramCount());
        $this->assertSame(1, $provider->constructEPG('PassionNovelas.af', $this->today)->getProgramCount());
        $this->assertSame(1, $provider->constructEPG('RTLReunion.fr', $this->today)->getProgramCount());
        $this->assertSame(1, $provider->constructEPG('AntenneReunionRadio.fr', $this->today)->getProgramCount());
    }

    public function testChannelOrDayWithoutProgramsReturnsFalse(): void
    {
        $this->mockApi();

        $provider = new AntenneReunion($this->client, 1);

        $this->assertFalse($provider->constructEPG('ExoTV.fr', $this->today));
        $this->assertFalse($provider->constructEPG('AntenneReunion.fr', (new \DateTimeImmutable($this->today))->modify('+30 days')->format('Y-m-d')));
    }

    public function testUnknownChannelReturnsFalse(): void
    {
        $this->client->expects($this->never())->method('post');

        $provider = new AntenneReunion($this->client, 1);

        $this->assertFalse($provider->constructEPG('Unknown.fr', $this->today));
    }

    public function testApiFailureReturnsFalse(): void
    {
        $this->client->method('post')->willReturn(new Response(200, [], '{"status":false,"result":null,"error":{"code":-1,"message":"Invalid auth"}}'));

        $provider = new AntenneReunion($this->client, 1);

        $this->assertFalse($provider->constructEPG('AntenneReunion.fr', $this->today));
    }

    public function testLogo(): void
    {
        $provider = new AntenneReunion($this->client, 1);

        $this->assertStringEndsWith('/proxy/imgdata?type=100&objectId=69&languageId=fr', $provider->getLogo('ExoTV.fr'));
        $this->expectException(\Exception::class);
        $provider->getLogo('Unknown.fr');
    }

    private function mockApi(): void
    {
        $at = fn (string $date, string $time) => (new \DateTimeImmutable("$date $time", new \DateTimeZone('Europe/Paris')))->getTimestamp();
        $event = fn (string $date, string $from, string $to, array $data) => $data + [
            'startutc' => $at($date, $from), 'endutc' => $at($date, $to), 'synopsis' => '', 'genre' => 'Magazine',
            'rating' => 0, 'id_channel' => 1, 'id_epg' => 1,
        ];
        $episode = $event($this->today, '20:00', '22:00', [
            'title' => 'Ici tout commence', 'id_epg' => 57076, 'genre' => 'Série dramatique', 'rating' => 10,
            'synopsis' => 'Synopsis tronqué',
            'direct_metadata' => json_encode([
                'AD_ORIGTYPE' => 'Série', 'TS_PRODYEAR' => 2026, 'AD_SERIESEA' => 7, 'AD_SERIESEP' => 1535,
                'AD_SYNO350C' => 'Synopsis complet \\"cité\\" bien plus long que la version tronquée.',
                'AD_LIACTORS' => ['Benjamin Baroche', 'Elsa Lunghini'], 'AD_LIREALIS' => ['Céline Guerraz', 'N/A'],
                'CA_PREMIERE' => true, 'CA_RETRBCST' => true, 'CA_LIVEBCST' => true,
            ]),
        ]);
        $weather = $event($this->today, '22:00', '22:05', ['title' => 'Météo', 'genre' => 'Météo']);
        $tomorrow = $event($this->tomorrow, '10:00', '11:00', ['title' => 'Demain', 'direct_metadata' => json_encode(['AD_ORIGTYPE' => 'Autre'])]);
        $other = fn (int $id) => [$event($this->today, '12:00', '13:00', ['title' => "Chaîne $id", 'id_channel' => $id])];
        $response = ['status' => true, 'result' => ['epg' => [
            'chan_1' => [$episode, $weather, $tomorrow],
            'chan_2' => $other(2),
            'chan_69' => [],
            'chan_71' => $other(71),
        ]]];

        $this->client
            ->expects($this->once())
            ->method('post')
            ->willReturn(new Response(200, [], json_encode($response)));
    }
}
