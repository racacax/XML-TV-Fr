<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;
use racacax\XmlTv\ValueObject\Tag;

/*
 * Uses external XMLTV files as a source. Every channel of channels_externalxml.json is associated to a list of sources:
 *
 *   "SonyOneFavoris.fr": [
 *     {"url": "https://example.com/epg.xml", "id": "channel-id-in-this-xml", "headers": {}, "timezone": "UTC"}
 *   ]
 *
 *  - url: XMLTV file (gzip compressed files are supported). It is only downloaded once per generation, whatever
 *    the number of channels and days that use it (HTTP cache)
 *  - id: id of the channel in this XML (attribute channel of the programmes)
 *  - headers (optional): HTTP headers sent with the request
 *  - timezone (optional): time zone of the dates without any offset (UTC by default, as stated by the XMLTV format)
 *
 * Sources are tried in order, the first one that has programs for the requested day is used.
 * Programs are copied as is: every XMLTV element and attribute of the source (credits, episode-num, rating,
 * subtitles, ...) is kept, only empty elements without any information (no content, no attribute other than lang) are ignored.
 */
class ExternalXML extends AbstractProvider implements ProviderInterface
{
    // Elements that are meaningful when they are empty
    private const FLAG_ELEMENTS = ['premiere', 'previously-shown', 'new', 'last-chance', 'audio-described'];
    private const CREDITS_ORDER = ['director', 'actor', 'writer', 'adapter', 'producer', 'composer', 'editor', 'presenter', 'commentator', 'guest'];
    private const DATE_FORMATS = ['YmdHis O', 'YmdHisO', 'YmdHis', 'YmdHi O', 'YmdHiO', 'YmdHi'];

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_externalxml.json'), $priority ?? 0.3);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($this->getSources($channel) as $source) {
            $xml = $this->getXml($source);
            if ($xml === null) {
                continue;
            }
            $hasPrograms = false;
            foreach ($this->readPrograms($xml, $source, $minDate, $maxDate) as $program) {
                $channelObj->addProgram($program);
                $hasPrograms = true;
            }
            if ($hasPrograms) {
                return $channelObj;
            }
        }

        return false;
    }

    /**
     * The logo is the icon of the channel in the first source that has one.
     */
    public function getLogo(string $channel): ?string
    {
        parent::getLogo($channel);

        foreach ($this->getSources($channel) as $source) {
            $xml = $this->getXml($source);
            $reader = $xml !== null ? $this->createReader($xml) : null;
            while ($reader?->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'channel' && $reader->getAttribute('id') === $source['id']) {
                    $element = $reader->expand();
                    $icon = $element instanceof \DOMElement ? $element->getElementsByTagName('icon')->item(0) : null;
                    if ($icon instanceof \DOMElement && $icon->getAttribute('src') !== '') {
                        return $icon->getAttribute('src');
                    }

                    break;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>> Sources having at least an url and an id (see the description of the class)
     */
    private function getSources(string $channel): array
    {
        $sources = $this->channelsList[$channel];
        // A single source can be written without the surrounding list
        if (is_array($sources) && isset($sources['url'])) {
            $sources = [$sources];
        }

        return array_values(array_filter(
            is_array($sources) ? $sources : [],
            fn ($source) => is_array($source) && !empty($source['url']) && isset($source['id'])
        ));
    }

    private function getXml(array $source): ?string
    {
        $content = $this->getContentFromURL($source['url'], $source['headers'] ?? [], false, false);
        if (str_starts_with($content, "\x1f\x8b")) {
            $content = gzdecode($content) ?: '';
        }

        return $content === '' ? null : $content;
    }

    private function createReader(string $xml): \XMLReader
    {
        $reader = new \XMLReader();
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return $reader;
    }

    /**
     * @return \Generator<Program>
     */
    private function readPrograms(string $xml, array $source, \DateTimeImmutable $minDate, \DateTimeImmutable $maxDate): \Generator
    {
        $timezone = new \DateTimeZone($source['timezone'] ?? 'UTC');
        $reader = $this->createReader($xml);
        $hasNode = $reader->read();
        while ($hasNode) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'programme') {
                $hasNode = $reader->read();

                continue;
            }
            $start = $this->parseDate((string) $reader->getAttribute('start'), $timezone);
            $end = $this->parseDate((string) $reader->getAttribute('stop'), $timezone);
            if ($reader->getAttribute('channel') === $source['id'] && $start && $end && $start >= $minDate && $start <= $maxDate && $start < $end) {
                $program = $this->createProgram($start, $end, $reader->expand());
                $hasNode = $reader->next();

                yield $program;

                continue;
            }
            // Programs are not necessarily sorted in the file
            $hasNode = $reader->next();
        }
    }

    private function createProgram(\DateTimeImmutable $start, \DateTimeImmutable $end, \DOMElement $element): Program
    {
        $program = new Program($start, $end);
        foreach ($element->attributes as $attribute) {
            if (!in_array($attribute->name, ['start', 'stop', 'channel'], true)) {
                $program->addAttribute($attribute->name, $attribute->value);
            }
        }
        foreach ($element->childNodes as $child) {
            $tag = $child instanceof \DOMElement ? $this->createTag($child) : null;
            if ($tag !== null) {
                $program->addChild($tag);
            }
        }

        return $program;
    }

    /**
     * Copies an XML element (with its attributes and children). Empty elements are ignored, unless they have attributes
     * other than lang (for example icon) or are flags (for example premiere).
     */
    private function createTag(\DOMElement $element): ?Tag
    {
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }
        $children = array_filter(iterator_to_array($element->childNodes), fn ($node) => $node instanceof \DOMElement);
        if (!empty($children)) {
            $tag = new Tag($element->nodeName, [], $attributes, $element->nodeName === 'credits' ? self::CREDITS_ORDER : []);
            $hasChild = false;
            foreach ($children as $child) {
                $childTag = $this->createTag($child);
                if ($childTag !== null) {
                    $tag->addChild($childTag);
                    $hasChild = true;
                }
            }

            return $hasChild || !empty($attributes) ? $tag : null;
        }

        $text = trim($element->textContent);
        if ($text !== '') {
            return new Tag($element->nodeName, $text, $attributes);
        }

        // lang alone does not make an empty element meaningful (<category lang="fr"></category>)
        $hasInformation = !empty(array_diff_key($attributes, ['lang' => true])) || in_array($element->nodeName, self::FLAG_ELEMENTS, true);

        return $hasInformation ? new Tag($element->nodeName, null, $attributes) : null;
    }

    /**
     * Supports XMLTV dates (20261025081917 +0000, with or without offset and seconds) and ISO 8601 dates.
     */
    private function parseDate(string $value, \DateTimeZone $defaultTimezone): ?\DateTimeImmutable
    {
        $value = trim($value);
        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, $defaultTimezone);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        try {
            return $value === '' ? null : new \DateTimeImmutable($value, $defaultTimezone);
        } catch (\Exception) {
            return null;
        }
    }
}
