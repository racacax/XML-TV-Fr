<?php

namespace racacax\XmlTv\Component;

use function Amp\async;
use function Amp\delay;

class MultiThreadedGenerator extends Generator
{
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

            delay(0); // permet d'alterner entre l'affichage et la manipulation des threads
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
            $guidesCount = count($this->guides);
            $ui = $this->configurator->getUI();
            foreach ($this->guides as $index => $guide) {
                $channels = Utils::getChannelsFromGuide($guide);
                $threads = [];
                $manager = new ChannelsManager($channels, $this);
                for ($i = 0; $i < $this->configurator->getNbThreads(); $i++) {
                    $threads[] = new ChannelThread($manager, $this, $generatorId, $guide['filename']);
                }
                $view = $ui->getClosure($threads, $manager, $guide, $logLevel, $index, $guidesCount);
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
