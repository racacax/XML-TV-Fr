<?php
date_default_timezone_set('Europe/Paris');
for($i=-1;$i<10;$i++)
{
    $days = array(
        'Mon'=> array('0000 || Voyage','0130 || Programme inconnu','0700 || Paysages','0830 || Animaux','1030 || Sports extrêmes','1200 || Découverte','1330 || Sports extrêmes','1530 || Découverte','1730 || Animaux','1900 || Voyage','2100 || Animaux','2300 || Sports extrêmes'),
        'Tue'=> array('0000 || Découverte','0130 || Programme inconnu','0700 || Paysages','0830 || Découverte','1030 || Voyage','1200 || Animaux','1330 || Animaux','1530 || Sports extrêmes','1730 || Sports extrêmes','1900 || Découverte','2100 || Découverte','2300 || Voyage'),
        'Wed'=> array('0000 || Animaux','0130 || Programme inconnu','0700 || Paysages','0830 || Sports extrêmes','1030 || Découverte','1200 || Sports extrêmes','1330 || Découverte','1530 || Animaux','1730 || Voyage','1900 || Animaux','2100 || Sports extrêmes','2300 || Découverte'),
        'Thu'=> array('0000 || Sports extrêmes','0130 || Programme inconnu','0700 || Paysages','0830 || Voyage','1030 || Découverte','1200 || Animaux','1330 || Sports extrêmes','1530 || Découverte','1730 || Animaux','1900 || Sports extrêmes','2100 || Voyage','2300 || Découverte'),
        'Fri'=> array('0000 || Animaux','0130 || Programme inconnu','0700 || Paysages','0830 || Découverte','1030 || Sports extrêmes','1200 || Découverte','1330 || Voyage','1530 || Animaux','1730 || Sports extrêmes','1900 || Animaux','2100 || Découverte','2300 || Sports extrêmes'),
        'Sat'=> array('0000 || Découverte','0130 || Programme inconnu','0700 || Paysages','0830 || Animaux','1030 || Animaux','1200 || Sports extrêmes','1330 || Découverte','1530 || Sports extrêmes','1730 || Voyage','1900 || Découverte','2100 || Animaux','2300 || Animaux'),
        'Sun'=> array('0000 || Sports extrêmes','0130 || Programme inconnu','0700 || Paysages','0830 || Sports extrêmes','1030 || Découverte','1200 || Voyage','1330 || Animaux','1530 || Animaux','1730 || Découverte','1900 || Sports extrêmes','2100 || Sports extrêmes','2300 || Animaux')
    );
    $d = $days[date('D',strtotime("now")+$i*86400)];
    for($j=0;$j<count($d);$j++)
    {
        $st = explode(' || ',$d[$j]);
        $st2 = explode(' || ',$d[$j+1])[0];
        $d1 = date('Ymd',strtotime("now")+86400*$i).$st[0].'00 '.date('O');
        $d2 = date('Ymd',strtotime("now")+86400*$i).$st2.'00 '.date('O');
        if(!$st2) {
            $d2 = date('Ymd',strtotime("now")+86400*$i+86400).'0000 '.date('O');
        }
        echo $d1.' - '.$d2.' - '.$st[1].chr(10);
        $fp = fopen("channels/UltraNature.xml","a");
        fputs($fp,'<programme start="'.$d1.'" stop="'.$d2.'" channel="UltraNature">
	<title lang="fr">'.htmlspecialchars($st[1],ENT_XML1).'</title>
	<desc lang="fr">Aucune description</desc>
	<category lang="fr">'.htmlspecialchars($st[1],ENT_XML1).'</category>
</programme>
');
        fclose( $fp );
    }
}