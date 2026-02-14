<?php

use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\StaticComponent\ChannelInformation;

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
function getChannelsWithProvider($index=0) {
    $channels = array();
    $client = getConfig()->getDefaultClient();
    foreach (Utils::getProviders() as $classe) {
        $instance = new $classe($client);
        foreach(array_keys($instance->getChannelsList()) as $channel) {
            if(empty($channels[$channel])) {
                $channels[$channel] = array("is_dummy"=>false, "key"=>$channel, "available_providers"=>[]);
            }

            $channels[$channel]["available_providers"][] = [
                'className' => get_class($instance),
                'label' => getProviderName(get_class($instance))
            ];
        }
    }
    foreach(getCurrentChannels($index) as $channel => $value) {
        $defaultValue = !empty($channels[$channel]) ? array('is_active'=>true) : array("is_dummy"=>true, "key"=>$channel, "available_providers"=>[], 'is_active'=>true);
        $channels[$channel] = array_merge(
            $defaultValue,
            $channels[$channel] ?? [],
            $value
        );
    }
    usort($channels,"sortActive");

    return $channels;
}

function getCurrentChannels($index=0) {
    $channels = getConfig()->getguides()[$index]['channels'];
    $json = json_decode(file_get_contents("../".$channels), true);
    return $json;
}
function getProviderName(string $className): string
{
    $tmp = explode('\\', $className);

    return end($tmp);
}

function getConfig() {
    return \racacax\XmlTv\Configurator::initFromConfigFile("../config/config.json");
}