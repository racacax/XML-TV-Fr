<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

class XmlExporter
{

    /**
     * @var XmlFormatter
     */
    private $formatter;
    /**
     * @var string
     */
    private $exportPath;
    /**
     * @var string
     */
    private $filename;
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

    public function __construct(string $exportPath, array $outputFormat, ?string $sevenZipPath)
    {
        $this->formatter = new XmlFormatter();
        $this->exportPath = $exportPath;
        $this->outputFormat = $outputFormat;
        $this->sevenZipPath = $sevenZipPath;
    }

    public function exportChannel(Channel $channel, string $date, ?ProviderInterface $provider)
    {

    }

    public function getFormatter(): XmlFormatter
    {
        return $this->formatter;
    }


    public function startExport(string $filename)
    {
        $this->filename = $this->exportPath .'/'. $filename;

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
        if (in_array('xml', $this->outputFormat)) {
            file_put_contents($this->filename, $content);
        }
        if (in_array('gz', $this->outputFormat)) {
            $filename = $this->filename.'.gz';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en GZ...\n");
            file_put_contents($filename, gzencode($content));
            Logger::log("\e[34m[EXPORT] \e[39mGZ : \e[32mOK\e[39m ($this->filename.'.gz')\n");
        }

        if (in_array('zip', $this->outputFormat)) {
            $filename = $this->filename.'.zip';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en ZIP...\n");
            $zip = new \ZipArchive();

            if (true !== $zip->open($filename, \ZipArchive::CREATE)) {
                Logger::log("\e[34m[EXPORT] \e[39mZIP : \e[31mHS\e[39m ($filename)\n");
                throw new \Exception('Impossible to create zip file '. $filename);
            }
            Logger::log("\e[34m[EXPORT] \e[39mZIP : \e[32mOK\e[39m ($filename)\n");
            $zip->addFromString($this->filename, $content);
            $zip->close();
        }

        if (in_array('xz', $this->outputFormat)) {
            if(empty($this->zipBinPath)) {
                Logger::log("\e[34m[EXPORT] \e[31mImpossible d'exporter en XZ (chemin de 7zip non défini)\e[39m\n");
                return;
            }
            file_put_contents($this->filename, $content);
            $filename = $this->filename.'.xz';
            Logger::log("\e[34m[EXPORT] \e[39mCompression du XMLTV en XZ...\n");
            $result = exec('"' . $this->zipBinPath . '" a -t7z "' . $filename . '" "' . $this->filename . '"');
            Logger::log("\e[34m[EXPORT] \e[39mRéponse de 7zip : $result");
        }
    }

}