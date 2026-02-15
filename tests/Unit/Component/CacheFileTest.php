<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\Component;

use racacax\XmlTv\ValueObject\EPGEnum;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\CacheFile;
use racacax\XmlTv\Configurator;

class CacheFileTest extends TestCase
{
    /**
     * @var string
     */
    private string $testFolder = 'var/test';

    public function setUp(): void
    {
        parent::setUp();

        // Create test folder if it doesn't exist
        if (!is_dir($this->testFolder)) {
            mkdir($this->testFolder, 0777, true);
        }

        // Remove all files in the folder
        $files = glob($this->testFolder.'/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Test NO_CACHE scenario: No cached file exists
     */
    public function testNoCacheWhenFileDoesNotExist(): void
    {
        $config = new Configurator(cache_ttl: 8, minEndTime: 84600); // 23h30
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();

        $this->assertEquals(EPGEnum::$NO_CACHE, $cache->getState($fileName));
    }

    /**
     * Test FULL_CACHE scenario: File more recent than cacheTTL with last program ending after minEndTime
     * minEndTime = 84600 (23h30), so last program must end after 23h30 of the same day
     */
    public function testFullCacheWhenRecentFileWithCompletePrograms(): void
    {
        $config = new Configurator(cache_ttl: 8, minEndTime: 84600); // 23h30 = 23.5 * 3600
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();

        // Create XMLTV content with programs ending at 23:50 (84600 + 1200 seconds)
        $baseDate = date('Y-m-d');
        $content = $this->generateXMLTVContent($baseDate, '06:00', '23:50');

        file_put_contents($this->testFolder.'/'.$fileName, $content);

        $this->assertEquals(EPGEnum::$FULL_CACHE, $cache->getState($fileName));
    }

    /**
     * Test PARTIAL_CACHE scenario: File more recent than cacheTTL but last program ends before minEndTime
     * minEndTime = 84600 (23h30), so if last program ends at 20:00, cache is partial
     */
    public function testPartialCacheWhenRecentFileWithIncompletePrograms(): void
    {
        $config = new Configurator(cache_ttl: 8, minEndTime: 84600); // 23h30
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();

        // Create XMLTV content with programs ending at 20:00 (72000 seconds, less than 84600)
        $baseDate = date('Y-m-d');
        $content = $this->generateXMLTVContent($baseDate, '06:00', '20:00');

        file_put_contents($this->testFolder.'/'.$fileName, $content);

        $this->assertEquals(EPGEnum::$PARTIAL_CACHE, $cache->getState($fileName));
    }

    /**
     * Test EXPIRED_CACHE scenario: File less recent than cacheTTL (regardless of minEndTime)
     * We need to create a file and modify its timestamp to be older than cacheTTL days
     */
    public function testExpiredCacheWhenFileOlderThanTTL(): void
    {
        $cacheTTL = 8; // days
        $config = new Configurator(cache_ttl: $cacheTTL, minEndTime: 84600);
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();

        // Create XMLTV content with complete programs (this doesn't matter for expired cache)
        $baseDate = date('Y-m-d');
        $content = $this->generateXMLTVContent($baseDate, '06:00', '23:50');

        $filePath = $this->testFolder.'/'.$fileName;
        file_put_contents($filePath, $content);

        // Modify file timestamp to be 9 days old (older than cacheTTL of 8 days)
        $oldTimestamp = time() - (($cacheTTL + 1) * 86400);
        touch($filePath, $oldTimestamp);

        $this->assertEquals(EPGEnum::$EXPIRED_CACHE, $cache->getState($fileName));
    }

    /**
     * Test edge case: File exactly at cacheTTL boundary should still be valid
     */
    public function testFullCacheAtExactTTLBoundary(): void
    {
        $cacheTTL = 8;
        $config = new Configurator(cache_ttl: $cacheTTL, minEndTime: 84600);
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();

        $baseDate = date('Y-m-d');
        $content = $this->generateXMLTVContent($baseDate, '06:00', '23:50');

        $filePath = $this->testFolder.'/'.$fileName;
        file_put_contents($filePath, $content);

        // Set file to exactly 8 days old (should still be valid)
        $timestamp = time() - ($cacheTTL * 86400);
        touch($filePath, $timestamp);

        $this->assertEquals(EPGEnum::$FULL_CACHE, $cache->getState($fileName));
    }

    /**
     * Test edge case: File with programs ending exactly at minEndTime should be FULL_CACHE
     */
    public function testFullCacheWhenProgramEndsExactlyAtMinEndTime(): void
    {
        $minEndTime = 84600; // 23h30
        $config = new Configurator(cache_ttl: 8, minEndTime: $minEndTime);
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();

        // Create XMLTV content with last program ending exactly at 23:30
        $baseDate = date('Y-m-d');
        $content = $this->generateXMLTVContent($baseDate, '06:00', '23:30');

        file_put_contents($this->testFolder.'/'.$fileName, $content);

        // The comparison in CacheFile is: $timeRange < $minEndTime
        // So exactly equal should result in FULL_CACHE
        $this->assertEquals(EPGEnum::$FULL_CACHE, $cache->getState($fileName));
    }

    /**
     * Test that store() method works correctly
     */
    public function testStoreMethodCreatesFileWithCorrectState(): void
    {
        $config = new Configurator(cache_ttl: 8, minEndTime: 84600);
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();

        $baseDate = date('Y-m-d');
        $content = $this->generateXMLTVContent($baseDate, '06:00', '23:50');

        $cache->store($fileName, $content);

        $this->assertEquals(EPGEnum::$FULL_CACHE, $cache->getState($fileName));
        $this->assertSame($content, $cache->get($fileName));
    }

    /**
     * Generate a mock XMLTV content with programs from startTime to endTime
     *
     * @param string $date Date in Y-m-d format
     * @param string $startTime Time in H:i format (e.g., "06:00")
     * @param string $endTime Time in H:i format (e.g., "23:50")
     * @return string XMLTV formatted content
     */
    private function generateXMLTVContent(string $date, string $startTime, string $endTime): string
    {
        $startDateTime = new \DateTime("$date $startTime", new \DateTimeZone('Europe/Paris'));
        $endDateTime = new \DateTime("$date $endTime", new \DateTimeZone('Europe/Paris'));

        // Format for XMLTV: YYYYMMDDHHmmss +ZZZZ
        $startFormatted = $startDateTime->format('YmdHis O');
        $endFormatted = $endDateTime->format('YmdHis O');

        // Create a simple XMLTV structure with one program
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!-- racacax\XmlTv\Component\Provider\TestProvider -->
<tv>
  <channel id="test.channel">
    <display-name>Test Channel</display-name>
  </channel>
  <programme start="$startFormatted" stop="$endFormatted" channel="test.channel">
    <title lang="fr">Test Program</title>
    <desc lang="fr">Test Description</desc>
  </programme>
</tv>
XML;

        return $xml;
    }

    private function generateCacheFileName(): string
    {
        return 'test_' . date('Y-m-d') . '_' . uniqid() . '.xml';
    }
}
