<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use \Exception;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.1 : 20/12/2023
 */
class Skweek extends AbstractProvider implements ProviderInterface
{
    private static ?string $API_KEY = null;
    private static ?string $API_VERSION = null;
    private static ?string $AUTH_TOKEN = null;
    private static ?string $nextAllowedDate = null;
    private static ?string $currentAllowedDate = null;
    private static array $cachedData = [];
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_skweek.json'), $priority ?? 0.6);
    }

    private function setAPIKey() {
        $get = $this->getContentFromURL("https://app.skweek.tv/schedule");
        preg_match('/src="(.*?)app\.js"/', $get, $app);
        $exp = explode('src="', $app[1]);
        $app[1] = end($exp);
        $get = $this->getContentFromURL("https://app.skweek.tv$app[1]app.js");
        preg_match('/API_KEY:"(.*?)"/', $get, $key);
        preg_match('/OUTPUT_FOLDER:"(.*?)"/', $get, $version);
        if(!isset($key[1])) {
            throw new Exception("Error while getting API key");
        }
        self::$API_KEY = $key[1];
        self::$API_VERSION = $version[1];
    }

    private static function isAuthTokenExpired(): bool
    {
        $json = json_decode(base64_decode(explode(".", self::$AUTH_TOKEN)[1]), true);
        return $json["exp"] < strtotime("now") + 10;

    }
    private function getAuthToken() {
        if(!isset(self::$AUTH_TOKEN) || $this->isAuthTokenExpired()) {
            if(!isset(self::$API_KEY)) {
                $this->setAPIKey();
            }
            $response = $this->client->post("https://dce-frontoffice.imggaming.com/api/v2/login/guest/checkin",
                ["headers"=>[
                    "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0",
                    "Accept" => "application/json, text/plain, */*",
                    "Accept-Language" => "fr-FR",
                    "Accept-Encoding" => "gzip, deflate, br",
                    "Referer" => "https://app.skweek.tv/",
                    "Content-Type" => "application/json",
                    "x-api-key"  => self::$API_KEY,
                    "app" => "dice",
                    "Realm" => "dce.fedcom",
                    "x-app-var" => self::$API_VERSION,
                    "Origin" => "https://app.skweek.tv",
                    "DNT" => "1",
                    "Sec-GPC" => "1",
                    "Connection" => "keep-alive",
                    "Sec-Fetch-Dest" => "empty",
                    "Sec-Fetch-Mode" => "cors",
                    "Sec-Fetch-Site" => "cross-site",
                    "Content-Length" => "0",
                    "TE" => "trailers"
                ]]);
            $data = json_decode($response->getBody()->getContents(), true);
            self::$AUTH_TOKEN = $data["authorisationToken"];
            if(!isset(self::$AUTH_TOKEN)) {
                throw new Exception("Error while getting authorisation token");
            }
        }
        return self::$AUTH_TOKEN;
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if(isset(self::$currentAllowedDate) && (self::$nextAllowedDate != $date && self::$currentAllowedDate != $date)) { # Multiple days are available in one request
            return false;
        }
        if (!$this->channelExists($channel)) {
            return false;
        }
        if(!isset(self::$cachedData[$date])) {
            $data = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)),
                [
                    "Authorization" => "Bearer " . $this->getAuthToken(),
                    "Referer" => "https://app.skweek.tv/",
                    "Realm" => "dce.fedcom",
                    "x-api-key"  => self::$API_KEY,
                    "x-app-var" => self::$API_VERSION,
                ]);
            $data = json_decode($data, true);
            if(empty($data["buckets"])) {
                return false;
            } else {
                self::$currentAllowedDate = $date;
                self::$nextAllowedDate = $data['paging']["lastSeen"];
                $times = [];
                foreach ($data["buckets"] as $bucket) {
                    foreach($bucket["contentList"] as $program) {
                        $title = $program["title"];
                        $img = $program["thumbnailUrl"];
                        $time = $program["startDate"] / 1000;
                        $endTime = $program["endDate"] / 1000;
                        $data = ["startDate" => $time, "endDate"=>$endTime,
                            "title" => $title,
                            "synopsis" => "", "img" => $img];
                        $done = false;
                        foreach($times as $key => $v) {
                            if(($v["startDate"] <= $time && $v["endDate"] >= $time)
                                || ($v["startDate"] <= ($endTime) && $v["endDate"] >= ($endTime))) {
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
                }

                $cached = [];

                foreach ($times as $time) {
                    $i = 1;
                    $cached["merged"][] = ['startDate'=> $time["startDate"],
                        "endDate"=>$time["endDate"], "synopsis"=>$this->getMergedData($time["programs"]), "title"=>"Matchs"];
                    foreach ($time["programs"] as $program) {
                        $cached["$i"][] = $program;
                        $i++;
                    }
                }
                self::$cachedData[$date] = $cached;
            }
        }
        $channelId = $this->channelsList[$channelObj->getId()];
        if(!isset(self::$cachedData[$date][$channelId])) {
            return false;
        }
        foreach (self::$cachedData[$date][$channelId] as $p) {
            $program = new Program($p["startDate"], $p["endDate"]);
            $program->addCategory("Basketball");
            $program->setIcon($p["img"]);
            $program->addTitle($p['title']);
            $program->addDesc($p['synopsis']);
            $channelObj->addProgram($program);
        }
        return $channelObj;
    }

    public static function getMergedData(array $programs) {
        $str = "Matchs :\n";
        foreach($programs as $program) {
            $str .= date("H:i", $program["startDate"])." : ".$program["title"]."\n";
        }
        return $str;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        return "https://dce-frontoffice.imggaming.com/api/v4/content/schedule?bpp=10&rpp=12&displaySectionLinkBuckets=SHOW&displayEpgBuckets=HIDE&displayEmptyBucketShortcuts=SHOW&displayContentAvailableOnSignIn=SHOW&lastSeen=".$date->format("Y-m-d")."&displayGeoblocked=SHOW&bspp=20&zo=Europe%2FParis";
    }
}
