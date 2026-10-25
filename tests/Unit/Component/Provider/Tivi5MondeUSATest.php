<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\Tivi5MondeUSA;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\ValueObject\Channel;

class Tivi5MondeUSATest extends TestCase
{
    /**
     * @var Client|\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;
    private string $date = '2026-07-15';
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

    public function testProviderWithDetails(): void
    {
        $this->mockWebsite();

        $provider = new Tivi5MondeUSA($this->client, 1);
        $channelObj = $provider->constructEPG('Tivi5.fr', $this->date);

        $this->assertInstanceOf(Channel::class, $channelObj);
        // Programs before the Paris day, after it, and the last program of the page (unknown end) are ignored
        $this->assertSame(2, $channelObj->getProgramCount());
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        // 2026-07-14T22:00:00Z is midnight in Paris (UTC+2 in July), whatever the New York time displayed on the website
        $this->assertStringContainsString('<programme start="20260715000000 +0200" stop="20260715001500 +0200"', $xml);
        $this->assertStringContainsString('<programme start="20260715001500 +0200" stop="20260716000000 +0200"', $xml);
        $this->assertStringContainsString('<title lang="fr">Bestioles &amp; Cie</title>', $xml);
        $this->assertStringContainsString('<sub-title lang="fr">Limaces et mollusques</sub-title>', $xml);
        $this->assertStringContainsString('<category lang="fr">New series!</category>', $xml);
        $this->assertStringContainsString('<icon src="https://www.tivi5mondeusa.com/sites/image.jpg?h=1&amp;itok=2" width="308" height="174"/>', $xml);
        $this->assertStringContainsString('<episode-num system="xmltv_ns">0.20.</episode-num>', $xml);
        $this->assertStringContainsString("<desc lang=\"fr\">Plongée au coeur de leur univers !\nRéalisation : Sandrine Boulanger</desc>", $xml);
        $this->assertStringNotContainsString('Programme du lendemain', $xml);
        $this->assertStringNotContainsString('Dernier programme', $xml);
        // Same episode and same series only requested once
        $this->assertSame(1, count(array_keys($this->requestedUrls, 'https://www.tivi5mondeusa.com/guide-tv/new-series/bestioles-cie/limaces-1')));
        $this->assertSame(1, count(array_keys($this->requestedUrls, 'https://www.tivi5mondeusa.com/guide-tv/new-series/bestioles-cie-Saison-1-1')));
    }

    public function testProviderWithoutDetailsOnlyRequestsTheGuide(): void
    {
        $this->mockWebsite();

        $provider = new Tivi5MondeUSA($this->client, 1, ['tivi5mondeusa_enable_details' => false]);
        $channelObj = $provider->constructEPG('Tivi5.fr', $this->date);

        $this->assertSame(2, $channelObj->getProgramCount());
        $this->assertSame(['https://www.tivi5mondeusa.com/guide-tv?day=2026-07-15'], $this->requestedUrls);
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);
        $this->assertStringNotContainsString('episode-num', $xml);
        $this->assertStringNotContainsString('<desc', $xml);
    }

    public function testDayWithoutProgramsReturnsFalse(): void
    {
        $this->client->method('get')->willReturn(new Response(200, [], '<html><body><div class="view-content"></div></body></html>'));

        $provider = new Tivi5MondeUSA($this->client, 1);

        $this->assertFalse($provider->constructEPG('Tivi5.fr', '2026-12-31'));
    }

    public function testUnknownChannelReturnsFalse(): void
    {
        $this->client->expects($this->never())->method('get');

        $provider = new Tivi5MondeUSA($this->client, 1);

        $this->assertFalse($provider->constructEPG('Unknown.fr', $this->date));
    }

    private function row(string $utc, string $title, string $episode, string $category = 'Favorite heroes', string $path = '/guide-tv/favorite-heroes/x/y-1', string $image = ''): string
    {
        return <<<HTML
<div class="jour-2026-07-14 kind-36 views-row"><div class="g-date-debut-1"><div><time datetime="$utc" class="datetime">2026-07-14</time></div></div>
<div class="node clearfix">$image<div class="views-field views-field-field-categorie"><div class="field-content">$category</div></div>
<div class="views-field views-field-nothing-4"><div class="field-content"><a href="$path"><h2>
<span class="views-field views-field-field-serie"><span class="field-content">$title</span></span>
<span class="views-field views-field-title"><span class="field-content">$episode</span></span>
</h2></a></div></div></div></div>
HTML;
    }

    private function mockWebsite(): void
    {
        $image = '<img alt="" class="media__element b-lazy" data-src="/sites/image.jpg?h=1&amp;itok=2" src="image/svg+xml;charset=utf-8,%3Csvg%3E" width="308" height="174" />';
        $guide = '<html><body><div class="view-content">'
            . $this->row('2026-07-14T21:45:00Z', 'Programme de la veille', 'Hier')
            . $this->row('2026-07-14T22:00:00Z', 'Bestioles &amp; Cie', 'Limaces et mollusques', 'New series!', '/guide-tv/new-series/bestioles-cie/limaces-1', $image)
            . $this->row('2026-07-14T22:15:00Z', 'Bestioles &amp; Cie', 'Limaces et mollusques', 'New series!', '/guide-tv/new-series/bestioles-cie/limaces-1')
            . $this->row('2026-07-15T22:00:00Z', 'Programme du lendemain', 'Demain')
            . $this->row('2026-07-15T22:30:00Z', 'Dernier programme', 'Sans fin')
            . '</div></body></html>';
        $episode = '<html><body><div class="season-no"><span>(</span><span class="field-label-inline">Saison</span><span>1</span><span>)</span></div>'
            . '<div class="field field--name-field-episode-number"><div class="field__label">Épisode</div><div class="field__item">21</div></div>'
            . '<div class="field--name-dynamic-token-fieldnode-lien-serie"><a href="https://www.tivi5mondeusa.com/guide-tv/new-series/bestioles-cie-Saison-1-1">Plus d\'infos</a></div></body></html>';
        $series = '<html><body><div class="clearfix text-formatted field field--name-body field__item">Plongée au coeur de leur univers&nbsp;!<br>' . "\n"
            . '<br>' . "\n" . 'Réalisation : Sandrine Boulanger<br></div></body></html>';

        $this->client->method('get')->willReturnCallback(function (string $url, array $options) use ($guide, $episode, $series) {
            $this->requestedUrls[] = $url;
            $this->assertArrayHasKey('Accept-Encoding', $options['headers']);

            return new Response(200, [], match (true) {
                str_contains($url, 'guide-tv?day=') => $guide,
                str_contains($url, 'Saison-1-1') => $series,
                default => $episode,
            });
        });
    }
}
