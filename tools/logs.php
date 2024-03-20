<?php
require_once 'functions.php';
$files = glob('../var/logs/*');
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
<?php foreach ($json as $key => $gr) { ?>
        <?php echo $key; ?><br/>
<table>
    <tr>
        <th>Chaine</th>
        <?php
            foreach(array_keys($gr['channels']) as $date) {
                echo '<th>'.$date.'</th>';
            }
        ?>
    </tr>
    <?php foreach(array_keys(array_values($gr['channels'])[0]) as $channel) { ?>
    <tr>
        <td><?php echo $channel; ?></td>
        <?php
        foreach(array_keys($gr['channels']) as $date) {
            $content = $gr['channels'][$date][$channel];
            echo '<th style="background: '.(($content['success']) ? 'green' : 'red').'">'.getProviderName(@$content['provider'] ?? '').((@$content['cache']) ? ' (Cache)' : '').'</th>';
        }
        ?>
    </tr>
    <?php } ?>
</table>
<?php } ?>
</body>
</html>
