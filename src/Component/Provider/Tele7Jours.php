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

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $tableau = [];

        $promises = [];
        for ($i = 1; $i <= 6; $i++) {
            $url = sprintf(
                $this->generateUrl($channelObj, new \DateTimeImmutable($date)),
                $i
            );
            $promises[$i] = $this->client->getAsync($url);
        }

        try {
            $response = Utils::all($promises)->wait();
        } catch (\Throwable $t) {
            return false;
        }

        $pl = 0;
        $v = 0;
        for ($i = 1; $i <= 6; $i++) {
            $get = (string)$response[$i]->getBody();
            $get = str_replace('$.la.t7.epg.grid.showDiffusions(', '', $get);
            $get = str_replace('127,101,', '', $get);
            $get = str_replace(');', '', $get);
            $get2 = $get;
            $get = json_decode($get, true);
            if (!isset($get)) {
                return false;
            }

            $pop = 0;
            if (!isset($get['grille']['aDiffusion'])) {
                return false;
            }
            foreach ($get['grille']['aDiffusion'] as $val) {
                $h = $val['heureDif'];
                $h = str_replace('h', ':', $h);
                if ($h[0] . $h[1] < $v && $i == 6) {
                    $pl += 86400;
                }
                $v = $h[0] . $h[1];
                if (strlen($val['soustitre']) > 2) {
                    $subtitle = $val['soustitre'];
                } else {
                    $subtitle = '';
                }
                $tableau[] = (strtotime($date . ' ' . $h) + $pl) . ' || ' . $val['titre'] . ' || ' . $subtitle . ' || ' . $val['nature'] . ' || ' . $val['photo'] . ' || ' . $val['saison'] . ' || ' . $val['numEpi'];
                $tableau = array_values(array_unique($tableau));
                $pop++;
            }
        }

        for ($i2 = 0; $i2 < count($tableau) - 1; $i2++) {
            $o = explode(' || ', $tableau[$i2]);
            $o2 = explode(' || ', $tableau[$i2 + 1]);

            $program = new Program($o[0], $o2[0]);
            $program->addTitle($o[1]);
            $program->addDesc('Aucune description');
            $program->addCategory($o[3]);
            if (!empty($o[2])) {
                $program->addSubtitle($o[2]);
            }
            $program->setIcon($o[4]);
            if ($o[5]) {
                if ($o[6] == '') {
                    $o[6] = '1';
                }
                $program->setEpisodeNum($o[5], $o[6]);
            }
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()];

        return 'https://www.programme-television.org/grid/tranches/' . $channel_id . '_' . $date->format('Ymd') . '_t%d.json';
    }
}
