<?php

chdir(__DIR__."/..");
$files = glob('logs/*');
$file = $files[count($files) -1];
$json = json_decode(file_get_contents($file), true);
?>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logs</title>
    <style>
        td, th {
            border: black 1px solid;
        }
    </style>
</head>
<body>
<table>
    <tr>
        <th>Chaine</th>
        <?php
            foreach(array_keys($json['channels']) as $date) {
                echo '<th>'.$date.'</th>';
            }
        ?>
    </tr>
    <?php foreach(array_keys(array_values($json['channels'])[0]) as $channel) { ?>
    <tr>
        <td><?php echo $channel; ?></td>
        <?php
        foreach(array_keys($json['channels']) as $date) {
            $content = $json['channels'][$date][$channel];
            echo '<th style="background: '.(($content['success']) ? 'green' : 'red').'">'.@$content['provider'].((@$content['cache']) ? ' (Cache)' : '').'</th>';
        }
        ?>
    </tr>
    <?php } ?>
</table>
</body>
</html>
