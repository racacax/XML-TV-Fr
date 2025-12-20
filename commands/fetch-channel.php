<?php
require_once 'vendor/autoload.php';
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\Configurator;

$channel = $argv[2];
$providerName = $argv[3];
$date = $argv[4];
$file = $argv[5];

$provider = Utils::getProvider($providerName);
$client = Configurator::getDefaultClient();
$providerClass = Utils::getProvider($providerName);
$provider = new $providerClass($client, null);


date_default_timezone_set('Europe/Paris');
$obj = $provider->constructEpg($channel, $date);
if ($obj === false || $obj->getProgramCount() === 0) {
    $data = 'false';
} else {
    $formatter = new XmlFormatter();
    $data = $formatter->formatChannel($obj, $provider);
}
file_put_contents($file, $data);
echo Utils::colorize("Contenu exporté vers $file", "green")."\n";