<?php
namespace racacax\XmlTv\Component\Export;

class GZExport extends AbstractExport implements ExportInterface
{

    public function __construct(array $_)
    {
    }

    public function export(string $exportPath, string $fileName, string $xmlContent): bool
    {
        $this->setStatus("Export de $fileName.xml.gz");
        $fullExportPath = $exportPath.$fileName.'.xml.gz';
        $result = file_put_contents($fullExportPath, gzencode($xmlContent));
        $this->setStatus($result ?"Export de $fileName.xml.gz terminé" : "Echec de l'export de $fileName.xml.gz");
        return $result;
    }
    public function getExtension() : string {
        return 'gz';
    }
}