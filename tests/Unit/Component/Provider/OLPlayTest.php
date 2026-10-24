<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\OLPlay;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\ValueObject\Channel;

class OLPlayTest extends TestCase
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

        $provider = new OLPlay($this->client, 1);
        $channelObj = $provider->constructEPG('OLTV.fr', $this->today);

        $this->assertInstanceOf(Channel::class, $channelObj);
        $this->assertSame(2, $channelObj->getProgramCount());
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        $this->assertStringContainsString('<title lang="fr">Hoffenheim - OL</title>', $xml);
        $this->assertStringContainsString('<sub-title lang="fr">Europa League</sub-title>', $xml);
        $this->assertStringContainsString("<desc lang=\"fr\">Premier paragraphe.\nSecond paragraphe &amp; fin.</desc>", $xml);
        $this->assertStringContainsString('<date>2026</date>', $xml);
        $this->assertStringContainsString('<icon src="https://files.example/match.jpg"/>', $xml);
        $this->assertStringContainsString('<keyword>direct</keyword>', $xml);
        $this->assertStringContainsString('<previously-shown', $xml);
        $this->assertStringContainsString('<subtitles type="teletext"', $xml);
        $this->assertStringContainsString('<audio-described', $xml);
        $this->assertStringContainsString('<actor>Jean Dupont</actor>', $xml);
        $this->assertStringContainsString('<category lang="fr">Football</category>', $xml);
        $this->assertStringContainsString('<country>France</country>', $xml);
        $this->assertStringContainsString('<value>-18</value>', $xml);
        $this->assertStringContainsString('<title lang="fr">Les programmes d\'OLPLAY</title>', $xml);
        $this->assertStringContainsString('start="' . str_replace('-', '', $this->today) . '200000 +0', $xml);
    }

    public function testSeveralDaysShareTheSameTwoRequests(): void
    {
        $this->mockApi();

        $provider = new OLPlay($this->client, 1);

        $this->assertSame(2, $provider->constructEPG('OLTV.fr', $this->today)->getProgramCount());
        $this->assertSame(1, $provider->constructEPG('OLTV.fr', $this->tomorrow)->getProgramCount());
    }

    public function testDayWithoutProgramsDoesNotRequestDetails(): void
    {
        $this->mockApi(1);

        $provider = new OLPlay($this->client, 1);

        $this->assertFalse($provider->constructEPG('OLTV.fr', (new \DateTimeImmutable($this->today))->modify('+30 days')->format('Y-m-d')));
    }

    public function testUnknownChannelReturnsFalse(): void
    {
        $this->client->expects($this->never())->method('post');

        $provider = new OLPlay($this->client, 1);

        $this->assertFalse($provider->constructEPG('Unknown.fr', $this->today));
    }

    public function testApiFailureReturnsFalse(): void
    {
        $this->client->method('post')->willReturn(new Response(200, [], '{"result":{"success":false,"code":1}}'));

        $provider = new OLPlay($this->client, 1);

        $this->assertFalse($provider->constructEPG('OLTV.fr', $this->today));
    }

    private function mockApi(int $calls = 2): void
    {
        $at = fn (string $date, string $time) => (new \DateTimeImmutable("$date $time", new \DateTimeZone('Europe/Paris')))->format('c');
        $schedule = ['programs' => ['olplay-channel-1' => [
            ['from' => $at($this->today, '20:00'), 'to' => $at($this->today, '22:00'), 'id' => 'prg.1'],
            ['from' => $at($this->today, '22:00'), 'to' => $at($this->today, '23:00'), 'id' => 'prg.2'],
            ['from' => $at($this->tomorrow, '10:00'), 'to' => $at($this->tomorrow, '11:00'), 'id' => 'prg.3'],
        ], 'olplay-live-events-1' => []], 'result' => ['success' => true, 'code' => 0]];
        $tiles = ['tiles' => [
            [
                'id' => 'prg.1', 'title' => 'Hoffenheim - OL', 'subTitle' => 'Europa League', 'date' => 2026,
                'description' => '<div>Premier paragraphe.</div><div><br></div><div>Second paragraphe &amp; fin.&nbsp;</div>',
                'shortDescription' => 'Résumé',
                'images' => [['role' => 'icon', 'url' => 'https://files.example/match.jpg', 'isMain' => false]],
                'isLive' => true, 'isPreviouslyShown' => true, 'hasSubtitles' => true, 'hasAudioDescription' => true,
                'isAdultContent' => true,
                'people' => [['roleCodename' => 'actor', 'firstName' => 'Jean', 'lastName' => 'Dupont']],
                'categories' => [['name' => 'Football', 'typeCodename' => 'genre']],
                'countries' => [['name' => 'France']],
            ],
            ['id' => 'prg.2', 'title' => "Les programmes d'OLPLAY", 'images' => []],
            ['id' => 'prg.3', 'title' => 'Demain'],
        ], 'result' => ['success' => true, 'code' => 0]];

        $this->client
            ->expects($this->exactly($calls))
            ->method('post')
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode($schedule)),
                new Response(200, [], json_encode($tiles))
            );
    }
}
