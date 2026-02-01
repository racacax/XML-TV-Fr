<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ChannelFactory;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.1 : 07/12/2023
 */

class NouvelObs extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_nouvelobs.json'), $priority ?? 0.46);

    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = ChannelFactory::createChannel($channel);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $response = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
        $programs = explode('<table cellspacing="0" cellpadding="0" class="tab_grille">', $response);
        if (count($programs) < 3) {
            return false;
        }
        for ($i = 2; $i < count($programs); $i++) {
            $val = utf8_encode(explode('</table>', $programs[$i])[0]);
            preg_match('/<td class="logo_chaine.*?>(.*?)<\/td>/', $val, $start);
            preg_match('/line4">(.*?)</', $val, $csa);
            preg_match('/src="(.*?)"/', $val, $image);
            preg_match_all('/<div class="b_d prog1">(.*?)<\/div>/', $val, $desc);
            preg_match('/<br\/>\((.*?)\)<\/div><\/td>/', $val, $duration);
            preg_match('/class="titre b">(.*?)</', $val, $titre);
            preg_match('/prog" \/>(.*?)<br\/>/', $val, $category);
            preg_match('/<span class="b">Saison (.*?) : Episode (.*?)<\/span>/', $val, $season);
            $start = $date . ' ' . str_replace('h', ':', $start[1]);
            $program = Program::withTimestamp(strtotime($start), strtotime($start) + intval($duration[1]) * 60);

            $exp = explode('>', $category[1] ?? 'Inconnu');
            $program->addCategory(end($exp));
            $exp = explode('>', $desc[1][1]);
            $desc = end($exp);
            if (empty($desc)) {
                $desc = 'Aucune description';
            }
            $program->addDesc($desc);
            $program->addTitle($titre[1] ?? 'Aucun titre');
            if (isset($season[1])) {
                $program->setEpisodeNum($season[1], explode('/', $season[2])[0]);
            }
            if (isset($image[1])) {
                $program->addIcon(str_replace('/p/p/', '/p/g/', $image[1]));
            }
            $csa = match ($csa[1] ?? '') {
                '2' => '-10',
                '3' => '-12',
                '4' => '-16',
                '5' => '-18',
                default => 'Tout public',
            };
            $program->setRating($csa);

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channelId = $this->channelsList[$channel->getId()];
        $date = $date->format('Y-m-d');

        return "https://programme-tv.nouvelobs.com/chaine/$channelId/$date.php";
    }
}
