<?php
require_once "Provider.php";
abstract class AbstractProvider {
    protected $channelObj;
    protected $channelsList;
    public function __construct($jsonPath)
    {
        if (!isset($this->channelsList) && file_exists($jsonPath)) {
            $constantHash = 'channelsList_'.md5($jsonPath);
            if(defined($constantHash)) {
                $this->channelsList = constant($constantHash);
            } else {
                $this->channelsList = json_decode(file_get_contents($jsonPath), true);
                define($constantHash, $this->channelsList);
            }
        }
    }

    public function constructEPG($channel,$date) {
        $this->channelObj = new Channel($channel, $date, get_class($this));
    }

    /**
     * @return mixed
     */
    public function getChannelsList()
    {
        return $this->channelsList;
    }

    public function channelExists($channel) {
        return isset($this->getChannelsList()[$channel]);
    }
}