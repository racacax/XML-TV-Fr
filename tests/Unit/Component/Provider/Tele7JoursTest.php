<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\Tele7Jours;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;

class Tele7JoursTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $requestedUrls = [];

    public function setUp(): void
    {
        parent::setUp();
        ProviderCache::clearCache();
        $this->requestedUrls = [];
    }

    public function tearDown(): void
    {
        ProviderCache::clearCache();
        parent::tearDown();
    }

    public function testDurationsInMinutesAndDetailsOfAMovie(): void
    {
        $item = fn (string $time, string $title, string $subDetails, string $href) => '<div class="tvgrid-broadcast__item">'
            . '<a class="tvgrid-broadcast__wrapper--link" href="' . $href . '"><div class="tvgrid-broadcast__details-time">' . $time . '</div>'
            . '<div class="tvgrid-broadcast__details-title">' . $title . '</div>'
            . '<div class="tvgrid-broadcast__subdetails">' . $subDetails . '</div>'
            . '<div class="tvgrid-broadcast__details-rating"><img src="https://cdn.example/icons/t7j.svg" alt="t7j"></div></a>'
            . '<div class="tvgrid-broadcast__poster"><a href="' . $href . '"><picture class="tvgrid-broadcast__poster-picture">'
            . '<img decoding="async" loading="lazy" src="//cdn.example/cache/poster' . md5($title) . '.jpg" alt="' . $title . '"></picture></a></div></div>';
        $grid = '<html>'
            . $item('21h00', 'La belle saison', 'Inédit | 101 mins | Films dramatiques', '/type/films/dramatiques/la-belle-saison-90241255')
            . $item('22h45', 'Une émission', '41 mn | Magazine', '/type/emissions/une-emission-1')
            // Without duration, the end of the program is unknown
            . $item('23h30', 'Sans durée', 'Magazine', '/type/emissions/sans-duree-2')
            . '</html>';
        $detail = '<ul class="program-details__info"><li class="program-details__info-item"><h2 class="program-details__info-title">Production</h2>'
            . '<span class="program-details__info-text">France - Belgique  - 2015</span></li></ul>'
            . '<p class="program-details__summary-text">Le Limousin, dans les années 1970.</p>'
            . '<ul class="casting"><li class="casting__item"><p class="casting__name">Catherine Corsini</p><span class="casting__role">Réalisateur</span></li></ul>';
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturnCallback(function (string $url) use ($grid, $detail) {
            $this->requestedUrls[] = $url;

            return new Response(200, [], str_contains($url, '/tv/chaines/') ? $grid : $detail);
        });

        $provider = new Tele7Jours($client, 1);
        $channelObj = $provider->constructEPG('TF1.fr', date('Y-m-d'));
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);

        $this->assertSame(2, $channelObj->getProgramCount());
        $this->assertSame(date('Ymd') . '210000', substr(explode('start="', $xml)[1], 0, 14));
        $this->assertStringContainsString('<title lang="fr">La belle saison</title>', $xml);
        $this->assertStringNotContainsString('Sans durée', $xml);
        // Relative links of the grid are completed
        $this->assertContains('https://www.programme-television.org/type/films/dramatiques/la-belle-saison-90241255', $this->requestedUrls);
        $this->assertStringContainsString('<date>2015</date>', $xml);
        $this->assertStringContainsString('<country>France - Belgique</country>', $xml);
        $this->assertStringContainsString('<director>Catherine Corsini</director>', $xml);
        // Poster of the grid (not the icons of the details of the program)
        $this->assertStringContainsString('<icon src="https://cdn.example/cache/poster' . md5('La belle saison') . '.jpg"/>', $xml);
        $this->assertStringNotContainsString('t7j.svg', $xml);
    }

    public function testTheNightIsReadInTheGridOfTheDayBefore(): void
    {
        $item = fn (string $time, string $title, string $href) => '<div class="tvgrid-broadcast__item">'
            . '<a class="tvgrid-broadcast__wrapper--link" href="' . $href . '"><div class="tvgrid-broadcast__details-time">' . $time . '</div>'
            . '<div class="tvgrid-broadcast__details-title">' . $title . '</div>'
            . '<div class="tvgrid-broadcast__subdetails">30 mins | Magazine</div></a></div>';
        $pages = [
            // The grid of a day goes from 05:00 to 05:00 on the next day
            '/hier' => $item('21h00', 'La veille', '/p/veille') . $item('23h30', 'Nuit de la veille', '/p/nuit')
                . $item('00h30', 'Après minuit', '/p/minuit') . $item('04h30', 'Fin de nuit', '/p/fin-de-nuit'),
            '/' => $item('05h00', 'Matin', '/p/matin') . $item('21h00', 'Soir', '/p/soir')
                . $item('23h50', 'Dernier', '/p/dernier') . $item('00h20', 'Lendemain', '/p/lendemain'),
        ];
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturnCallback(function (string $url) use ($pages) {
            $this->requestedUrls[] = $url;
            $path = parse_url($url, PHP_URL_PATH);

            return new Response(200, [], str_contains($path, '/tv/chaines/') ? $pages['/' . ltrim(substr($path, strlen('/tv/chaines/tf1')), '/')] : '<html></html>');
        });

        $provider = new Tele7Jours($client, 1);
        $channelObj = $provider->constructEPG('TF1.fr', date('Y-m-d'));
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);

        preg_match_all('#<title lang="fr">(.*?)</title>#', $xml, $titles);
        $this->assertSame(['Après minuit', 'Fin de nuit', 'Matin', 'Soir', 'Dernier'], $titles[1]);
        // 00:30 and 04:30 of the grid of the day before are on the day, 00:20 of the grid of the day is on the day after
        $this->assertStringContainsString('<programme start="' . date('Ymd') . '003000', $xml);
        $this->assertStringContainsString('<programme start="' . date('Ymd') . '235000', $xml);
        $this->assertContains('https://www.programme-television.org/tv/chaines/tf1/hier', $this->requestedUrls);
        // Details are only requested for the programs of the day
        $this->assertContains('https://www.programme-television.org/p/minuit', $this->requestedUrls);
        $this->assertNotContains('https://www.programme-television.org/p/veille', $this->requestedUrls);
        $this->assertNotContains('https://www.programme-television.org/p/lendemain', $this->requestedUrls);
    }
}
