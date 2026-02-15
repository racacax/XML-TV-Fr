<?php

namespace racacax\XmlTv\Component\UI;

use Closure;
use racacax\XmlTv\Component\ChannelsManager;

interface UI
{
    public function getClosure(array $threads, ChannelsManager $manager, string $logLevel): Closure;
}
