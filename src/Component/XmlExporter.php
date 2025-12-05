<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class XmlExporter
{
    private XmlFormatter $formatter;
    private \DOMDocument $content;
    private array $outputFormat;
    private ?string $sevenZipPath;
    private string $filePath;

    public function __construct(array $outputFormat, ?string $sevenZipPath)
    {
        $this->formatter = new XmlFormatter();
        $this->outputFormat = $outputFormat;
        $this->sevenZipPath = $sevenZipPath;
    }

    public function getFormatter(): XmlFormatter
    {
        return $this->formatter;
    }


    public function startExport(string $filePath): void
    {
        $this->filePath = $filePath;

        $this->content = new \DOMDocument();
        $this->content->preserveWhiteSpace = false;
        $this->content->formatOutput = true;
        $this->content->loadXML('<?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE tv SYSTEM "resources/validation/xmltv.dtd">
    <!-- Generated with XML TV Fr v3.6.3 -->
    <tv/>');
        $this->content->documentElement->setAttribute('source-info-url', 'https://github.com/racacax/XML-TV-Fr');
        $this->content->documentElement->setAttribute('source-info-name', 'XML TV Fr');
        $this->content->documentElement->setAttribute('generator-info-name', 'XML TV Fr');
        $this->content->documentElement->setAttribute('generator-info-url', 'https://github.com/racacax/XML-TV-Fr');
    }
    public function addChannel($channelKey, $name, $icon): void
    {
        $channel = new \SimpleXMLElement('<channel/>');
        $channel->addAttribute('id', $channelKey);
        $channel->addChild(
            'display-name',
            str_replace('"', '&quot;', htmlspecialchars($name, ENT_XML1))
        );

        if (!empty($icon)) {
            $channel->addChild('icon')->addAttribute('src', $icon);
        }
        $this->content->documentElement->appendChild($this->content->importNode(dom_import_simplexml($channel), true));
    }

    public function addProgramsAsString(string $programs): void
    {
        $root = simplexml_load_string("<root>$programs</root>");
        foreach ($root->children() as $child) {
            $this->content->documentElement->appendChild($this->content->importNode(dom_import_simplexml($child), true));
        }
    }

    public function stopExport(): void
    {
        $this->content->loadXML($this->content->saveXML());

        if ($this->content->validate()) {
            Logger::log("\e[34m[EXPORT] \e[32mXML Valide\e[39m\n");
        } else {
            Logger::log("\e[34m[EXPORT] \e[31mXML non valide selon xmltv.dtd\e[39m\n");
        }

        $content = str_replace('"resources/validation/xmltv.dtd"', '"xmltv.dtd"', $this->content->saveXML());

        if (in_array('xml', $this->outputFormat) || in_array('xz', $this->outputFormat)) {
            file_put_contents($this->filePath, $content);
        }
        if (in_array('gz', $this->outputFormat)) {
            $filename = $this->filePath.'.gz';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en GZ...\n");
            file_put_contents($filename, gzencode($content));
            Logger::log("\e[34m[EXPORT] \e[39mGZ : \e[32mOK\e[39m ($this->filePath.'.gz')\n");
        }
        $split_fp = explode('.', $this->filePath);
        if (count($split_fp) == 1) {
            $lengthToRemove = 0;
        } else {
            $lengthToRemove = strlen('.'.end($split_fp));
        }
        $shortenedFilePath = substr($this->filePath, 0, -$lengthToRemove);
        $shortenedFileName = explode('/', $this->filePath);
        $shortenedFileName = end($shortenedFileName);
        if (in_array('zip', $this->outputFormat)) {
            $filename = $shortenedFilePath.'.zip';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en ZIP...\n");
            $zip = new \ZipArchive();

            if (true !== $zip->open($filename, \ZipArchive::CREATE)) {
                Logger::log("\e[34m[EXPORT] \e[39mZIP : \e[31mHS\e[39m ($filename)\n");

                throw new \Exception('Impossible to create zip file '. $filename);
            }
            Logger::log("\e[34m[EXPORT] \e[39mZIP : \e[32mOK\e[39m ($filename)\n");

            $zip->addFromString($shortenedFileName, $content);
            $zip->close();
        }

        if (in_array('xz', $this->outputFormat)) {
            if (empty($this->sevenZipPath)) {
                Logger::log("\e[34m[EXPORT] \e[31mImpossible d'exporter en XZ (chemin de 7zip non défini)\e[39m\n");

                return;
            }
            $filename = $shortenedFilePath.'.xz';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en XZ...\n");
            $result = exec('"' . $this->sevenZipPath . '" a -t7z "' . $filename . '" "' . $this->filePath . '"');
            Logger::log("\e[34m[EXPORT] \e[39mRéponse de 7zip : $result\n");

            if (!in_array('xml', $this->outputFormat)) {
                unlink($this->filePath);
            }
        }
    }
}
