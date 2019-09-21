<?php
set_time_limit(0);
date_default_timezone_set('Europe/Paris');
$channelsid = array("1:108:10801" => "LaUne.be", "1:108:10802" => "LaDeux.be", "1:108:10803" => "LaTrois.be", "1:108:10804" => "RTLTVI.be", "1:108:10805" => "ClubRTL.be", "1:108:10806" => "PlugRTL.be", "1:104:10401" => "TF1.fr", "1:104:10402" => "France2.fr", "1:104:10403" => "France3.fr", "1:110:11002" => "Arte.be", "1:103:10306" => "TV5Monde.fr", "1:106:10603" => "13Rue.fr", "1:101:10103" => "AB3.be", "1:101:10104" => "ABXploreFR.be", "1:104:10404" => "France4.fr", "1:104:10405" => "France5.fr", "1:104:10407" => "FranceO.fr", "1:104:10406" => "TvBreizh.fr", "1:9:901" => "Be1.be", "1:13:1305" => "Be1Plus1.be",
    "1:9:902" => "BeSeries.be", "1:9:903" => "BeCine.be", "1:12:1201" => "CinePlusPremier.be", "1:12:1202" => "CinePlusFrisson.be", "1:12:1203" => "CinePlusClassic.be", "1:9:904" => "Be3D.be",
    "1:12:1205" => "ElevenSports1FR.be", "1:12:1206" => "ElevenSports2FR.be", "1:14:1404" => "RMCSportAccess2.fr", "1:14:1405" => "RMCSport4.fr", "1:15:1505" => "ABMoteurs.fr",
    "1:110:11006" => "InfosportPlus.fr", "1:8:806" => "VOOSport1fr.be",
    "1:8:802" => "VOOSport2fr.be", "1:8:803" => "VOOSport3fr.be", "1:8:804" => "VOOSport4fr.be", "1:8:805" => "VOOSport5fr.be", "1:105:10504" => "Equidia.fr",
    "1:220:22001" => "RTCLiege.be", "1:101:10118" => "TeleSambre.be", "1:101:10116" => "TVCom.be", "1:101:10113" => "TVLux.be", "1:220:22002" => "TeleVesdre.be", "1:101:10119" => "NoTele.be",
    "1:101:10115" => "CanalC.be", "1:101:10121" => "TeleMB.be", "1:101:10120" => "ACTV.be", "1:101:10114" => "MaTele.be", "1:101:10117" => "CanalZoom.be", "1:101:10109" => "BX1.be",
    "1:106:10602" => "PIWI.fr", "1:105:10507" => "Nickelodeon.be", "1:105:10514" => "Boomerang.fr", "1:105:10503" => "Gulli.fr", "1:17:1715" => "Toonami.fr",
    "1:105:10502" => "DisneyChannel.be", "1:102:10202" => "DisneyJunior.fr", "1:14:1402" => "DisneyXD.fr", "1:204:20402" => "DisneyCinema.fr",
    "1:16:1602" => "Teletoon.fr", "1:16:1615" => "TIJI.fr", "1:17:1712" => "CanalJ.fr", "1:16:1607" => "Boing.fr", "1:16:1606" => "Mangas.fr", "1:16:1608" => "CartoonNetwork.fr",
    "1:106:10608" => "UshuaiaTV.fr", "1:17:1711" => "Histoire.fr", "1:16:1605" => "TouteHistoire.fr", "1:14:1406" => "ScienceEtVie.fr", "1:17:1706" => "Seasons.fr",
    "1:16:1604" => "ChassePeche.fr", "1:13:1302" => "Animaux.fr", "1:13:1306" => "Voyage.fr", "1:204:20401" => "Trek.fr", "1:203:20303" => "NationalGeographic.fr",
    "1:15:1502" => "NatGeoWild.fr", "1:13:1301" => "PlanetePlus.fr", "1:15:1501" => "PLANETEJustice.fr", "1:14:1401" => "PlaneteAction.fr", "1:15:1504" => "WarnerTV.fr",
    "1:15:1506" => "Syfy.fr", "1:13:1303" => "ComediePlus.fr", "1:14:1403" => "ElleGirl.fr", "1:15:1503" => "TF1SeriesFilms.fr", "1:205:20502" => "Festival.be",
    "1:17:1713" => "Vivolta.fr", "1:17:1709" => "MyCuisine.fr", "1:17:1704" => "EEntertainment.fr", "1:16:1603" => "Melody.fr", "1:105:10515" => "CStar.fr", "1:17:1707" => "MyZenTV.fr",
    "1:16:1601" => "Extreme.fr", "1:16:1613" => "GameOne.fr", "1:13:1304" => "Viceland.fr", "1:103:10302" => "MTV.fr", "1:16:1610" => "MTVHitsFrance.fr",
    "1:17:1705" => "ClubbingTV.fr", "1:113:11304" => "MCM.fr", "1:17:1702" => "RFMTV.fr", "1:17:1708" => "TraceUrban.fr", "1:16:1611" => "M6Music.fr", "1:17:1701" => "DJAZ.fr",
    "1:16:1612" => "Mezzo.fr", "1:203:20302" => "TCM.fr", "1:16:1614" => "Action.fr", "1:204:20403" => "Sundance.fr", "1:16:1617" => "DorcelTV.fr",
    "1:16:1616" => "XXL.fr",
    "1:108:10811" => "BELRTL.be", "1:113:11303" => "NRJHits.be", "1:110:11008" => "PureVision.be", "1:103:10304" => "M6Boutique.fr", "1:101:10105" => "LCI.fr", "1:103:10307" => "Euronews.fr",
    "1:104:10408" => "France24.fr", "1:110:11007" => "CanalZ.be", "1:113:11301" => "KTO.fr", "1:17:1710" => "BFMTV.fr", "1:17:1703" => "CNews.fr", "1:101:10101" => "EEN.be",
    "1:1:107"=>"Be1.be", "1:1:102"=>"Be1Plus1.be", "1:1:104"=>"BeSeries.be", "1:1:108"=>"BeCine.be",
    "1:110:11004"=>"ElevenSports1.be", "1:110:11009"=>"ElevenSports2.be", "1:9:906"=>"Voosport.be",
 "1:101:10111"=>"RTC.be", "1:101:10112"=>"TELEVESDRE.be", "1:16:1609"=>"TCM.fr", "1:205:20501"=>"Festival4K", "1:105:10508"=>"LAPREMIERE", "1:105:10511"=>"VIVACITE",
 "1:105:10510"=>"MUSIQ3", "1:105:10513"=>"PUREFM", "1:105:10509"=>"CLASSIC21", "1:105:10512"=>"BRF", "1:113:11302"=>"BelRTLTV", "1:105:10505"=>"RadioContact", 
 "1:108:10815"=>"ContactRNB", "1:108:10812"=>"MINT", "1:105:10516"=>"DHRADIO", "1:107:10701"=>"RadioJudaica", "1:107:10707"=>"QMusic", 
 "1:107:10708"=>"Joe", "1:201:20101"=>"LaUne.be", "1:201:20102"=>"LaDeux.be", "1:201:20103"=>"LaTrois.be", "1:202:20201"=>"RTLTVI.be", "1:202:20202"=>"ClubRTL.be",
 "1:202:20203"=>"PlugRTL.be", "1:201:20104"=>"TF1.fr", "1:203:20306"=>"France2.fr", "1:203:20304"=>"France3.fr", "1:202:20204"=>"Arte.be", "1:201:20105"=>"13eRue.fr",
 "1:203:20305"=>"AB3.fr", "1:202:20205"=>"UshuaiaTV.fr", "1:220:22003"=>"NoTele.be", 
 "1:105:10501"=>"VOO.be" );
$channelidz = array_keys($channelsid);
$tableau = array();
unlink("tmp/hs_voo.txt");
foreach($channelidz as $channelid)
{
    $hs = "OK";
    if(strtotime("now") - filemtime("channels/".$channelsid[$channelid].".xml") > 85000) {
        $end = strtotime("now");
        $ch3 = curl_init();
        curl_setopt($ch3, CURLOPT_URL, 'https://publisher.voomotion.be/traxis/web/Channel/' . $channelid . '/Events/Filter/AvailabilityEnd%3C=' . date('Y-m-d', strtotime("now") + 86400 * 10) . 'T01:30:00Z%26%26AvailabilityStart%3E=' . date('Y-m-d', strtotime("now") + 86400 * -1) . 'T19:30:00Z/Sort/AvailabilityStart/Props/IsAvailable,Products,AvailabilityEnd,AvailabilityStart,ChannelId,AspectRatio,DurationInSeconds,Titles,Channels?output=json&Language=fr&Method=PUT');
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch3, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0");
        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch3, CURLOPT_POST, 1);
        $str = '<SubQueryOptions><QueryOption path="Titles">/Props/Name,Pictures,ShortSynopsis,LongSynopsis,Genres,Events,SeriesCount,SeriesCollection</QueryOption><QueryOption path="Titles/Events">/Props/IsAvailable</QueryOption><QueryOption path="Products">/Props/ListPrice,OfferPrice,CouponCount,Name,EntitlementState,IsAvailable</QueryOption><QueryOption path="Channels">/Props/Products</QueryOption><QueryOption path="Channels/Products">/Filter/EntitlementEnd>2018-01-27T14:40:43Z/Props/EntitlementEnd,EntitlementState</QueryOption></SubQueryOptions>';
        curl_setopt($ch3, CURLOPT_POSTFIELDS, "" . $str . "");
        $res3 = curl_exec($ch3);
        curl_close($ch3);

        $json = json_decode($res3, true);
        if (!$json["Events"]["Event"]) {
           $hs = "HS";
            }
        foreach ($json["Events"]["Event"] as $event) {
            $start = strtotime($event["AvailabilityStart"]);
            if ($start > $end + 1) {
                $fp = fopen("channels/".$channelsid[$channelid].".xml", "a");
                fputs($fp, '<programme start="' . date('YmdHis O', ($end)) . '" stop="' . date('YmdHis O', $start) . '" channel="' . $channelsid[$event["ChannelId"]] . '">
	<title lang="fr">Pas de programme</title>
	<desc lang="fr">Pas de programme</desc>
	<category lang="fr">Inconnu</category>
</programme>
');
                fclose($fp);
            }
            $end = strtotime($event["AvailabilityEnd"]);
            $fp = fopen("channels/".$channelsid[$channelid].".xml", "a");
            fputs($fp, '<programme start="' . date('YmdHis O', ($start)) . '" stop="' . date('YmdHis O', $end) . '" channel="' . $channelsid[$event["ChannelId"]] . '">
	<title lang="fr">' . htmlspecialchars($event["Titles"]["Title"][0]["Name"], ENT_XML1) . '</title>
	<desc lang="fr">' . htmlspecialchars($event["Titles"]["Title"][0]["LongSynopsis"], ENT_XML1) . '</desc>
	<category lang="fr">' . htmlspecialchars($event["Titles"]["Title"][0]["Genres"]["Genre"][0]["Value"], ENT_XML1) . '</category>
	<icon src="' . htmlspecialchars($event["Titles"]["Title"][0]["Pictures"]["Picture"][0]["Value"], ENT_XML1) . '" />
</programme>
');
            fclose($fp);
        }
        if($hs == "HS")
            file_put_contents("tmp/hs_voo.txt",file_get_contents("tmp/hs_voo.txt").$channelsid[$channelid].chr(10));
    }
}