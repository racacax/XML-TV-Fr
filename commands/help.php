<?php
$commands = [
    "help" => "Aide à propos du programme",
    "export" => "Générer le XMLTV.\n\tParamètres :
    \t   --skip-generation : Réaliser l'export sans génération (utilise uniquement le cache)
    \t   --keep-cache: Garde le cache même si expiré",
    "fetch-channel" => "Récupérer le programme d'une chaine pour une journée et un provider donné (dans var/cache/).
    
    \tUtilisation:
    \t\tphp manager.php fetch-channel [CHANNEL] [PROVIDER] [DATE] [FILENAME]

    \tExample:
    \t\tphp manager.php fetch-channel TF1.fr Orange 2025-12-14 content.xml",
    "update-default-logos" => "Mettre à jour tous les logos par défaut depuis un provider donné.
    
    \tUtilisation:
    \t\tphp manager.php update-default-logos [PROVIDER]

    \tExample:
    \t\tphp manager.php update-default-logos MyCanal"
];
echo "\033[1mListe des commandes\n\n";
foreach ($commands as $command => $desc) {
    echo "\033[1m".$command."\t\033[0m".$desc."\n\n";
}