<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class SixPlay extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_6play.json'), $priority ?? 0.2);
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $page = 1;
        $channelId = $this->channelsList[$channelObj->getId()];
        while(!is_null($page)) {
            $json = json_decode($this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date), $page)), true);
            if (empty($json[$channelId])) {
                return false;
            }
            if(count($json[$channelId]) < 100) {
                $page = null;
            } else {
                $page++;
            }
            foreach ($json[$channelId] as $program) {
                $genre = 'Inconnu';

                if(isset($program['csa']) && $program['csa']['age'] > 0) {
                    $csa = strval(-$program['csa']['age']);
                } else {
                    $csa = 'Tout public';
                }

                $image = null;
                foreach($program['images'] as $im) {
                    if($im['role'] == 'vignette') {
                        $image = 'https://images.6play.fr/v2/images/'.$im['id'].'/raw';

                        break;
                    }
                }
                $programObj = new Program(strtotime($program['real_diffusion_start_date']), strtotime($program['real_diffusion_end_date']));
                $programObj->addTitle($program['title']);
                $programObj->addSubtitle(@$program['subtitle']);
                $programObj->addDesc(@$program['description']);
                $programObj->addCategory($genre);
                $programObj->setIcon($image);
                $programObj->setRating($csa);
                $channelObj->addProgram($programObj);
            }
        }


        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date, int $page = 0): string
    {
        $channelId = $this->channelsList[$channel->getId()];
        $startTime = $date->format('Y-m-d 00:00:00');
        $endTime = $date->modify('+1 days')->format('Y-m-d 23:59:59');
        $offset = 100 * ($page - 1);

        return "https://pc.middleware.6play.fr/6play/v2/platforms/m6group_web/services/m6replay/guidetv?channel=$channelId&from=$startTime&offset=$offset&limit=100&to=$endTime&with=realdiffusiondates";
    }
}
