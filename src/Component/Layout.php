<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;

class Layout
{
    private array $lines;
    private array $linesColumnLayouts;
    public function __construct()
    {
        $this->lines = [];
        $this->linesColumnLayouts = [];
    }

    public function addLine(array $columns, array $layout): void
    {
        $this->lines[] = $columns;
        $this->linesColumnLayouts[] = $layout;
    }

    public function resetScreen(): void
    {
        echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
    }
    public static function getVisibleLength(string $string): int
    {
        $clean = preg_replace('/\e\[[0-9;]*m/', '', $string);

        return strlen($clean);
    }

    private function displayLine(int $i): void
    {
        $line = $this->lines[$i];
        $layout = $this->linesColumnLayouts[$i];
        for ($j = 0; $j < count($layout); $j++) {
            $columnLength = $layout[$j];
            $column = str_replace("\t", ' ', $line[$j]);
            $currentColumnLength = $this->getVisibleLength($column);
            while ($currentColumnLength > $columnLength) {
                $column = substr($column, 0, -1);
                $currentColumnLength = $this->getVisibleLength($column);
            }
            if ($currentColumnLength < $columnLength) {
                $column .= str_repeat(' ', $columnLength - $currentColumnLength);
            }
            echo $column."\033[0m";
        }
        echo "\n";
    }
    public function display(): void
    {
        $this->resetScreen();
        for ($i = 0; $i < count($this->lines); $i++) {
            $this->displayLine($i);
        }
    }
}
