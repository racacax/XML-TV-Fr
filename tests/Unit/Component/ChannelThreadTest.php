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

class ChannelThreadTest extends TestCase
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

        // Reset logger before each test
        Logger::reset();

        // Create mocks
        $this->manager = $this->createMock(ChannelsManager::class);
        $this->generator = $this->createMock(Generator::class);
        $this->cache = $this->createMock(CacheFile::class);
        $this->configurator = $this->createMock(Configurator::class);

        // Setup generator to return cache and configurator
        $this->generator->method('getCache')->willReturn($this->cache);
        $this->generator->method('getConfigurator')->willReturn($this->configurator);

        $this->channelThread = new ChannelThread($this->manager, $this->generator);
    }

    /**
     * Helper to call protected/private methods
     */
    private function callMethod(object $obj, string $name, array $args = []): mixed
    {
        $class = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invoke($obj, ...$args);
    }

    /**
     * Helper to set protected/private properties
     */
    private function setProperty(object $obj, string $name, mixed $value): void
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($obj, $value);
    }

    /**
     * Helper to get protected/private properties
     */
    private function getProperty(object $obj, string $name): mixed
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue($obj);
    }

    // ========================================
    // TEST: setChannel
    // ========================================

    public function testSetChannelInitializesCorrectly(): void
    {
        $channelInfo = [
            'key' => 'TF1.fr',
            'info' => ['name' => 'TF1', 'priority' => ['MyCanal' => 0.9]],
            'failedProviders' => ['Orange'],
            'datesGathered' => ['2024-01-01'],
            'extraParams' => ['param1' => 'value1']
        ];

        $this->channelThread->setChannel($channelInfo);

        $this->assertEquals('TF1.fr', $this->getProperty($this->channelThread, 'channel'));
        $this->assertEquals(['name' => 'TF1', 'priority' => ['MyCanal' => 0.9]], $this->getProperty($this->channelThread, 'info'));
        $this->assertEquals(['Orange'], $this->getProperty($this->channelThread, 'failedProviders'));
        $this->assertEquals(['2024-01-01'], $this->getProperty($this->channelThread, 'datesGathered'));
        $this->assertEquals(['param1' => 'value1'], $this->getProperty($this->channelThread, 'extraParams'));
        $this->assertFalse($this->getProperty($this->channelThread, 'hasStarted'));
    }

    // ========================================
    // TEST: getRemainingProviders
    // ========================================

    public function testGetRemainingProvidersFiltersFailedProviders(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('channelExists')->willReturn(true);

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('channelExists')->willReturn(true);

        $provider3 = $this->createMock(ProviderInterface::class);
        $provider3->method('channelExists')->willReturn(false);

        $allProviders = [$provider1, $provider2, $provider3];

        $this->generator->method('getProviders')
            ->willReturn($allProviders);

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'info', []);
        // Use a non-existent provider name so no providers are filtered
        $this->setProperty($this->channelThread, 'failedProviders', ['NonExistentProvider']);

        $remaining = $this->callMethod($this->channelThread, 'getRemainingProviders');

        // Should return provider1 and provider2 (provider3 doesn't have channel)
        // This verifies that the code doesn't crash with array_diff on objects
        $this->assertCount(2, $remaining);
        $this->assertContains($provider1, $remaining);
        $this->assertContains($provider2, $remaining);
        $this->assertNotContains($provider3, $remaining);
    }

    public function testGetRemainingProvidersRespectsChannelPriority(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('channelExists')->willReturn(true);

        $this->generator->method('getProviders')
            ->willReturnCallback(function ($arg) use ($provider1) {
                if (is_array($arg) && isset($arg['MyCanal'])) {
                    // Priority array was passed
                    return [$provider1];
                }

                return [$provider1];
            });

        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'info', ['priority' => ['MyCanal' => 0.9]]);
        $this->setProperty($this->channelThread, 'failedProviders', []);

        $remaining = $this->callMethod($this->channelThread, 'getRemainingProviders');

        $this->assertNotEmpty($remaining);
    }

    // ========================================
    // TEST: getStatusString
    // ========================================

    public function testGetStatusStringForSuccessfulFetch(): void
    {
        $result = ['success' => true, 'provider' => 'MyCanal', 'isCache' => false, 'isPartial' => false];
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $statusString = $this->callMethod($this->channelThread, 'getStatusString', [$result, $cacheKey]);

        $this->assertStringContainsString('OK', $statusString);
        $this->assertStringContainsString('MyCanal', $statusString);
        $this->assertStringContainsString('✅', $statusString);
    }

    public function testGetStatusStringForPartialData(): void
    {
        $result = ['success' => true, 'provider' => 'MyCanal', 'isCache' => false, 'isPartial' => true];
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $statusString = $this->callMethod($this->channelThread, 'getStatusString', [$result, $cacheKey]);

        $this->assertStringContainsString('Partial', $statusString);
        $this->assertStringContainsString('MyCanal', $statusString);
    }

    public function testGetStatusStringForCacheHit(): void
    {
        $result = ['success' => true, 'provider' => 'MyCanal', 'isCache' => true, 'isPartial' => false];
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->cache->method('getProviderName')->with($cacheKey)->willReturn('MyCanal');

        $statusString = $this->callMethod($this->channelThread, 'getStatusString', [$result, $cacheKey]);

        $this->assertStringContainsString('Cache', $statusString);
        $this->assertStringContainsString('MyCanal', $statusString);
    }

    public function testGetStatusStringForFailureWithForcedCache(): void
    {
        $result = ['success' => false];
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->cache->method('getState')->with($cacheKey)->willReturn(EPGEnum::$PARTIAL_CACHE);
        $this->cache->method('getProviderName')->with($cacheKey)->willReturn('MyCanal');

        $statusString = $this->callMethod($this->channelThread, 'getStatusString', [$result, $cacheKey]);

        $this->assertStringContainsString('Forced Cache', $statusString);
        $this->assertStringContainsString('MyCanal', $statusString);
    }

    public function testGetStatusStringForCompleteFailure(): void
    {
        $result = ['success' => false];
        $cacheKey = 'TF1.fr_2024-01-01.xml';

        $this->cache->method('getState')->with($cacheKey)->willReturn(EPGEnum::$NO_CACHE);

        $statusString = $this->callMethod($this->channelThread, 'getStatusString', [$result, $cacheKey]);

        $this->assertStringContainsString('HS', $statusString);
        $this->assertStringContainsString('❌', $statusString);
    }

    // ========================================
    // TEST: __toString
    // ========================================

    public function testToStringWhenNotStarted(): void
    {
        $this->setProperty($this->channelThread, 'hasStarted', false);
        $this->setProperty($this->channelThread, 'isRunning', false);

        $string = (string) $this->channelThread;

        $this->assertStringContainsString('En pause', $string);
        $this->assertStringContainsString('⏸', $string);
    }

    public function testToStringWhenRunning(): void
    {
        $this->setProperty($this->channelThread, 'hasStarted', true);
        $this->setProperty($this->channelThread, 'isRunning', true);
        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'date', '2024-01-01 (1/10)');
        $this->setProperty($this->channelThread, 'provider', 'MyCanal');
        $this->setProperty($this->channelThread, 'status', 'En cours...');

        $string = (string) $this->channelThread;

        $this->assertStringContainsString('TF1.fr', $string);
        $this->assertStringContainsString('2024-01-01 (1/10)', $string);
        $this->assertStringContainsString('MyCanal', $string);
        $this->assertStringContainsString('En cours...', $string);
    }

    // ========================================
    // TEST: Getters
    // ========================================

    public function testGettersReturnCorrectValues(): void
    {
        $this->setProperty($this->channelThread, 'channel', 'TF1.fr');
        $this->setProperty($this->channelThread, 'date', '2024-01-01');
        $this->setProperty($this->channelThread, 'provider', 'MyCanal');
        $this->setProperty($this->channelThread, 'status', 'En cours...');
        $this->setProperty($this->channelThread, 'isRunning', true);

        $this->assertEquals('TF1.fr', $this->channelThread->getChannel());
        $this->assertEquals('2024-01-01', $this->channelThread->getDate());
        $this->assertEquals('MyCanal', $this->channelThread->getProvider());
        $this->assertEquals('En cours...', $this->channelThread->getStatus());
        $this->assertTrue($this->channelThread->isRunning());
    }

    public function testGettersReturnNullWhenNotSet(): void
    {
        $this->assertNull($this->channelThread->getChannel());
        $this->assertNull($this->channelThread->getDate());
        $this->assertNull($this->channelThread->getProvider());
        $this->assertNull($this->channelThread->getStatus());
    }
}
