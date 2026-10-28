<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\Telecablesat;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;

class TelecablesatTest extends TestCase
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

    public function testProgramsOfTheRightChannelOfThePage(): void
    {
        // TF1.fr is channel 192 on the first page
        $provider = $this->createProvider(['34', '192']);

        $channelObj = $provider->constructEPG('TF1.fr', '2026-07-15');

        $this->assertSame(1, $channelObj->getProgramCount());
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        $this->assertStringContainsString('<title lang="fr">Programme de la chaîne 192</title>', $xml);
        $this->assertStringNotContainsString('chaîne 34', $xml);
    }

    public function testChannelMissingFromThePageDoesNotGetTheProgramsOfAnotherChannel(): void
    {
        $provider = $this->createProvider(['34', '35']);

        $this->assertSame(0, $provider->constructEPG('TF1.fr', '2026-07-15')->getProgramCount());
    }

    /**
     * @param string[] $channelIds Channels displayed on the page, in order
     */
    private function createProvider(array $channelIds): Telecablesat
    {
        $start = (new \DateTimeImmutable('2026-07-15 21:00', new \DateTimeZone('Europe/Paris')))->getTimestamp();
        $logos = '';
        $rows = '';
        foreach ($channelIds as $id) {
            $logos .= '<img src="/logos_chaines/' . $id . '.png" title="Chaîne ' . $id . '">';
            $rows .= '<div class="row"><div data-start="' . $start . '" data-end="' . ($start + 3600) . '" data-diffusion="' . $id . '00">'
                . '<img data-src="//img.example/' . $id . '.jpg"><div class="hour-type"><span>21h</span>Film</div>'
                . '<span class="title">Programme de la chaîne ' . $id . '</span><a class="link" href="/emission/' . $id . '.html"></a></div></div>';
        }
        $page = $logos . $rows . "<div class='paging'></div>";
        // The page of the day before has no program (an empty page would be considered as a rate limit)
        $emptyPage = $logos . "<div class='paging'></div>";
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturnCallback(fn (string $url) => new Response(200, [], str_contains($url, 'date=2026-07-15') ? $page : $emptyPage));

        return new Telecablesat($client, 1, ['telecablesat_enable_details' => false, 'telecablesat_request_interval' => 0]);
    }

    public function testNeighbourPagesAreTriedWhenTheChannelHasMoved(): void
    {
        // Ciné+ Emotion (283) is configured on page 4 but is now on page 3
        $start = (new \DateTimeImmutable('2026-07-15 21:00', new \DateTimeZone('Europe/Paris')))->getTimestamp();
        $row = fn (string $title) => '<div class="row"><div data-start="' . $start . '" data-end="' . ($start + 3600) . '" data-diffusion="1">'
            . '<img data-src="//img.example/a.jpg"><div class="hour-type"><span>21h</span>Film</div>'
            . '<span class="title">' . $title . '</span><a class="link" href="/emission/a.html"></a></div></div>';
        $page = fn (array $ids, string $rows) => implode('', array_map(fn ($id) => '<img src="/logos_chaines/' . $id . '.png" title="Chaîne ' . $id . '">', $ids)) . $rows . "<div class='paging'></div>";
        $requested = [];
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturnCallback(function (string $url) use ($page, $row, &$requested) {
            $requested[] = $url;
            preg_match('/date=(\d{4}-\d{2}-\d{2})&page=(\d+)/', $url, $matches);
            $content = match ($matches[2]) {
                '4' => $page(['500'], $row('Autre chaîne')),
                '3' => $page(['283'], $matches[1] === '2026-07-15' ? $row('Programme déplacé') : ''),
                default => $page(['600'], ''),
            };

            return new Response(200, [], $content);
        });
        $provider = new Telecablesat($client, 1, ['telecablesat_enable_details' => false, 'telecablesat_request_interval' => 0]);

        $channelObj = $provider->constructEPG('CinePlusEmotion.fr', '2026-07-15');

        $this->assertSame(1, $channelObj->getProgramCount());
        $this->assertStringContainsString('Programme déplacé', (new XmlFormatter())->formatChannel($channelObj, $provider));
        // Day before and day: configured page, then the previous one
        $this->assertSame(
            ['date=2026-07-14&page=4', 'date=2026-07-14&page=3', 'date=2026-07-15&page=4', 'date=2026-07-15&page=3'],
            array_map(fn ($url) => substr($url, strpos($url, 'date=')), $requested)
        );
    }
}
