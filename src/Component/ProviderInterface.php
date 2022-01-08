<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\ValueObject\Channel;

interface ProviderInterface {

    public function __construct();

    /**
     * @param $channel
     * @param $date
     * @return Channel|false
     */
    public function constructEPG(string $channel, string $date);

    static function getPriority(): float;
}