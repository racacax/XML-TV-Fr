<?php

namespace racacax\XmlTv\Component\Provider;

use DateTimeImmutable;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class DummyProvider extends AbstractProvider implements ProviderInterface {
    public function __construct(Client $___, ?float $_ = null, array $__ = [])
    {
        parent::__construct($___, "NOFILE", 0.1);
    }
    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = new Channel($channel, "", "TestChannel");
        if($channel == "TestChannel.fr") {
            $program = new Program(strtotime($date." 00:00"),strtotime($date." 01:00"));
            $program->addTitle("My Title");
            $channelObj->addProgram($program);
            return $channelObj;
        } else if($channel == "TestChannelException.fr") {
            throw new \Exception();
        } else if($channel == "TestChannelNoProgram.fr") {
            return $channelObj;
        }
        return false;
    }

    public function generateUrl(Channel $channel, DateTimeImmutable $date): string
    {
        return "";
    }
}