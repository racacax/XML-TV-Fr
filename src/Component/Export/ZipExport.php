<?php
namespace racacax\XmlTv\Component\Export;

class ZipExport extends AbstractExport implements ExportInterface
{

    public function __construct(array $_)
    {
    }

    public function export(string $exportPath, string $fileName, string $xmlContent): bool
    {
        $this->setStatus("Export de $fileName.zip");
        $fullPath = $exportPath.$fileName.'.zip';
        $zip = new \ZipArchive();

        if (true !== $zip->open($fullPath, \ZipArchive::CREATE)) {
            $this->setStatus("Impossible de créer le fichier $fileName.zip");
            return false;
        }
        $zip->addFromString($fileName.'.xml', $xmlContent);
        $zip->close();
        $this->setStatus("Export ZIP réussi");
        return true;
    }
    public function getExtension() : string {
        return 'zip';
    }
}