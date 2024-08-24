<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class Logger
{
    private static $level = 'none';
    /**
     * @var string
     */
    private static $debugFolder = __DIR__.'/../../var/logs';
    /**
     * @var string
     */
    private static $lastLog;

    public static function setLogLevel(string $level): void
    {
        self::$level = $level;
    }
    public static function setLogFolder(string $path): void
    {
        @mkdir($path, 0777, true);

        self::$debugFolder = rtrim($path, DIRECTORY_SEPARATOR);
    }
    public static function getLastLog(): string
    {
        return self::$lastLog;
    }

    public static function log(string $log): void
    {
        self::$lastLog = $log;
        if (self::$level === 'none') {
            return;
        }

        echo $log;
    }

    public static function updateLine(string $content): void
    {
        $previousLog = self::$lastLog;
        self::log("\r".self::$lastLog . $content);

        self::$lastLog = $previousLog;
    }

    public static function debug(string $content): void
    {
        //        if (self::$level !== 'debug') {
        //            return;
        //        }
        $log_path = self::$debugFolder . DIRECTORY_SEPARATOR . 'logs'.date('YmdHis').'.json';
        file_put_contents($log_path, $content);
        self::log("\e[36m[LOGS] \e[39m Export des logs vers $log_path\n");
    }


    public static function clearLog(): void
    {
        array_map(
            function ($file) {
                unlink($file);
            },
            glob(self::$debugFolder.DIRECTORY_SEPARATOR.'*')
        );
    }

    /**
     * @return string
     */
    public static function getLogLevel(): string
    {
        return self::$level;
    }
}
