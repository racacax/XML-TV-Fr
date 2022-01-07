<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

interface ProviderInterface {

    public function __construct();

    /**
     * @param $channel
     * @param $date
     * @return mixed
     */
    public function constructEPG($channel,$date);

    static function getPriority();
}