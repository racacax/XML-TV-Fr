<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

use Exception;

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
    private bool $forceTodayGrab;
    private int $minTimeRange;
    public static int $NO_CACHE = 0;
    public static int $OBSOLETE_CACHE = 1;
    public static int $PARTIAL_CACHE = 2;
    public static int $FULL_CACHE = 3;

    public function __construct(string $basePath, bool $forceTodayGrab, int $minTimeRange)
    {
        @mkdir($basePath, 0777, true);

        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->forceTodayGrab = $forceTodayGrab;
        $this->minTimeRange = $minTimeRange;
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
            'state' => $this->has($key)
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

    public function has(string $key): int
    {
        if (isset($this->listFile[$key])) {
            return ($this->listFile[$key]['state']);
        }

        if (str_contains($key, date('Y-m-d')) && $this->forceTodayGrab && !isset($this->createdKeys[$key])) {
            return self::$OBSOLETE_CACHE;
        }
        if (file_exists($this->getFileName($key))) {
            $timeRange = Utils::getTimeRangeFromXMLString($this->getFileContent($key));

            $cacheState = self::$FULL_CACHE;
            if ($timeRange < $this->minTimeRange) {
                $cacheState = self::$PARTIAL_CACHE;
            }

            return $cacheState;
        }

        return self::$NO_CACHE;
    }

    /**
     * @throws Exception
     */
    public function get(string $key): string
    {
        if (!$this->has($key)) {
            throw new Exception("Cache '$key' not found");
        }

        return $this->getFileContent($key);
    }


    /**
     * @throws Exception
     */
    public function clear(string $key): bool
    {
        if (!$this->has($key)) {
            throw new Exception("Cache '$key' not found");
        }
        $file = $this->listFile[$key]['file'];
        unset($this->listFile[$key]);

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
