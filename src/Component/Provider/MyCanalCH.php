<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use racacax\XmlTv\Component\ProviderInterface;

class MyCanalCH extends MyCanal implements ProviderInterface
{
    protected $region = 'ch';
}
