<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Configurator;

class ConfiguratorTest extends TestCase
{
    private string $testFolder = 'var/test/configurator';

    public function setUp(): void
    {
        parent::setUp();
        @mkdir($this->testFolder, 0777, true);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $files = glob($this->testFolder.'/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->testFolder);
    }

    /**
     * Test default provider limits when none specified in constructor
     */
    public function testDefaultProviderLimitsIncludeSfr(): void
    {
        $config = new Configurator();

        $limits = $config->getProviderLimits();

        $this->assertArrayHasKey('SFR', $limits);
        $this->assertEquals(5, $limits['SFR']);
    }

    /**
     * Test custom provider limits passed via constructor
     */
    public function testCustomProviderLimitsViaConstructor(): void
    {
        $config = new Configurator(providerLimits: ['SFR' => 10, 'Orange' => 3]);

        $limits = $config->getProviderLimits();

        $this->assertEquals(10, $limits['SFR']);
        $this->assertEquals(3, $limits['Orange']);
    }

    /**
     * Test empty provider limits disables all overrides (all providers default to 1)
     */
    public function testEmptyProviderLimitsViaConstructor(): void
    {
        $config = new Configurator(providerLimits: []);

        $this->assertEmpty($config->getProviderLimits());
    }

    /**
     * Test provider_limits parsed from config file
     */
    public function testProviderLimitsParsedFromConfigFile(): void
    {
        $configData = [
            'fetch_policies' => [],
            'export_handlers' => [],
            'provider_limits' => ['SFR' => 8, 'Orange' => 2],
        ];
        $configFile = $this->testFolder.'/config.json';
        file_put_contents($configFile, json_encode($configData));

        $config = Configurator::initFromConfigFile($configFile);

        $limits = $config->getProviderLimits();
        $this->assertEquals(8, $limits['SFR']);
        $this->assertEquals(2, $limits['Orange']);
    }

    /**
     * Test default SFR=5 applies when provider_limits is absent from config file
     */
    public function testDefaultSfrLimitWhenAbsentFromConfigFile(): void
    {
        $configData = ['fetch_policies' => [], 'export_handlers' => []];
        $configFile = $this->testFolder.'/config.json';
        file_put_contents($configFile, json_encode($configData));

        $config = Configurator::initFromConfigFile($configFile);

        $limits = $config->getProviderLimits();
        $this->assertArrayHasKey('SFR', $limits);
        $this->assertEquals(5, $limits['SFR']);
    }

    /**
     * Test that provider_limits: {} in config file overrides the default SFR=5
     */
    public function testEmptyProviderLimitsInConfigFileOverridesDefault(): void
    {
        $configData = [
            'fetch_policies' => [],
            'export_handlers' => [],
            'provider_limits' => (object)[],
        ];
        $configFile = $this->testFolder.'/config.json';
        file_put_contents($configFile, json_encode($configData));

        $config = Configurator::initFromConfigFile($configFile);

        $this->assertEmpty($config->getProviderLimits());
    }

    /**
     * Test that a config without export_handlers falls back to the default GZ export
     */
    public function testDefaultExportHandlerWhenAbsentFromConfigFile(): void
    {
        $configFile = $this->testFolder.'/config.json';
        file_put_contents($configFile, json_encode(['fetch_policies' => []]));

        $config = Configurator::initFromConfigFile($configFile);

        $this->assertCount(1, $config->getExportHandlers());
    }

    /**
     * Test that a 3.x config file is rejected with an explicit message
     */
    public function testLegacyConfigFileIsRejected(): void
    {
        $configFile = $this->testFolder.'/config.json';
        file_put_contents($configFile, json_encode(['days' => 8, 'enable_gz' => true, 'output_path' => 'var/export/']));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/format 3\.x.*days, enable_gz/');

        Configurator::initFromConfigFile($configFile);
    }
}
