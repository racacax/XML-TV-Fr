<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\UI;

use racacax\XmlTv\Component\Utils;

class Layout
{
    private array $lines;
    private array $linesColumnLayouts;
    public function __construct()
    {
        $this->lines = [];
        $this->linesColumnLayouts = [];
    }

    /**
     * Disables Terminal cursor
     * @return void
     */
    public static function hideCursor(): void
    {
        echo "\033[?25l";
    }

    private static function showCursor(): void
    {
        echo "\033[?25h";
    }

    /**
     * @return void
     */
    public static function showCursorOnExit(): void
    {
        register_shutdown_function(function () {
            self::showCursor();
        });
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function ($_) {
                self::showCursor();
                exit;
            });
        }
    }
    public function addLine(array $columns, array $layout): void
    {
        $this->lines[] = $columns;
        $this->linesColumnLayouts[] = $layout;
    }

    public static function resetScreen(): void
    {
        echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
    }
    public static function getVisibleLength(string $string): int
    {
        $clean = preg_replace('/\e\[[0-9;]*m/', '', $string);
        $clean = Utils::replaceBuggyWidthCharacters($clean);

        return mb_strwidth($clean, 'UTF-8');
    }

    private function displayLine(int $i): void
    {
        $line = $this->lines[$i];
        $layout = $this->linesColumnLayouts[$i];
        for ($j = 0; $j < count($layout); $j++) {
            $columnLength = $layout[$j];
            $column = $line[$j];
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

    private function getLineCount(): int
    {
        $lines = array_map(function ($line) {
            return join(' ', $line);
        }, $this->lines);
        $result = explode("\n", join("\n", $lines));

        return count($result);
    }
    private function moveCursorUp(int $cursorPosition): void
    {
        echo "\033[{$cursorPosition}A";
    }
    private function clearLine(): void
    {
        echo "\033[2K";
    }

    /**
     * Display lines and return where next cursor position should be
     * @param int $cursorPosition
     * @return int
     */
    public function display(int $cursorPosition): int
    {
        if ($cursorPosition > 0) {
            $this->moveCursorUp($cursorPosition);
        }
        $lineCount = $this->getLineCount();
        for ($i = 0; $i < count($this->lines); $i++) {
            $this->displayLine($i);
        }
        if ($lineCount < $cursorPosition) {
            for ($i = $lineCount; $i < $cursorPosition; $i++) {
                $this->clearLine();
                echo "\n";
            }
            $lineCount = $cursorPosition;
        }

        return $lineCount;
    }
}
