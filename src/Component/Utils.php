<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use racacax\XmlTv\Component\UI\MultiColumnUI;
use racacax\XmlTv\Component\UI\ProgressiveUI;
use racacax\XmlTv\Component\UI\UI;
use Throwable;

class Utils
{
    /**
     * @var class-string<ProviderInterface>[]|null
     */
    private static ?array $providers;

    public static function getProviders(): ?array
    {
        if (isset(self::$providers)) {
            return self::$providers;
        }

        $files = glob(__DIR__ . '/Provider/*.php');
        foreach ($files as $provider) {
            require_once $provider;
        }

        $listProvider = array_values(array_filter(
            get_declared_classes(),
            function ($className) {
                return
                    str_starts_with($className, 'racacax\\') &&
                    in_array(ProviderInterface::class, class_implements($className));
            }
        ));

        return self::$providers = $listProvider;
    }

    public static function getProvider(string $providerName): ?string
    {
        $providers = self::getProviders();
        foreach ($providers as $provider) {
            $e = explode('\\', $provider);
            if (end($e) == $providerName) {
                return $provider;
            }
        }

        return null;
    }

    public static function getChannelDataFromProvider(ProviderInterface $provider, string $channelId, string $date): string
    {
        try {
            date_default_timezone_set('Europe/Paris');
            $obj = $provider->constructEpg($channelId, $date);
        } catch (Throwable $_) {
            $obj = false;
        }
        if ($obj === false || $obj->getProgramCount() === 0) {
            $data = 'false';
        } else {
            $formatter = new XmlFormatter();
            $data = $formatter->formatChannel($obj, $provider);
        }

        return $data;
    }

    public static function extractProviderName(ProviderInterface $provider): string
    {
        $tmp = explode('\\', get_class($provider));

        return end($tmp);
    }

    public static function getContent($url, $headers): bool|string
    {
        $timeout = 3;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (preg_match('`^https://`i', $url)) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $page_content = curl_exec($ch);

        curl_close($ch);

        return $page_content;
    }

    public static function hasOneThreadRunning(array $threads): bool
    {
        foreach ($threads as $thread) {
            if ($thread->isRunning()) {
                return true;
            }
        }

        return false;
    }

    public static function colorize($content, $color = null): string
    {

        // if a color is set use the color set.
        if (!empty($color)) {
            // if our color string is not a numeric value
            if (!is_numeric($color)) {
                //lowercase our string value.
                $c = strtolower($color);

            } else {
                $c = $color;
            }

        } else {    // no color argument was passed, so lets pick a random one w00t
            $c = rand(1, 14);
        }

        $cheader = '';
        $cfooter = "\033[0m";

        // let check which color code was used so we can then wrap our content.
        switch ($c) {

            case 1:
            case 'red':

                // color code header.
                $cheader .= "\033[31m";

                break;

            case 2:
            case 'green':

                // color code header.
                $cheader .= "\033[32m";

                break;

            case 3:
            case 'yellow':

                // color code header.
                $cheader .= "\033[33m";

                break;

            case 4:
            case 'blue':

                // color code header.
                $cheader .= "\033[34m";

                break;

            case 5:
            case 'magenta':

                // color code header.
                $cheader .= "\033[35m";

                break;

            case 6:
            case 'cyan':

                // color code header.
                $cheader .= "\033[36m";

                break;

            case 7:
            case 'light grey':

                // color code header.
                $cheader .= "\033[37m";

                break;

            case 8:
            case 'dark grey':

                // color code header.
                $cheader .= "\033[90m";

                break;

            case 9:
            case 'light red':

                // color code header.
                $cheader .= "\033[91m";

                break;

            case 10:
            case 'light cyan':
            case 14:
            case 'light green':

                // color code header.
                $cheader .= "\033[92m";

                break;

            case 11:
            case 'light yellow':

                // color code header.
                $cheader .= "\033[93m";

                break;

            case 12:
            case 'light blue':

                // color code header.
                $cheader .= "\033[94m";

                break;

            case 13:
            case 'light magenta':

                // color code header.
                $cheader .= "\033[95m";

                break;

        }

        return $cheader . $content . $cfooter;


    }


    public static function recurseRmdir($dir): bool
    {
        if (file_exists($dir) && is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                (is_dir("$dir/$file") && !is_link("$dir/$file")) ? self::recurseRmdir("$dir/$file") : unlink("$dir/$file");
            }

            return rmdir($dir);
        }

        return false;
    }

    public static function getStartAndEndDatesFromXMLString(string $xmlContent): array
    {
        preg_match_all('/start="(.*?)"/', $xmlContent, $startDates);
        $startDates = array_map('strtotime', $startDates[1]);
        preg_match_all('/stop="(.*?)"/', $xmlContent, $endDates);
        $endDates = array_map('strtotime', $endDates[1]);

        return [$startDates, $endDates];
    }

    public static function getTimeRangeFromXMLString(string $xmlContent): int
    {
        /*
         * Returns the difference between earliest start time and latest start time
         * of an XML cache file, in seconds.
        */
        [$startDates, $endDates] = self::getStartAndEndDatesFromXMLString($xmlContent);
        if (count($endDates) == 0 || count($startDates) == 0) {
            return 0;
        }

        return max($endDates) - min($startDates);
    }

    public static function getCanadianRatingSystem(string $rating, $lang = 'fr'): ?string
    {
        if (in_array($rating, ['PG', '14A', '18A', 'R', 'A']) || ($rating == 'G' && $lang == 'en')) {
            return 'CHVRS';
        } elseif (in_array($rating, ['G', '13', '16', '18'])) {
            return 'RCQ';
        }

        return null;
    }

    /**
     * Merge all channels file used from a guide. If a channel is present in multiple file, the information from the latest file will be used
     * @param array $guide
     * @return array|mixed
     */
    public static function getChannelsFromGuide(array $guide)
    {
        if (is_string($guide['channels'])) {
            return json_decode(file_get_contents($guide['channels']), true);
        } elseif (is_array($guide['channels'])) {
            $channels_arrays = [];
            foreach ($guide['channels'] as $channelFile) {
                $channels_arrays[] = json_decode(file_get_contents($channelFile), true) ?? [];
            }

            return array_merge(...$channels_arrays);
        } else {
            return [];
        }
    }

    /**
     * Converts a string into a slug. It should be lowercase, with spaces becoming dashes
     * @param string $string
     * @return string
     */
    public static function slugify(string $string): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
    }

    /**
     * @return int Number of columns available in the terminal
     */
    public static function getMaxTerminalLength(): int
    {
        try {
            $descriptorspec = [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open('tput cols', $descriptorspec, $pipes);

            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                if (is_numeric(trim($output))) {
                    return (int) trim($output);
                }
            }

            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                $process = proc_open('mode con', $descriptorspec, $pipes);
                if (is_resource($process)) {
                    $output = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    $exp = explode(':', $output);
                    $columns = trim(explode("\n", @$exp[3] ? $exp[3] : '')[0]);
                    if (!empty($columns)) {
                        return (int) $columns;
                    }
                }
            }
        } catch (Throwable $_) {

        }

        return 180;
    }

    /**
     * mb_strwidth may return wrong width for some emojis. We replace those emojis with corresponding width as spaces
     * to properly calculate the width afterwards
     * @param string $string
     * @return string
     */
    public static function replaceBuggyWidthCharacters(string $string): string
    {
        $elems = [['chars' => [TerminalIcon::success(), TerminalIcon::error(), TerminalIcon::pause()], 'width' => 2]];
        foreach ($elems as $elem) {
            foreach ($elem['chars'] as $char) {
                $string = str_replace($char, str_repeat(' ', $elem['width']), $string);
            }
        }

        return $string;
    }

    public static function getUI(string $ui): UI
    {
        if ($ui === 'MultiColumnUI') {
            return new MultiColumnUI();
        } else {
            return new ProgressiveUI();
        }
    }
}
