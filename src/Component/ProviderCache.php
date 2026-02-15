<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class ProviderCache
{
    private static string $PATH = 'var/provider/';
    private string $file;
    public function __construct(string $file)
    {
        $file = str_replace(['../', '..' . DIRECTORY_SEPARATOR, '/', '\\'], '_', $file);
        $file = basename($file);

        $this->file = $file;
    }

    public function getContent(): string|null
    {
        if (file_exists(self::$PATH.$this->file)) {
            return file_get_contents(self::$PATH.$this->file);
        }

        return null;
    }


    public function getArray(): array
    {
        $content = $this->getContent() ?? '[]';

        $result = json_decode($content, true);

        return is_array($result) ? $result : [];
    }


    public function setArrayKey(string $key, mixed $content): void
    {
        $array = $this->getArray();
        $array[$key] = $content;
        $this->setContent(json_encode($array));
    }

    public function setContent(string $content): void
    {
        @mkdir(self::$PATH, 0777, true);
        file_put_contents(self::$PATH.$this->file, $content);
    }

    public static function clearCache(): void
    {
        Utils::recurseRmdir(self::$PATH);
    }
}
