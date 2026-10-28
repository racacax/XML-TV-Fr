<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\Telerama;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;

class TeleramaTest extends TestCase
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

    public function testDetailsOfAMovie(): void
    {
        $item = fn (string $label, string $value) => '<div class="sheet__info-item"><p class="sheet__info-item-label">' . $label . '</p><p class="sheet__info-item-value"> ' . $value . ' </p></div>';
        $html = '<p class="article__page-subtitle">Un film</p><p class="sheet__synopsis-content">Le Limousin, dans les années 1970.</p>'
            . $item('Genre', 'Drame')
            . $item('Réalisateurs', 'Catherine Corsini, Un autre')
            . $item('Scénaristes', 'Catherine Corsini, Laurette Polmanss')
            . $item('Musique', 'Grégoire Hetzel')
            . $item('Sortie', '2015')
            . $item('Pays', 'France - Belgique');
        $grid = ['channels' => ['192' => ['broadcasts' => [[
            'title' => 'La belle saison', 'start_date' => '2026-07-15T21:00:00.000+02:00', 'end_date' => '2026-07-15T22:41:00.000+02:00',
            'type' => 'film', 'is_inedit' => false, 'illustration' => ['url' => null], 'deeplink' => 'tlrm://element?id=%2Fbroadcast%2F1',
        ]]]]];
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturnCallback(function (string $url) use ($grid, $html) {
            $body = match (true) {
                str_contains($url, 'tv-program/grid?date=2026-07-15') => $grid,
                str_contains($url, 'tv-program/grid') => ['channels' => []],
                default => ['templates' => ['raw_content' => ['content' => $html]]],
            };

            return new Response(200, [], json_encode($body));
        });

        $provider = new Telerama($client, 1);
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('TF1.fr', '2026-07-15'), $provider);

        $this->assertStringContainsString('<date>2015</date>', $xml);
        $this->assertStringContainsString('<country>France - Belgique</country>', $xml);
        $this->assertSame(1, preg_match('#<credits>\s*<director>Catherine Corsini</director>\s*<director>Un autre</director>\s*<writer>Catherine Corsini</writer>\s*<writer>Laurette Polmanss</writer>\s*<composer>Grégoire Hetzel</composer>\s*</credits>#', $xml));
        $this->assertStringContainsString('<desc lang="fr">Le Limousin, dans les années 1970.</desc>', $xml);
    }
}
