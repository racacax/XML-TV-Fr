<?php

namespace racacax\XmlTv\Component\UI;

use racacax\XmlTv\Component\ChannelsManager;
use racacax\XmlTv\Component\Utils;

use function Amp\delay;

class ProgressiveUI implements UI
{
    private int $cursorPosition = 0;
    public function getClosure(array $threads, ChannelsManager $manager, string $logLevel): \Closure
    {
        Layout::showCursorOnExit();
        $this->cursorPosition = 0;

        return function () use ($threads, $manager, $logLevel) {
            $eventsDisplayed = 0;
            if ($logLevel != 'none') {
                Layout::hideCursor();
                $hasThreadRunning = true;
                echo Utils::colorize("XML TV Fr - Génération des fichiers XMLTV\n", 'light blue');
                while ($hasThreadRunning) {
                    $layoutLength = @Utils::getMaxTerminalLength();
                    $events = $manager->getLatestEvents(PHP_INT_MAX);
                    $count = count($events);
                    $layout = new Layout();
                    $eventsDisplayedCount = 0;
                    if ($count > $eventsDisplayed) {
                        $eventsToDisplay = array_slice($events, $eventsDisplayed);
                        $eventsDisplayedCount = count($eventsToDisplay);
                        $eventsDisplayed = $count;
                        foreach ($eventsToDisplay as $event) {
                            $layout->addLine([$event], [$layoutLength]);
                        }
                    }
                    $i = 1;
                    $layout->addLine([str_repeat('-', $layoutLength)], [$layoutLength]);
                    foreach ($threads as $thread) {
                        $layout->addLine(["Thread $i : ".$thread], [$layoutLength]);
                        $i++;
                    }
                    $this->cursorPosition = $layout->display($this->cursorPosition) - $eventsDisplayedCount;
                    $hasThreadRunning = $manager->hasRemainingChannels() || Utils::hasOneThreadRunning($threads);
                    delay(0.1); // refresh rate 1 tenth. We don't need a refresh rate higher than that.
                }
            }
        };
    }
}
