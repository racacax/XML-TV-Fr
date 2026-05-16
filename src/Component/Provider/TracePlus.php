<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ChannelFactory;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class TracePlus extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_traceplus.json'), $priority ?? 0.4);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = ChannelFactory::createChannel($channel);
        if (!$this->channelExists($channel)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $url = $this->generateUrl((string) $this->channelsList[$channel]);
        $content = $this->getContentFromURL($url, [], false, false);

        if (empty($content)) {
            return false;
        }

        // Strip BOM and default namespace to simplify SimpleXML access
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = str_replace(' xmlns="http://www.xmltv.org/xmltv"', '', $content);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
        if ($xml === false) {
            return false;
        }

        foreach ($xml->programme as $programme) {
            $startStr = (string) $programme['start'];
            $stopStr = (string) $programme['stop'];

            try {
                $startTime = new \DateTimeImmutable($startStr);
                $endTime = new \DateTimeImmutable($stopStr);
            } catch (\Throwable) {
                continue;
            }

            if ($startTime < $minDate) {
                continue;
            } elseif ($startTime > $maxDate) {
                break;
            }

            $program = new Program($startTime, $endTime);

            $title = $this->getLangValue($programme->title, 'FR')
                ?? $this->getLangValue($programme->title, 'EN')
                ?? 'Aucun titre';
            $program->addTitle($title);

            $desc = $this->getLangValue($programme->desc, 'FR')
                ?? $this->getLangValue($programme->desc, 'EN');
            if (!empty($desc)) {
                $program->addDesc($desc);
            }

            $category = $this->getLangValue($programme->category, 'FR')
                ?? $this->getLangValue($programme->category, 'EN');
            if (!empty($category)) {
                $program->addCategory($category);
            }

            foreach ($programme->{'episode-num'} as $episodeNum) {
                $epNum = (string) $episodeNum;
                if (ctype_digit($epNum)) {
                    $program->setEpisodeNum(0, (int) $epNum);

                    break;
                }
            }

            if (isset($programme->icon) && isset($programme->icon['src'])) {
                $iconSrc = (string) $programme->icon['src'];
                if (!empty($iconSrc)) {
                    $program->addIcon(str_replace('/test/', '/', $iconSrc));
                }
            }

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(string $slug): string
    {
        return 'https://filesapp.trace.tv/files/epg/export/' . $slug . '/epg.xml';
    }

    private function getLangValue(\SimpleXMLElement $elements, string $lang): ?string
    {
        foreach ($elements as $element) {
            if (strtoupper((string) $element['lang']) === strtoupper($lang)) {
                $value = trim((string) $element);

                return !empty($value) ? $value : null;
            }
        }

        return null;
    }
}
