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

        // Remove all file on the folder
        $files = glob($this->testFolder.'/*') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function testCache(): void
    {
        $config = new Configurator(forceTodayGrab: false, minTimeRange: 0);
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();
        $content = uniqid();
        $this->assertEquals($cache->getState($fileName), EPGEnum::$NO_CACHE);
        $cache->store($fileName, $content);
        $this->assertEquals($cache->getState($fileName), EPGEnum::$FULL_CACHE);
        $this->assertSame($content, $cache->get($fileName));
    }

    public function testCacheWithoutForceTodayGrab(): void
    {
        $config = new Configurator(forceTodayGrab: false, minTimeRange: 0);
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();
        $content = uniqid();
        // create file
        file_put_contents($this->testFolder.'/'.$fileName, $content);
        $this->assertEquals($cache->getState($fileName), EPGEnum::$FULL_CACHE);
        $this->assertSame($content, $cache->get($fileName));
    }
    public function testCacheWithMinTimeRange(): void
    {
        $config = new Configurator(forceTodayGrab: false, minTimeRange: 3600);
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();
        $content = uniqid();
        // create file
        file_put_contents($this->testFolder.'/'.$fileName, $content);
        $this->assertEquals($cache->getState($fileName), EPGEnum::$PARTIAL_CACHE);
        $this->assertSame($content, $cache->get($fileName));
    }

    public function testInvalidationCache(): void
    {
        $config = new Configurator(forceTodayGrab: true, minTimeRange: 0);
        $cache = new CacheFile($this->testFolder, $config);
        $fileName = $this->generateCacheFileName();
        $content = uniqid();
        // create file
        file_put_contents($this->testFolder.'/'.$fileName, $content);
        $this->assertEquals($cache->getState($fileName), EPGEnum::$OBSOLETE_CACHE);
    }


    private function generateCacheFileName(): string
    {
        return uniqid(date('Y-m-d'));
    }
}
