<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class ProcessCache
{
    private string $path;
    public function __construct($mode = "cache") {
        if($mode == "cache") {
            $this->path = "var/process";
        } else {
            $this->path = "var/status";
        }
    }
    public function save(string $fileName, string $content) {
        @mkdir($this->path, 0777, true);
        file_put_contents($this->path."/".$fileName, $content);
    }
    public function pop(string $fileName) {
        $content = file_get_contents($this->path."/".$fileName);
        unlink($this->path."/".$fileName);
        return $content;
    }

    public function exists(string $fileName) {
        return file_exists($this->path."/".$fileName);
    }
}