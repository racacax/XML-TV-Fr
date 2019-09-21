<?php
ini_set("display_errors",0);error_reporting(0);
set_time_limit(0);
$ptvepg = 'rouge-tv,public-senat-24-24,lcpan,rennes-35-bretagne,tv5-monde-europe,bfm-business,seasons,piwi-plus,teletoon,telenantes,nolife,comedie,mcm,ab1,idf1,demain-tv,equidia-live,equidia-life,serie-club,canal-plus-series,rtl-9,chasse-et-peche,toute-l-histoire,teva,cine-plus-classic,cine-plus-club,cine-plus-emotion,cine-plus-frisson,cine-plus-premier,cine-plus-famiz,rts-un,canal-plus,m6,c8,w9,tmc,tfx,nrj-12,lcp-ps,bfm-tv,cnews,cstar,gulli,france-o,tf1-series-films,lequipe,6ter,rmc-story,rmc-decouverte,cherie-25,paris-premiere,canal-plus-sport,canal-plus-cinema,canal-plus-family,planete,planete-plus-no-limit,planete-plus-justice,canal-plus-decale,game-one';
$channels = explode(',',$ptvepg);
$nvobs2 = 'RougeTV.ch,PublicSenat.fr,LCP2424.fr,TVR35.fr,TV5MondeEurope.fr,BFMBusiness.fr,Seasons.fr,PIWI.fr,TeleToonPlus.fr,TeleNantes,NoLife.fr,Comedie.fr,MCM.fr,AB1.fr,IDF1,DemainTV,Equidia.fr,EquidiaLife.fr,SerieClub.fr,CanalplusSeries.fr,RTL9.fr,ChassePeche.fr,TouteHistoire.fr,Teva.fr,CinecinemaClassic.fr,CinecinemaClub.fr,CinecinemaEmotion.fr,CinecinemaFrisson.fr,CinePlusPremier.fr,CinePlusaFamiz.fr,RTSUn.ch,CanalPlus.fr,M6.fr,C8.fr,W9.fr,TMC.fr,NT1.fr,NRJ12.fr,LCP.fr,BFMTV.fr,CNews.fr,CStar.fr,Gulli.fr,FranceO.fr,HD1.fr,EquipeTV.fr,6ter.fr,Numero23.fr,RMCdecouverte.fr,Cherie25.fr,ParisPremiere.fr,CanalPlusSport.fr,CanalPlusCinema.fr,CanalPlusFamily.fr,PlanetePlus.fr,PlaneteAction.fr,PlaneteJustice.fr,CanalPlusDecale.fr,GameOne.fr';
$channel2 = explode(',',$nvobs2);
$tableau = array();
$i77 = 0;
for($j=0;$j<count($channels);$j++)
{
        $channel = $channels[$j];
        $channel1 = $channel2[$j];
    if(strtotime("now") - filemtime("channels/".$channel1.".xml") > 85000) {
        for ($i = 0; $i < 7; $i++) {
            if (!file_exists('epg/playtv/playtv-' . $channel . date('Y-m-d', strtotime("now") + $i * 86400))) {
                $url = 'http://m.playtv.fr/api/programmes/?channel_id=' . $channel . '&date=' . date('Y-m-d', strtotime("now") + $i * 86400) . '&preset=daily';
                $ch1 = curl_init();
                curl_setopt($ch1, CURLOPT_URL, $url);
                curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch1, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:49.0) Gecko/20100101 Firefox/49.0");
                $res1 = curl_exec($ch1);
                curl_close($ch1);
                $res1 = str_replace('assets\/images\/tv-default.svg', 'http://img.src.ca/ouglo/emission/480x270/findesemissions.jpg', $res1);
                $res2 = json_decode($res1, true);
                $b = count($res2) - 1;
                $start = $res2[$b]["start"];
                $end = $res2[$b]["end"];
                if (date('H', $start) > 10 && date('H', $end) >= 0 && $b > 3) {
                    file_put_contents('epg/playtv/playtv-' . $channel . date('Y-m-d', strtotime("now") + $i * 86400), $res1);
                }
            } else {
                $res1 = file_get_contents('epg/playtv/playtv-' . $channel . date('Y-m-d', strtotime("now") + $i * 86400));
            }
            $res1 = json_decode($res1, true);
            foreach ($res1 as $val) {
                if (date('YmdHis O', $val["start"]) != $bwh) {
                    $ns = '';
                    $season = '';
                    $de = '';
                    if ($val["program"]["episode"]) {
                        if ($val["program"]["season"] == "") {
                            $val["program"]["season"] = '1';
                        }
                        $de = ' : ';
                        $season = 'Saison ' . $val["program"]["season"] . ' Episode ' . $val["program"]["episode"];
                        $ns = chr(10) . '	<episode-num system="xmltv_ns">' . ($val["program"]["season"] - 1) . '.' . ($val["program"]["episode"] - 1) . '.</episode-num>';
                    }
                    $csa = 'TP';
                    if ($val["program"]["csa_id"] == "2") {
                        $csa = '-10';
                    }
                    if ($val["program"]["csa_id"] == "3") {
                        $csa = '-12';
                    }
                    if ($val["program"]["csa_id"] == "4") {
                        $csa = '-16';
                    }
                    if ($val["program"]["csa_id"] == "5") {
                        $csa = '-18';
                    }
                    $subtitle = '';
                    if ($val["program"]["subtitle"]) {
                        $season = $season . $de . $val["program"]["subtitle"];
                        $subtitle = chr(10) . '	<sub-title lang="fr">' . htmlspecialchars($val["program"]["subtitle"], ENT_XML1) . '</sub-title>';
                    }
                    $subcat = '';
                    if (strlen($season) > 2) {
                        $season = $season . chr(10);
                    }
                    if ($val["program"]["subgender"]) {
                        $subcat = chr(10) . '	<category lang="fr">' . htmlspecialchars($val["program"]["subgender"], ENT_XML1) . '</category>';
                    }
                    $fp = fopen("channels/" . $channel1 . ".xml", "a");
                    fputs($fp, '<programme start="' . date('YmdHis O', $val["start"]) . '" stop="' . date('YmdHis O', $val["end"]) . '" channel="' . $channel1 . '">
	<title lang="fr">' . htmlspecialchars($val["program"]["title"], ENT_XML1) . '</title>' . $subtitle . '
	<desc lang="fr">' . htmlspecialchars($season . $val["program"]["summary_long"], ENT_XML1) . '</desc>
	<category lang="fr">' . htmlspecialchars($val["program"]["gender"], ENT_XML1) . '</category>' . $subcat . '
	<icon src="' . htmlspecialchars($val["program"]["images"]["xlarge"], ENT_XML1) . '" />
	<year>' . htmlspecialchars($val["program"]["year"], ENT_XML1) . '</year>
	<rating system="csa">
      <value>' . $csa . '</value>
    </rating>' . $ns . '
</programme>
');
                    fclose($fp);
                    $bwh = date('YmdHis O', $val["start"]);
                }
            }
        }
    }
}