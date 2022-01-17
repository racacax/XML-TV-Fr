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
    private $createdKeys = [];

    public function __construct(string $basePath)
    {
        @mkdir($basePath, 0777, true);

        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public function store(string $key, string $content)
    {
        $fileName = $this->basePath . DIRECTORY_SEPARATOR . $key;

        if (false === file_put_contents($fileName, $content)) {
            throw new \Exception('Impossible to cache : ' . $key);
        }
        $this->listFile[$key] = [
            'file'=> $fileName,
            'key' => $key
        ];
    }

    public function has(string $key): bool
    {
        if (isset($this->listFile[$key])) {
            return true;
        }
        $fileName = $this->basePath . DIRECTORY_SEPARATOR . $key;
        if(count(explode(date('Y-m-d'), $key)) > 1 && !isset($this->createdKeys[$key])) {
            unlink($fileName);
            $this->createdKeys[$key] = true;
            return false;
        }
        if (file_exists($fileName)) {
            $this->listFile[$key] = [
                'file'=> $fileName,
                'key' => $key
            ];
            return true;
        }

        return false;
    }

    public function get(string $key): string
    {
        if (!$this->has($key)) {
            throw new \Exception("Cache '$key' not found");
        }

        return file_get_contents($this->listFile[$key]['file']);
    }

    public function clearCache(int $maxCacheDay): void
    {
        $files = glob($this->basePath.DIRECTORY_SEPARATOR.'*');

        foreach ($files as $file) {
            if (time()-filemtime($file) >= 86400 * $maxCacheDay) {
                unlink($file);
            }
        }
    }
}
