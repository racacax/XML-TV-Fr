<?php
require_once "Provider.php";
abstract class AbstractProvider {
    protected $channelObj;
    protected $channelsList;
    protected static $priority;
    public function __construct($jsonPath, $priority)
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
        self::$priority[static::class] = $priority;
    }

    public static function getPriority() {
        return CONFIG['custom_priority_orders'][static::class] ?? self::$priority[static::class];
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

    protected function getContentFromURL($url) {
        $ch1 = curl_init();
        curl_setopt($ch1, CURLOPT_URL, $url);
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0');
        $res1 = html_entity_decode(curl_exec($ch1),ENT_QUOTES);
        curl_close($ch1);
        return $res1;
    }
}