<?php

namespace racacax\XmlTv\Component\Export;

class GZExport extends AbstractExport implements ExportInterface
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
        $this->setStatus("Export de $fileName.xml.gz", 'cyan');
        $fullExportPath = $exportPath.$fileName.'.xml.gz';
        $result = file_put_contents($fullExportPath, gzencode($xmlContent));
        $this->setStatus($result ? "Export de $fileName.xml.gz terminé" : "Echec de l'export de $fileName.xml.gz", $result ? 'green' : 'red');

        return $result;
    }
    public function getExtension(): string
    {
        return 'gz';
    }
}
