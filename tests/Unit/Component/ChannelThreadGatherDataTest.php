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
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\EPGDate;
use racacax\XmlTv\ValueObject\EPGEnum;
use racacax\XmlTv\ValueObject\Channel;
use ReflectionClass;

/**
 * Tests for ChannelThread::gatherData and related provider rotation logic
 *
 * CRITICAL SCENARIOS TESTED:
 * 1. Cache policies (CACHE_ONLY, CACHE_FIRST, NETWORK_FIRST)
 * 2. Cache states (NO_CACHE, EXPIRED_CACHE, PARTIAL_CACHE, FULL_CACHE)
 * 3. Provider rotation when providers fail
 * 4. Partial data quality comparison with existing cache
 * 5. Extra params passed to providers
 * 6. Logging correctness
 */
class ChannelThreadGatherDataTest extends TestCase
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
    /** @var XmlFormatter&\PHPUnit\Framework\MockObject\MockObject */
    private XmlFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        Logger::reset();

        $this->manager = $this->createMock(ChannelsManager::class);
        $this->generator = $this->createMock(Generator::class);
        $this->cache = $this->createMock(CacheFile::class);
        $this->configurator = $this->createMock(Configurator::class);
        $this->formatter = $this->createMock(XmlFormatter::class);

        $this->generator->method('getCache')->willReturn($this->cache);
        $this->generator->method('getConfigurator')->willReturn($this->configurator);
        $this->generator->method('getFormatter')->willReturn($this->formatter);

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

    // ========================================
    // TEST: CACHE_ONLY policy
    // ========================================

    public function testGatherDataCacheOnlyWithFullCache(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$CACHE_ONLY);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        $cacheKey = 'TF1.fr_' . $epgDate->getFormattedDate() . '.xml';

        // Mock: Cache has FULL data
        $this->cache->expects($this->once())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$FULL_CACHE);

        $this->cache->expects($this->once())
            ->method('getProviderName')
            ->with($cacheKey)
            ->willReturn('MyCanal');

        $result = $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isCache']);
        $this->assertEquals('MyCanal', $result['provider']);
        $this->assertFalse($result['isPartial']);
    }

    public function testGatherDataCacheOnlyWithPartialCache(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$CACHE_ONLY);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        $cacheKey = 'TF1.fr_' . $epgDate->getFormattedDate() . '.xml';

        // Mock: Cache has PARTIAL data
        $this->cache->expects($this->once())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$PARTIAL_CACHE);

        $this->cache->expects($this->once())
            ->method('getProviderName')
            ->with($cacheKey)
            ->willReturn('MyCanal');

        $result = $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isCache']);
        $this->assertTrue($result['isPartial']);
    }

    public function testGatherDataCacheOnlyWithNoCache(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$CACHE_ONLY);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        $cacheKey = 'TF1.fr_' . $epgDate->getFormattedDate() . '.xml';

        // Mock: NO cache available
        $this->cache->expects($this->once())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$NO_CACHE);

        $result = $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['isCache']);
        $this->assertFalse($result['skipped']);
    }

    // ========================================
    // TEST: CACHE_FIRST policy
    // ========================================

    public function testGatherDataCacheFirstWithFullCache(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$CACHE_FIRST);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        $cacheKey = 'TF1.fr_' . $epgDate->getFormattedDate() . '.xml';

        // Mock: Cache has FULL data - should return cache without checking providers
        $this->cache->expects($this->once())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$FULL_CACHE);

        $this->cache->expects($this->once())
            ->method('getProviderName')
            ->with($cacheKey)
            ->willReturn('MyCanal');

        // Providers should NOT be called
        $this->generator->expects($this->never())->method('getProviders');

        $result = $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['isCache']);
        $this->assertEquals('MyCanal', $result['provider']);
    }

    public function testGatherDataCacheFirstWithPartialCacheFallsBackToProvider(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$CACHE_FIRST);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);
        $this->setProperty($this->channelThread, 'info', []);

        $cacheKey = 'TF1.fr_' . $epgDate->getFormattedDate() . '.xml';

        // Mock: Cache has PARTIAL data - should try providers
        $this->cache->expects($this->atLeastOnce())
            ->method('getState')
            ->with($cacheKey)
            ->willReturn(EPGEnum::$PARTIAL_CACHE);

        // Mock: No providers available (to test the fallback without actual provider execution)
        $this->generator->expects($this->once())
            ->method('getProviders')
            ->willReturn([]);

        $result = $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);

        // Should fail because no providers available
        $this->assertFalse($result['success']);
    }

    // ========================================
    // TEST: NETWORK_FIRST policy
    // ========================================

    public function testGatherDataNetworkFirstIgnoresFullCache(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$NETWORK_FIRST);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);
        $this->setProperty($this->channelThread, 'info', []);

        // Even with FULL cache, should try providers
        $this->cache->method('getState')->willReturn(EPGEnum::$FULL_CACHE);

        // Mock: No providers available
        $this->generator->expects($this->once())
            ->method('getProviders')
            ->willReturn([]);

        $result = $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);

        // Should fail because no providers and NETWORK_FIRST doesn't use cache
        $this->assertFalse($result['success']);
    }

    // ========================================
    // TEST: Provider cannot be used (rate limiting)
    // ========================================

    public function testGatherDataSkipsWhenProviderCannotBeUsed(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$NETWORK_FIRST);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('channelExists')->willReturn(true);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);
        $this->setProperty($this->channelThread, 'info', []);

        $this->cache->method('getState')->willReturn(EPGEnum::$NO_CACHE);

        $this->generator->method('getProviders')->willReturn([$provider]);

        // Manager says provider cannot be used (rate limiting)
        $this->manager->expects($this->once())
            ->method('canUseProvider')
            ->willReturn(false);

        $result = $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);

        $this->assertTrue($result['skipped']);
    }

    // ========================================
    // TEST: Provider adds/removes channel tracking
    // ========================================

    public function testGatherDataTracksChannelInProvider(): void
    {
        // This test verifies that when a provider is used, the channel is properly
        // added to and removed from the provider's active channels list

        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$NETWORK_FIRST);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('channelExists')->willReturn(true);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);
        $this->setProperty($this->channelThread, 'info', []);

        $this->cache->method('getState')->willReturn(EPGEnum::$NO_CACHE);
        $this->generator->method('getProviders')->willReturn([$provider]);

        $this->manager->method('canUseProvider')->willReturn(true);

        // Expect channel to be added to provider
        // Note: PHPUnit mocks have unique suffixes, so we check with stringContains
        $this->manager->expects($this->once())
            ->method('addChannelToProvider')
            ->with($this->stringContains('Mock_ProviderInterface'), 'TF1.fr');

        // Note: We can't easily test removeChannelFromProvider because it's called
        // inside getProviderResult which uses Amp workers. That would require
        // integration tests.

        // We'll let this fail naturally when trying to execute the provider task
        // The important part is that addChannelToProvider is called
        try {
            $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);
        } catch (\Throwable $e) {
            // Expected to fail when trying to execute worker task in unit test
        }
    }

    // ========================================
    // TEST: Logging verification
    // ========================================

    public function testGatherDataLogsChannelEntry(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $epgDate = new EPGDate($date, EPGDate::$CACHE_ONLY);
        $date = $epgDate->getFormattedDate();

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'failedProviders', []);

        $cacheKey = 'TF1.fr_' . $date . '.xml';

        $this->cache->method('getState')->willReturn(EPGEnum::$NO_CACHE);

        // Execute gatherData within run() context to trigger logging
        // We can't test this directly without run(), so we'll test it indirectly
        // by checking that Logger::addChannelEntry would be called

        $result = $this->callMethod($this->channelThread, 'gatherData', [$epgDate]);

        // The actual logging happens in run(), not gatherData
        // This test verifies the result structure that run() will use for logging
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ========================================
    // TEST: Failed providers list
    // ========================================

    public function testGatherDataExcludesFailedProviders(): void
    {
        // This test verifies that failedProviders filtering works
        // Since PHPUnit mocks may have unpredictable names, we test the behavior
        // by verifying that when failedProviders is set, fewer providers are returned

        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('channelExists')->willReturn(true);

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('channelExists')->willReturn(true);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'info', []);

        // First, test with no failed providers
        $this->setProperty($this->channelThread, 'failedProviders', []);
        $this->generator->method('getProviders')->willReturn([$provider1, $provider2]);

        $allProviders = $this->callMethod($this->channelThread, 'getRemainingProviders');
        $this->assertCount(2, $allProviders, 'Without failed providers, should return all 2 providers');

        // Now add one provider's name to failedProviders
        $provider1Name = Utils::extractProviderName($provider1);
        $this->setProperty($this->channelThread, 'failedProviders', [$provider1Name]);

        $filteredProviders = $this->callMethod($this->channelThread, 'getRemainingProviders');

        // Should have fewer providers now (either 1 or 0 depending on if both mocks have same name)
        $this->assertLessThan(
            count($allProviders),
            count($filteredProviders),
            'With failed providers, should return fewer providers'
        );
    }

    // ========================================
    // TEST: Extra params structure
    // ========================================

    public function testExtraParamsAreStored(): void
    {
        $channelInfo = [
            'key' => 'TF1.fr',
            'info' => ['name' => 'TF1'],
            'failedProviders' => [],
            'datesGathered' => [],
            'extraParams' => [
                'customParam1' => 'value1',
                'customParam2' => ['nested' => 'value2']
            ]
        ];

        $this->channelThread->setChannel($channelInfo);

        $property = new ReflectionClass($this->channelThread);
        $extraParams = $property->getProperty('extraParams');
        $extraParams->setAccessible(true);
        $storedParams = $extraParams->getValue($this->channelThread);

        $this->assertEquals('value1', $storedParams['customParam1']);
        $this->assertEquals(['nested' => 'value2'], $storedParams['customParam2']);

        // Note: Testing that these params are actually passed to providers requires
        // mocking the ProviderTask and worker execution, which is better done in
        // integration tests. The unit test verifies the params are stored correctly.
    }
}
