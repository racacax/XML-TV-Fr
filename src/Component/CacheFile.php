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

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function store(string $key, string $content)
    {
        $fileName = $this->basePath . $key;
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
        $fileName = $this->basePath . $key;
        if (file_exists($fileName)){
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
}