<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class TerminalIcon
{
    public static function pause(): string
    {
        return '⏸️';
    }

    /**
     * Spin with a 1/10 frequency
     * @return string
     */
    public static function spinner(): string
    {
        $parts = ['⠋','⠙','⠹','⠸','⠼','⠴','⠦','⠧','⠇','⠏'];
        $index = intval(microtime(true) * 10) % 10;

        return $parts[$index];
    }

    public static function success(): string
    {
        return '✅';
    }

    public static function error(): string
    {
        return '❌';
    }
}
