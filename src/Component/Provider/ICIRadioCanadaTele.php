<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.1 : 18/12/2021
 */
class ICIRadioCanadaTele extends AbstractProvider implements ProviderInterface
{
    private static array $HEADERS = [
        'Host' => 'services.radio-canada.ca',
        'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:145.0) Gecko/20100101 Firefox/145.0',
        'Accept' => '*/*',
        'Accept-Language' => 'fr-FR,fr-CA;q=0.8,en;q=0.5,en-US;q=0.3',
        'Accept-Encoding' => 'gzip, deflate, br, zstd',
        'Referer' => 'https://ici.radio-canada.ca/',
        'Origin' => 'https://ici.radio-canada.ca',
        'Connection' => 'keep-alive',
        'Sec-Fetch-Dest' => 'empty',
        'Sec-Fetch-Mode' => 'no-cors',
        'Sec-Fetch-Site' => 'same-site',
        'Content-Type' => 'application/json',
        'X-Requested-With' => 'appTele-vcinq@19.3.4-node@v22.11.0',
        'Priority' => 'u=4'
    ];
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_iciradiocanada.json'), $priority ?? 0.65);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $dateObj = new \DateTimeImmutable($date);
        $jsonPreviousDay = json_decode($this->getContentFromURL($this->generateUrl($channelObj, $dateObj->modify('-1 day')), self::$HEADERS), true);
        $json = json_decode($this->getContentFromURL($this->generateUrl($channelObj, $dateObj), self::$HEADERS), true);

        if (!isset($json['data']['broadcasts'])) {
            return false;
        }
        $programs = array_merge(@$jsonPreviousDay['data']['broadcasts'] ?? [], $json['data']['broadcasts']);

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($programs as $index => $broadcast) {
            $startDate = new \DateTimeImmutable('@'.strtotime($broadcast['startsAt']));
            if ($startDate < $minDate) {
                continue;
            } elseif ($startDate > $maxDate) {
                return $channelObj;
            }
            $program = Program::withTimestamp(strtotime($broadcast['startsAt']), strtotime($programs[$index + 1]['startsAt']));
            $program->addCategory($broadcast['subtheme']);
            $program->setIcon(str_replace('{0}', '635', str_replace('{1}', '16x9', @$broadcast['image']['url'] ?? '')));
            $program->addTitle($broadcast['title']);
            $program->addDesc(strip_tags($broadcast['descriptionHtml'] ?? 'Aucune description'));
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];
        $formattedDate = $date->format('Y-m-d');

        return  "https://services.radio-canada.ca/bff/tele/graphql?opname=getBroadcasts&extensions=%7B%22persistedQuery%22%3A%7B%22version%22%3A1%2C%22sha256Hash%22%3A%22c3f32e9e14b027abb59011e5e9f8ac0a2c8554889b68cbe1e7879c74fa1c7679%22%7D%7D&variables=%7B%22params%22%3A%7B%22date%22%3A%22$formattedDate%22%2C%22regionId%22%3A$channel_id%7D%7D";

    }
}
