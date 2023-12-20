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
 * @version 0.1 : 20/12/2023
 */
class AmazonPrime extends AbstractProvider implements ProviderInterface
{
    private static $cachedEpg = null;
    private static $REPLACABLE_MONTHS = ["janv" => "01", "févr"=>"02", "mars"=>"03", "avr"=>"04", "mai"=>"05", "juin"=>"06", "juill"=>"07", "aout"=>"08", "août"=>"08", "sept"=>"09", "oct"=>"10", "nov"=>"11", "déc"=>"12"];
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_amazonprime.json'), $priority ?? 0.6);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if($date != date('Y-m-d')) { # We only gather EPG in one Go
            return false;
        }
        if (!$this->channelExists($channel)) {
            return false;
        }
        if(!isset(self::$cachedEpg)) {
            self::$cachedEpg = [];
            $get = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
            $get = explode('<p>Le Pass Ligue 1: Événements en direct et à venir</p>', $get);
            if(empty($get)) {
                return false;
            }
            $get = explode('</ul>', $get[1])[0];
            $programs = explode('<li ', $get);
            unset($programs[0]);
            $times = [];
            foreach($programs as $program) {
                preg_match('/aria-label="(.*?)"/', $program, $title);
                preg_match('/href="(.*?)"/', $program, $detail);
                $title = $title[1];
                $detail = $this->getContentFromURL("https://www.primevideo.com".$detail[1]);
                preg_match('/"pageDateTimeBadge":"(.*?)"/', $detail, $time);
                preg_match('/"location":"(.*?)"/', $detail, $location);
                preg_match('/"synopsis":"(.*?)"/', $detail, $synopsis);
                preg_match('/srcSet="(.*?)"/', $detail, $img);
                $duration = (str_contains($title, "Multiplex")) ? 10800 : 8400;
                $time[1] = str_replace(".", "", $time[1]);
                foreach (self::$REPLACABLE_MONTHS as $m => $v) {
                    $time[1] = str_replace(" $m ", "-$v-", $time[1]);
                }
                $time = strtotime($time[1]);
                $data = ["startDate" => $time, "endDate"=>$time + $duration,
                    "title" => $title, "location"=>$location[1],
                    "synopsis" => $synopsis[1], "img" => explode(" ", $img[1])[0]];
                $done = false;
                foreach($times as $key => $v) {
                    if(($v["startDate"] <= $time && $v["endDate"] >= $time) || ($v["startDate"] <= ($time + $duration) && $v["endDate"] >= ($time + $duration))) {
                        $v["programs"][] = $data;
                        if($v["startDate"] > $data["startDate"]) {
                            $v["startDate"] = $data["startDate"];
                        }
                        if($v["endDate"] < $data["endDate"]) {
                            $v["endDate"] = $data["endDate"];
                        }
                        $times[$key] = $v;
                        $done = true;
                        break;
                    }
                }
                if(!$done) {
                    $times[] = ["startDate"=>$data["startDate"], "endDate"=>$data["endDate"], "programs"=>[$data]];
                }
            }


            foreach ($times as $time) {
                $i = 1;
                self::$cachedEpg["merged"][] = ['startDate'=> $time["startDate"],
                    "endDate"=>$time["endDate"], "synopsis"=>$this->getMergedData($time["programs"]), "title"=>"Matchs"];
                foreach ($time["programs"] as $program) {
                    if(str_contains($program["title"], "Multiplex")) {
                        $program["synopsis"] .= chr(10).($this->getMergedData($time["programs"]));
                        if(!isset(self::$cachedEpg["multiplex"])) {
                            self::$cachedEpg["multiplex"] = [];
                        }
                        self::$cachedEpg["multiplex"][] = $program;
                    } else {
                        self::$cachedEpg["$i"][] = $program;
                        $i++;
                    }
                }
            }
        }
        $channelId = $this->channelsList[$channelObj->getId()];
        if(!isset(self::$cachedEpg[$channelId])) {
            return false;
        }
        foreach (self::$cachedEpg[$channelId] as $p) {
            $program = new Program($p["startDate"], $p["endDate"]);
            $program->addCategory("Football");
            $program->setIcon($p["img"]);
            $program->addTitle($p['title']);
            $program->addSubtitle($p['location']);
            $program->addDesc($p['synopsis']);
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public static function getMergedData(array $programs) {
        $str = "Matchs :\n";
        foreach($programs as $program) {
            if(!str_contains($program["title"], "Multiplex")) {
                $str .= date("H:i", $program["startDate"])." : ".$program["title"]." au ".$program["location"]."\n";
            }
        }
        return $str;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        return "https://www.primevideo.com/-/fr/storefront/ref=atv_live_hom_c_9zZ8D2_hm_sports?language=fr_FR&contentType=home&contentId=Sports";
    }
}
