<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;

class Tebeosud extends AbstractProvider implements ProviderInterface
{
    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct(ResourcePath::getInstance()->getChannelPath("channels_tebeosud.json"), $priority ?? 0.2);
    }

    public function constructEPG(string $channel, string $date)
    {
        parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        if ($date != date('Y-m-d')) {
            return false;
        }
        $channel_id = $this->channelsList[$channel];


        $url = "https://www.tebe$channel_id.bzh/le-programme";
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, $url);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch1, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0");
        $res1 = curl_exec($ch1);
        curl_close($ch1);
        if (count(explode('<span class="rouge">Programme</span>', $res1)) < 2) {
            return false;
        }
        $separateDays = explode('<h3 class="grid_16 titre">', $res1);
        $day = explode(' ', explode('<h2>', $res1)[1])[1];
        if ($day == date('d')) {
            $startDate = date('Y-m-d');
        } elseif ($day == date('d', strtotime("-1 days"))) {
            $startDate = date('Y-m-d', strtotime('-1 days'));
        } else {
            return false;
        }
        $programs = [];
        $count = count($separateDays);
        for ($i=0; $i<$count; $i++) {
            preg_match_all('/\<td class="date"\>\<a href="(.*?)"\>(.*?)\<\/a\>\<\/td\>/', $separateDays[$i], $infos);
            preg_match_all('/\<td class="nom"\>\<a href="(.*?)"\>(.*?)\<\/a\>\<\/td\>/', $separateDays[$i], $infos2);
            $count2 = count($infos[1]);
            for ($j=0; $j<$count2; $j++) {
                Logger::updateLine(" $i/$count : ".round($j*100/$count2, 2)." %");
                $url = $infos[1][$j];
                if ($url[0] != 'h') {
                    $url = 'https:'.$url;
                }
                $ch1 = curl_init();
                curl_setopt($ch1, CURLOPT_URL, $url);
                curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch1, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0");
                $res1 = curl_exec($ch1);
                curl_close($ch1);
                preg_match('/\<p class="description"\>(.*?)\<\/p\>/', $res1, $desc);
                preg_match('/meta property="og:image" content="(.*?)"/', $res1, $img);
                if (isset($desc[1]) && $desc[1][0] == '(') {
                    $genre = ltrim(explode(',', $desc[1])[0], '(');
                } else {
                    $genre = "Inconnu";
                }
                if (isset($img[1]) && $img[1][0] != 'h') {
                    $img[1] = 'https:'.$img[1];
                }
                $currentProgram = array("startTime"=>strtotime($startDate." ".$infos[2][$j]),
                                    "title"=>$infos2[2][$j],
                                    "desc"=>@$desc[1],
                                    "img"=>@$img[1],
                                    "genre"=>$genre
                );
                $programs[] = $currentProgram;
                if (isset($lastTime)) {
                    $program = $programs[$lastTime];
                    $programObj = $this->channelObj->addProgram($program["startTime"], $currentProgram['startTime']);
                    $programObj->addTitle($program['title']);
                    $programObj->addDesc($program['desc']);
                    $programObj->setIcon($program['img']);
                    $programObj->addCategory($program['genre']);
                }
                $lastTime = count($programs) -1;
            }
        }
        return $this->channelObj;
    }
}
