<?php
declare(strict_types=1);

namespace racacax\XmlTv\Component;

class Utils {

    /**
     * @var class-string<ProviderInterface>[]
     */
    private static $providers;

    public static function getProviders()
    {
        if(isset(self::$providers)){
            return self::$providers;
        }

        $files = glob('src/Component/Provider/*.php');
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

        return self::$providers= $listProvider;
    }

    public static function extractProviderName(ProviderInterface $provider): string
    {
        $tmp = explode('\\', get_class($provider));

        return end($tmp);
    }
}