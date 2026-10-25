<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\OuestFrance;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\XmlFormatter;

class OuestFranceTest extends TestCase
{
    private const BASE = 'https://programmetv.ouest-france.fr';

    /**
     * @var array<int, array{string, array}>
     */
    private array $requests = [];

    /**
     * @var array<string, array<int, array{string, string, string, string}>>
     */
    private array $pages = [];
    /**
     * @var array<string, string>
     */
    private array $details = [];

    public function setUp(): void
    {
        parent::setUp();
        ProviderCache::clearCache();
        $this->requests = [];
    }

    public function tearDown(): void
    {
        ProviderCache::clearCache();
        parent::tearDown();
    }

    public function testProgramsOfTheParisDay(): void
    {
        $this->mockWebsite([
            '2026-07-14' => [
                ['21:00', 'La veille', 'Film • 1h30', '/film/comedie/la-veille-c1/'],
                // After midnight: it is the next day
                ['23:30', 'Programme de nuit', 'Divertissement • 1h00', '/divers/nuit-m2/'],
                ['00:30', 'Après minuit', 'Magazine • 0h30', '/magazine/apres-minuit-m3/'],
            ],
            '2026-07-15' => [
                ['02:15', 'Premier de la page', 'Météo • 0h05', '/divers/meteo-m4/'],
                ['20:00', 'Journal 20h00', 'Journal • 1h00', '/divers/journal/journal-20h00-m5/'],
                ['21:10', 'La terre et le sang', 'Série dramatique • 0h50', '/serie/dramatique/la-terre-et-le-sang-m6/s01e03-la-terre-et-le-sang-c7/'],
                ['23:50', 'Dernier du jour', 'Film • 2h00', '/film/dernier-c8/'],
                ['00:20', 'Lendemain', 'Magazine • 0h10', '/magazine/lendemain-m9/'],
            ],
        ]);

        $provider = new OuestFrance($this->client(), 1, ['ouestfrance_enable_details' => false]);
        $channelObj = $provider->constructEPG('France2.fr', '2026-07-15');
        $xml = (new XmlFormatter())->formatChannel($channelObj, $provider);

        // The pages of the day before and of the day are requested, with the headers required by the website
        $this->assertSame([self::BASE . '/chaines/programme-tv-france-2-4/?date=2026-07-14', self::BASE . '/chaines/programme-tv-france-2-4/?date=2026-07-15'], array_column($this->requests, 0));
        $this->assertArrayHasKey('Accept-Encoding', $this->requests[0][1]['headers']);
        $this->assertArrayHasKey('Accept-Language', $this->requests[0][1]['headers']);
        // 23:30 and 00:30 of the page of the day before: 00:30 is on the 15th, 23:30 on the 14th
        $this->assertSame(['Après minuit', 'Premier de la page', 'Journal 20h00', 'La terre et le sang', 'Dernier du jour'], $this->titles($xml));
        // End: start + duration, or the start of the next program when it comes first (00:20 here)
        $this->assertStringContainsString('<programme start="20260715003000 +0200" stop="20260715010000 +0200"', $xml);
        $this->assertStringContainsString('<programme start="20260715021500 +0200" stop="20260715022000 +0200"', $xml);
        $this->assertStringContainsString('<programme start="20260715235000 +0200" stop="20260716002000 +0200"', $xml);
        $this->assertStringContainsString('<category lang="fr">Série dramatique</category>', $xml);
        $this->assertStringContainsString('<icon src="https://media.example/p.jpg?width=300&amp;sign=abc"/>', $xml);
        $this->assertStringContainsString('<episode-num system="xmltv_ns">0.2.</episode-num>', $xml);
    }

    public function testTheHourWhenTheClocksGoBackIsResolvedThroughTheDurationOfThePreviousProgram(): void
    {
        $this->mockWebsite([
            '2026-10-24' => [
                ['23:43', 'Les bien-aimés', 'Comédie dramatique • 2h14', '/film/a-c1/'],
                ['01:57', 'Jeanne et le garçon formidable', 'Comédie musicale • 1h35', '/film/b-c2/'],
            ],
            '2026-10-25' => [
                // 02:00 to 03:00 exists twice: 01:57 CEST + 1h35 is 02:32 CET
                ['02:32', 'Crustacés et coquillages', 'Comédie • 1h32', '/film/c-c3/'],
                ['04:04', 'Emma', 'Comédie • 1h58', '/film/d-c4/'],
            ],
        ]);

        $provider = new OuestFrance($this->client(), 1, ['ouestfrance_enable_details' => false]);
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('France2.fr', '2026-10-25'), $provider);

        $this->assertStringContainsString('<programme start="20261025015700 +0200" stop="20261025023200 +0100"', $xml);
        $this->assertStringContainsString('<programme start="20261025023200 +0100" stop="20261025040400 +0100"', $xml);
        $this->assertStringContainsString('<programme start="20261025040400 +0100" stop="20261025060200 +0100"', $xml);
    }

    public function testDetailsAndFullTitles(): void
    {
        $this->mockWebsite(['2026-07-14' => [], '2026-07-15' => [
            ['21:00', 'Affaire conclue, tout le monde a quel...', 'Magazine • 0h55', '/magazine/affaire-m1/'],
            ['22:00', 'Averroès et Rosa Parks', 'Film documentaire • 2h15', '/film/documentaire/averroes-c2/'],
        ]], [
            '/magazine/affaire-m1/' => '<h1>Affaire conclue, tout le monde a quelque chose à vendre</h1>',
            '/film/documentaire/averroes-c2/' => '<h1>Averroès et Rosa Parks</h1><p><strong>Durée</strong> 2h15</p><p><strong>De</strong> Nicolas Philibert </p>'
                . '<p><strong>Avec</strong> Joëlle Albiac, Célia Bagla </p><p><strong>Année de production</strong> 2024</p>'
                . '<div class="su-article su-margin-bottom-l content-summary"> Focus sur deux unités &quot;soignants-soignés&quot;. </div>',
        ]);

        $provider = new OuestFrance($this->client(), 1);
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('France2.fr', '2026-07-15'), $provider);

        $this->assertSame(['Affaire conclue, tout le monde a quelque chose à vendre', 'Averroès et Rosa Parks'], $this->titles($xml));
        $this->assertStringContainsString('<desc lang="fr">Focus sur deux unités "soignants-soignés".</desc>', str_replace('&quot;', '"', $xml));
        $this->assertStringContainsString('<date>2024</date>', $xml);
        $this->assertStringContainsString('<director>Nicolas Philibert</director>', $xml);
        $this->assertStringContainsString('<actor>Joëlle Albiac</actor>', $xml);
        $this->assertStringContainsString('<actor>Célia Bagla</actor>', $xml);
    }

    public function testWithoutDetailsOnlyTruncatedTitlesAreCompleted(): void
    {
        $this->mockWebsite(['2026-07-14' => [], '2026-07-15' => [
            ['21:00', 'Affaire conclue, tout le monde a quel...', 'Magazine • 0h55', '/magazine/affaire-m1/'],
            ['22:00', 'Averroès et Rosa Parks', 'Film documentaire • 2h15', '/film/documentaire/averroes-c2/'],
        ]], ['/magazine/affaire-m1/' => '<h1>Affaire conclue, tout le monde a quelque chose à vendre</h1>']);

        $provider = new OuestFrance($this->client(), 1, ['ouestfrance_enable_details' => false]);
        $xml = (new XmlFormatter())->formatChannel($provider->constructEPG('France2.fr', '2026-07-15'), $provider);

        $this->assertSame(['Affaire conclue, tout le monde a quelque chose à vendre', 'Averroès et Rosa Parks'], $this->titles($xml));
        $this->assertCount(3, $this->requests);
    }

    public function testNoProgramOrUnknownChannelReturnsFalse(): void
    {
        $this->mockWebsite(['2026-07-14' => [], '2026-07-15' => []]);
        $provider = new OuestFrance($this->client(), 1);

        $this->assertFalse($provider->constructEPG('France2.fr', '2026-07-15'));
        $this->assertFalse($provider->constructEPG('Unknown.fr', '2026-07-15'));
    }

    /**
     * @return string[]
     */
    private function titles(string $xml): array
    {
        preg_match_all('#<title lang="fr">(.*?)</title>#', $xml, $matches);

        return array_map(fn ($title) => htmlspecialchars_decode($title), $matches[1]);
    }

    /**
     * @param array<string, array<int, array{string, string, string, string}>> $pages Programs (time, title, infos, url) of the page of each day
     * @param array<string, string> $details Content of the pages of the programs, by path
     */
    private function mockWebsite(array $pages, array $details = []): void
    {
        $this->pages = $pages;
        $this->details = $details;
    }

    private function client(): Client
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturnCallback(function (string $url, array $options) {
            $this->requests[] = [$url, $options];
            if (preg_match('/date=(\d{4}-\d{2}-\d{2})/', $url, $date)) {
                $items = '';
                foreach ($this->pages[$date[1]] ?? [] as [$time, $title, $infos, $path]) {
                    $items .= "<li\nclass=\"programme \"\n><a href=\"" . self::BASE . $path . '" title="Détails du programme"><figure>'
                        . "<img data-oflazyload='{\"src\":\"https://media.example/p.jpg?width&#61;300&amp;sign&#61;abc\"}' alt=\"" . $title . '"></figure>'
                        . '<div class="meta"><p class="horaire su-text-small">' . $time . '</p><p class="nom"><strong>' . $title . '</strong></p>'
                        . '<p class="infos su-text-xsmall">' . $infos . '</p></div></a></li>';
                }

                return new Response(200, [], '<html><ul class="programmes">' . $items . '</ul></html>');
            }

            return new Response(200, [], $this->details[str_replace(self::BASE, '', $url)] ?? '<html></html>');
        });

        return $client;
    }
}
