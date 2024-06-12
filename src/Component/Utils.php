<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class Utils
{
    /**
     * @var class-string<ProviderInterface>[]|null
     */
    private static $providers;

    public static function getProviders()
    {
        if (isset(self::$providers)) {
            return self::$providers;
        }

        $files = glob(__DIR__.'/Provider/*.php');
        foreach ($files as $provider) {
            require_once $provider;
        }

        $listProvider = array_values(array_filter(
            get_declared_classes(),
            function ($className) {
                return
                    strpos($className, 'racacax\\') === 0 &&
                    in_array(ProviderInterface::class, class_implements($className));
            }
        ));

        return self::$providers = $listProvider;
    }

    public static function extractProviderName(ProviderInterface $provider): string
    {
        $tmp = explode('\\', get_class($provider));

        return end($tmp);
    }

    public static function getContent($url, $headers)
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
            if($thread->isRunning()) {
                return true;
            }
        }
        return false;
    }

    public static function colorize($content, $color=null)
    {

        // if a color is set use the color set.
        if(!empty($color))
        {
            // if our color string is not a numeric value
            if(!is_numeric($color))
            {
                //lowercase our string value.
                $c = strtolower($color);

            }
            else
            {
                // check if our color value is not empty.
                if(!empty($color))
                {

                    $c = $color;

                }
                else
                {
                    // no color was set so lets pick a random one...
                    $c = rand(1,14);

                }

            }

        }
        else    // no color argument was passed, so lets pick a random one w00t
        {

            $c = rand(1,14);

        }

        $cheader = '';
        $cfooter = "\033[0m";

        // let check which color code was used so we can then wrap our content.
        switch($c)
        {

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

            case 14:
            case 'light cyan':

                // color code header.
                $cheader .= "\033[92m";

                break;

        }

        // wrap our content.
        $content = $cheader.$content.$cfooter;

        //return our new content.
        return $content;


    }
}
