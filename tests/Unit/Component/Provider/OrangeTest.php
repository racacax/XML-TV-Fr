<?php

namespace racacax\XmlTvTest\Unit\Component\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use racacax\XmlTv\Component\Provider\Orange;
use racacax\XmlTv\Component\XmlFormatter;
use racacax\XmlTv\ValueObject\Channel;

class OrangeTest extends TestCase
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
            ->expects($this->exactly(1))
            ->method('get')
            ->willReturn(
                new Response(200, [], file_get_contents('./tests/Ressources/Provider/Orange/tf1.json'))
            )
        ;

        $provider = $this->getInstance();
        $channelObj = $provider->constructEPG('TF1.fr', date('Y-m-d'));

        $this->assertNotEmpty($channelObj);
        /** @var Channel $channelObj */
        $this->assertSame(Channel::class, get_class($channelObj));
        $this->assertSame(1, $channelObj->getProgramCount());


        $formater = new XmlFormatter();
        $content = $formater->formatChannel($channelObj, $provider);
        $this->assertSame(
            file_get_contents('./tests/Ressources/Provider/Orange/tf1-formatted.xml'),
            $content
        );
    }


    private function getInstance(): Orange
    {
        return new Orange(
            $this->client,
            1
        );
    }
}
