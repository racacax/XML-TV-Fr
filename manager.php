<?php
$commands = ["export", "help", "fetch-channel", "update-default-logos"];
if(in_array($argv[1], $commands)) {
    include "commands/".$argv[1].".php";
} else {
    echo "La commande $argv[1] n'existe pas";
}