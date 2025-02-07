<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\Component;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\CacheFile;

class CacheFileTest extends TestCase
{
    /**
     * @var string
     */
    private $testFolder = 'var/test';

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
        $cache = new CacheFile($this->testFolder, false, 0);
        $fileName = $this->generateCacheFileName();
        $content = uniqid();
        $this->assertEquals($cache->has($fileName), CacheFile::$NO_CACHE);
        $cache->store($fileName, $content);
        $this->assertEquals($cache->has($fileName), CacheFile::$FULL_CACHE);
        $this->assertSame($content, $cache->get($fileName));
    }

    public function testCacheWithoutForceTodayGrab(): void
    {
        $cache = new CacheFile($this->testFolder, false, 0);
        $fileName = $this->generateCacheFileName();
        $content = uniqid();
        // create file
        file_put_contents($this->testFolder.'/'.$fileName, $content);
        $this->assertEquals($cache->has($fileName), CacheFile::$FULL_CACHE);
        $this->assertSame($content, $cache->get($fileName));
    }
    public function testCacheWithMinTimeRange(): void
    {
        $cache = new CacheFile($this->testFolder, false, 3600);
        $fileName = $this->generateCacheFileName();
        $content = uniqid();
        // create file
        file_put_contents($this->testFolder.'/'.$fileName, $content);
        $this->assertEquals($cache->has($fileName), CacheFile::$PARTIAL_CACHE);
        $this->assertSame($content, $cache->get($fileName));
    }

    public function testInvalidationCache(): void
    {
        $cache = new CacheFile($this->testFolder, true, 0);
        $fileName = $this->generateCacheFileName();
        $content = uniqid();
        // create file
        file_put_contents($this->testFolder.'/'.$fileName, $content);
        $this->assertEquals($cache->has($fileName), CacheFile::$OBSOLETE_CACHE);
    }


    private function generateCacheFileName(): string
    {
        return uniqid(date('Y-m-d'));
    }
}
