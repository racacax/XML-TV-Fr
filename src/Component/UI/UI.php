<?php

namespace racacax\XmlTv\Component\UI;

use Closure;
use racacax\XmlTv\Component\ChannelsManager;

interface UI
{
    public function getClosure(array $threads, ChannelsManager $manager, array $guide, string $logLevel, int $index, int $guidesCount): Closure;
}
