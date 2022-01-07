<?php
declare(strict_types=1);

namespace racacax\XmlTv\StaticComponent;

final  class ChannelInformation
{
    private static $instance;
    private $channelInfo = [];

    private function __construct()
    {
        $this->channelInfo = json_decode(file_get_contents('resources/information/default_channels_infos.json'), true);
    }

    public static function getInstance(): self
    {
        return self::$instance ?? self::$instance = new self();
    }

    public function getDefaultIcon($channelKey): ?string
    {
        return $this->channelInfo[$channelKey]['icon'] ?? null;
    }

    public function getDefaultName($channelKey)
    {
        return $this->channelInfo[$channelKey]['name'] ?? null;
    }

}