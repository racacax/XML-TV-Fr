<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\PlutoTV;
use racacax\XmlTv\ValueObject\Channel;

class PlutoTVTest extends TestCase
{
    /**
     * @var Client|\PHPUnit\Framework\MockObject\MockObject
     */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Client::class);
    }

    public function testProvider(): void
    {
        // fake all HttpClient
        $this->client
            ->expects($this->exactly(7))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode(['sessionToken' => 'token'])),
                new Response(200, [], file_get_contents('./tests/Ressources/Provider/PlutoTv/south-park.json'))
            )
        ;

        $channelObj = $this->getInstance()
            ->constructEPG('SouthPark.fr', date('Y-m-d'))
        ;

        $this->assertNotEmpty($channelObj);
        /** @var Channel $channelObj */
        $this->assertSame(Channel::class, get_class($channelObj));
        $this->assertSame(1, $channelObj->getProgramCount());
    }

    private function getInstance(): PlutoTV
    {
        return new PlutoTV(
            $this->client,
            1
        );
    }
}
