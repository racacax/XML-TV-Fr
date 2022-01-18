<?php

declare(strict_types=1);

namespace racacax\XmlTv\ValueObject;

use racacax\XmlTv\Component\ChannelFactory;

class DummyChannel extends Channel
{
    public function __construct(string $id, $date)
    {
        $channel = ChannelFactory::createChannel($id);
        parent::__construct($channel->getId(), $channel->getIcon(), $channel->getName());

        for ($i = 0; $i < 12; $i++) {
            $time = strtotime($date) + $i * 2 * 3600;

            $program = new Program($time, $time + 2 * 3600);
            $program->addTitle('Aucun programme');

            $this->addProgram($program);
        }
    }
}
