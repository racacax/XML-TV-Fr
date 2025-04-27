<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class Tele7Jours extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tele7jours.json'), $priority ?? 0.6);
    }

    private function getProgramList(array $response, string $date): array
    {
        $programList = [];
        $lastIndex = -1;
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $currentCursor = (new \DateTimeImmutable($date))->modify('-1 day')->modify('+4 hours');
        for ($i = 0; $i <= 6; $i++) {
            $content = (string)$response[$i]->getBody();
            $content = str_replace('$.la.t7.epg.grid.showDiffusions(', '', $content);
            $content = str_replace('127,101,', '', $content);
            $content = str_replace(');', '', $content);
            $json = json_decode($content, true);
            if (!isset($json)) {
                return $programList;
            }
            if (!isset($json['grille']['aDiffusion'])) {
                return $programList;
            }
            foreach ($json['grille']['aDiffusion'] as $val) {
                [$hour, $minute] = explode('h', $val['heureDif']);
                $startDate = $currentCursor->setTime(intval($hour), intval($minute));
                if ($startDate < $currentCursor) {
                    $startDate = $startDate->modify('+1 day');
                }
                $currentCursor = $startDate;
                if ($lastIndex > -1) {
                    $programList[$lastIndex]['endTime'] = $startDate->getTimestamp();
                }
                if ($startDate < $minDate) {
                    continue;
                } elseif ($startDate > $maxDate) {
                    return $programList;
                }
                $val['startTime'] = $startDate->getTimestamp();
                $programList[] = $val;
                $lastIndex = $lastIndex + 1;
            }
        }

        return $programList;
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $promises = [];
        $currentDate = new \DateTimeImmutable($date);
        $url = sprintf(
            $this->generateUrl($channelObj, $currentDate->modify('-1 day')),
            6
        );
        $promises[0] = $this->client->getAsync($url);
        for ($i = 1; $i <= 6; $i++) {
            $url = sprintf(
                $this->generateUrl($channelObj, $currentDate),
                $i
            );
            $promises[$i] = $this->client->getAsync($url);
        }

        try {
            $response = Utils::all($promises)->wait();
        } catch (\Throwable $t) {
            return false;
        }

        $programList = $this->getProgramList($response, $date);
        foreach ($programList as $program) {
            $programObj = Program::withTimestamp($program['startTime'], $program['endTime']);
            $programObj->addTitle($program['titre']);
            $programObj->addCategory($program['nature']);
            $programObj->addSubtitle($program['soustitre']);
            if ($program['numEpi'] > 0) {
                $programObj->setEpisodeNum($program['saison'], $program['numEpi']);
            }
            $programObj->setIcon($program['photo']);
            foreach ($program['participant'] ?? [] as $categoryParticipant) {
                foreach ($categoryParticipant ?? [] as $participant) {
                    $programObj->addCredit($participant);
                }
            }
            $channelObj->addProgram($programObj);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];

        return 'https://www.programme-television.org/grid/tranches/' . $channel_id . '_' . $date->format('Ymd') . '_t%d.json';
    }
}
