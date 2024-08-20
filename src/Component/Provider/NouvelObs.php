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

    public function constructEPG(string $channel, string $date)
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
            $program = new Program(strtotime($start), strtotime($start) + intval($duration) * 60);

            $exp = explode('>', $category[1] ?? 'Inconnu');
            $program->addCategory(end($exp));
            $exp = explode('>', $desc[1][1]);
            $desc = end($exp);
            if(empty($desc)) {
                $desc = 'Aucune description';
            }
            $program->addDesc($desc);
            $program->addTitle($titre[1] ?? 'Aucun titre');
            if (isset($season[1])) {
                $program->setEpisodeNum($season[1], explode('/', $season[2])[0]);
            }
            if (isset($image[1])) {
                $program->setIcon(str_replace('/p/p/', '/p/g/', $image[1]));
            }
            switch ($csa[1] ?? '') {
                case '2':
                    $csa = '-10';

                    break;
                case '3':
                    $csa = '-12';

                    break;
                case '4':
                    $csa = '-16';

                    break;
                case '5':
                    $csa = '-18';

                    break;
                default:
                    $csa = 'Tout public';

                    break;
            }
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
