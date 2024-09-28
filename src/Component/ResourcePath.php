<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class ResourcePath
{
    /**
     * @var ResourcePath|null
     */
    private static ?ResourcePath $instance;
    /**
     * @var string
     */
    private string $resourcePath;

    private function __construct()
    {
        $this->resourcePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources';
    }

    public static function getInstance(): self
    {
        return self::$instance ?? self::$instance = new self();
    }

    public function getChannelPath(string $channel): string
    {
        return implode(
            DIRECTORY_SEPARATOR,
            [
                $this->resourcePath,
                'channel_config',
                $channel
            ]
        );
    }


    public function getChannelInfoPath(): string
    {
        return implode(
            DIRECTORY_SEPARATOR,
            [
                $this->resourcePath,
                'information',
                'default_channels_infos.json'
            ]
        );
    }

    public function getRatingPictoPath(): string
    {
        return implode(
            DIRECTORY_SEPARATOR,
            [
                $this->resourcePath,
                'information',
                'ratings_picto.json'
            ]
        );
    }
}
