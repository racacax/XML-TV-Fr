<?php
declare(strict_types=1);

namespace racacax\XmlTv\Provider;

use racacax\XmlTv\Component\AbstractProvider;
use racacax\XmlTv\Component\ProviderInterface;

class Telecablesat extends AbstractProvider implements ProviderInterface
{

    private static $cache = []; // multiple channels are on the same page
    private static $BASE_URL = "https://tv-programme.telecablesat.fr";
    private $loopCounter = 0;
    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct("resources/channel_config/channels_telecablesat.json", $priority ?? 0.55);
    }

    function constructEPG($channel, $date)
    {
        parent::constructEPG($channel, $date);
        if(!$this->channelExists($channel))
        {
            return false;
        }
        $channel_content = $this->getChannelsList()[$channel];
        $page = $channel_content['page'];
        $channel_id = $channel_content['id'];
        $channel_url = "https://tv-programme.telecablesat.fr/programmes-tele/?date=$date&page=$page";
        if(!isset(self::$cache[md5($channel_url)])) {
            $res1 = $this->getContentFromURL($channel_url);
            if(empty($res1)) {
                $this->loopCounter++;
                if($this->loopCounter > 3)
                    return false;
                displayTextOnCurrentLine(" \e[31mRate limited, waiting 30s ($this->loopCounter)\e[39m");
                sleep(30);
                return $this->constructEPG($channel, $date);
            }
            self::$cache[md5($channel_url)] = $res1;
        }
        $this->loopCounter = 0;
        $content = self::$cache[md5($channel_url)];
        preg_match_all('/logos_chaines\/(.*?).png" title="(.*?)"/', $content, $channels);
        $channel_index = array_search($channel_id, $channels[1]);
        if($channel_index>=0) {
            $channel_content = @explode('<div class="row">', explode("<div class='paging'>", $content)[0])[$channel_index+1];
            if(isset($channel_content))  {
                preg_match_all('/data-start="(.*?)" data-end="(.*?)"/', $channel_content, $times);
                preg_match_all('/data-src="(.*?)"/', $channel_content, $imgs);
                preg_match_all('/<div class="hour-type">.*?<\/span>(.*?)<\/div>.*?<span class="title">(.*?)<\/span>/', $channel_content, $genresAndTitles);
                preg_match_all('/class="link" href="(.*?)"/', $channel_content, $links);
                $count = count($times[1]);
                if(count($imgs[1]) != $count || count($genresAndTitles[1]) != $count || count($links[1]) != $count)
                    return false;
                $retry_counter = 0;
                for($i=0; $i<$count; $i++) {
                    displayTextOnCurrentLine(" ".round($i*100/$count, 2)." %");
                    $program = $this->channelObj->addProgram(intval($times[1][$i]), intval($times[2][$i]));
                    $program->addTitle(trim($genresAndTitles[2][$i]));
                    $program->addCategory(trim($genresAndTitles[1][$i]));
                    $program->setIcon("https:".$imgs[1][$i]);
                    $content = $this->getContentFromURL(self::$BASE_URL.$links[1][$i]);
                    if(empty($content)) {
                        $retry_counter++;
                        if($retry_counter > 3) {
                            $retry_counter = 0;
                            continue;
                        }
                        $this->channelObj->popLastProgram();
                        displayTextOnCurrentLine(" \e[31mRate limited, waiting 30s ($retry_counter)\e[39m");
                        sleep(30); // if we are rate limited by website
                        $i--;
                        continue;
                    }
                    $content = explode('<div class="top-menu">', $content)[1];
                    $content = explode('<h2>Prochains épisodes</h2>', $content)[0];
                    $content = str_replace("<br>","\n", $content);
                    $content = str_replace("<br />","\n", $content);
                    preg_match('/class="age-(.*?)"/', $content, $csa);
                    if(isset($csa[1])) {
                        $program->setRating("-".$csa[1]);
                    }
                    preg_match('/itemprop="episodeNumber">(.*?)<\/span>/s', $content, $season);
                    preg_match('/<\/span>\\((.*?)\/<span itemprop="numberOfEpisodes">/s', $content, $episode);
                    $program->setEpisodeNum(@$season[1],@$episode[1]);
                    $critique = @explode('<h2>Critique</h2>', $content)[1];
                    preg_match("/<p>(.*?)</s", $critique, $critique);
                    $resume = @explode('<h2>Résumé</h2>', $content)[1];
                    preg_match("/<p>(.*?)<\/p>/s", $resume, $resume);
                    preg_match('/<h2 class="subtitle">(.*?)<\/h2>/s', $content, $subtitle);
                    preg_match('/itemprop="director">(.*?)<\/span>/s', $content, $directors);
                    preg_match_all('/span itemprop="actor">(.*?)<\/span>(.*?)</s', $content, $actors);
                    preg_match('/<div class="label w40">.*?Présentateur.*?<\/div>.*?<div class="text w60">(.*?)<\/div>/s', $content, $presenter);
                    preg_match('/<div class="overlayerpicture">.*?<img class="lazy" alt=".*?" data-src="(.*?)"/s', $content, $imgs);
                    if(isset($subtitle[1]))
                        $program->addSubtitle($subtitle[1]);
                    if(isset($imgs[1]))
                        $program->setIcon("https:".$imgs[1]);
                    $desc = '';
                    if(isset($resume[1])) {
                        $desc.=trim($resume[1])."\n\n";
                    }
                    if(isset($critique[1])) {
                        $desc.="Critique : ".trim($critique[1])."\n\n";
                    }
                    if(isset($directors[1])) {
                        $directors_split = explode(',', $directors[1]);
                        $desc.= "Réalisateur(s) : ".trim($directors[1])."\n";
                        foreach($directors_split as $director) {
                            $program->addCredit(trim($director), "director");
                        }
                    }
                    if(isset($presenter[1])) {
                        $presenter_split = explode(',', $presenter[1]);
                        $desc.= "Présentateur(s) : ".trim($presenter[1])."\n";
                        foreach($presenter_split as $presenter) {
                            $program->addCredit(trim($presenter), "presenter");
                        }
                    }
                    if(!empty($actors[1])) {
                        $desc.="Acteurs : ";
                        for($j=0; $j<count($actors[1]); $j++) {
                            $program->addCredit(trim($actors[1][$j]), "actor");
                            $desc.=trim($actors[1][$j].$actors[2][$j])." ";
                        }
                        $desc.="\n";
                    }
                    $program->addDesc($desc);
                    $retry_counter = 0;
                }
            }

        }
        return $this->channelObj;
    }
}