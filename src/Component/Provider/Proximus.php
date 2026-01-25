<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use Exception;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;
use racacax\XmlTv\Component\Utils;

class Proximus extends AbstractProvider implements ProviderInterface
{
    private static ?string $VERSION;
    private static array $HEADERS = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
        'Accept: */*',
        'Accept-Language: fr-FR,fr-CA;q=0.8,en;q=0.5,en-US;q=0.3',
        'Origin: https://www.pickx.be',
        'Connection: keep-alive',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: no-cors',
        'Sec-Fetch-Site: cross-site',
        'TE: trailers',
        'Pragma: no-cache',
        'Cache-Control: no-cache',
        'Referer: https://www.pickx.be/'];

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_proximus.json'), $priority ?? 0.59);
    }

    private function getVersion()
    {
        if (!isset(self::$VERSION)) {
            $content = Utils::getContent('https://www.pickx.be/fr/television/programme-tv', self::$HEADERS);
            $hash = explode('"', explode('"hashes":["', $content)[1])[0];
            self::$VERSION = @json_decode(Utils::getContent("https://www.pickx.be/api/s-$hash", self::$HEADERS), true)['version'];
            if (!isset(self::$VERSION)) {
                throw new Exception('No access to Proximus API');
            }
        }

        return self::$VERSION;
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $get = Utils::getContent($this->generateUrl($channelObj, new \DateTimeImmutable($date)), self::$HEADERS);

        $json = json_decode($get, true);

        $programs = $json;

        if (empty($programs)) {
            return false;
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($programs as $program) {
            $startDate = new \DateTimeImmutable('@'.strtotime($program['programScheduleStart']));
            if ($startDate < $minDate) {
                continue;
            } elseif ($startDate > $maxDate) {
                break;
            }
            if (!empty($program['program']['VCHIP'])) {
                $csa = match ($program['program']['VCHIP']) {
                    '10' => '-10',
                    '12' => '-12',
                    '16' => '-16',
                    '18' => '-18',
                    default => 'Tout public',
                };
            } else {
                $csa = 'Tout public';
            }
            $programObj = Program::withTimestamp(strtotime($program['programScheduleStart']), strtotime($program['programScheduleEnd']));
            $programObj->addTitle($program['program']['title'] ?? 'Aucun titre');
            $programObj->addDesc(@$program['program']['description'] ?? 'Aucune description');
            $programObj->addCategory($this->formatCategory(@$program['category'] ?? 'Inconnu'));
            $programObj->addCategory($this->formatCategory(@$program['subCategory'] ?? 'Inconnu'));
            if (isset($program['program']['posterFileName'])) {
                $programObj->addIcon('https://experience-cache.cdi.streaming.proximustv.be/posterserver/poster/EPG/' . $program['program']['posterFileName']);
            }
            $programObj->setRating($csa);

            $channelObj->addProgram($programObj);
        }

        return $channelObj;
    }

    private function formatCategory(string $category)
    {
        $split = explode('C.', $category);

        return end($split);
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->getChannelsList()[$channel->getId()];

        return 'https://px-epg.azureedge.net/airings/' . $this->getVersion() . '/' . $date->format('Y-m-d') . '/channel/' . $channelId . '?timezone=Europe%2FParis';
    }
}
