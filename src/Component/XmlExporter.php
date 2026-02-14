<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\Configurator;

class XmlExporter
{
    private XmlFormatter $formatter;
    private \DOMDocument $content;
    private Configurator $configurator;
    private string $exportPath;
    private string $fileName;

    public function __construct(Configurator $configurator)
    {
        $this->formatter = new XmlFormatter();
        $this->configurator = $configurator;
    }

    public function getFormatter(): XmlFormatter
    {
        return $this->formatter;
    }

    public function getConfigurator(): Configurator
    {
        return $this->configurator;
    }


    public function startExport(string $exportPath, string $fileName): void
    {
        $this->exportPath = $exportPath;
        $this->fileName = $fileName;

        $this->content = new \DOMDocument();
        $this->content->preserveWhiteSpace = false;
        $this->content->formatOutput = true;
        $this->content->loadXML('<?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE tv SYSTEM "resources/validation/xmltv.dtd">
    <!-- Generated with XML TV Fr v4.0.0 -->
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

        $rawXMLPath = $this->exportPath.$this->fileName.'.xml';
        Logger::log("\e[34m[EXPORT] \e[39mExport du XMLTV Brut...\n");
        file_put_contents($rawXMLPath, $content);
        foreach ($this->configurator->getExportHandlers() as $handler) {
            $className = explode('\\', get_class($handler));
            Logger::log("\e[34m[EXPORT] \e[39mLancement de ".end($className)."...\n");
            $handler->export($this->exportPath, $this->fileName, $content);
        }
        if ($this->configurator->isDeleteRawXml()) {
            Logger::log("\e[34m[EXPORT] \e[39mSuppression du XML Brut...\n");
            unlink($rawXMLPath);
        }
    }
}
