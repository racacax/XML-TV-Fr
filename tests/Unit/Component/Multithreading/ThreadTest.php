<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\Component\Multithreading;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Utils;

class ThreadTest extends TestCase
{
    /**
     * @var string
     */
    private string $testFolder = 'var/test';
    private string $processFolder = 'var/process';
    private string $providerFolder = "src/Component/Provider";
    private string $resourcePath = "tests/Ressources/Multithreading";

    public function setUp(): void
    {
        parent::setUp();

        // Remove all file on the folder
        $files = glob($this->testFolder.'/*') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        // Remove all file on the folder
        $files = glob($this->processFolder.'/*') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        copy($this->resourcePath."/DummyProvider.php", $this->providerFolder."/DummyProvider.php");
    }
    public function tearDown(): void
    {
        parent::tearDown();
        @unlink($this->providerFolder."/DummyProvider.php");
    }

    /**
     * @throws \Exception
     */
    public function testFileIsExportedCorrectly() {
        $content = $this->executeThread( '{"key":"TestChannel.fr","info":[],"extraParams":{}}', "exportedFile");
        $this->assertEquals($content, file_get_contents($this->resourcePath."/expectedExportedFile.xml"));
    }
    /**
     * @throws \Exception
     */
    public function testFalseIsReturnedWhenProviderFalse() {
        $content = $this->executeThread( '{"key":"TestChannelFalse.fr","info":[],"extraParams":{}}', 'providerFalse');
        $this->assertEquals($content, "false");
    }
    /**
     * @throws \Exception
     */
    public function testFalseIsReturnedWhenProviderFails() {
        $content = $this->executeThread( '{"key":"TestChannelException.fr","info":[],"extraParams":{}}', "providerFail");
        $this->assertEquals($content, "false");
    }
    /**
     * @throws \Exception
     */
    public function testFalseIsReturnedWhenNoProgram() {
        $content = $this->executeThread( '{"key":"TestChannelNoProgram.fr","info":[],"extraParams":{}}', "noProgram");
        $this->assertEquals($content, "false");
    }

    private function executeThread(string $channelInfo, $fileName) {
        $cmd = Utils::getThreadCommand("DummyProvider", "2024-12-08", $channelInfo, $fileName, "testGeneratorId");
        shell_exec($cmd);
        $content = file_get_contents($this->processFolder . "/" . $fileName);
        return $content;
    }
}
