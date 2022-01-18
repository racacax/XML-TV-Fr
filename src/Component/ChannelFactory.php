<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\StaticComponent\ChannelInformation;
use racacax\XmlTv\ValueObject\Channel;

class ChannelFactory
{
    private function __construct()
    {
    }

    public static function createChannel(string $channelKey): Channel
    {
        $info = ChannelInformation::getInstance();

        return new Channel(
            $channelKey,
            $info->getDefaultIcon($channelKey),
            $info->getDefaultName($channelKey)
        );
    }
}
