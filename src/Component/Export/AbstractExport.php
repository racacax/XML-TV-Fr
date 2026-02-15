<?php

namespace racacax\XmlTv\Component\Export;

use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\Utils;

class AbstractExport
{
    protected string $status;

    protected function setStatus(string $status, string $color = 'green'): void
    {
        $this->status = $status;
        Logger::log("\e[34m[EXPORT] ".Utils::colorize($status."\n", $color));
    }
    public function getStatus(): string
    {
        return $this->status;
    }
}
