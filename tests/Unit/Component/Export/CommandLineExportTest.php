<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\Component\Export;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Export\CommandLineExport;

class CommandLineExportTest extends TestCase
{
    private string $testFolder = 'var/test/export_commandline';

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
     * Test that missing command parameter throws exception
     */
    public function testMissingCommandParameterThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Missing "command" parameter');

        new CommandLineExport([]);
    }

    /**
     * Test successful command execution with basic echo command
     */
    public function testSuccessfulCommandExecution(): void
    {
        // Use a simple command that always succeeds
        $command = 'echo "Success"';
        $exporter = new CommandLineExport(['command' => $command]);

        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_xmltv';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
    }

    /**
     * Test variable substitution for {fileName}
     */
    public function testFileNameVariableSubstitution(): void
    {
        // Command that creates a file using the {fileName} variable
        $command = 'echo "test" > {exportPath}{fileName}.test';
        $exporter = new CommandLineExport(['command' => $command]);

        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_filename';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
        $this->assertFileExists($this->testFolder.'/'.$fileName.'.test');
    }

    /**
     * Test variable substitution for {exportPath}
     */
    public function testExportPathVariableSubstitution(): void
    {
        // Command that uses exportPath to create a file
        $command = 'touch {exportPath}exportpath_test.txt';
        $exporter = new CommandLineExport(['command' => $command]);

        $xmlContent = $this->generateSampleXML();
        $fileName = 'test';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
        $this->assertFileExists($this->testFolder.'/exportpath_test.txt');
    }

    /**
     * Test variable substitution for {rawXMLFilePath}
     */
    public function testRawXMLFilePathVariableSubstitution(): void
    {
        // First create the raw XML file that the command will reference
        $fileName = 'test_raw';
        $xmlContent = $this->generateSampleXML();
        file_put_contents($this->testFolder.'/'.$fileName.'.xml', $xmlContent);

        // Command that copies the raw XML file
        $command = 'cp {rawXMLFilePath} {exportPath}{fileName}.copy';
        $exporter = new CommandLineExport(['command' => $command]);

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
        $this->assertFileExists($this->testFolder.'/'.$fileName.'.copy');
    }

    /**
     * Test success_regex parameter for validating command output
     */
    public function testSuccessRegexValidation(): void
    {
        // Command that outputs "SUCCESS"
        $command = 'echo "Operation SUCCESS completed"';
        $exporter = new CommandLineExport([
            'command' => $command,
            'success_regex' => '/SUCCESS/'
        ]);

        $xmlContent = $this->generateSampleXML();
        $fileName = 'test';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
    }

    /**
     * Test success_regex parameter fails when pattern doesn't match
     */
    public function testSuccessRegexFailsWhenPatternDoesNotMatch(): void
    {
        // Command that outputs "FAILURE"
        $command = 'echo "Operation FAILURE"';
        $exporter = new CommandLineExport([
            'command' => $command,
            'success_regex' => '/SUCCESS/'
        ]);

        $xmlContent = $this->generateSampleXML();
        $fileName = 'test';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertFalse($result);
    }

    /**
     * Test command failure is properly handled
     */
    public function testCommandFailureIsHandled(): void
    {
        // Use a command that will fail
        $command = 'exit 1';
        $exporter = new CommandLineExport([
            'command' => $command,
            'success_regex' => '/this_will_not_match/'
        ]);

        $xmlContent = $this->generateSampleXML();
        $fileName = 'test';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        // With success_regex set, if the pattern doesn't match, it should return false
        $this->assertFalse($result);
    }

    /**
     * Test getExtension with custom extension parameter
     */
    public function testGetExtensionWithCustomExtension(): void
    {
        $exporter = new CommandLineExport([
            'command' => 'echo test',
            'extension' => 'xz'
        ]);

        $this->assertEquals('xz', $exporter->getExtension());
    }

    /**
     * Test getExtension with default extension when not provided
     */
    public function testGetExtensionWithDefaultExtension(): void
    {
        $exporter = new CommandLineExport([
            'command' => 'echo test'
        ]);

        $this->assertEquals('Inconnue', $exporter->getExtension());
    }

    /**
     * Test status is set correctly on success
     */
    public function testStatusIsSetOnSuccess(): void
    {
        $command = 'echo "Success"';
        $exporter = new CommandLineExport(['command' => $command]);

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
        $command = 'echo "Failed"';
        $exporter = new CommandLineExport([
            'command' => $command,
            'success_regex' => '/this_pattern_will_not_match/'
        ]);

        $xmlContent = $this->generateSampleXML();
        $fileName = 'test_fail';

        $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $status = $exporter->getStatus();
        $this->assertStringContainsString('Echec', $status);
    }

    /**
     * Test all variable substitutions in one command
     */
    public function testAllVariableSubstitutionsInOneCommand(): void
    {
        $fileName = 'test_all_vars';
        $xmlContent = $this->generateSampleXML();

        // Create the raw XML file
        file_put_contents($this->testFolder.'/'.$fileName.'.xml', $xmlContent);

        // Command that uses all variables and creates a test file
        $command = 'echo "{fileName}|{exportPath}|{rawXMLFilePath}" > {exportPath}all_vars.txt';
        $exporter = new CommandLineExport(['command' => $command]);

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
        $this->assertFileExists($this->testFolder.'/all_vars.txt');

        // Verify substitutions were made
        $content = file_get_contents($this->testFolder.'/all_vars.txt');
        $this->assertStringContainsString($fileName, $content);
        $this->assertStringContainsString($this->testFolder, $content);
    }

    /**
     * Test command with no success_regex succeeds by default
     */
    public function testCommandWithoutSuccessRegexSucceedsByDefault(): void
    {
        // Even a failing command should return true if no success_regex is set
        $command = 'echo "anything"';
        $exporter = new CommandLineExport(['command' => $command]);

        $xmlContent = $this->generateSampleXML();
        $fileName = 'test';

        $result = $exporter->export($this->testFolder.'/', $fileName, $xmlContent);

        $this->assertTrue($result);
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
  </programme>
</tv>
XML;
    }
}
