<?php
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\StaticComponent\ChannelInformation;

require_once '../vendor/autoload.php';
require_once "./functions.php";
$data = file_get_contents('php://input');
if (!file_put_contents('../'.getConfig()->getguides()[intval($_GET['index']) ?? 0]['channels'], $data)){
    throw new \Exception('impossible to create file');
}
echo true;