<?php

namespace racacax\XmlTv\Component\Export;

class ZipExport extends AbstractExport implements ExportInterface
{
    /**
     * @param array<mixed> $params Unused - required by ExportInterface
     * @phpstan-param array<mixed> $params
     */
    public function __construct(array $params)
    {
        unset($params);
    }

    public function export(string $exportPath, string $fileName, string $xmlContent): bool
    {
        $this->setStatus("Export de $fileName.zip", 'cyan');
        $fullPath = $exportPath.$fileName.'.zip';
        $zip = new \ZipArchive();

        if (true !== $zip->open($fullPath, \ZipArchive::CREATE)) {
            $this->setStatus("Impossible de créer le fichier $fileName.zip", 'red');

            return false;
        }
        $zip->addFromString($fileName.'.xml', $xmlContent);
        $zip->close();
        $this->setStatus('Export ZIP réussi');

        return true;
    }
    public function getExtension(): string
    {
        return 'zip';
    }
}
