<?php

namespace racacax\XmlTv\Component;

use Closure;

use function Amp\async;
use function Amp\delay;

class MultiThreadedGenerator extends Generator
{
    private int $cursorPosition = 0;
    /**
     * Fonction d'affichage de l'UI
     * @param array $threads
     * @param ChannelsManager $manager
     * @param array $guide
     * @param string $logLevel
     * @param int $index
     * @param int $guidesCount
     * @return Closure
     */
    protected function getUIClosure(array $threads, ChannelsManager $manager, array $guide, string $logLevel, int $index, int $guidesCount): Closure
    {
        Layout::showCursorOnExit();

        return function () use ($threads, $manager, $guide, $logLevel, $index, $guidesCount) {
            if ($logLevel != 'none') {
                Layout::hideCursor();
                $hasThreadRunning = true;
                while ($hasThreadRunning) {
                    $layoutLength = Utils::getMaxTerminalLength();
                    $eventLength = max(count($threads), 5);
                    $layout = new Layout();
                    $layout->addLine([Utils::colorize('XML TV Fr - Génération des fichiers XMLTV', 'light blue')], [$layoutLength]);
                    $layout->addLine([' '], [$layoutLength]);
                    $layout->addLine([Utils::colorize('Chaines récupérées : ', 'cyan').$manager->getStatus().'   |   '.
                        Utils::colorize('Fichier :', 'cyan')." {$guide['filename']} ($index/$guidesCount)"], [$layoutLength]);
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
                    delay(0.1); // permet d'alterner entre l'affichage et la manipulation des threads
                }
            }
        };
    }

    /**
     * Vérifie si le processus principal est toujours en cours et tue tous les threads enfants si celui-ci est arrêté
     * @param string $generatorId
     * @return void
     */
    protected function startThreadWatcher(string $generatorId): void
    {
        $p = PHP_BINARY;
        $pid = getmypid();
        $encodedId = base64_encode($generatorId);
        $cmd = "$p src/Multithreading/thread_watcher.php $pid $encodedId";
        Utils::startCmd($cmd);
    }

    /**
     * Boucle ordonnant la génération de l'EPG de chaque chaine. Les chaines/dates seront affectées à chaque thread en fonction
     * de la disponibilité des providers
     * @param array $threads
     * @param ChannelsManager $manager
     * @return void
     */
    protected function generateChannels(array $threads, ChannelsManager $manager): void
    {
        $threadsStack = array_values($threads);
        while ($manager->hasRemainingChannels() || Utils::hasOneThreadRunning($threads)) { // Necessary if one channel fails

            delay(0.001); // permet d'alterner entre l'affichage et la manipulation des threads
            for ($i = 0; $i < count($threads); $i++) {
                $thread = $threadsStack[0];
                unset($threadsStack[0]);
                $threadsStack[] = $thread;
                $threadsStack = array_values($threadsStack);
                if (!$thread->isRunning()) {
                    $channelData = $manager->shiftChannel();
                    if (empty($channelData)) {
                        break;
                    }
                    $thread->setChannel($channelData);
                    $thread->start();
                }
            }
        }
        delay(0.5); // Let UI thread write the last frame
    }
    protected function generateEpg(): void
    {
        $generatorId = bin2hex(random_bytes(10));
        $fn = function () use ($generatorId) {
            $logLevel = Logger::getLogLevel();
            Logger::setLogLevel('none');
            $this->startThreadWatcher($generatorId);
            $guidesCount = count($this->guides);
            foreach ($this->guides as $index => $guide) {
                $channels = Utils::getChannelsFromGuide($guide);
                $threads = [];
                $manager = new ChannelsManager($channels, $this);
                for ($i = 0; $i < $this->configurator->getNbThreads(); $i++) {
                    $threads[] = new ChannelThread($manager, $this, $generatorId, $guide['filename']);
                }
                $view = $this->getUIClosure($threads, $manager, $guide, $logLevel, $index, $guidesCount);
                async($view);
                $this->generateChannels($threads, $manager);
            }
            Logger::setLogLevel($logLevel);
            Logger::log("\e[95m[EPG GRAB] \e[39mRécupération du guide des programmes terminée...\n");
        };
        $future = async($fn);
        $future->await();
    }
}
