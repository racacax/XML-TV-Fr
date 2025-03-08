<?php

namespace racacax\XmlTv\Component;

use Closure;

use function Amp\async;
use function Amp\delay;

class MultiThreadedGenerator extends Generator
{
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
        return function () use ($threads, $manager, $guide, $logLevel, $index, $guidesCount) {
            if ($logLevel != 'none') {
                while ($manager->hasRemainingChannels() || Utils::hasOneThreadRunning($threads)) {
                    echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
                    echo Utils::colorize("XML TV Fr - Génération des fichiers XMLTV\n", 'light blue');
                    echo Utils::colorize('Chaines récupérées : ', 'cyan').$manager->getStatus().'   |   '.
                        Utils::colorize('Fichier :', 'cyan')." {$guide['channels']} ($index/$guidesCount)\n";
                    $i = 1;
                    foreach ($threads as $thread) {
                        echo "Thread $i : ";
                        echo $thread;
                        echo "\n";
                        $i++;
                    }
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
                $channels = json_decode(file_get_contents($guide['channels']), true);
                $threads = [];
                $manager = new ChannelsManager($channels, $this);
                for ($i = 0; $i < $this->configurator->getNbThreads(); $i++) {
                    $threads[] = new ChannelThread($manager, $this, $generatorId, $guide['channels']);
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
