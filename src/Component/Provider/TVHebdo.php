<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class TVHebdo extends AbstractProvider implements ProviderInterface
{
    private mixed $proxy;
    private bool $enableDetails;
    private DateTimeZone $timezone;


    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tvhebdo.json'), $priority ?? 0.2);
        $this->proxy = ['http://kdbase.com/dreamland/browse.php?u=', '&b=24'];
        if (isset($extraParam['tvhebdo_proxy'])) {
            $this->proxy = $extraParam['tvhebdo_proxy'];
        }
        $this->enableDetails = $extraParam['tvhebdo_enable_details'] ?? true;
        $this->timezone = new \DateTimeZone('America/Montreal');
    }

    public function getDataPerDay(Channel $channelObj, \DateTimeImmutable $dateObj)
    {
        $content = $this->getContentFromURL(
            $this->generateUrl($channelObj, $dateObj),
            ['Referer' => $this->proxy]
        );
        $content = str_replace('href="/', 'href="http://'.explode('/', $this->proxy[0])[2].'/', $content);
        $content = html_entity_decode($content, ENT_QUOTES);
        @$content = explode('Mes<br>alertes courriel', $content)[1];
        if (empty($content)) {
            return [];
        }
        preg_match_all('/class="heure"\>(.*?)\<\/td\>/', $content, $time);
        preg_match_all('/class="titre"\>.*?href="(.*?)"\>(.*?)\<\/a\>/', $content, $titlesAndUrls);
        if (count($time[1]) != count($titlesAndUrls[1])) {
            return [];
        }
        $data = [];
        for ($i = 0; $i < count($titlesAndUrls[1]); $i++) {
            $startDate = new \DateTimeImmutable($dateObj->format('Y-m-d').' '.$time[1][$i], $this->timezone);
            $data[] = ['startDate' => $startDate, 'title' => $titlesAndUrls[2][$i], 'url' => $titlesAndUrls[1][$i]];
        }

        return $data;
    }
    public function fetchPrograms(Channel $channelObj, string $date)
    {
        $dateObj = new \DateTimeImmutable($date);
        $dateDayBefore = $dateObj->modify('-1 day');
        $data = array_merge($this->getDataPerDay($channelObj, $dateDayBefore), $this->getDataPerDay($channelObj, $dateObj));
        $programsWithDetailUrl = [];
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        for ($i = 0; $i < count($data); $i++) {
            if ($data[$i]['startDate'] < $minDate) {
                continue;
            } elseif ($data[$i]['startDate'] > $maxDate) {
                return $programsWithDetailUrl;
            }
            $program = new Program($data[$i]['startDate'], @$data[$i + 1]['startDate'] ?? ($data[$i]['startDate']->modify('+1 hour')));
            $program->addTitle($data[$i]['title']);
            $programsWithDetailUrl[] = [$program, $data[$i]['url']];
            $channelObj->addProgram($program);
        }

        return $programsWithDetailUrl;
    }

    public function fillProgramDetails(Program $program, string $content)
    {
        try {
            $infos = str_replace("\n", ' ', explode('</h4>', explode('<h4>', $content)[1] ?? '')[0]);
            $infos = explode(' - ', $infos);
            $genre = @trim($infos[0] ?? '');
            $lang = @trim(strtolower($infos[2] ?? ''));
            $potentialYear = @strval(intval(@trim($infos[3] ?? '')));
            if (@trim($infos[3] ?? '') == $potentialYear) {
                $year = $potentialYear;
            } else {
                $rating = @trim($infos[3] ?? '');
            }
            if (isset($infos[4])) {
                $potentialYear = @intval(@trim($infos[4]));
                if ($potentialYear > 0) {
                    $year = $potentialYear;
                }
            }
            $desc = @explode('</p>', explode('<p id="dd_desc">', $content)[1] ?? '')[0];
            if (isset($year)) {
                $desc .= "\n\nAnnée : ".$year;
            }
            $intervenants = @explode('</p>', explode('<p id="dd_inter">', $content)[1] ?? '')[0];
            $desc = str_replace('<br />', "\n", $desc.$intervenants);
            $tmp_desc = '';
            $splited_desc = explode("\n", $desc);
            foreach ($splited_desc as $line) {
                $tmp_desc .= @trim($line)."\n";
            }
            $desc = $tmp_desc;
            $program->addDesc($desc, $lang);
            $program->addCategory($genre, $lang);
            $current_role = 'guest';
            $intervenants_split = explode('<br />', $intervenants);
            foreach ($intervenants_split as $line) {
                $line = @trim($line);
                if ($line == 'Réalisation :') {
                    $current_role = 'director';
                } elseif (strpos($line, ':') !== false) {
                    $current_role = 'guest';
                } else {
                    $program->addCredit($line, $current_role);
                }
            }
            $program->addIcon('https://i.imgur.com/5CHM14O.png');
            if (isset($rating) && strlen($rating) > 0 && strlen($rating) < 4) {
                $rating_system = \racacax\XmlTv\Component\Utils::getCanadianRatingSystem($rating, $lang);
                if (isset($rating_system)) {
                    $program->setRating($rating, $rating_system);
                }
            }
        } catch (\Throwable) {
            // We allow details to fail
        }
    }
    public function fillDetails(array $programsWithDetailUrl)
    {
        $count = count($programsWithDetailUrl);
        $promises = [];
        $promisesResolved = 0;
        for ($j = 0;$j < $count;$j++) {
            [$program, $url] = $programsWithDetailUrl[$j];
            $promise = $this->client->getAsync($url);
            $promise->then(function ($response) use (&$promisesResolved, $count, $program) {
                $promisesResolved++;
                $this->setStatus(round($promisesResolved * 100 / $count, 2).' %');
                $this->fillProgramDetails($program, $response->getBody()->getContents() ?? '');
            });
            $promises[$url] = $promise;
        }
        Utils::all($promises)->wait();
    }
    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $programsWithDetailUrl = $this->fetchPrograms($channelObj, $date);
        if ($this->enableDetails) {
            $this->fillDetails($programsWithDetailUrl);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $url = 'http://www.tvhebdo.com/horaire-tele/'.$this->channelsList[$channel->getId()].'/date/'.$date->format('Y-m-d');

        return $this->proxy[0].urlencode($url).$this->proxy[1];
    }
}
