<?php
declare(strict_types=1);

namespace racacax\XmlTv\Provider;

use racacax\XmlTv\Component\AbstractProvider;
use racacax\XmlTv\Component\ProviderInterface;

class TVHebdo extends AbstractProvider implements ProviderInterface
{

    private $proxy;

    public function __construct(?float $priority = null, array $extraParam = [])
    {
        parent::__construct("resources/channel_config/channels_tvhebdo.json", $priority ?? 0.2);
        $this->proxy = "http://www.ekamali.com/index.php";
        if(isset($extraParam['tvhebdo_proxy']))
            $this->proxy = $extraParam['tvhebdo_proxy'];
    }

    protected function getContentFromURL($url) {
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, $url);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch1, CURLOPT_REFERER, $this->proxy);
        curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0');
        $res1 = html_entity_decode(curl_exec($ch1),ENT_QUOTES);
        curl_close($ch1);
        return $res1;
    }

    function constructEPG($channel, $date)
    {
        parent::constructEPG($channel, $date);
        date_default_timezone_set('America/Montreal');
        if(!$this->channelExists($channel))
        {
            return false;
        }
        $res1 = $this->getContentFromURL($this->proxy.'?q='.base64_encode('http://www.tvhebdo.com/horaire-tele/'.$this->channelsList[$channel].'/date/'.$date).'&hl=3ed');
        @$res1 = explode('Mes<br>alertes courriel',$res1)[1];
        preg_match_all('/class="heure"\>(.*?)\<\/td\>/',$res1,$time);
        preg_match_all('/class="titre"\>.*?href="(.*?)"\>(.*?)\<\/a\>/',$res1,$titre);
        $t8 = json_encode($time);
        $t9 = json_encode($titre);
        $t8 = $t8.'|||||||||||||||||||||||'.$t9;
        if(strlen($t8)<=100){
            return false; }

        $count = count($titre[2]);
        for($j=0;$j<$count;$j++)
        {
            displayTextOnCurrentLine(" ".round($j*100/$count, 2)." %");
            $dateStart = strtotime($date.' '.$time[1][$j]);
            $titreProgram = $titre[2][$j];
            $url = $titre[1][$j];
            $content = $this->getContentFromURL($url);
            $infos = str_replace("\n", " ",explode('</h4>',explode('<h4>', $content)[1])[0]);
            $infos = explode(' - ', $infos);
            $genre = trim($infos[0]);
            $duration = @intval(explode(' ', trim($infos[1]))[0]);
            $lang = trim(strtolower($infos[2]));
            $potentialYear = @strval(intval(trim($infos[3])));
            if(@trim($infos[3]) == $potentialYear) {
                $year = $potentialYear;
            } else {
                $rating = @trim($infos[3]);
            }
            if(isset($infos[4])) {
                $potentialYear = @intval(trim($infos[4]));
                if(isset($potentialYear) && $potentialYear > 0) {
                    $year = $potentialYear;
                }
            }
            $desc =@explode('</p>',explode('<p id="dd_desc">', $content)[1])[0];
            if(isset($year)) {
                $desc.= "\n\nAnnée : ".$year;
            }
            $intervenants = @explode('</p>',explode('<p id="dd_inter">', $content)[1])[0];
            $program = $this->channelObj->addProgram($dateStart, $dateStart+$duration*60);
            $program->addTitle($titreProgram, $lang);
            $desc = str_replace('<br />', "\n",$desc.$intervenants);
            $tmp_desc = '';
            $splited_desc = explode("\n", $desc);
            foreach($splited_desc as $line) {
                $tmp_desc.=trim($line)."\n";
            }
            $desc = $tmp_desc;
            $program->addDesc($desc, $lang);
            $program->addCategory($genre, $lang);
            $current_role = "guest";
            $intervenants_split = explode('<br />', $intervenants);
            foreach ($intervenants_split as $line) {
                $line = trim($line);
                if($line == "Réalisation :") {
                    $current_role = "director";
                } elseif(strpos($line, ':') !== false) {
                    $current_role = "guest";
                } else {
                    $program->addCredit($line, $current_role);
                }
            }
            $img_zone = explode('<div id="dd_votes_container">',explode('<div id="dd_votes">', $content)[1])[0];
            preg_match('/src="(.*?)"/', $img_zone, $img_url);
            $program->setIcon(@$img_url[1]);
            if(isset($rating) && strlen($rating) > 0 && strlen($rating) < 4) {
                if(in_array($rating, ["PG", "14A", "18A", "R", "A"]) || ($rating == "G" && $lang == "en")) {
                    $rating_system = "CHVRS";
                } elseif (in_array($rating, ["G", "13", "16", "18"])) {
                    $rating_system = "RCQ";
                }
                if(isset($rating_system))
                    $program->setRating($rating, $rating_system);
            }
        }
        return $this->channelObj;
    }
}