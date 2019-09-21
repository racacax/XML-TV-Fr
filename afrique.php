<?php
set_time_limit(0);
$ids = '80129,80125,80302,80144,80018,80016,80402,80403,80124,80149,80393,80394';
$channels = 'CanalPlusCentre,CanalPlusCinemaCentre,CanalPlusSeriesCentre,CanalPlusFamilyCentre,CanalPlusOuest,CanalPlusCinemaOuest,CanalPlusSeriesOuest,CanalPlusFamilyOuest,CanalPlusSport1,CanalPlusSport2,CanalPlusSport3,CanalPlusSport4';
$ids = explode(',',$ids);
$channels = explode(',',$channels);
$tableau = array();
unlink("tmp/hs_afrique.txt");
if(!is_dir("epg/afrique/"))
{
    mkdir("epg/afrique/",0770);
}
for($i=0;$i<count($ids);$i++)
{
    if(strtotime("now") - filemtime("channels/".$channels[$i].".xml") > 85000) {
        $hs = "HS";
        for ($day = -1; $day < 7; $day++) {
            if (!file_exists('epg/afrique/' . $ids[$i] . date('Y-m-d', strtotime("now") + 86400 * $day))) {
                $ch3 = curl_init();
                curl_setopt($ch3, CURLOPT_URL, 'https://service.canal-overseas.com/ott-frontend/vector/83001/channel/' . $ids[$i] . '/events?filter.day=' . $day);
                curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch3, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:49.0) Gecko/20100101 Firefox/49.0");
                curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, 1);
                $res3 = curl_exec($ch3);
                curl_close($ch3);
                file_put_contents('epg/afrique/' . $ids[$i] . date('Y-m-d', strtotime("now") + 86400 * $day), $res3);
            } else {
                $res3 = file_get_contents('epg/afrique/' . $ids[$i] . date('Y-m-d', strtotime("now") + 86400 * $day));
            }
            $res3 = json_decode($res3, true);
            $json = $res3["timeSlices"];
            foreach ($json as $section) {
                foreach ($section["contents"] as $section2) {
                    $hs="OK";
                    $fp = fopen("channels/".$channels[$i].".xml", "a");
                    fputs($fp, '<programme start="' . date('YmdHis O', ($section2["startTime"])) . '" stop="' . date('YmdHis O', $section2["endTime"]) . '" channel="' . $channels[$i] . '">
	<title lang="fr">' . htmlspecialchars($section2["title"], ENT_XML1) . '</title>
	<desc lang="fr">Aucune description</desc>
	<category lang="fr">Inconnu</category>
	<icon src="' . htmlspecialchars($section2["URLImage"], ENT_XML1) . '" />
</programme>
');
                    fclose($fp);
                }
            }
        }
        if($hs == "HS")
            file_put_contents("tmp/hs_afrique.txt",file_get_contents("tmp/hs_afrique.txt").$channels[$i].chr(10));

    }
}