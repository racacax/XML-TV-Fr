<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\Component\Export;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Export\ZipExport;

class ZipExportTest extends TestCase
{
    private string $testFolder = 'var/test/export_zip';

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
     * Test successful ZIP export creates file at correct location
     */
    public function testSuccessfulExportCreatesFile(): void
    {
        $exporter = new ZipExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_xmltv';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
        $this->assertFileExists($this->testFolder.'/'.$fileName.'.zip');
    }

    /**
     * Test exported ZIP file contains XML file with correct content
     */
    public function testExportedZipContainsCorrectContent(): void
    {
        $exporter = new ZipExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_xmltv';

        $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        // Extract and verify content
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->testFolder.'/'.$fileName.'.zip'));

        // Check that the XML file is in the zip
        $this->assertEquals(1, $zip->numFiles);
        $this->assertEquals($fileName.'.xml', $zip->getNameIndex(0));

        // Extract and compare content
        $extractedContent = $zip->getFromName($fileName.'.xml');
        $zip->close();

        $this->assertEquals($xmlContent, $extractedContent);
    }

    /**
     * Test export with special characters in XML content
     */
    public function testExportWithSpecialCharacters(): void
    {
        $exporter = new ZipExport([]);
        $xmlContent = $this->generateSampleXML() . "\n<!-- Special chars: éàù ñ © -->";
        $fileName = 'test_special';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);

        $zip = new \ZipArchive();
        $zip->open($this->testFolder.'/'.$fileName.'.zip');
        $extractedContent = $zip->getFromName($fileName.'.xml');
        $zip->close();

        $this->assertEquals($xmlContent, $extractedContent);
    }

    /**
     * Test export with large XML content
     */
    public function testExportWithLargeContent(): void
    {
        $exporter = new ZipExport([]);
        $largeXml = $this->generateLargeXML(1000);
        $fileName = 'test_large';

        $result = $exporter->export($this->testFolder.'/', $fileName, $largeXml);

        $this->assertTrue($result);

        $zip = new \ZipArchive();
        $zip->open($this->testFolder.'/'.$fileName.'.zip');
        $extractedContent = $zip->getFromName($fileName.'.xml');
        $zip->close();

        $this->assertEquals($largeXml, $extractedContent);
    }

    /**
     * Test failure when export path points to an existing file (not a directory)
     */
    public function testExportFailsWithInvalidPath(): void
    {
        $exporter = new ZipExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_xmltv';

        // Create a regular file where the zip should be created
        // This will cause ZipArchive::open to fail
        $invalidZipPath = $this->testFolder.'/'.$fileName.'.zip';
        file_put_contents($invalidZipPath, 'this is not a zip file');
        chmod($invalidZipPath, 0444); // Make it read-only to prevent overwrite

        // Try to export to the same path - should fail because file exists and is read-only
        $result = @$exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        // Clean up
        chmod($invalidZipPath, 0644);

        $this->assertFalse($result);
    }

    /**
     * Test getExtension returns correct extension
     */
    public function testGetExtensionReturnsZip(): void
    {
        $exporter = new ZipExport([]);

        $this->assertEquals('zip', $exporter->getExtension());
    }

    /**
     * Test status is set correctly on success
     */
    public function testStatusIsSetOnSuccess(): void
    {
        $exporter = new ZipExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_status';

        $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $status = $exporter->getStatus();
        $this->assertStringContainsString('réussi', $status);
    }

    /**
     * Test status is set correctly on failure
     */
    public function testStatusIsSetOnFailure(): void
    {
        $exporter = new ZipExport([]);
        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_fail';

        // Create a read-only file to cause failure
        $invalidZipPath = $this->testFolder.'/'.$fileName.'.zip';
        file_put_contents($invalidZipPath, 'not a zip');
        chmod($invalidZipPath, 0444);

        // Suppress the warning that ZipArchive may emit
        @$exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        // Clean up
        chmod($invalidZipPath, 0644);

        $status = $exporter->getStatus();
        $this->assertStringContainsString('Impossible', $status);
        $this->assertStringContainsString($fileName.'.zip', $status);
    }

    /**
     * Test that multiple exports to the same file overwrite correctly
     */
    public function testMultipleExportsOverwriteCorrectly(): void
    {
        $exporter = new ZipExport([]);
        $fileName = 'test_overwrite';

        // First export
        $firstContent = $this->generateSampleXML();
        $exporter->export($this->testFolder.'/', $fileName, $firstContent);

        // Second export with different content
        $secondContent = $this->generateLargeXML(10);
        $exporter->export($this->testFolder.'/', $fileName, $secondContent);

        // Verify only the second content is in the file
        $zip = new \ZipArchive();
        $zip->open($this->testFolder.'/'.$fileName.'.zip');
        $extractedContent = $zip->getFromName($fileName.'.xml');
        $zip->close();

        $this->assertEquals($secondContent, $extractedContent);
        $this->assertNotEquals($firstContent, $extractedContent);
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
