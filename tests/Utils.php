<?php

namespace racacax\XmlTvTest;

class Utils
{
    public static function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::glob_recursive($dir.'/'.basename($pattern), $flags));
        }

        return $files;
    }

    public static function generateHash(): string
    {
        $dirs = ['example', 'resources', 'src', 'tests', 'tools'];
        $hashes = '';
        foreach ($dirs as $dir) {
            $files = self::glob_recursive($dir.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $hashes .= hash('sha256', str_replace("\r", '', file_get_contents($file)));
                }
            }
        }

        return hash('sha256', $hashes);
    }
}
