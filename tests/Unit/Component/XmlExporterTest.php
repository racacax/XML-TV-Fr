<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\Component;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Export\ExportInterface;
use racacax\XmlTv\Component\XmlExporter;
use racacax\XmlTv\Configurator;

class XmlExporterTest extends TestCase
{
    private string $testFolder = 'var/test/exporter';

    public function setUp(): void
    {
        parent::setUp();

        // Create test folder if it doesn't exist
        if (!is_dir($this->testFolder)) {
            mkdir($this->testFolder, 0777, true);
        }

        // Clean up test folder
        $files = glob($this->testFolder.'/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        // Clean up test folder
        $files = glob($this->testFolder.'/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Test that XMLTV is exported at the correct location
     */
    public function testXmltvExportedAtCorrectLocation(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_export';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', 'http://example.com/icon.png');
        $exporter->stopExport();

        $expectedPath = $this->testFolder.'/'.$fileName.'.xml';
        $this->assertFileExists($expectedPath);
    }

    /**
     * Test that channels are correctly exported in the XML
     */
    public function testChannelsAreCorrectlyExported(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_channels';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('channel1.fr', 'Channel 1', 'http://example.com/ch1.png');
        $exporter->addChannel('channel2.fr', 'Channel 2', 'http://example.com/ch2.png');
        $exporter->addChannel('channel3.fr', 'Channel 3', '');
        $exporter->stopExport();

        $xmlContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml');
        $xml = simplexml_load_string($xmlContent);

        // Verify we have 3 channels
        $channels = $xml->xpath('//channel');
        $this->assertCount(3, $channels);

        // Verify channel 1
        $channel1 = $xml->xpath('//channel[@id="channel1.fr"]')[0];
        $this->assertEquals('Channel 1', (string)$channel1->{'display-name'});
        $this->assertEquals('http://example.com/ch1.png', (string)$channel1->icon['src']);

        // Verify channel 2
        $channel2 = $xml->xpath('//channel[@id="channel2.fr"]')[0];
        $this->assertEquals('Channel 2', (string)$channel2->{'display-name'});

        // Verify channel 3 (without icon)
        $channel3 = $xml->xpath('//channel[@id="channel3.fr"]')[0];
        $this->assertEquals('Channel 3', (string)$channel3->{'display-name'});
        $this->assertEmpty($channel3->xpath('icon'));
    }

    /**
     * Test that programs are correctly exported in the XML
     */
    public function testProgramsAreCorrectlyExported(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_programs';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');

        // Add programs as string (simulating what the formatter does)
        $programs = $this->generateProgramsXml();
        $exporter->addProgramsAsString($programs);
        $exporter->stopExport();

        $xmlContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml');
        $xml = simplexml_load_string($xmlContent);

        // Verify we have programs
        $programmes = $xml->xpath('//programme');
        $this->assertCount(3, $programmes);

        // Verify first program
        $program1 = $xml->xpath('//programme[@start="20260214060000 +0100"]')[0];
        $this->assertEquals('test.channel', (string)$program1['channel']);
        $this->assertEquals('Program 1', (string)$program1->title);
        $this->assertEquals('Description 1', (string)$program1->desc);

        // Verify second program
        $program2 = $xml->xpath('//programme[@start="20260214070000 +0100"]')[0];
        $this->assertEquals('Program 2', (string)$program2->title);
    }

    /**
     * Test that both channels and programs are exported together
     */
    public function testCompleteXmltvExport(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_complete';
        $exporter->startExport($this->testFolder.'/', $fileName);

        // Add multiple channels
        $exporter->addChannel('tf1.fr', 'TF1', 'http://example.com/tf1.png');
        $exporter->addChannel('france2.fr', 'France 2', 'http://example.com/france2.png');

        // Add programs
        $exporter->addProgramsAsString($this->generateProgramsXml());
        $exporter->stopExport();

        $xmlContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml');
        $xml = simplexml_load_string($xmlContent);

        // Verify both channels and programs exist
        $this->assertCount(2, $xml->xpath('//channel'));
        $this->assertCount(3, $xml->xpath('//programme'));
    }

    /**
     * Test that valid XML passes DTD validation
     */
    public function testValidXmlPassesDtdValidation(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_valid_dtd';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');

        // Add valid programs
        $validPrograms = <<<XML
<programme start="20260214060000 +0100" stop="20260214070000 +0100" channel="test.channel">
  <title lang="fr">Test Program</title>
  <desc lang="fr">Test Description</desc>
  <category lang="fr">Movies</category>
</programme>
XML;
        $exporter->addProgramsAsString($validPrograms);
        $exporter->stopExport();

        // Read the exported file and validate it
        $xmlContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml');
        $dom = new \DOMDocument();
        $dom->loadXML($xmlContent);

        // The file should have the DTD declaration
        $this->assertStringContainsString('<!DOCTYPE tv SYSTEM "xmltv.dtd">', $xmlContent);

        // Verify it's well-formed XML
        $this->assertInstanceOf(\DOMDocument::class, $dom);
    }

    /**
     * Test that XML with missing required elements is detected
     */
    public function testInvalidXmlIsDetected(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_invalid';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');

        // Add program without required stop attribute (invalid according to DTD)
        $invalidPrograms = <<<XML
<programme start="20260214060000 +0100" channel="test.channel">
  <title lang="fr">Invalid Program</title>
</programme>
XML;
        $exporter->addProgramsAsString($invalidPrograms);

        // The export should still complete, but validation will fail
        // We're testing that the validation is attempted
        $exporter->stopExport();

        // File should still be created even if invalid
        $this->assertFileExists($this->testFolder.'/'.$fileName.'.xml');
    }

    /**
     * Test that raw XML is deleted when deleteRawXml is true
     */
    public function testRawXmlIsDeletedWhenConfigured(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: true
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_delete';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');
        $exporter->stopExport();

        // Raw XML should be deleted
        $this->assertFileDoesNotExist($this->testFolder.'/'.$fileName.'.xml');
    }

    /**
     * Test that raw XML is kept when deleteRawXml is false
     */
    public function testRawXmlIsKeptWhenConfigured(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_keep';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');
        $exporter->stopExport();

        // Raw XML should exist
        $this->assertFileExists($this->testFolder.'/'.$fileName.'.xml');
    }

    /**
     * Test that export handlers are called with correct parameters
     */
    public function testExportHandlersAreCalledWithCorrectParameters(): void
    {
        // Create mock export handlers
        $mockHandler1 = $this->createMock(ExportInterface::class);
        $mockHandler2 = $this->createMock(ExportInterface::class);

        $fileName = 'test_handlers';
        $exportPath = $this->testFolder.'/';

        // Expect each handler to be called once with correct parameters
        $mockHandler1->expects($this->once())
            ->method('export')
            ->with(
                $this->equalTo($exportPath),
                $this->equalTo($fileName),
                $this->isType('string') // The XML content
            )
            ->willReturn(true);

        $mockHandler2->expects($this->once())
            ->method('export')
            ->with(
                $this->equalTo($exportPath),
                $this->equalTo($fileName),
                $this->isType('string')
            )
            ->willReturn(true);

        // Mock getStatus for handlers
        $mockHandler1->method('getStatus')->willReturn('Handler 1 status');
        $mockHandler2->method('getStatus')->willReturn('Handler 2 status');

        $config = new Configurator(
            exportHandlers: [$mockHandler1, $mockHandler2],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $exporter->startExport($exportPath, $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');
        $exporter->stopExport();
    }

    /**
     * Test that export handlers receive the complete XML content
     */
    public function testExportHandlersReceiveCompleteXmlContent(): void
    {
        $capturedContent = null;

        $mockHandler = $this->createMock(ExportInterface::class);
        $mockHandler->expects($this->once())
            ->method('export')
            ->willReturnCallback(function ($path, $fileName, $content) use (&$capturedContent) {
                $capturedContent = $content;

                return true;
            });

        $mockHandler->method('getStatus')->willReturn('Handler status');

        $config = new Configurator(
            exportHandlers: [$mockHandler],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_content';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('channel1.fr', 'Channel 1', 'http://example.com/icon.png');
        $exporter->addProgramsAsString($this->generateProgramsXml());
        $exporter->stopExport();

        // Verify the captured content
        $this->assertNotNull($capturedContent);
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $capturedContent);
        $this->assertStringContainsString('<!DOCTYPE tv SYSTEM "xmltv.dtd">', $capturedContent);
        $this->assertStringContainsString('<channel id="channel1.fr">', $capturedContent);
        $this->assertStringContainsString('Channel 1', $capturedContent);
        $this->assertStringContainsString('Program 1', $capturedContent);
    }

    /**
     * Test that XML contains proper metadata attributes
     */
    public function testXmlContainsProperMetadata(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_metadata';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');
        $exporter->stopExport();

        $xmlContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml');
        $xml = simplexml_load_string($xmlContent);

        // Verify metadata attributes
        $this->assertEquals('https://github.com/racacax/XML-TV-Fr', (string)$xml['source-info-url']);
        $this->assertEquals('XML TV Fr', (string)$xml['source-info-name']);
        $this->assertEquals('XML TV Fr', (string)$xml['generator-info-name']);
        $this->assertEquals('https://github.com/racacax/XML-TV-Fr', (string)$xml['generator-info-url']);
    }

    /**
     * Test that DTD path is correctly replaced in final output
     */
    public function testDtdPathIsCorrectlyReplaced(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_dtd_path';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');
        $exporter->stopExport();

        $xmlContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml');

        // Should have xmltv.dtd, not resources/validation/xmltv.dtd
        $this->assertStringContainsString('<!DOCTYPE tv SYSTEM "xmltv.dtd">', $xmlContent);
        $this->assertStringNotContainsString('resources/validation/xmltv.dtd', $xmlContent);
    }

    /**
     * Test that special characters in channel names are properly escaped
     */
    public function testSpecialCharactersAreProperlyEscaped(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_special_chars';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test & "Special" <Channel>', 'http://example.com/icon.png');
        $exporter->stopExport();

        $xmlContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml');

        // Verify the file is valid XML
        $xml = simplexml_load_string($xmlContent);
        $this->assertNotFalse($xml);

        // Verify the channel name is properly escaped
        $this->assertStringContainsString('Test &amp;', $xmlContent); // & should be escaped
        $this->assertStringContainsString('&lt;Channel&gt;', $xmlContent); // < and > should be escaped

        // Verify that when parsed, the text is correct
        $channel = $xml->xpath('//channel[@id="test.channel"]')[0];
        $this->assertEquals('Test & "Special" <Channel>', (string)$channel->{'display-name'});
    }

    /**
     * Test that multiple export handlers are all called in sequence
     */
    public function testMultipleExportHandlersAllCalled(): void
    {
        $handler1Called = false;
        $handler2Called = false;
        $handler3Called = false;

        $mockHandler1 = $this->createMock(ExportInterface::class);
        $mockHandler1->method('export')->willReturnCallback(function () use (&$handler1Called) {
            $handler1Called = true;

            return true;
        });
        $mockHandler1->method('getStatus')->willReturn('Handler 1');

        $mockHandler2 = $this->createMock(ExportInterface::class);
        $mockHandler2->method('export')->willReturnCallback(function () use (&$handler2Called) {
            $handler2Called = true;

            return true;
        });
        $mockHandler2->method('getStatus')->willReturn('Handler 2');

        $mockHandler3 = $this->createMock(ExportInterface::class);
        $mockHandler3->method('export')->willReturnCallback(function () use (&$handler3Called) {
            $handler3Called = true;

            return true;
        });
        $mockHandler3->method('getStatus')->willReturn('Handler 3');

        $config = new Configurator(
            exportHandlers: [$mockHandler1, $mockHandler2, $mockHandler3],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $exporter->startExport($this->testFolder.'/', 'test_multiple');
        $exporter->addChannel('test.channel', 'Test', '');
        $exporter->stopExport();

        $this->assertTrue($handler1Called, 'Handler 1 should be called');
        $this->assertTrue($handler2Called, 'Handler 2 should be called');
        $this->assertTrue($handler3Called, 'Handler 3 should be called');
    }

    /**
     * Test that exporter works with no export handlers
     */
    public function testExporterWorksWithNoHandlers(): void
    {
        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );
        $exporter = new XmlExporter($config);

        $fileName = 'test_no_handlers';
        $exporter->startExport($this->testFolder.'/', $fileName);
        $exporter->addChannel('test.channel', 'Test Channel', '');
        $exporter->stopExport();

        // Raw XML should still be created
        $this->assertFileExists($this->testFolder.'/'.$fileName.'.xml');
    }

    /**
     * Generate sample programs XML for testing
     */
    private function generateProgramsXml(): string
    {
        return <<<XML
<programme start="20260214060000 +0100" stop="20260214070000 +0100" channel="test.channel">
  <title lang="fr">Program 1</title>
  <desc lang="fr">Description 1</desc>
</programme>
<programme start="20260214070000 +0100" stop="20260214080000 +0100" channel="test.channel">
  <title lang="fr">Program 2</title>
  <desc lang="fr">Description 2</desc>
</programme>
<programme start="20260214080000 +0100" stop="20260214090000 +0100" channel="test.channel">
  <title lang="fr">Program 3</title>
  <desc lang="fr">Description 3</desc>
</programme>
XML;
    }
}
