<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\LeTelegramme;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;

class LeTelegrammeTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $requestedUrls = [];
    /**
     * @var array<string, string>
     */
    private array $pages = [];

    public function setUp(): void
    {
        parent::setUp();
        ProviderCache::clearCache();
        $this->requestedUrls = [];
        $this->pages = [];
    }

    public function tearDown(): void
    {
        ProviderCache::clearCache();
        parent::tearDown();
    }

    public function testProgramsOfTheParisDay(): void
    {
        $this->pages = [
            '2026-07-14' => $this->page([
                $this->program('21:00', 'La veille', 'FILM'),
                $this->program('23:30', 'Programme de nuit', 'MAGAZINE'),
                // After midnight: it is the next day
                $this->program('00:30', 'Après minuit', 'MAGAZINE D&#039;INFORMATION'),
                $this->program('05:50', 'Avant la page du jour', 'JOURNAL'),
            ]),
            '2026-07-15' => $this->page([
                $this->program('06:00', 'Le 6h info', 'JOURNAL', ['infos' => '2023. Drame France, 1h35', 'duration' => '1h35mn']),
                $this->program('20:00', 'Journal', 'JOURNAL', ['subTitle' => 'Édition du soir.']),
                $this->program('23:40', 'Dernier du jour', 'FILM', ['duration' => '2h00mn']),
                $this->program('00:20', 'Lendemain', 'MAGAZINE'),
            ]),
        ];

        $provider = new LeTelegramme($this->client(), 1);
        $channelObj = $provider->constructEPG('TF1.fr', '2026-07-15');
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);

        $this->assertSame([
            'https://telegramme.libreserviceweb.fr/television/programmes-chaine.html?np_television_fronttvprogramschannelfiltertype%5Bdate%5D=2026-07-14&np_television_fronttvprogramschannelfiltertype%5BtvChannelListId%5D=299&np_television_fronttvprogramschannelfiltertype%5BtvChannelId%5D=4',
            'https://telegramme.libreserviceweb.fr/television/programmes-chaine.html?np_television_fronttvprogramschannelfiltertype%5Bdate%5D=2026-07-15&np_television_fronttvprogramschannelfiltertype%5BtvChannelListId%5D=299&np_television_fronttvprogramschannelfiltertype%5BtvChannelId%5D=4',
        ], $this->requestedUrls);
        preg_match_all('#<title lang="fr">(.*?)</title>#', $xml, $titles);
        $this->assertSame(['Après minuit', 'Avant la page du jour', 'Le 6h info', 'Journal', 'Dernier du jour'], $titles[1]);
        // Without duration, the end is the start of the next program; the date changes after midnight
        $this->assertStringContainsString('<programme start="20260715003000 +0200" stop="20260715055000 +0200"', $xml);
        // With a duration shorter than the gap: start + duration
        $this->assertStringContainsString('<programme start="20260715060000 +0200" stop="20260715073500 +0200"', $xml);
        // Duration longer than the gap: the next program starts at 00:20
        $this->assertStringContainsString('<programme start="20260715234000 +0200" stop="20260716002000 +0200"', $xml);
        $this->assertStringContainsString('<category lang="fr">Magazine d\'information</category>', $xml);
        $this->assertStringContainsString('<sub-title lang="fr">Édition du soir.</sub-title>', $xml);
    }

    public function testFilmInformation(): void
    {
        $this->pages = ['2026-07-14' => $this->page([]), '2026-07-15' => $this->page([
            $this->program('20:50', 'La belle saison', 'FILM', [
                'infos' => '2014. Drame, Biopic États-Unis, Royaume-Uni, 1h51',
                'duration' => '1h51mn',
                'hearts' => 3,
                'castings' => 'De <a href="/p/1">Catherine Corsini</a> avec <a href="/p/2">Cécile de France</a>&nbsp;(Carole), <a href="/p/3">Izïa Higelin</a>&nbsp;(Delphine), <a href="/p/5">Un troisième</a>',
                'summary' => '1971. Delphine, fille de paysans.',
                'opinion' => 'Une histoire d\'amour passionnante.',
                'image' => '/file/image/productionversionedproductionphoto/f/4/9/photo.jpg',
            ]),
            $this->program('22:45', 'Le Grand Quiz', 'JEU', ['castings' => 'Présenté par <a href="/p/4">Jean-Luc Reichmann</a>']),
            // The end of the previous program is the start of the next one
            $this->program('00:30', 'Fin de soirée', 'MAGAZINE'),
        ])];

        $provider = new LeTelegramme($this->client(), 1);
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('TF1.fr', '2026-07-15'), $provider);

        $this->assertStringContainsString('<date>2014</date>', $xml);
        $this->assertStringContainsString('<category lang="fr">Film</category>', $xml);
        $this->assertStringContainsString('<category lang="fr">Drame</category>', $xml);
        $this->assertStringContainsString('<category lang="fr">Biopic</category>', $xml);
        $this->assertStringContainsString('<country>États-Unis - Royaume-Uni</country>', $xml);
        $this->assertSame(1, preg_match('#<credits>\s*<director>Catherine Corsini</director>\s*<actor role="Carole">Cécile de France</actor>\s*<actor role="Delphine">Izïa Higelin</actor>\s*<actor>Un troisième</actor>\s*</credits>#', $xml));
        $this->assertStringContainsString('<presenter>Jean-Luc Reichmann</presenter>', $xml);
        $this->assertStringContainsString('<desc lang="fr">1971. Delphine, fille de paysans.</desc>', $xml);
        $this->assertStringContainsString('<value>3/5</value>', $xml);
        $this->assertSame(1, substr_count($xml, '<star-rating'));
        $this->assertStringContainsString('<review source="Le Télégramme" type="text">Une histoire d\'amour passionnante.</review>', $xml);
        $this->assertStringContainsString('<icon src="https://telegramme.libreserviceweb.fr/file/image/productionversionedproductionphoto/f/4/9/photo.jpg"/>', $xml);
    }

    public function testNoProgramOrUnknownChannelReturnsFalse(): void
    {
        $this->pages = ['2026-07-14' => $this->page([]), '2026-07-15' => $this->page([])];
        $provider = new LeTelegramme($this->client(), 1);

        $this->assertFalse($provider->constructEPG('TF1.fr', '2026-07-15'));
        $this->assertFalse($provider->constructEPG('Unknown.fr', '2026-07-15'));
    }

    /**
     * @param string[] $programs
     */
    private function page(array $programs): string
    {
        return '<html><ul class="np-channel-programs-list">' . implode('', $programs) . '</ul></html>';
    }

    /**
     * @param array{infos?: string, duration?: string, hearts?: int, castings?: string, summary?: string, opinion?: string, subTitle?: string, image?: string} $options
     */
    private function program(string $time, string $title, string $type, array $options = []): string
    {
        $hearts = '';
        if (isset($options['hearts'])) {
            $hearts = '<span class="np-event-note" title="Notre note">' . str_repeat('<span class="glyphicon glyphicon-heart"></span>', $options['hearts'])
                . str_repeat('<span class="glyphicon glyphicon-heart-empty"></span>', 5 - $options['hearts']) . '</span>';
        }

        return '<li class="np-program-infos"><div class="np-television-program-schedule">' . "\n    " . $time . "\n" . '</div>'
            . '<div class="np-television-program-type-reservation"><div class="np-tv-program-grid-channel-tv-program-type np-tv-program-grid-channel-tv-program-type-12" title="' . $type . '">TYPE</div></div>'
            . '<div class="np-television-tv-program-content"><div class="np-television-program-title"><a href="/television/programme-tv/1.html" title="' . $title . '">'
            . "\n    " . $title . $hearts . '</a></div>'
            . (isset($options['subTitle']) ? '<div title="' . $options['subTitle'] . '" class="np-television-program-sub-title">' . $options['subTitle'] . '</div>' : '')
            . (isset($options['duration']) ? '<div class="np-tv-program-channel-technical-infos">' . "\n  " . $options['duration'] . "\n" . '</div>' : '')
            . '<div class="np-channel-program-synopsis">'
            . (isset($options['infos']) ? '<div> ' . $options['infos'] . ' </div>' : '')
            . '<div class="np-television-profile-description-castings">' . ($options['castings'] ?? '') . '</div>'
            . (isset($options['summary']) ? '<div class="np-television-profile-description-infos"><span class="np-bold">Résumé :</span> ' . $options['summary'] . "\n" . '</div>' : '')
            . (isset($options['opinion']) ? '<div class="np-television-profile-description-infos"><span class="np-bold">Notre opinion :</span> ' . $options['opinion'] . "\n" . '</div>' : '')
            . '</div></div><div class="np-programs-prime-program-image">'
            . (isset($options['image']) ? '<figure><img src="' . $options['image'] . '" title="' . $title . '" /></figure>' : '')
            . '</div></li>';
    }

    private function client(): Client
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturnCallback(function (string $url) {
            $this->requestedUrls[] = $url;
            preg_match('/date%5D=(\d{4}-\d{2}-\d{2})/', $url, $date);

            return new Response(200, [], $this->pages[$date[1]] ?? $this->page([]));
        });

        return $client;
    }
}
