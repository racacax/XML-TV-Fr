<?php
chdir(__DIR__."/..");
$data = file_get_contents('php://input');
file_put_contents('channels.json', $data);