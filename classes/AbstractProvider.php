<?php
require_once "Provider.php";
abstract class AbstractProvider {
    protected $channelObj;
    protected $CHANNELS_LIST;
    protected $CHANNELS_KEY;
    public function __construct($jsonPath)
    {
        if (!isset($this->CHANNELS_LIST) && file_exists($jsonPath)) {
            $constantHash = 'channels_list_'.md5($jsonPath);
            if(defined($constantHash)) {
                $this->CHANNELS_LIST = constant($constantHash);
            } else {
                $this->CHANNELS_LIST = json_decode(file_get_contents($jsonPath), true);
                define($constantHash, $this->CHANNELS_LIST);
            }
            $this->CHANNELS_KEY = array_keys($this->CHANNELS_LIST);
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
        return $this->CHANNELS_LIST;
    }

    /**
     * @return array
     */
    public function getChannelsKey(): array
    {
        return $this->CHANNELS_KEY;
    }
}