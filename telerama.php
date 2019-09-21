<?php
set_time_limit(0);
date_default_timezone_set('Europe/Paris');
ini_set("display_errors", 1);
error_reporting(0);
$tableau = array();
$ookkj = '';

$json = '{"2334":"WarnerTV.fr","2326":"PolarPlus.fr","2":"13eRue.fr","340":"2MMonde.fr","1403":"6ter.fr","421":"8MontBlanc.fr","2049":"APlusInternationalFrance","5":"AB1.fr","254":"AB3.fr","303":"ABXploreFR.fr","15":"ABMoteurs.fr","10":"Action.fr","524":"Alsace20.fr","2320":"AlticeStudio.fr","12":"Animaux.fr",
"111":"Arte.fr","295":"ATVMartinique","29":"Be1.be","417":"BeCine.be","418":"BeSeries.be","1290":"beINSPORTS1.fr",
"1304":"beINSPORTS2.fr","1335":"beINSPORTS3.fr","1342":"beINSPORTSMAX10.fr","1336":"beINSPORTSMAX4.fr","1337":"beINSPORTSMAX5.fr","1338":"beINSPORTSMAX6.fr",
"1339":"beINSPORTSMAX7.fr","1340":"beINSPORTSMAX8.fr","1341":"beINSPORTSMAX9.fr","1960":"BET.fr","1073":"BFMBusiness.fr","481":"BFMTV.fr",
"924":"Boing.fr","321":"Boomerang.fr","475":"BrazzersTV.fr","445":"C8.fr","34":"CanalPlus.fr",
"227":"CanalPlusAfriqueOuest.fr","33":"CanalPlusCinema.fr","1105":"CanalPlusCinemaDROM.fr","30":"CanalPlusDecale.fr","657":"CanalPlusFamily.fr",
"32":"CanalJ.fr","703":"CanalpartageTNTIDF.fr","461":"CanalPlusPolynesie.fr","1563":"CanalPlusSeries.fr","35":"CanalPlusSport.fr",
"24":"Canvas","36":"CartoonNetwork.fr","38":"ChasseEtPeche.fr","1399":"Cherie25.fr",
"287":"CinePlusClassic.fr","437":"CinePlusClassic.be","285":"CinePlusClassic.fr","283":"CinePlusEmotion.fr",
"401":"CinePlusFamiz.fr","284":"CinePlusFrisson.fr","280":"CinePlusFrisson.fr","288":"CineFX.fr","282":"CinePlusPremier.fr","294":"CinePlusPremier.be",
"279":"CinePlusStarAfrique.fr","1353":"ClassicaEnglish","50":"ClubRTL.be","226":"CNews.fr",
"54":"ComediePlus.fr","2037":"CrimeDistrict.fr","458":"CStar.fr","57":"DemainTV","400":"DiscoveryChannel.fr","1374":"DiscoveryScience.fr",
"58":"DisneyChannel.fr","299":"DisneyChannelPlus1.fr","652":"DisneyCinema.fr","300":"DisneyJunior.fr","79":"DisneyXD.fr","560":"DorcelTV.fr",
"405":"EEntertainement.fr","23":"een","403":"ElleGirl.fr","1146":"EquidiaLife.fr","64":"Equidia.fr",
"1190":"Eurochannel.fr","140":"Euronews.fr","76":"Eurosport1.fr","439":"Eurosport2.fr","253":"Extreme.fr","100":"FootPlus24.fr","4":"France2.fr",
"529":"France24.fr","80":"France3.fr","1921":"France3Alpes.fr", "1922":"France3Alsace.fr", "1923":"France3Aquitaine.fr", 
 "1924":"France3Auvergne.fr", "1925":"France3BasseNormandie.fr", "1926":"France3Bourgogne.fr", "1927":"France3Bretagne.fr", "1928":"France3Centre.fr", "1929":"France3ChampagneArdenne.fr", "308":"France3Corse.fr",
 "1931":"France3CotedAzur.fr", "1932":"France3FrancheComte.fr", "1933":"France3HauteNormandie.fr",
 "1934":"France3Languedoc.fr", "1935":"France3Limousin.fr", "1936":"France3Lorraine.fr", "1937":"France3MidiPyrenees.fr", "1938":"France3NordPasDeCalais.fr", "1939":"FranceFrance3ParisIdF.fr", 
 "1940":"France3PaysDeLaLoire.fr", "1941":"France3Picardie.fr", "1942":"France3PoitouCharentes.fr", "1943":"France3ProvenceAlpes.fr", "1944":"France3RhoneAlpes.fr",
 "78":"France4.fr","47":"France5.fr","160":"FranceO.fr","2111":"FranceInfo.fr","87":"GameOne.fr","563":"Ginx.fr","1295":"GolfPlus.fr",
 "1166":"GolfChannel.fr","621":"GongMax.fr","329":"Guadeloupe1.fr","482":"Gulli.fr","260":"Guyane1.fr","1404":"TF1SeriesFilms.fr","88":"Histoire.fr",
 "781":"I24news.fr","701":"IDF1","94":"InfosportPlus.fr","1585":"JOne.fr","1280":"Ketnet","110":"KTO.fr","929":"KZTV.fr","1401":"LEquipe21.fr",
 "124":"LaChaîneMeteo.fr","234":"LaChaineParlementaire.fr","187":"LaDeux.be","892":"LaTrois.be","164":"LaUne.be",
 "112":"LCI.fr","535":"LMTVSarthe.fr","118":"M6.fr","184":"M6Boutique.fr","453":"M6Music.fr","6":"Mangas.fr","328":"Martinique1.fr",
 "987":"MCE.fr","121":"MCM.fr","343":"MCMTop.fr","265":"Melody.fr","125":"Mezzo.fr","907":"MezzoLiveHD.fr","1045":"MirabelleTV.fr",
 "128":"MTV.fr","263":"MTVBase.fr","2014":"MTVDance.fr","262":"MTVHits.uk","2006":"MTVHits.fr","264":"MTVRocks.fr","98":"Multisports.fr",
 "101":"Multisports1.fr","102":"Multisports2.fr","103":"Multisports3.fr","104":"Multisports4.fr","105":"Multisports5.fr","106":"Multisports6.fr",
 "719":"NatGeoWild.fr","243":"NationalGeographic.fr","415":"NauticalChannel.fr","473":"Nickelodeon.fr","888":"NickelodeonJunior.fr",
 "1746":"Nickelodeon4Teen.fr","787":"Nolife.fr","1461":"NollywoodTV.fr","240":"NouvelleCaledonie.fr",
 "444":"NRJ12.fr","605":"NRJHits.fr","446":"NT1.fr","1402":"Numero23.fr","732":"OCSChoc.fr","733":"OCSCity.fr","734":"OCSGeants.fr","730":"OCSMax.fr",
 "463":"OLTV.fr","334":"OMTV.fr","517":"Onzeo.fr","1562":"ParamountChannel.fr","145":"ParisPremiere.fr","344":"PIWI.fr","147":"PlanetePlus.fr",
 "402":"PlaneteAction.fr","662":"PLANETEJustice.fr","377":"PlugRTL.be","289":"Polar.fr","459":"Polynesie1.fr","245":"Reunion1.fr","241":"RFMTV.fr",
 "546":"RMC.fr","1400":"RMCDecouverte.fr","168":"RTLTVI.be","183":"RTSDeux.ch","202":"RTSUn.ch",
 "63":"ScienceEtVieTV.fr","173":"Seasons.fr","49":"serieclub.fr","2095":"RMCSportAccess1.fr","675":"RMCSportAccess2.fr","2029":"RMCSportUHD.fr",
 "1382":"RMCSport4.fr","835":"StingrayBrava.fr","1357":"StingrayDjazz.fr","604":"StingrayiConcerts.fr","833":"SundanceTV.fr",
 "479":"Syfy.fr","185":"TCM.fr","491":"Telenantes.fr","449":"Telesud.fr","197":"TeleToonPlus.fr","293":"TeleTOONplus1.fr",
 "191":"Teva.fr","192":"TF1.fr","229":"TIJI.fr","116":"TeleLyonMetropole.fr","195":"TMC.fr","2040":"Toonami.fr","7":"TouteHistoire.fr",
 "1179":"TraceAfrica.fr","1168":"TRACESportStars.fr","1948":"TraceToca.fr","753":"TraceTropical.fr","325":"TraceUrban.fr","1776":"Trek.fr","540":"TVTours.fr",
 "205":"TV5Monde.fr","233":"TV5MondeAfrique.fr","232":"TV5MondeEurope.fr","273":"TV7Bordeaux.fr","225":"TvBreizh.fr",
"539":"TVRRennes35Bretagne.fr","492":"TVSud.fr","451":"UshuaiaTV.fr","210":"VH1.fr","690":"VH1Classic.fr",
"659":"Vivolta.fr","413":"VOOSportWorld1.be","414":"VOOSportWorld2.be","212":"Voyage.fr","119":"W9.fr",
"218":"XXL.fr"}';
$json = json_decode($json, true);
$channels = array_keys($json);
if(!is_dir("epg/telerama/"))
{
    mkdir("epg/telerama/",0770);
}
unlink("tmp/hs_telerama.txt");
function compile($get)
{
    global $tableau;
    global $json;
    foreach ($get["donnees"] as $donnee) {
        if ($donnee["annee_realisation"]) {
            $annee = $donnee["annee_realisation"];
        } else {
            $annee = '';
        }
        $intervenants = array();
        $inte = '';
        $int = '';
        if (!$donnee["intervenants"]) {
            $donnee["intervenants"] = array();
        }
        $int2 = chr(10) . '	<credits>' . chr(10);
        foreach ($donnee["intervenants"] as $intervenant) {
            if (!$intervenant["libelle"]) {
                $intervenant["libelle"] = 'Avec';
            }
            $intervenants[$intervenant["libelle"]][] = $intervenant["prenom"] . ' ' . $intervenant["nom"];
            $libelle = 'guest';
            $role = "";
            if ($intervenant["libelle"] == 'Présentateur vedette' || $intervenant["libelle"] == 'Autre présentateur') {
                $libelle = 'presenter';
            }
            if ($intervenant["libelle"] == 'Acteur') {
                $libelle = 'actor';
                if ($intervenant["role"] == '') {
                    $role = ' (' . $intervenant["role"] . ')';
                }
            }
            if ($intervenant["libelle"] == 'Réalisateur') {
                $libelle = 'director';
            }
            if ($intervenant["libelle"] == 'Scénariste' || $intervenant["libelle"] == 'Origine Scénario' || $intervenant["libelle"] == 'Scénario') {
                $libelle = 'writer';
            }
            if ($intervenant["libelle"] == 'Créateur') {
                $libelle = 'editor';
            }
            if ($intervenant["libelle"] == 'Musique') {
                $libelle = 'composer';
            }
            if ($intervenant["libelle"] == '') {
                if ($intervenant["role"] != '') {
                    $libelle = 'actor';
                    $role = ' (' . $intervenant["role"] . ')';
                } else {
                    $libelle = 'director';
                }
            }
            $int2 = $int2 . '		<' . $libelle . '>' . htmlspecialchars($intervenant["prenom"] . ' ' . $intervenant["nom"] . $role, ENT_XML1) . '</' . $libelle . '>' . chr(10);
        }
        $int2 = $int2 . '	</credits>';
        $keys = array_keys($intervenants);
        for ($i = 0; $i < count($intervenants); $i++) {
            $int = '';
            $a = $intervenants[$keys[$i]];
            $b = '';
            foreach ($a as $intervenant) {
                $int = $int . $b . $intervenant;
                $b = ', ';
            }
            $inte = $keys[$i] . ' : ' . $int . chr(10) . $inte;
        }
        $intervenants = $inte;
        if ($donnee["soustitre"]) {
            $soustitre = chr(10) . '	<sub-title lang="fr">' . htmlspecialchars($donnee["soustitre"], ENT_XML1) . '</sub-title>';
        } else {
            $soustitre = '';
        }
        $serie = '';
        $xmlns = '';
        for ($i = 0; $i < count($donnee["serie"]); $i++) {
            $serie = 'Saison ' . $donnee["serie"]["saison"] . ' Episode ' . $donnee["serie"]["numero_episode"] . chr(10);
            if (!$donnee["serie"]["saison"]) {
                $donnee["serie"]["saison"] = '1';
            }
            if (!$donnee["serie"]["numero_episode"]) {
                $donnee["serie"]["numero_episode"] = '1';
            }
            $xmlns = chr(10) . '	<episode-num system="xmltv_ns">' . ($donnee["serie"]["saison"] - 1) . '.' . ($donnee["serie"]["numero_episode"] - 1) . '.</episode-num>';
        }
        if ($donnee["critique"]) {
            $critique = $donnee["critique"] . chr(10);
        } else {
            $critique = '';
        }
        if ($donnee["resume"]) {
            $resume = $donnee["resume"] . chr(10);
        } else {
            $resume = '';
        }
        $channel = $json[$donnee["id_chaine"]];
        $descri = $serie . $resume . $critique . $intervenants . $annee;
        $descri = str_replace('<P>', '', $descri);
        $descri = str_replace('</P>', '', $descri);
        $descri = str_replace('<I>', '', $descri);
        $descri = str_replace('</I>', '', $descri);
        $moins = '';
        if (intval($donnee["csa"]) > 1) {
            $moins = '-';
        }
        $fp = fopen("channels/".$channel.".xml", "a");
        fputs($fp, '<programme start="' . date('YmdHis O', (strtotime($donnee["horaire"]["debut"]))) . '" stop="' . date('YmdHis O', (strtotime($donnee["horaire"]["fin"]))) . '" channel="' . $channel . '">
	<title lang="fr">' . htmlspecialchars($donnee["titre"], ENT_XML1) . '</title>' . $soustitre . $xmlns . '
	<desc lang="fr">' . htmlspecialchars($descri, ENT_XML1) . '</desc>' . $int2 . '
	<category lang="fr">' . htmlspecialchars($donnee["genre_specifique"], ENT_XML1) . '</category>
	<icon src="' . htmlspecialchars($donnee["vignettes"]["grande169"], ENT_XML1) . '" />
	<rating system="csa">
      <value>' . $moins . htmlspecialchars($donnee["csa"], ENT_XML1) . '</value>
    </rating>
</programme>
');
        fclose($fp);

    }
}

foreach ($channels as $channel1) {
    $hs = "HS";
    if(strtotime("now") - filemtime("channels/".$json[$channel1].".xml") > 85000) {
        for ($if = -5; $if < -2; $if++) {
            $date1 = date('Y-m-d', strtotime("now") + 86400 * $if);
            $hash1 = hash_hmac('sha1', '/v1/programmes/telechargementappareilandroid_tablettedates' . $date1 . 'id_chaines' . $channel1 . 'nb_par_page80000000000000page1', 'Eufea9cuweuHeif');
            if (file_exists('epg/telerama/' . $hash1)) {
                unlink('epg/telerama/' . $hash1);
            }
        }
        for ($iof = -1; $iof < 9; $iof++) {
            $date = date('Y-m-d', strtotime("now") + 86400 * $iof);
            $hash = hash_hmac('sha1', '/v1/programmes/telechargementappareilandroid_tablettedates' . $date . 'id_chaines' . $channel1 . 'nb_par_page80000000000000page1', 'Eufea9cuweuHeif');
            if (!file_exists('epg/telerama/' . $hash)) {
                $uu = curl_init('http://api.telerama.fr/v1/programmes/telechargement?dates=' . $date . '&id_chaines=' . $channel1 . '&nb_par_page=80000000000000&page=1&api_cle=apitel-5304b49c90511&api_signature=' . $hash . '&appareil=android_tablette');
                curl_setopt($uu, CURLOPT_USERAGENT, 'okhttp/3.2.0');
                curl_setopt($uu, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($uu, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($uu, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($uu, CURLOPT_SSL_VERIFYHOST, 0);
                $get = curl_exec($uu);
                curl_close($uu);
                $get = json_decode($get, true);
                $get2 = json_encode($get);
                if (strlen($get2) > 250) {
                    file_put_contents('epg/telerama/' . $hash, gzencode($get2));
                    echo $json[$channel1] . '-' . $date . ' : OK http://api.telerama.fr/v1/programmes/telechargement?dates=' . $date . '&id_chaines=' . $channel1 . '&nb_par_page=80000000000000&page=1&api_cle=apitel-5304b49c90511&api_signature=' . $hash . '&appareil=android_tablette' . chr(10);
                    $hs = "OK";
                } else {
                    $get = array();
                    echo $json[$channel1] . '-' . $date . ' : HS http://api.telerama.fr/v1/programmes/telechargement?dates=' . $date . '&id_chaines=' . $channel1 . '&nb_par_page=80000000000000&page=1&api_cle=apitel-5304b49c90511&api_signature=' . $hash . '&appareil=android_tablette' . chr(10);
                    for ($rr = 1; $rr <= 25; $rr++) {
                        $date = date('Y-m-d', strtotime("now") + 86400 * $iof);
                        $hash = hash_hmac('sha1', '/v1/programmes/telechargementappareilandroid_tablettedates' . $date . 'id_chaines' . $channel1 . 'nb_par_page8page' . $rr, 'Eufea9cuweuHeif');
                        if (!file_exists('epg/telerama/' . $hash)) {
                            $uu = curl_init('http://api.telerama.fr/v1/programmes/telechargement?dates=' . $date . '&id_chaines=' . $channel1 . '&nb_par_page=8&page=' . $rr . '&api_cle=apitel-5304b49c90511&api_signature=' . $hash . '&appareil=android_tablette');
                            curl_setopt($uu, CURLOPT_USERAGENT, 'okhttp/3.2.0');
                            curl_setopt($uu, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($uu, CURLOPT_FOLLOWLOCATION, true);
                            curl_setopt($uu, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($uu, CURLOPT_SSL_VERIFYHOST, 0);
                            $get3 = curl_exec($uu);
                            curl_close($uu);
                            if (preg_match('(a pas de programmes)', $get3)) {
                                break;
                            }
                            $get1 = json_decode($get3, true);
                            $get2 = json_encode($get1);
                            if (strlen($get2) > 250) {
                                file_put_contents('epg/telerama/' . $hash, gzencode($get2));
                                echo $json[$channel1] . '-' . $date . ' : OK http://api.telerama.fr/v1/programmes/telechargement?dates=' . $date . '&id_chaines=' . $channel1 . '&nb_par_page=8&page=' . $rr . '&api_cle=apitel-5304b49c90511&api_signature=' . $hash . '&appareil=android_tablette' . chr(10);
                                $hs = "OK";
                            } else {
                                echo $json[$channel1] . '-' . $date . ' : HS http://api.telerama.fr/v1/programmes/telechargement?dates=' . $date . '&id_chaines=' . $channel1 . '&nb_par_page=8&page=' . $rr . '&api_cle=apitel-5304b49c90511&api_signature=' . $hash . '&appareil=android_tablette' . chr(10);
                                for ($rr1 = 0; $rr1 < 8; $rr1++) {
                                    $date = date('Y-m-d', strtotime("now") + 86400 * $iof);
                                    $hash = hash_hmac('sha1', '/v1/programmes/telechargementappareilandroid_tablettedates' . $date . 'id_chaines' . $channel1 . 'nb_par_page1page' . ($rr * 8 + $rr1), 'Eufea9cuweuHeif');
                                    if (!file_exists('epg/telerama/' . $hash)) {
                                        $uu = curl_init('http://api.telerama.fr/v1/programmes/telechargement?dates=' . $date . '&id_chaines=' . $channel1 . '&nb_par_page=1&page=' . ($rr * 8 + $rr1) . '&api_cle=apitel-5304b49c90511&api_signature=' . $hash . '&appareil=android_tablette');
                                        curl_setopt($uu, CURLOPT_USERAGENT, 'okhttp/3.2.0');
                                        curl_setopt($uu, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($uu, CURLOPT_FOLLOWLOCATION, true);
                                        curl_setopt($uu, CURLOPT_SSL_VERIFYPEER, false);
                                        curl_setopt($uu, CURLOPT_SSL_VERIFYHOST, 0);
                                        $get3 = curl_exec($uu);
                                        curl_close($uu);
                                        file_put_contents('epg/telerama/' . $hash, gzencode($get3));
                                        echo $json[$channel1] . '-' . $date . ' : OK http://api.telerama.fr/v1/programmes/telechargement?dates=' . $date . '&id_chaines=' . $channel1 . '&nb_par_page=1&page=' . ($rr * 8 + $rr1) . '&api_cle=apitel-5304b49c90511&api_signature=' . $hash . '&appareil=android_tablette' . chr(10);
                                        $hs = "OK";
                                    } else {
                                        $get3 = gzdecode(file_get_contents('epg/telerama/' . $hash));
                                        echo $json[$channel1] . '-' . $date . ' : OK Page ' . ($rr * 8 + $rr1) . chr(10);
                                    }
                                    if (preg_match('(a pas de programmes)', $get3)) {
                                        break;
                                    }
                                    $get1 = json_decode($get3, true);
                                    compile($get1);
                                }
                            }
                        } else {
                            $get3 = gzdecode(file_get_contents('epg/telerama/' . $hash));
                            $get1 = json_decode($get3, true);
                            echo $json[$channel1] . '-' . $date . ' : OK Page ' . ($rr) . chr(10);
                            $hs = "OK";
                        }
                        compile($get1);
                    }
                }
            } else {
                $get = gzdecode(file_get_contents('epg/telerama/' . $hash));
                $get = json_decode($get, true);
                echo $json[$channel1] . '-' . $date . ' : OK' . chr(10);
                $hs = "OK";
            }
            compile($get);
        }
    } else { $hs = "OK"; }
    if($hs=="HS")
        file_put_contents("tmp/hs_telerama.txt",file_get_contents("tmp/hs_telerama.txt").$json[$channel1].chr(10));
}

