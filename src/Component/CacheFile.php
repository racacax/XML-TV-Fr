<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use Exception;
use racacax\XmlTv\Configurator;
use racacax\XmlTv\ValueObject\EPGEnum;

class CacheFile
{
    private string $basePath;

    private array $listFile = [];
    /**
     * This var store all key created during the current process
     */
    private array $createdKeys = [];
    /**
     * This bool help to ignore (and remove) the cache of the day
     */
    private Configurator $config;

    public function __construct(string $basePath, Configurator $config)
    {
        @mkdir($basePath, 0777, true);

        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->config = $config;
    }

    /**
     * @throws Exception
     */
    public function store(string $key, string $content): void
    {
        $fileName = $this->basePath . DIRECTORY_SEPARATOR . $key;

        if (false === file_put_contents($fileName, $content)) {
            throw new Exception('Impossible to cache : ' . $key);
        }
        $this->createdKeys[$key] = true;
        $this->listFile[$key] = [
            'file' => $fileName,
            'key' => $key,
            'state' => $this->getState($key)
        ];
    }

    private function getFileName(string $key): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $key;
    }
    private function getFileContent(string $key): string
    {

        return file_get_contents($this->getFileName($key));
    }

    public function getState(string $key): int
    {
        if (isset($this->listFile[$key])) {
            return ($this->listFile[$key]['state']);
        }
        $exists = file_exists($this->getFileName($key));
        if (str_contains($key, date('Y-m-d')) && $this->config->isForceTodayGrab() && !isset($this->createdKeys[$key])) {
            return $exists ? EPGEnum::$OBSOLETE_CACHE : EPGEnum::$NO_CACHE;
        }
        if ($exists) {
            $timeRange = Utils::getTimeRangeFromXMLString($this->getFileContent($key));

            $cacheState = EPGEnum::$FULL_CACHE;
            if ($timeRange < $this->config->getMinTimeRange()) {
                $cacheState = EPGEnum::$PARTIAL_CACHE;
            }

            return $cacheState;
        }

        return EPGEnum::$NO_CACHE;
    }

    /**
     * @throws Exception
     */
    public function get(string $key): string
    {
        if (!$this->getState($key)) {
            throw new Exception("Cache '$key' not found");
        }

        return $this->getFileContent($key);
    }


    /**
     * @throws Exception
     */
    public function clear(string $key): bool
    {
        if (!$this->getState($key)) {
            throw new Exception("Cache '$key' not found");
        }
        $file = $this->getFileName($key);
        if (in_array($key, $this->listFile)) {
            unset($this->listFile[$key]);
        }

        return unlink($file);
    }

    public function clearCache(int $maxCacheDay): void
    {
        $files = glob($this->basePath.DIRECTORY_SEPARATOR.'*');

        foreach ($files as $file) {
            if (time() - filemtime($file) >= 86400 * $maxCacheDay) {
                unlink($file);
            }
        }
    }
}
