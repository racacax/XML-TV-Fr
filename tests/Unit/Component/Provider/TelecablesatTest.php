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

        return new Telecablesat($client, 1, ['telecablesat_enable_details' => false]);
    }
}
