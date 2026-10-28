<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * Tivi5 Monde USA TV guide (https://www.tivi5mondeusa.com/guide-tv).
 *
 * Time zones: the guide is displayed in America/New_York time (including the day the programs are grouped by),
 * but every program start is given as a real UTC instant in its `datetime` attribute. Only that attribute is used.
 * The page of a day (?day=YYYY-MM-DD) lists the programs from the day before (UTC) until the day after, which
 * covers the whole Paris day. The guide has no end time: a program ends when the next one starts.
 *
 * Season and episode number are only available on the page of each episode, and the synopsis on the page of the
 * series (see tivi5mondeusa_enable_details).
 */
class Tivi5MondeUSA extends AbstractProvider implements ProviderInterface
{
    private const BASE_URL = 'https://www.tivi5mondeusa.com';
    // Without browser-like headers, the website denies access
    private const HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
        'Accept-Encoding' => 'gzip, deflate',
        'Upgrade-Insecure-Requests' => '1',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
    ];
    private bool $enableDetails;

    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        $this->enableDetails = $extraParam['tivi5mondeusa_enable_details'] ?? true;
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tivi5mondeusa.json'), $priority ?? 0.5);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $xpath = $this->loadHtml(self::BASE_URL . '/guide-tv?day=' . $date);
        $rows = $xpath ? $xpath->query("//div[contains(@class, 'views-row') and starts-with(@class, 'jour-')]") : false;
        if (!$rows || $rows->length === 0) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $count = $rows->length;
        foreach ($rows as $index => $row) {
            $start = new \DateTimeImmutable($xpath->evaluate('string(.//time/@datetime)', $row));
            if ($start < $minDate) {
                continue;
            } elseif ($start > $maxDate) {
                break;
            }
            // No end time in the guide: the program ends when the next one starts
            if ($index + 1 >= $count) {
                break;
            }
            $end = new \DateTimeImmutable($xpath->evaluate('string(.//time/@datetime)', $rows->item($index + 1)));
            $title = $this->getText($xpath, ".//span[contains(@class, 'views-field-field-serie')]//span[@class='field-content']", $row);
            $episodeTitle = $this->getText($xpath, ".//span[contains(@class, 'views-field-title')]//span[@class='field-content']", $row);
            if ($end <= $start || ($title ?? $episodeTitle) === null) {
                continue;
            }

            $program = new Program($start, $end);
            $program->addTitle($title ?? $episodeTitle);
            $program->addSubTitle($title !== null ? $episodeTitle : null);
            $program->addCategory($this->getText($xpath, ".//div[contains(@class, 'views-field-field-categorie')]//div[@class='field-content']", $row));
            $this->addIcon($program, $xpath, $row);
            if ($this->enableDetails) {
                $this->setStatus(sprintf('%d/%d', $index + 1, $count));
                $this->addDetails($program, $xpath->evaluate('string(.//a[starts-with(@href, "/guide-tv/")]/@href)', $row));
            }
            $channelObj->addProgram($program);
        }

        return $channelObj->getProgramCount() > 0 ? $channelObj : false;
    }

    private function addIcon(Program $program, \DOMXPath $xpath, \DOMNode $row): void
    {
        $src = $xpath->evaluate('string(.//img/@data-src)', $row);
        if ($src !== '' && str_starts_with($src, '/')) {
            $program->addIcon(self::BASE_URL . $src, $xpath->evaluate('string(.//img/@width)', $row) ?: null, $xpath->evaluate('string(.//img/@height)', $row) ?: null);
        }
    }

    /**
     * The episode page gives the season and the episode number, the series page gives the synopsis.
     */
    private function addDetails(Program $program, string $episodePath): void
    {
        $xpath = $episodePath !== '' ? $this->loadHtml(self::BASE_URL . $episodePath) : null;
        if (!$xpath) {
            return;
        }
        preg_match('/\d+/', $this->getText($xpath, "//*[contains(@class, 'season-no')]") ?? '', $season);
        $episode = $this->getText($xpath, "//*[contains(@class, 'field--name-field-episode-number')]//*[@class='field__item']");
        $program->setEpisodeNum($season[0] ?? null, $episode);

        $seriesUrl = $xpath->evaluate('string(//*[contains(@class, "field--name-dynamic-token-fieldnode-lien-serie")]//a/@href)');
        $seriesXpath = $seriesUrl !== '' ? $this->loadHtml($seriesUrl) : null;
        $description = $seriesXpath?->query("//*[contains(@class, 'field--name-body')]")->item(0);
        if ($description) {
            $program->addDesc($this->getFormattedText($description));
        }
    }

    private function loadHtml(string $url): ?\DOMXPath
    {
        $html = $this->getContentFromURL($url, self::HEADERS, false, false);
        if ($html === '') {
            return null;
        }
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        return new \DOMXPath($dom);
    }

    private function getText(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $xpath->evaluate("string($query)", $context)));

        return $text === '' ? null : $text;
    }

    private function getFormattedText(\DOMNode $node): ?string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $child->nodeName === 'br' ? "\n" : $child->textContent;
        }
        $text = str_replace("\u{A0}", ' ', $text);

        return trim(preg_replace(['/[ \t]+/', '/\s*\R\s*/'], [' ', "\n"], $text)) ?: null;
    }
}
