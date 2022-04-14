<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class ViniPF extends AbstractProvider implements ProviderInterface
{
    private static $cache_per_day = []; // ViniPF send all channels data for two hours. No need to request for every channel

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_vinipf.json'), $priority ?? 0.4);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        //@todo check
        $timezone = new \DateTimeZone('Pacific/Tahiti');
        $datetime = new \DateTimeImmutable($date.' 14:00:00', $timezone);

        $promises = [];
        $count = 12;
        for ($i=0; $i <$count; $i++) {
            $currentDate = $datetime->modify(sprintf('+%d hours', $i*2));
            $dateDebut = '{"dateDebut":"'.$currentDate->format('c').'"}';
            if (!isset(self::$cache_per_day[md5($dateDebut)])) {
                $promises[] = $this->client->postAsync(
                    $this->generateUrl($channelObj, new \DateTimeImmutable($date)),
                    [
                        'body'=>$dateDebut
                    ]
                )->then(function (Response $response) use ($dateDebut) {
                    self::$cache_per_day[md5($dateDebut)] = json_decode((string)$response->getBody(), true);

                    return $response;
                });
            }
        }
        Utils::all($promises)->wait();
        $count = 1;
        foreach(self::$cache_per_day as $cacheData) {
            Logger::updateLine(' '.round(count(self::$cache_per_day)*100/$count++, 2).' %');
            foreach ($cacheData['programmes'] as $viniChannel) {
                if ($viniChannel['nid'] == $this->channelsList[$channel]) {
                    foreach ($viniChannel['programmes'] as $programme) {
                        $program = new Program($programme['timestampDeb'], $programme['timestampFin']);
                        $program->addTitle($programme['titreP']);
                        $program->addSubtitle($programme['legendeP']);
                        $program->addDesc($programme['desc']);
                        $program->setIcon($programme['srcP']);
                        $program->addCategory($programme['categorieP']);

                        $channelObj->addProgram($program);
                    }
                }
            }
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        return 'https://programme-tv.vini.pf/programmesJSON';
    }
}
