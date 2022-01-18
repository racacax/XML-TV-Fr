<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class UltraNature extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_ultranature.json'), $priority ?? 0.1);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (@$this->channelsList[$channel]!='UltraNature') {
            return false;
        }
        $days = [
            'Mon'=> ['0000 || Voyage','0130 || Programme inconnu','0700 || Paysages','0830 || Animaux','1030 || Sports extrêmes','1200 || Découverte','1330 || Sports extrêmes','1530 || Découverte','1730 || Animaux','1900 || Voyage','2100 || Animaux','2300 || Sports extrêmes'],
            'Tue'=> ['0000 || Découverte','0130 || Programme inconnu','0700 || Paysages','0830 || Découverte','1030 || Voyage','1200 || Animaux','1330 || Animaux','1530 || Sports extrêmes','1730 || Sports extrêmes','1900 || Découverte','2100 || Découverte','2300 || Voyage'],
            'Wed'=> ['0000 || Animaux','0130 || Programme inconnu','0700 || Paysages','0830 || Sports extrêmes','1030 || Découverte','1200 || Sports extrêmes','1330 || Découverte','1530 || Animaux','1730 || Voyage','1900 || Animaux','2100 || Sports extrêmes','2300 || Découverte'],
            'Thu'=> ['0000 || Sports extrêmes','0130 || Programme inconnu','0700 || Paysages','0830 || Voyage','1030 || Découverte','1200 || Animaux','1330 || Sports extrêmes','1530 || Découverte','1730 || Animaux','1900 || Sports extrêmes','2100 || Voyage','2300 || Découverte'],
            'Fri'=> ['0000 || Animaux','0130 || Programme inconnu','0700 || Paysages','0830 || Découverte','1030 || Sports extrêmes','1200 || Découverte','1330 || Voyage','1530 || Animaux','1730 || Sports extrêmes','1900 || Animaux','2100 || Découverte','2300 || Sports extrêmes'],
            'Sat'=> ['0000 || Découverte','0130 || Programme inconnu','0700 || Paysages','0830 || Animaux','1030 || Animaux','1200 || Sports extrêmes','1330 || Découverte','1530 || Sports extrêmes','1730 || Voyage','1900 || Découverte','2100 || Animaux','2300 || Animaux'],
            'Sun'=> ['0000 || Sports extrêmes','0130 || Programme inconnu','0700 || Paysages','0830 || Sports extrêmes','1030 || Découverte','1200 || Voyage','1330 || Animaux','1530 || Animaux','1730 || Découverte','1900 || Sports extrêmes','2100 || Sports extrêmes','2300 || Animaux']
        ];
        $d = $days[date('D', strtotime($date))];
        $date2 = date('Ymd', strtotime($date));
        for ($j=0;$j<count($d);$j++) {
            $st = explode(' || ', $d[$j]);
            if (isset($d[$j+1])) {
                $st2 = explode(' || ', $d[$j + 1])[0];
            } else {
                $st2 = '0000';
            }
            $d1 = $date2.$st[0].'00 '.date('O');
            if ($st2!='0000') {
                $d2 = $date2 . $st2 . '00 ' . date('O');
            } else {
                $d2 = date('Ymd', strtotime($date)+86400) . $st2 . '00 ' . date('O');
            }
            $program = new Program(strtotime($d1), strtotime($d2));
            $program->addTitle($st[1]);
            $program->addDesc('Aucune description');
            $program->addCategory($st[1]);

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        return 'UltraNature';
    }
}
