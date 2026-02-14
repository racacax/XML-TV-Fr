<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\Component;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\CacheFile;
use racacax\XmlTv\Component\Generator;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\XmlExporter;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\EPGDate;
use racacax\XmlTv\ValueObject\EPGEnum;

class GeneratorTest extends TestCase
{
    private string $testFolder = 'var/test/generator';

    public function setUp(): void
    {
        parent::setUp();

        if (!is_dir($this->testFolder)) {
            mkdir($this->testFolder, 0777, true);
        }

        $files = glob($this->testFolder.'/**/*', GLOB_MARK) ?: [];
        foreach (array_reverse($files) as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                @rmdir($file);
            }
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $files = glob($this->testFolder.'/**/*', GLOB_MARK) ?: [];
        foreach (array_reverse($files) as $file) {
            if (is_file($file)) {
                @unlink($file);
            } elseif (is_dir($file)) {
                @rmdir($file);
            }
        }
    }

    /**
     * Test setProviders and getProviders
     */
    public function testSetAndGetProviders(): void
    {
        $generator = $this->createMockGenerator();
        $mockProvider = $this->createMock(ProviderInterface::class);

        $generator->setProviders([$mockProvider]);
        $providers = $generator->getProviders();

        $this->assertCount(1, $providers);
        $this->assertSame($mockProvider, $providers[0]);
    }

    /**
     * Test getProviders returns all providers when no filter
     */
    public function testGetProvidersReturnsAllWithoutFilter(): void
    {
        $generator = $this->createMockGenerator();

        $provider1 = $this->createMock(ProviderInterface::class);
        $provider2 = $this->createMock(ProviderInterface::class);

        $generator->setProviders([$provider1, $provider2]);
        $providers = $generator->getProviders();

        $this->assertCount(2, $providers);
    }

    /**
     * Test addGuides
     */
    public function testAddGuides(): void
    {
        $generator = $this->createMockGenerator();
        $guides = [
            ['channels' => 'config/channels.json', 'filename' => 'xmltv'],
            ['channels' => 'config/channels2.json', 'filename' => 'xmltv2']
        ];

        $generator->addGuides($guides);

        $this->assertSame($guides, $generator->guides);
    }

    /**
     * Test setExporter and getFormatter
     */
    public function testSetExporterAndGetFormatter(): void
    {
        $config = new Configurator();
        $generator = $this->createMockGenerator($config);
        $exporter = new XmlExporter($config);

        $generator->setExporter($exporter);

        $this->assertInstanceOf(\racacax\XmlTv\Component\XmlFormatter::class, $generator->getFormatter());
    }

    /**
     * Test setCache and getCache
     */
    public function testSetAndGetCache(): void
    {
        $generator = $this->createMockGenerator();
        $cache = $this->createMock(CacheFile::class);

        $generator->setCache($cache);

        $this->assertSame($cache, $generator->getCache());
    }

    /**
     * Test exportEpg creates export directory
     */
    public function testExportEpgCreatesDirectory(): void
    {
        $exportPath = $this->testFolder.'/export/';

        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );

        $generator = $this->createMockGenerator($config);
        $exporter = new XmlExporter($config);
        $cache = $this->createMock(CacheFile::class);

        // Return EPGEnum::$NO_CACHE (0) for getState
        $cache->method('getState')->willReturn(EPGEnum::$NO_CACHE);

        $generator->setExporter($exporter);
        $generator->setCache($cache);

        // Create a simple channels file
        $channelsFile = $this->testFolder.'/channels.json';
        file_put_contents($channelsFile, json_encode([]));

        $generator->addGuides([['channels' => $channelsFile, 'filename' => 'test']]);

        $generator->exportEpg($exportPath);

        $this->assertDirectoryExists($exportPath);
    }

    /**
     * Test exportEpg processes multiple guides
     */
    public function testExportEpgProcessesMultipleGuides(): void
    {
        $exportPath = $this->testFolder.'/multi_export/';

        $config = new Configurator(
            exportHandlers: [],
            deleteRawXml: false
        );

        $generator = $this->createMockGenerator($config);
        $exporter = new XmlExporter($config);
        $cache = $this->createMock(CacheFile::class);

        $cache->method('getState')->willReturn(EPGEnum::$NO_CACHE);

        $generator->setExporter($exporter);
        $generator->setCache($cache);

        // Create channel files
        $channelsFile1 = $this->testFolder.'/channels1.json';
        $channelsFile2 = $this->testFolder.'/channels2.json';
        file_put_contents($channelsFile1, json_encode([]));
        file_put_contents($channelsFile2, json_encode([]));

        $generator->addGuides([
            ['channels' => $channelsFile1, 'filename' => 'guide1'],
            ['channels' => $channelsFile2, 'filename' => 'guide2']
        ]);

        $generator->exportEpg($exportPath);

        $this->assertFileExists($exportPath.'guide1.xml');
        $this->assertFileExists($exportPath.'guide2.xml');
    }

    /**
     * Test exportEpg with channel alias substitution
     */
    public function testExportEpgSubstitutesChannelAliases(): void
    {
        $exportPath = $this->testFolder.'/alias_export/';
        $cachePath = $this->testFolder.'/alias_cache/';
        @mkdir($cachePath, 0777, true);

        $epgDate = new EPGDate(new \DateTimeImmutable('2026-02-14'), EPGDate::$CACHE_FIRST);

        $config = new Configurator(
            epgDates: [$epgDate],
            exportHandlers: [],
            deleteRawXml: false
        );

        // Create cache with original channel ID
        $cacheContent = <<<XML
<!-- Provider -->
<programme start="20260214060000 +0100" stop="20260214070000 +0100" channel="original.id">
  <title>Test Program</title>
</programme>
XML;

        $generator = $this->createMockGenerator($config);
        $exporter = new XmlExporter($config);
        $cache = new CacheFile($cachePath, $config);

        $cache->store('original.id_2026-02-14.xml', $cacheContent);

        $generator->setExporter($exporter);
        $generator->setCache($cache);

        // Create channels file with alias
        $channelsData = [
            'original.id' => [
                'name' => 'Test Channel',
                'alias' => 'aliased.id'
            ]
        ];
        $channelsFile = $this->testFolder.'/channels_alias.json';
        file_put_contents($channelsFile, json_encode($channelsData));

        $generator->addGuides([['channels' => $channelsFile, 'filename' => 'alias_test']]);

        $generator->exportEpg($exportPath);

        $xmlContent = file_get_contents($exportPath.'alias_test.xml');

        // Channel ID should be replaced with alias
        $this->assertStringContainsString('channel="aliased.id"', $xmlContent);
        $this->assertStringNotContainsString('channel="original.id"', $xmlContent);
    }

    /**
     * Test exportEpg skips cache entries with NO_CACHE state
     */
    public function testExportEpgSkipsNoCacheEntries(): void
    {
        $exportPath = $this->testFolder.'/nocache_export/';

        $epgDate = new EPGDate(new \DateTimeImmutable('2026-02-14'), EPGDate::$CACHE_FIRST);

        $config = new Configurator(
            epgDates: [$epgDate],
            exportHandlers: [],
            deleteRawXml: false
        );

        $generator = $this->createMockGenerator($config);
        $exporter = new XmlExporter($config);
        $cache = $this->createMock(CacheFile::class);

        // Return NO_CACHE
        $cache->method('getState')->willReturn(EPGEnum::$NO_CACHE);
        $cache->expects($this->never())->method('get');

        $generator->setExporter($exporter);
        $generator->setCache($cache);

        $channelsData = ['test.channel' => ['name' => 'Test']];
        $channelsFile = $this->testFolder.'/channels_nocache.json';
        file_put_contents($channelsFile, json_encode($channelsData));

        $generator->addGuides([['channels' => $channelsFile, 'filename' => 'nocache']]);

        $generator->exportEpg($exportPath);

        // File should exist but have no programs
        $xmlContent = file_get_contents($exportPath.'nocache.xml');
        $this->assertStringNotContainsString('<programme', $xmlContent);
    }

    /**
     * Test exportEpg clears corrupted cache
     */
    public function testExportEpgClearsCorruptedCache(): void
    {
        $exportPath = $this->testFolder.'/corrupt_export/';
        $cachePath = $this->testFolder.'/corrupt_cache/';
        @mkdir($cachePath, 0777, true);

        $epgDate = new EPGDate(new \DateTimeImmutable('2026-02-14'), EPGDate::$CACHE_FIRST);

        $config = new Configurator(
            epgDates: [$epgDate],
            exportHandlers: [],
            deleteRawXml: false
        );

        $generator = $this->createMockGenerator($config);
        $exporter = new XmlExporter($config);
        $cache = new CacheFile($cachePath, $config);

        // Create corrupted cache
        $corruptedContent = 'This is not valid XML <<invalid>>';
        $cacheKey = 'test.channel_2026-02-14.xml';
        $cache->store($cacheKey, $corruptedContent);

        $this->assertFileExists($cachePath.'/'.$cacheKey);

        $generator->setExporter($exporter);
        $generator->setCache($cache);

        $channelsData = ['test.channel' => ['name' => 'Test']];
        $channelsFile = $this->testFolder.'/channels_corrupt.json';
        file_put_contents($channelsFile, json_encode($channelsData));

        $generator->addGuides([['channels' => $channelsFile, 'filename' => 'corrupt']]);

        $generator->exportEpg($exportPath);

        // Cache should be cleared
        $this->assertFileDoesNotExist($cachePath.'/'.$cacheKey);
    }

    /**
     * Test clearCache delegates to cache object
     */
    public function testClearCacheDelegatesToCacheObject(): void
    {
        $generator = $this->createMockGenerator();
        $cache = $this->createMock(CacheFile::class);

        $cache->expects($this->once())
            ->method('clearCache')
            ->with(8);

        $generator->setCache($cache);
        $generator->clearCache(8);
    }

    /**
     * Test getConfigurator returns configurator
     */
    public function testGetConfiguratorReturnsConfigurator(): void
    {
        $config = new Configurator();
        $generator = $this->createMockGenerator($config);

        $this->assertSame($config, $generator->getConfigurator());
    }

    /**
     * Test exportEpg with multiple channels and dates
     */
    public function testExportEpgWithMultipleChannelsAndDates(): void
    {
        $exportPath = $this->testFolder.'/multi_ch_export/';
        $cachePath = $this->testFolder.'/multi_ch_cache/';
        @mkdir($cachePath, 0777, true);

        $epgDate1 = new EPGDate(new \DateTimeImmutable('2026-02-14'), EPGDate::$CACHE_FIRST);
        $epgDate2 = new EPGDate(new \DateTimeImmutable('2026-02-15'), EPGDate::$CACHE_FIRST);

        $config = new Configurator(
            epgDates: [$epgDate1, $epgDate2],
            exportHandlers: [],
            deleteRawXml: false
        );

        $generator = $this->createMockGenerator($config);
        $exporter = new XmlExporter($config);
        $cache = new CacheFile($cachePath, $config);

        // Create cache for multiple channels and dates
        $prog1 = '<!-- Provider --><programme start="20260214060000 +0100" stop="20260214070000 +0100" channel="ch1"><title>P1</title></programme>';
        $prog2 = '<!-- Provider --><programme start="20260215060000 +0100" stop="20260215070000 +0100" channel="ch1"><title>P2</title></programme>';
        $prog3 = '<!-- Provider --><programme start="20260214080000 +0100" stop="20260214090000 +0100" channel="ch2"><title>P3</title></programme>';

        $cache->store('ch1_2026-02-14.xml', $prog1);
        $cache->store('ch1_2026-02-15.xml', $prog2);
        $cache->store('ch2_2026-02-14.xml', $prog3);

        $generator->setExporter($exporter);
        $generator->setCache($cache);

        $channelsData = [
            'ch1' => ['name' => 'Channel 1'],
            'ch2' => ['name' => 'Channel 2']
        ];
        $channelsFile = $this->testFolder.'/channels_multi.json';
        file_put_contents($channelsFile, json_encode($channelsData));

        $generator->addGuides([['channels' => $channelsFile, 'filename' => 'multi']]);

        $generator->exportEpg($exportPath);

        $xmlContent = file_get_contents($exportPath.'multi.xml');

        // All programs should be exported
        $this->assertStringContainsString('P1', $xmlContent);
        $this->assertStringContainsString('P2', $xmlContent);
        $this->assertStringContainsString('P3', $xmlContent);
    }

    /**
     * Helper to create a mock generator (since Generator is abstract)
     */
    private function createMockGenerator(?Configurator $config = null): Generator
    {
        if ($config === null) {
            $config = new Configurator();
        }

        return new class ($config) extends Generator {
            protected function generateEpg(): void
            {
                // Mock implementation - does nothing
            }
        };
    }
}
