<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

class Logger
{
    private static $level = 'none';

    public static function setLogLevel(string $level): void
    {
        self::$level = $level;
    }

    public static function log(string $log): void
    {
        if (self::$level==='none') {
            return;
        }

        echo $log;
    }
}