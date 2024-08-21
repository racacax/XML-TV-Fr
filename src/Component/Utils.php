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
}
