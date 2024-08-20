<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Integration;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\Bouygues;
use racacax\XmlTv\Component\Provider\DAZN;
use racacax\XmlTv\Component\Provider\ICIRadioCanadaTele;
use racacax\XmlTv\Component\Provider\MyCanal;
use racacax\XmlTv\Component\Provider\NouvelObs;
use racacax\XmlTv\Component\Provider\Orange;
use racacax\XmlTv\Component\Provider\PlayTV;
use racacax\XmlTv\Component\Provider\PlutoTV;
use racacax\XmlTv\Component\Provider\Proximus;
use racacax\XmlTv\Component\Provider\SFR;
use racacax\XmlTv\Component\Provider\SixPlay;
use racacax\XmlTv\Component\Provider\Skweek;
use racacax\XmlTv\Component\Provider\Tebeosud;
use racacax\XmlTv\Component\Provider\Tele7Jours;
use racacax\XmlTv\Component\Provider\Teleboy;
use racacax\XmlTv\Component\Provider\Telecablesat;
use racacax\XmlTv\Component\Provider\TeleLoisirs;
use racacax\XmlTv\Component\Provider\Telerama;
use racacax\XmlTv\Component\Provider\TV5;
use racacax\XmlTv\Component\Provider\TV5Global;
use racacax\XmlTv\Component\Provider\TVHebdo;
use racacax\XmlTv\Component\Provider\Voo;
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\Channel;

/**
 * @group integration
 */
class ProvidersTest extends TestCase
{
    private static array $TESTED_PROVIDERS_CHANNELS = [
        [Bouygues::class, ["channels" => ["TF1.fr"]]],
        [DAZN::class, ["channels" => ["DAZNLigue1.fr"]]],
        [ICIRadioCanadaTele::class, ["channels" => ["CBAFT.ca"]]],
        [MyCanal::class, ["channels" => ["TF1.fr"]]],
        [NouvelObs::class, ["channels" => ["TF1.fr"]]],
        [Orange::class, ["channels" => ["TF1.fr"]]],
        [PlayTV::class, ["channels" => ["TF1.fr"]]],
        [Proximus::class, ["channels" => ["TF1.fr"]]],
        [SFR::class, ["channels" => ["TF1.fr"]]],
        [SixPlay::class, ["channels" => ["M6.fr"]]],
        [Tebeosud::class, ["channels" => ["Tebeo.fr"]]],
        [Tele7Jours::class, ["channels" => ["TF1.fr"]]],
        [Telecablesat::class, ["channels" => ["BFMTV.fr"]]],
        [TeleLoisirs::class, ["channels" => ["TF1.fr"]]],
        [Telerama::class, ["channels" => ["TF1.fr"]]],
        [TV5::class, ["channels" => ["TV5MondeAsie.fr"]]],
        [TV5Global::class, ["channels" => ["TV5MondeAsie.fr"]]],
        [Voo::class, ["channels" => ["TF1.fr"]]],
        [TVHebdo::class, ["channels" => ["RDS.ca"]]],
    ];
    private static array $IGNORED_PROVIDERS = [PlutoTV::class, Skweek::class, Teleboy::class];

    /**
     * All Providers must have at least a channel to gather or to have specifically been ignored
     */
    public function testAllProvidersHaveBeenConsidered(): void
    {
        $configurator = new Configurator();
        $allProviders = array_map(function ($p) {
            return get_class($p);
        }, $configurator->getGenerator()->getProviders());

        foreach (self::$TESTED_PROVIDERS_CHANNELS as $data) {
            $this->assertGreaterThanOrEqual(count($data[1]['channels']), 1);
        }
        $testedProviders = array_merge(array_map(function($p) { return $p[0]; }, self::$TESTED_PROVIDERS_CHANNELS), self::$IGNORED_PROVIDERS);
        sort($testedProviders);
        sort($allProviders);
        $this->assertEquals($allProviders, $testedProviders);
    }

    /**
     * @dataProvider dataProviderListProvider
     */
    public function testOneChannelOnAllProvider(string $provider, array $data): void
    {
        $channels = $data["channels"];
        $configurator = new Configurator();
        $provider = new $provider($configurator->getDefaultClient());
        $formatter = new XmlFormatter();
        $channelObj = null;
        $count = 0;
        foreach ($channels as $channelCode) {
            $count++;
            $channelObj = $provider->constructEPG($channelCode, date('Y-m-d'));
            if (false !== $channelObj) {
                if ($channelObj->getProgramCount() > 0) {
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


        }

        $this->assertNotEmpty($channelObj, 'Error on provider : ' . get_class($provider));
        /** @var Channel $channelObj */
        $this->assertSame(Channel::class, get_class($channelObj));
        // $this->assertGreaterThan(1, $channelObj->getProgramCount(), 'Channel '.$channelObj->getName().' without programs with provider '.get_class($provider));
        // the goal of this application is to build xml, so we need to test the generation
        $this->assertNotEmpty($formatter->formatChannel($channelObj, $provider));
    }


    /**
     */
    public function dataProviderListProvider(): array
    {
        $data = self::$TESTED_PROVIDERS_CHANNELS;
        $array = [];
        foreach($data as $testItem) {
            $exp = explode('\\', $testItem[0]);
            $array[end($exp)] = $testItem;
        }
        return $array;
    }
}
