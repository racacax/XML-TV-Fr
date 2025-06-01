<?php

namespace racacax\XmlTv\Component\UI;

use racacax\XmlTv\Component\ChannelsManager;
use racacax\XmlTv\Component\Utils;

use function Amp\delay;

class MultiColumnUI implements UI
{
    private int $cursorPosition = 0;
    public function getClosure(array $threads, ChannelsManager $manager, string $logLevel): \Closure
    {
        Layout::showCursorOnExit();

        return function () use ($threads, $manager, $logLevel) {
            if ($logLevel != 'none') {
                Layout::hideCursor();
                $hasThreadRunning = true;
                while ($hasThreadRunning) {
                    $layoutLength = @Utils::getMaxTerminalLength();
                    $eventLength = max(count($threads), 5);
                    $layout = new Layout();
                    $layout->addLine([Utils::colorize('XML TV Fr - Génération des fichiers XMLTV', 'light blue')], [$layoutLength]);
                    $layout->addLine([' '], [$layoutLength]);
                    $layout->addLine([Utils::colorize('Chaines récupérées : ', 'cyan').$manager->getStatus()], [$layoutLength]);
                    $layout->addLine([' '], [$layoutLength]);
                    $columnLengths = [intval($layoutLength / 2), intval($layoutLength / 2)];
                    $layout->addLine([Utils::colorize('Threads:', 'light blue'), Utils::colorize('Derniers évènements:', 'light blue')], $columnLengths);
                    $i = 1;
                    $column1 = [];
                    foreach ($threads as $thread) {
                        $column1[] = "Thread $i : ".$thread;
                        $i++;
                    }
                    $column2 = $manager->getLatestEvents($eventLength);
                    for ($i = 0; $i < max(count($column1), count($column2)); $i++) {
                        $layout->addLine([isset($column1[$i]) ? $column1[$i] : '', @$column2[$i] ?? ''], $columnLengths);
                    }
                    $this->cursorPosition = $layout->display($this->cursorPosition);
                    $hasThreadRunning = $manager->hasRemainingChannels() || Utils::hasOneThreadRunning($threads);
                    delay(0.1); // refresh rate 1 tenth. We don't need a refresh rate higher than that.
                }
            }
        };
    }
}
