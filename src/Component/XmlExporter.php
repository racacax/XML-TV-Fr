<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\ValueObject\Channel;

class XmlExporter
{

    /**
     * @var XmlFormatter
     */
    private $formatter;
    /**
     * @var \DOMDocument
     */
    private $content;
    /**
     * @var array
     */
    private $outputFormat;
    /**
     * @var string|null
     */
    private $sevenZipPath;
    /**
     * @var string
     */
    private $filePath;

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


    public function startExport(string $filePath)
    {
        $this->filePath = $filePath;

        $this->content = new \DOMDocument();
        $this->content->loadXML('<?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE tv SYSTEM "resources/validation/xmltv.dtd">
    <!-- Generated with XML TV Fr v1.5.1 -->
    <tv/>');
        $this->content->documentElement->setAttribute('source-info-url', "https://github.com/racacax/XML-TV-Fr");
        $this->content->documentElement->setAttribute('source-info-name', "XML TV Fr");
        $this->content->documentElement->setAttribute('generator-info-name', "XML TV Fr");
        $this->content->documentElement->setAttribute('generator-info-url', "https://github.com/racacax/XML-TV-Fr");

    }
    public function addChannel($channelKey, $name, $icon)
    {

        $channel = new \SimpleXMLElement('<channel/>');
        $channel->addAttribute('id', $channelKey);
        $channel->addChild('display-name', $name);
        if(!empty($icon)){
            $channel->addChild('icon')->addAttribute('src', $icon);
        }
        $this->content->documentElement->appendChild($this->content->importNode(dom_import_simplexml($channel), true));
    }

    public function addProgramsAsString(string $programs)
    {
        $root = simplexml_load_string("<root>$programs</root>");
        foreach($root->children() as $child) {
            $this->content->documentElement->appendChild($this->content->importNode(dom_import_simplexml($child), true));
        }
    }

    public function stopExport()
    {
        $content = $this->content->saveXML();
        //currently, the dtd validation doesn't work
        //$this->content->validate();
        if (in_array('xml', $this->outputFormat) || in_array('xz', $this->outputFormat)) {
            file_put_contents($this->filePath, $content);
        }
        if (in_array('gz', $this->outputFormat)) {
            $filename = $this->filePath.'.gz';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en GZ...\n");
            file_put_contents($filename, gzencode($content));
            Logger::log("\e[34m[EXPORT] \e[39mGZ : \e[32mOK\e[39m ($this->filePath.'.gz')\n");
        }

        if (in_array('zip', $this->outputFormat)) {
            $filename = $this->filePath.'.zip';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en ZIP...\n");
            $zip = new \ZipArchive();

            if (true !== $zip->open($filename, \ZipArchive::CREATE)) {
                Logger::log("\e[34m[EXPORT] \e[39mZIP : \e[31mHS\e[39m ($filename)\n");
                throw new \Exception('Impossible to create zip file '. $filename);
            }
            Logger::log("\e[34m[EXPORT] \e[39mZIP : \e[32mOK\e[39m ($filename)\n");
            $zip->addFromString($this->filePath, $content);
            $zip->close();
        }

        if (in_array('xz', $this->outputFormat)) {
            if(empty($this->zipBinPath)) {
                Logger::log("\e[34m[EXPORT] \e[31mImpossible d'exporter en XZ (chemin de 7zip non défini)\e[39m\n");
                return;
            }
            $filename = $this->filePath.'.xz';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en XZ...\n");
            $result = exec('"' . $this->zipBinPath . '" a -t7z "' . $filename . '" "' . $this->filePath . '"');
            Logger::log("\e[34m[EXPORT] \e[39mRéponse de 7zip : $result");

            if (!in_array('xml', $this->outputFormat)) {
                unlink($this->filePath);
            }
        }
    }

}