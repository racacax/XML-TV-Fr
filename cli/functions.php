<?php
chdir(__DIR__."/..");
require_once "classes/Utils.php";
function getClassesCache() {
    if(!defined('CLASSES_CACHE'))
        define('CLASSES_CACHE', Utils::getClasses());
    return CLASSES_CACHE;
}
function sortActive($a, $b) {
    if(@$a['is_active'] && !@$b['is_active'])
        return -1;
    if(@$b['is_active'] && !@$a['is_active'])
        return 1;
    if($a['key'] > $b['key']) {
        return 1;
    } elseif($a['key'] < $b['key']) {
        return -1;
    }
    return 0;
}
function getChannelsWithProvider() {
    $channels = array();
    foreach (getClassesCache() as $classe) {
        $instance = new $classe();
        foreach($instance->getChannelsKey() as $channel) {
            if(!isset($channels[$channel])) {
                $channels[$channel] = array("key"=>$channel, "available_providers"=>[$classe]);
            } else {
                $channels[$channel]["available_providers"][] = $classe;
            }
        }
    }
    foreach(getCurrentChannels() as $channel => $value) {
        if(isset($channels[$channel])) {
            $channels[$channel] = array_merge($channels[$channel], $value, array('is_active'=>true));
        }
    }
    usort($channels,"sortActive");
    return $channels;

}

function getProviderFromFileName($fileName) {
    $fileName = explode('/', $fileName);
    $fileName = $fileName[count($fileName) - 1];
    return ucfirst(explode('.', explode('_', $fileName)[1])[0]);
}

function getCurrentChannels() {
    $json = json_decode(file_get_contents("channels.json"), true);
    return $json;
}