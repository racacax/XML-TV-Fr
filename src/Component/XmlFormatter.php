<?php

namespace racacax\XmlTv\Component;

use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;
use racacax\XmlTv\ValueObject\Tag;

/**
 * @author benoit
 *
 * class Formatter
 * @package racacax\XmlTv\Component
 */
class XmlFormatter
{
    private static array $DEFAULT_MANDATORY_FIELDS = [
        'title' => 'Aucun titre',
    ];
    public function formatChannel(Channel $channel, ?ProviderInterface $provider): string
    {
        $content = [];
        if (isset($provider)) {
            $content[] = '<!-- ' . get_class($provider) . ' -->';
        }

        foreach ($channel->getPrograms() as $program) {
            $this->fillMandatoryFields($program);
            $program->addAttribute('channel', $channel->getId());
            $content[] = $program->asXML();
        }

        return implode("\n", array_filter($content));
    }

    private function fillMandatoryFields(Program $program): void
    {
        foreach (self::$DEFAULT_MANDATORY_FIELDS as $mandatoryField => $defaultValue) {
            if (empty($program->getChildren($mandatoryField))) {
                $program->setChild(new Tag($mandatoryField, $defaultValue));
            }
        }
    }
}
