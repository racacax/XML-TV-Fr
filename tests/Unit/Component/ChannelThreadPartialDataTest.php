<?php

declare(strict_types=1);

namespace racacax\XmlTv\Tests\Unit\Component;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\ChannelThread;
use racacax\XmlTv\Component\ChannelsManager;
use racacax\XmlTv\Component\Generator;
use racacax\XmlTv\Component\CacheFile;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\EPGEnum;
use ReflectionClass;

/**
 * Tests for partial data handling in ChannelThread
 *
 * CRITICAL SCENARIOS:
 * 1. New partial data is better than existing cache → Accept new data
 * 2. New partial data is worse than existing cache → Reject new data, keep cache
 * 3. New partial data with no existing cache → Accept new data
 * 4. Full data replaces partial cache
 *
 * This is one of the most bug-prone areas!
 */
class ChannelThreadPartialDataTest extends TestCase
{
    private ChannelThread $channelThread;
    /** @var ChannelsManager&\PHPUnit\Framework\MockObject\MockObject */
    private ChannelsManager $manager;
    /** @var Generator&\PHPUnit\Framework\MockObject\MockObject */
    private Generator $generator;
    /** @var CacheFile&\PHPUnit\Framework\MockObject\MockObject */
    private CacheFile $cache;
    /** @var Configurator&\PHPUnit\Framework\MockObject\MockObject */
    private Configurator $configurator;

    protected function setUp(): void
    {
        parent::setUp();

        Logger::reset();

        $this->manager = $this->createMock(ChannelsManager::class);
        $this->generator = $this->createMock(Generator::class);
        $this->cache = $this->createMock(CacheFile::class);
        $this->configurator = $this->createMock(Configurator::class);

        $this->generator->method('getCache')->willReturn($this->cache);
        $this->generator->method('getConfigurator')->willReturn($this->configurator);

        $this->channelThread = new ChannelThread($this->manager, $this->generator);
    }

    private function callMethod(object $obj, string $name, array $args = []): mixed
    {
        $class = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invoke($obj, ...$args);
    }

    private function setProperty(object $obj, string $name, mixed $value): void
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($obj, $value);
    }

    private function getProperty(object $obj, string $name): mixed
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue($obj);
    }

    /**
     * Helper to create XML content with programs at specific times
     */
    private function createXMLWithPrograms(array $startTimes): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><tv>';
        $xml .= '<channel id="TF1.fr"><display-name>TF1</display-name></channel>';

        foreach ($startTimes as $startTime) {
            $endTime = $startTime + 3600; // 1 hour program
            $xml .= sprintf(
                '<programme start="%s" stop="%s" channel="TF1.fr"><title>Program</title></programme>',
                date('YmdHis O', $startTime),
                date('YmdHis O', $endTime)
            );
        }

        $xml .= '</tv>';

        return $xml;
    }

    // ========================================
    // TEST: Partial data quality comparison
    // ========================================

    public function testGetDataFromProviderAcceptsPartialDataWhenBetterThanCache(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $providerName = 'MyCanal';
        $date = '2024-01-01';
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        // Existing cache has programs ending at 18:00 (64800 seconds)
        $cacheStartTimes = [
            strtotime('2024-01-01 06:00:00'),
            strtotime('2024-01-01 12:00:00'),
            strtotime('2024-01-01 18:00:00')
        ];
        $cacheXML = $this->createXMLWithPrograms($cacheStartTimes);

        // New provider data has programs ending at 20:00 (72000 seconds) - BETTER!
        $newStartTimes = [
            strtotime('2024-01-01 06:00:00'),
            strtotime('2024-01-01 12:00:00'),
            strtotime('2024-01-01 20:00:00')
        ];
        $newXML = $this->createXMLWithPrograms($newStartTimes);

        // Mock cache state: has partial cache
        $this->cache->expects($this->once())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$PARTIAL_CACHE);

        // Mock cache get: return old XML
        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn($cacheXML);

        // Provider returns partial data state
        $provider->expects($this->once())
            ->method('getChannelStateFromTimes')
            ->willReturn(EPGEnum::$PARTIAL_CACHE);

        // Mock getProviderResult to return the new XML
        $mockThread = $this->getMockBuilder(ChannelThread::class)
            ->setConstructorArgs([$this->manager, $this->generator])
            ->onlyMethods(['getProviderResult'])
            ->getMock();

        $mockThread->method('getProviderResult')->willReturn($newXML);

        $this->setProperty($mockThread, 'channel', 'TF1.fr');
        $this->setProperty($mockThread, 'failedProviders', []);

        // Cache should be updated with new (better) data
        $this->cache->expects($this->once())
            ->method('store')
            ->with($cacheKey, $newXML);

        $result = $this->callMethod($mockThread, 'getDataFromProvider', [
            $providerName,
            $provider,
            $date,
            $cacheKey
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isPartial']);
        $this->assertEquals($providerName, $result['provider']);
    }

    public function testGetDataFromProviderRejectsPartialDataWhenWorseThanCache(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $providerName = 'Orange';
        $date = '2024-01-01';
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        // Existing cache has programs ending at 20:00 (72000 seconds)
        $cacheStartTimes = [
            strtotime('2024-01-01 06:00:00'),
            strtotime('2024-01-01 12:00:00'),
            strtotime('2024-01-01 20:00:00')
        ];
        $cacheXML = $this->createXMLWithPrograms($cacheStartTimes);

        // New provider data has programs ending at 18:00 (64800 seconds) - WORSE!
        $newStartTimes = [
            strtotime('2024-01-01 06:00:00'),
            strtotime('2024-01-01 12:00:00'),
            strtotime('2024-01-01 18:00:00')
        ];
        $newXML = $this->createXMLWithPrograms($newStartTimes);

        // Mock cache state: has partial cache (better than what provider will return)
        $this->cache->expects($this->once())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$PARTIAL_CACHE);

        // Mock cache get: return old (better) XML
        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn($cacheXML);

        // Provider returns partial data state
        $provider->expects($this->once())
            ->method('getChannelStateFromTimes')
            ->willReturn(EPGEnum::$PARTIAL_CACHE);

        // Mock getProviderResult to return the new (worse) XML
        $mockThread = $this->getMockBuilder(ChannelThread::class)
            ->setConstructorArgs([$this->manager, $this->generator])
            ->onlyMethods(['getProviderResult'])
            ->getMock();

        $mockThread->method('getProviderResult')->willReturn($newXML);

        $this->setProperty($mockThread, 'channel', 'TF1.fr');
        $this->setProperty($mockThread, 'failedProviders', []);

        // Cache should NOT be updated (new data is worse)
        $this->cache->expects($this->never())->method('store');

        $result = $this->callMethod($mockThread, 'getDataFromProvider', [
            $providerName,
            $provider,
            $date,
            $cacheKey
        ]);

        // Should return failure because new data is worse
        $this->assertFalse($result['success']);
    }

    public function testGetDataFromProviderAcceptsPartialDataWhenNoCache(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $providerName = 'MyCanal';
        $date = '2024-01-01';
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        // New provider data (partial)
        $newStartTimes = [
            strtotime('2024-01-01 06:00:00'),
            strtotime('2024-01-01 12:00:00')
        ];
        $newXML = $this->createXMLWithPrograms($newStartTimes);

        // Mock cache state: NO cache
        $this->cache->expects($this->once())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$NO_CACHE);

        // Provider returns partial data state
        $provider->expects($this->once())
            ->method('getChannelStateFromTimes')
            ->willReturn(EPGEnum::$PARTIAL_CACHE);

        // Mock getProviderResult
        $mockThread = $this->getMockBuilder(ChannelThread::class)
            ->setConstructorArgs([$this->manager, $this->generator])
            ->onlyMethods(['getProviderResult'])
            ->getMock();

        $mockThread->method('getProviderResult')->willReturn($newXML);

        $this->setProperty($mockThread, 'channel', 'TF1.fr');
        $this->setProperty($mockThread, 'failedProviders', []);

        // Cache should be stored (no existing cache, so any data is good)
        $this->cache->expects($this->once())
            ->method('store')
            ->with($cacheKey, $newXML);

        $result = $this->callMethod($mockThread, 'getDataFromProvider', [
            $providerName,
            $provider,
            $date,
            $cacheKey
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isPartial']);
    }

    public function testGetDataFromProviderAcceptsFullData(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $providerName = 'MyCanal';
        $date = '2024-01-01';
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        // Full data (programs until 23:30+)
        $newStartTimes = [
            strtotime('2024-01-01 06:00:00'),
            strtotime('2024-01-01 12:00:00'),
            strtotime('2024-01-01 18:00:00'),
            strtotime('2024-01-01 23:00:00')
        ];
        $newXML = $this->createXMLWithPrograms($newStartTimes);

        // Provider returns FULL data state
        $provider->expects($this->once())
            ->method('getChannelStateFromTimes')
            ->willReturn(EPGEnum::$FULL_CACHE);

        // Mock getProviderResult
        $mockThread = $this->getMockBuilder(ChannelThread::class)
            ->setConstructorArgs([$this->manager, $this->generator])
            ->onlyMethods(['getProviderResult'])
            ->getMock();

        $mockThread->method('getProviderResult')->willReturn($newXML);

        $this->setProperty($mockThread, 'channel', 'TF1.fr');
        $this->setProperty($mockThread, 'failedProviders', []);

        // Cache should be stored
        $this->cache->expects($this->once())
            ->method('store')
            ->with($cacheKey, $newXML);

        $result = $this->callMethod($mockThread, 'getDataFromProvider', [
            $providerName,
            $provider,
            $date,
            $cacheKey
        ]);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['isPartial']);
    }

    // ========================================
    // TEST: Provider failure handling
    // ========================================

    public function testGetDataFromProviderHandlesFailure(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $providerName = 'MyCanal';
        $date = '2024-01-01';
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        // Mock getProviderResult to return 'false' (failure)
        $mockThread = $this->getMockBuilder(ChannelThread::class)
            ->setConstructorArgs([$this->manager, $this->generator])
            ->onlyMethods(['getProviderResult'])
            ->getMock();

        $mockThread->method('getProviderResult')->willReturn('false');

        $this->setProperty($mockThread, 'channel', 'TF1.fr');
        $this->setProperty($mockThread, 'failedProviders', []);

        $result = $this->callMethod($mockThread, 'getDataFromProvider', [
            $providerName,
            $provider,
            $date,
            $cacheKey
        ]);

        $this->assertFalse($result['success']);

        // Verify provider was added to failed providers
        $failedProviders = $this->getProperty($mockThread, 'failedProviders');
        $this->assertContains($providerName, $failedProviders, 'Failed provider should be added to failedProviders list');
    }

    // ========================================
    // TEST: Cache state comparison logic
    // ========================================

    public function testPartialDataOnlyComparedWhenCacheBetterThanExpired(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $providerName = 'MyCanal';
        $date = '2024-01-01';
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        $newXML = $this->createXMLWithPrograms([strtotime('2024-01-01 06:00:00')]);

        // Mock cache state: EXPIRED (less than PARTIAL threshold)
        // According to line 136: if (($cache->getState($cacheKey) > EPGEnum::$EXPIRED_CACHE))
        // This means comparison only happens if cache state > EXPIRED
        $this->cache->expects($this->once())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$EXPIRED_CACHE);

        // Provider returns partial
        $provider->expects($this->once())
            ->method('getChannelStateFromTimes')
            ->willReturn(EPGEnum::$PARTIAL_CACHE);

        $mockThread = $this->getMockBuilder(ChannelThread::class)
            ->setConstructorArgs([$this->manager, $this->generator])
            ->onlyMethods(['getProviderResult'])
            ->getMock();

        $mockThread->method('getProviderResult')->willReturn($newXML);

        $this->setProperty($mockThread, 'channel', 'TF1.fr');
        $this->setProperty($mockThread, 'failedProviders', []);

        // Cache.get should NOT be called because cache state <= EXPIRED
        $this->cache->expects($this->never())->method('get');

        // Should store the new partial data without comparison
        $this->cache->expects($this->once())
            ->method('store')
            ->with($cacheKey, $newXML);

        $result = $this->callMethod($mockThread, 'getDataFromProvider', [
            $providerName,
            $provider,
            $date,
            $cacheKey
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isPartial']);
    }
}
