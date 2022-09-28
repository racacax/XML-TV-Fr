<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Integration;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\PlutoTV;
use racacax\XmlTv\Component\Provider\ViniPF;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\Channel;

class ProvidersTest extends TestCase
{
    /**
     * @dataProvider dataProviderListProvider
     */
    public function testOneChannelOnAllProvider(ProviderInterface $provider): void
    {
        $channels = array_keys($provider->getChannelsList());
        shuffle($channels);
        $this->assertGreaterThanOrEqual(1, count($channels), 'Provider without channel');
        $formater = new XmlFormatter();
        $channelObj = null;
        $count = 0;
        foreach ($channels as $channelCode) {
            $count++;
            $channelObj = $provider->constructEPG($channelCode, date('Y-m-d'));
            if (false !== $channelObj) {
                if ($channelObj->getProgramCount()>0) {
                    break;
                }

                $this->addWarning(
                    sprintf(
                        'Provider "%s" has empty channel : "%s", it is normal ?',
                        Utils::extractProviderName($provider),
                        $channelCode
                    )
                );
            }

            //Some Provider have some empty channel AND has cache for other channel
            if (ViniPF::class === get_class($provider)) {
                continue;
            }
            // test only 3 channels
            if ($count>=3) {
                break;
            }
        }

        $this->assertNotEmpty($channelObj, 'Error on provider : ' .get_class($provider));
        /** @var Channel $channelObj */
        $this->assertSame(Channel::class, get_class($channelObj));
        // $this->assertGreaterThan(1, $channelObj->getProgramCount(), 'Channel '.$channelObj->getName().' without programs with provider '.get_class($provider));
        // the goal of this application is to build xml, so we need to test the generation
        $this->assertNotEmpty($formater->formatChannel($channelObj, $provider));
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
