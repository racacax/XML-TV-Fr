<?php
// check_mycanal.php
// Checks whether the current IP can retrieve a MyCanal API token.
// Exit 0: token OK
// Exit 2: blocked (launch.sh will retry with a new VPN IP)

require_once __DIR__ . '/vendor/autoload.php';

use racacax\XmlTv\Component\Provider\MyCanal;
use racacax\XmlTv\Configurator;

try {
    $provider = new MyCanal(Configurator::getDefaultClient());
    $token = $provider->getApiKey();
    echo '[MyCanal check] Token OK (' . substr($token, 0, 8) . '...)' . PHP_EOL;
    exit(0);
} catch (\Exception $e) {
    echo '[MyCanal check] ' . $e->getMessage() . PHP_EOL;
    exit(2);
}
