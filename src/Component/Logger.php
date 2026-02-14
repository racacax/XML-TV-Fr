<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class Logger
{
    private static string $level = 'none';
    private static string $debugFolder = __DIR__ . '/../../var/logs';
    private static string $lastLog = '';

    private static array $logFile = [];

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
        $content = "\r" . self::$lastLog . $content;
        $content = substr($content, 0, 100);
        self::log("\r".str_repeat(' ', 100));
        self::log($content);
        self::$lastLog = $previousLog;
    }

    public static function save(array $guides): void
    {
        if (self::$level !== 'debug') {
            return;
        }
        $log_path = self::$debugFolder . DIRECTORY_SEPARATOR . 'logs' . date('YmdHis') . '.json';
        $formattedLogs = [];
        foreach ($guides as $guide) {
            $guideChannels = array_keys(Utils::getChannelsFromGuide($guide));
            $filename = $guide['filename'];
            if (!isset($formattedLogs[$filename])) {
                $formattedLogs[$filename] = ['channels' => [], 'failed_providers' => self::$logFile['failed_providers'] ?? []];
            }
            foreach ((self::$logFile['channels'] ?? []) as $date => $logChannels) {
                if (!isset($formattedLogs[$filename]['channels'][$date])) {
                    $formattedLogs[$filename]['channels'][$date] = [];
                }
                foreach ($logChannels as $logChannel => $value) {
                    if (in_array($logChannel, $guideChannels)) {
                        $formattedLogs[$filename]['channels'][$date][$logChannel] = $value;
                    }
                }
            }
        }
        file_put_contents($log_path, json_encode($formattedLogs));
        self::log("\e[36m[LOGS] \e[39m Export des logs vers $log_path\n");
    }

    public static function addChannelEntry(string $channel, string $date): void
    {
        if (!isset(self::$logFile['channels'][$date][$channel])) {
            self::$logFile['channels'][$date][$channel] = [
                'success' => false,
                'provider' => null,
                'cache' => false,
                'failed_providers' => [],
            ];
        }
    }
    public static function addChannelFailedProvider(string $channel, string $date, string $provider): void
    {
        self::addChannelEntry($channel, $date);
        self::$logFile['channels'][$date][$channel]['failed_providers'][] = $provider;
        self::$logFile['failed_providers'][$provider] = true;
    }
    public static function setChannelSuccessfulProvider(string $channel, string $date, string $provider, bool $isCache = false): void
    {
        self::addChannelEntry($channel, $date);
        self::$logFile['channels'][$date][$channel]['success'] = true;
        self::$logFile['channels'][$date][$channel]['provider'] = $provider;
        self::$logFile['channels'][$date][$channel]['cache'] = $isCache;
    }

    public static function hasChannelSuccessfulProvider(string $channel, string $date): bool
    {
        return @(self::$logFile['channels'][$date][$channel]['success']) ?? false;
    }

    public static function addAdditionalError(string $error, string $message): void
    {
        if (!isset(self::$logFile['additional_errors'])) {
            self::$logFile['additional_errors'] = [
            ];
        }
        self::$logFile['additional_errors'][] = ['error' => $error, 'message' => $message];
    }


    public static function clearLog(): void
    {
        array_map(
            function ($file) {
                unlink($file);
            },
            glob(self::$debugFolder . DIRECTORY_SEPARATOR . '*')
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
