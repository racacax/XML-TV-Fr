<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class TVHebdo extends AbstractProvider implements ProviderInterface
{
    private $proxy;

    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_tvhebdo.json'), $priority ?? 0.2);
        $this->proxy = ['http://kdbase.com/dreamland/browse.php?u=', '&b=24'];
        if (isset($extraParam['tvhebdo_proxy'])) {
            $this->proxy = $extraParam['tvhebdo_proxy'];
        }
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        //@todo: use datetime with timezone instead of update timezone
        $timezone = new \DateTimeZone('America/Montreal');

        if (!$this->channelExists($channel)) {
            return false;
        }
        $res1 = $this->getContentFromURL(
            $this->generateUrl($channelObj, new \DateTimeImmutable($date)),
            ['Referer' => $this->proxy]
        );
        $res1 = str_replace('href="/', 'href="http://'.explode('/', $this->proxy[0])[2].'/', $res1);
        $res1 = html_entity_decode($res1, ENT_QUOTES);
        @$res1 = explode('Mes<br>alertes courriel', $res1)[1];
        if (empty($res1)) {
            return false;
        }
        preg_match_all('/class="heure"\>(.*?)\<\/td\>/', $res1, $time);
        preg_match_all('/class="titre"\>.*?href="(.*?)"\>(.*?)\<\/a\>/', $res1, $titre);

        $t8 = json_encode($time);
        $t9 = json_encode($titre);
        $t8 = $t8.'|||||||||||||||||||||||'.$t9;
        if (strlen($t8) <= 100) {
            return false;
        }

        $count = count($titre[2]);

        $promises = [];
        $promisesResolved = 0;
        for ($j = 0;$j < $count;$j++) {
            $url = $titre[1][$j];
            $promise = $this->client->getAsync($url);
            $promise->then(function () use (&$promisesResolved, $count) {
                $promisesResolved++;
                $this->setStatus(round($promisesResolved * 100 / $count, 2).' %');
            });
            $promises[$url] = $promise;
        }
        $response = Utils::all($promises)->wait();

        for ($j = 0;$j < $count;$j++) {
            $dateStart = (new \DateTimeImmutable($date.' '.$time[1][$j], $timezone))->getTimestamp();
            $titreProgram = $titre[2][$j];
            $url = $titre[1][$j];
            $content = (string)$response[$url]->getBody();
            $infos = str_replace("\n", ' ', explode('</h4>', explode('<h4>', $content)[1] ?? '')[0]);
            $infos = explode(' - ', $infos);
            $genre = @trim($infos[0] ?? '');
            $duration = @intval(explode(' ', @trim($infos[1] ?? ''))[0]);
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
            $program = new Program($dateStart, $dateStart + $duration * 60);
            $program->addTitle($titreProgram, $lang);
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
            $img_zone = explode('<div id="dd_votes_container">', explode('<div id="dd_votes">', $content)[1] ?? '')[0];
            preg_match('/src="(.*?)"/', $img_zone, $img_url);
            $program->setIcon(@$img_url[1]);
            if (isset($rating) && strlen($rating) > 0 && strlen($rating) < 4) {
                $rating_system = \racacax\XmlTv\Component\Utils::getCanadianRatingSystem($rating, $lang);
                if (isset($rating_system)) {
                    $program->setRating($rating, $rating_system);
                }
            }
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $url = 'http://www.tvhebdo.com/horaire-tele/'.$this->channelsList[$channel->getId()].'/date/'.$date->format('Y-m-d');

        return $this->proxy[0].urlencode($url).$this->proxy[1];
    }
}
