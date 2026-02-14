<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\Component\Export;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Export\GZExport;

class GZExportTest extends TestCase
{
    private string $testFolder = 'var/test/export_gz';

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
     * Test successful GZ export creates file at correct location
     */
    public function testSuccessfulExportCreatesFile(): void
    {
        $exporter = new GZExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_xmltv';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
        $this->assertFileExists($this->testFolder.'/'.$fileName.'.xml.gz');
    }

    /**
     * Test exported GZ file contains correct compressed content
     */
    public function testExportedFileHasCorrectContent(): void
    {
        $exporter = new GZExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_xmltv';

        $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        // Read and decompress the file
        $compressedContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml.gz');
        $decompressedContent = gzdecode($compressedContent);

        $this->assertEquals($xmlContent, $decompressedContent);
    }

    /**
     * Test export with special characters in XML content
     */
    public function testExportWithSpecialCharacters(): void
    {
        $exporter = new GZExport([]);
        $xmlContent = $this->generateSampleXML() . "\n<!-- Special chars: éàù ñ © -->";
        $fileName = 'test_special';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);

        $compressedContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml.gz');
        $decompressedContent = gzdecode($compressedContent);

        $this->assertEquals($xmlContent, $decompressedContent);
    }

    /**
     * Test export with large XML content
     */
    public function testExportWithLargeContent(): void
    {
        $exporter = new GZExport([]);
        $largeXml = $this->generateLargeXML(1000); // 1000 programs
        $fileName = 'test_large';

        $result = $exporter->export($this->testFolder.'/', $fileName, $largeXml);

        $this->assertTrue($result);

        $compressedContent = file_get_contents($this->testFolder.'/'.$fileName.'.xml.gz');
        $decompressedContent = gzdecode($compressedContent);

        $this->assertEquals($largeXml, $decompressedContent);
    }

    /**
     * Test failure when export path is invalid/not writable
     */
    public function testExportFailsWithInvalidPath(): void
    {
        $exporter = new GZExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_xmltv';
        $invalidPath = '/invalid/nonexistent/path/';

        // Suppress the warning that file_put_contents will emit
        $result = @$exporter->export($invalidPath, $fileName, $xmlContent);

        $this->assertFalse($result);
        $this->assertFileDoesNotExist($invalidPath.$fileName.'.xml.gz');
    }

    /**
     * Test getExtension returns correct extension
     */
    public function testGetExtensionReturnsGz(): void
    {
        $exporter = new GZExport([]);

        $this->assertEquals('gz', $exporter->getExtension());
    }

    /**
     * Test status is set correctly on success
     */
    public function testStatusIsSetOnSuccess(): void
    {
        $exporter = new GZExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_status';

        $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $status = $exporter->getStatus();
        $this->assertStringContainsString('terminé', $status);
        $this->assertStringContainsString($fileName.'.xml.gz', $status);
    }

    /**
     * Test status is set correctly on failure
     */
    public function testStatusIsSetOnFailure(): void
    {
        $exporter = new GZExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_fail';
        $invalidPath = '/invalid/path/';

        // Suppress the warning that file_put_contents will emit
        @$exporter->export($invalidPath, $fileName, $xmlContent);

        $status = $exporter->getStatus();
        $this->assertStringContainsString('Echec', $status);
        $this->assertStringContainsString($fileName.'.xml.gz', $status);
    }

    /**
     * Generate sample XMLTV content for testing
     */
    private function generateSampleXML(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<tv>
  <channel id="test.channel">
    <display-name>Test Channel</display-name>
  </channel>
  <programme start="20260214060000 +0100" stop="20260214070000 +0100" channel="test.channel">
    <title lang="fr">Test Program</title>
    <desc lang="fr">Test Description</desc>
  </programme>
</tv>
XML;
    }

    /**
     * Generate large XMLTV content with multiple programs
     */
    private function generateLargeXML(int $programCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<tv>'."\n";
        $xml .= '  <channel id="test.channel">'."\n";
        $xml .= '    <display-name>Test Channel</display-name>'."\n";
        $xml .= '  </channel>'."\n";

        for ($i = 0; $i < $programCount; $i++) {
            $startHour = 6 + ($i % 18);
            $endHour = $startHour + 1;
            $xml .= sprintf(
                '  <programme start="202602140%02d0000 +0100" stop="202602140%02d0000 +0100" channel="test.channel">'."\n",
                $startHour,
                $endHour
            );
            $xml .= '    <title lang="fr">Program '.$i.'</title>'."\n";
            $xml .= '  </programme>'."\n";
        }

        $xml .= '</tv>';

        return $xml;
    }
}
