<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;


use racacax\XmlTv\Component\ProviderInterface;

class Tele7Jours extends AbstractProvider implements ProviderInterface
{

    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct("resources/channel_config/channels_tele7jours.json", $priority ?? 0.6);
    }

    public function constructEPG(string $channel, string $date)
    {
        parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel))
            return false;
        $channel_id = $this->channelsList[$channel];


        $pl = 0;
        $v = 0;
        for ($i = 1; $i <= 6; $i++) {
            $uu = curl_init("https://www.programme-television.org/grid/tranches/" . $channel_id . "_" . date('Ymd', strtotime($date)) . "_t" . $i . ".json");
            curl_setopt($uu, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0');
            curl_setopt($uu, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($uu, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($uu, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($uu, CURLOPT_FOLLOWLOCATION, true);
            $get = curl_exec($uu);
            curl_close($uu);
            $get = str_replace('$.la.t7.epg.grid.showDiffusions(', '', $get);
            $get = str_replace('127,101,', '', $get);
            $get = str_replace(');', '', $get);
            $get2 = $get;
            $get = json_decode($get, true);
            if (!isset($get))
                return false;

            $pop = 0;
            if(!isset($get["grille"]["aDiffusion"]))
                return false;
            foreach ($get["grille"]["aDiffusion"] as $val) {
                $h = $val["heureDif"];
                $h = str_replace('h', ':', $h);
                if ($h[0] . $h[1] < $v && $i == 6) {
                    $pl += 86400;
                }
                $v = $h[0] . $h[1];
                if (strlen($val["soustitre"]) > 2) {
                    $subtitle = $val["soustitre"];
                } else {
                    $subtitle = '';
                }
                $tableau[] = (strtotime($date . ' ' . $h) + $pl) . ' || ' . $val["titre"] . ' || ' . $subtitle . ' || ' . $val["nature"] . ' || ' . $val["photo"] . ' || ' . $val["saison"] . ' || ' . $val["numEpi"];
                $tableau = array_values(array_unique($tableau));
                $pop++;
            }
        }

        for ($i2 = 0; $i2 < count($tableau) - 1; $i2++) {
            $o = explode(' || ', $tableau[$i2]);
            $o2 = explode(' || ', $tableau[$i2 + 1]);
            $program = $this->channelObj->addProgram($o[0], $o2[0]);
            $program->addTitle($o[1]);
            $program->addDesc("Aucune description");
            $program->addCategory($o[3]);
            if(!empty($o[2])) {
                $program->addSubtitle($o[2]);
            }
            $program->setIcon($o[4]);
            if ($o[5]) {
                if ($o[6] == "") {
                    $o[6] = '1';
                }
                $program->setEpisodeNum($o[5], $o[6]);
            }
        }
        return $this->channelObj;
    }


}