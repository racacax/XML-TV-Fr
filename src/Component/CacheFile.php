<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class CacheFile
{
    /**
     * @var string
     */
    private $basePath;

    private $listFile = [];
    /**
     * This var store all key created during the current process
     * @var array
     */
    private $createdKeys = [];
    /**
     * This bool help to ignore (and remove) the cache of the day
     * @var bool
     */
    private $forceTodayGrab;

    public function __construct(string $basePath, bool $forceTodayGrab)
    {
        @mkdir($basePath, 0777, true);

        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->forceTodayGrab = $forceTodayGrab;
    }

    public function store(string $key, string $content)
    {
        $fileName = $this->basePath . DIRECTORY_SEPARATOR . $key;

        if (false === file_put_contents($fileName, $content)) {
            throw new \Exception('Impossible to cache : ' . $key);
        }
        $this->createdKeys[$key] = true;
        $this->listFile[$key] = [
            'file' => $fileName,
            'key' => $key
        ];
    }

    public function has(string $key, bool $preventClear = false): bool
    {
        if (isset($this->listFile[$key])) {
            return true;
        }
        $fileName = $this->basePath . DIRECTORY_SEPARATOR . $key;
        $allowClear = !$preventClear && $this->forceTodayGrab;
        if ($allowClear && str_contains($key, date('Y-m-d')) && !isset($this->createdKeys[$key])) {
            @unlink($fileName);
            $this->createdKeys[$key] = true;

            return false;
        }
        if (file_exists($fileName)) {
            $this->listFile[$key] = [
                'file' => $fileName,
                'key' => $key
            ];

            return true;
        }

        return false;
    }

    public function get(string $key, bool $preventClear = false): string
    {
        if (!$this->has($key, $preventClear)) {
            throw new \Exception("Cache '$key' not found");
        }

        return file_get_contents($this->listFile[$key]['file']);
    }


    public function clear(string $key): bool
    {
        if (!$this->has($key)) {
            throw new \Exception("Cache '$key' not found");
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
