<?php
date_default_timezone_set('Europe/Paris');
set_time_limit(0);
function compare_classe($a,$b)
{
    if(class_exists($a) && class_exists($b))
    {
        if(call_user_func($a. "::getPriority") > call_user_func($b. "::getPriority"))
            return -1;
        return 1;
    } else {
        return 0;
    }
}
$classes = glob('classes/*.php');
$PROVIDER = 'Provider';
$UTILS = 'Provider';
$classes_priotity = array();
$XML_PATH = "channels/";
$DAY_LIMIT = 8;
$CLASS_PREFIX = "EPG_";
foreach($classes as $classe) {
    require_once $classe;
    $class_name = explode('/',explode('.php',$classe)[0]);
    $class_name = $class_name[count($class_name)-1];
    if(class_exists($class_name) && $class_name != $PROVIDER && $class_name != $UTILS)
    {
        if(method_exists(new $class_name($XML_PATH),'getPriority' ) && method_exists(new $class_name($XML_PATH),'constructEPG' ))
            $classes_priotity[] = $class_name;
    }
}
usort($classes_priotity,"compare_classe");
if(!file_exists('channels.json'))
{
    echo 'channels.json manquant';
}
$channels = json_decode(file_get_contents('channels.json'),true);
$channels_key = array_keys($channels);
foreach($channels_key as $channel)
{
    if(isset($channels[$channel]["priority"]) && count($channels[$channel]["priority"]) > 0)
    {
        $priority = $channels[$channel];
    } else {
        $priority = $classes_priotity;
    }
    for($i=-1;$i<$DAY_LIMIT;$i++)
    {
        $date = date('Y-m-d',time()+86400*$i);
        if(!file_exists(Utils::generateFilePath($XML_PATH,$channel,$date))) {
            $success = false;
            foreach ($priority as $classe) {
                if(!class_exists($classe))
                    break;
                if(!isset(${$CLASS_PREFIX.$classe}))
                    ${$CLASS_PREFIX.$classe} = new $classe($XML_PATH);
                if(${$CLASS_PREFIX.$classe}->constructEPG($channel,$date))
                {
                    echo $date." - ".$channel." : OK ".$classe.chr(10);
                    $success = true;
                    break;
                }
            }
            if(!$success)
            {
                echo $date." - ".$channel." : HS".chr(10);
            }
        } else {
            echo $date." - ".$channel." : Cache".chr(10);
        }
    }
}
$xmltv = glob('xmltv/xmltv*');
foreach($xmltv as $file)
    unlink($file);

$filepath = "xmltv/xmltv.xml";
$files = glob($XML_PATH.'*');
foreach($files as $file){
    if(time()-filemtime($file) > 864000)
        unlink($file);
}
$out = fopen($filepath, "w");
fwrite($out,'<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE tv SYSTEM "xmltv.dtd">

<tv source-info-url="http://allfrtv.com/" source-info-name="XML TV Fr" generator-info-name="XML TV Fr" generator-info-url="http://allfrtv.com/">
  ');
foreach($channels as $key => $channel)
{
    @$icon = $channel['icon'];
    @$name = $channel['name'];
    fwrite($out,'<channel id="'.$key.'">
    <display-name>'.$name.'</display-name>
    <icon src="'.$icon.'" />
  </channel>');
}


foreach($files as $file){
    $in = fopen($file, "r");
    while ($line = fgets($in)){
        print $file;
        fwrite($out, $line);
    }
    fclose($in);
}
fwrite($out,'</tv>');
fclose($out);