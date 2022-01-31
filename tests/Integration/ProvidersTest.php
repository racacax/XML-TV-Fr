<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Integration;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\PlutoTV;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\Channel;

class ProvidersTest extends TestCase
{
    /**
     * @dataProvider dataProviderListProvider
     */
    public function testOneChannelOnAllProvider(ProviderInterface $provider): void
    {
        $channels = $provider->getChannelsList();
        $this->assertGreaterThanOrEqual(1, count($channels), 'Provider without channel');
        $channelObj = null;
        $count = 0;
        foreach ($channels as $channelCode => $_) {
            $count++;
            $channelObj = $provider->constructEPG($channelCode, date('Y-m-d'));
            if (false !== $channelObj && $channelObj->getProgramCount()>0) {
                break;
            }
            // test only 3 channels
            if ($count>=3) {
                break;
            }
        }

        $this->assertNotEmpty($channelObj, 'Error on provider : ' .get_class($provider));
        /** @var Channel $channelObj */
        $this->assertSame(Channel::class, get_class($channelObj));
        $this->assertGreaterThan(1, $channelObj->getProgramCount(), 'Channel without programs');
    }


    /**
     * @return \Generator<ProviderInterface[]>
     */
    public function dataProviderListProvider(): \Generator
    {
        $configurator = new Configurator();
        $providers = $configurator->getGenerator()->getProviders();

        foreach ($providers as $provider) {
            // ignore PlutoTv
            if (PlutoTV::class === get_class($provider)) {
                continue;
            }
            yield [$provider];
        }
    }
}
