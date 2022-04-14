<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use racacax\XmlTv\Component\ProviderInterface;

class MyCanalCH extends MyCanal implements ProviderInterface
{
    protected $apiKey = '9989c08397738c9bd2f99ec3fa602182';
    protected $region = 'ch';
}
