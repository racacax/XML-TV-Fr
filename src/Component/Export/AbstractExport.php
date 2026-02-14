<?php

namespace racacax\XmlTv\Component\Export;

class AbstractExport
{
    protected string $status;

    protected function setStatus(string $status): void
    {
        $this->status = $status;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
}
