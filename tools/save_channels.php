<?php
$data = file_get_contents('php://input');
if (!file_put_contents('../config/channels.json', $data)){
    throw new \Exception('impossible to create file');
}
echo true;