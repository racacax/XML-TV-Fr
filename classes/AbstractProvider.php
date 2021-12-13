<?php
require_once "Provider.php";
abstract class AbstractProvider implements Provider {
    protected $channelObj;
    public function constructEPG($channel,$date) {
        $this->channelObj = new Channel($channel, $date);
    }
}