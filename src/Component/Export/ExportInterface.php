<?php

namespace racacax\XmlTv\Component\Export;

interface ExportInterface
{
    public function __construct(array $params);
    public function export(string $exportPath, string $fileName, string $xmlContent): bool;
    public function getExtension(): string;
    public function getStatus(): string;
}
