<?php
$commands = [
    "help" => "Aide à propos du programme",
    "export" => "Générer le XMLTV.\n\tParamètres :
    \t   --skip-generation : Réaliser l'export sans génération (utilise uniquement le cache)
    \t   --keep-cache: Garde le cache même si expiré"
];
echo "\033[1mListe des commandes\n\n";
foreach ($commands as $command => $desc) {
    echo "\033[1m".$command." : \033[0m".$desc."\n\n";
}