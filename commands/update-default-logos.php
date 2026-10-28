<?php
require_once 'vendor/autoload.php';
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\Configurator;

$providerName = $argv[2];

$provider = Utils::getProvider($providerName);
$client = Configurator::getDefaultClient();
$providerClass = Utils::getProvider($providerName);
$provider = new $providerClass($client, null);
$path = "resources/information/default_channels_infos.json";
$defaultChannelInfos = json_decode(file_get_contents($path), true);
Utils::colorize("Mise à jour des logos depuis $providerName...", "green")."\n";
$count = 0;
foreach ($defaultChannelInfos as $key => $channelInfo) {
    try{
        $logoUrl = $provider->getLogo($key);
        if($logoUrl) {
            $count++;
            $defaultChannelInfos[$key]['icon'] = $logoUrl;
            echo Utils::colorize("$key", "cyan")."\n";
        }
    } catch (\Exception $e) {
    }
}
// Keep the file format (2 spaces indentation, readable characters) to get a minimal diff
$json = json_encode($defaultChannelInfos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$json = preg_replace_callback('/^( {4})+/m', fn ($m) => str_repeat('  ', intdiv(strlen($m[0]), 4)), $json);
file_put_contents($path, $json."\n");
echo Utils::colorize("$count logos mis à jour", "green")."\n";