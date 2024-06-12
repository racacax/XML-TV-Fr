<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component;


use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Client;
use racacax\XmlTv\StaticComponent\ChannelInformation;
use racacax\XmlTv\ValueObject\DummyChannel;
use function Amp\async;
use function Amp\delay;

class ChannelThread
{
    private ?string $channel;
    private ?string $provider = null;
    private ?array $info;
    private ?array $failedProviders;
    private ?array $datesGathered;
    private ?array $extraParams;
    private ChannelsManager $manager;
    private Generator $generator;
    private string $status;
    private string $date;
    private bool $isRunning;
    private bool $hasStarted;

    public function __construct(ChannelsManager $manager, Generator $generator) {
        $this->manager = $manager;
        $this->generator = $generator;
        $this->isRunning = false;
        $this->hasStarted = false;
    }

    public function setChannel(array $channelInfo) {
        $this->hasStarted = false;
        $this->status = "\e[35mDémarrage...e[39m";
        $this->channel = $channelInfo["key"];
        $this->info = $channelInfo["info"];
        $this->failedProviders = $channelInfo["failedProviders"];
        $this->datesGathered = $channelInfo["datesGathered"];
        $this->extraParams = $channelInfo["extraParams"];
    }

    public function getString() {
        if(!$this->hasStarted || !$this->isRunning) {
            return Utils::colorize("En pause...", "yellow");
        }
        $str = $this->getChannel()." - ".$this->getDate()." - ".$this->getProvider();
        $status = $this->getStatus();
        if(isset($status)) {
            $str.= " ".$status;
        }
        return $str;
    }

    private function getChannelInfo() {
        return json_encode(["key" => $this->channel, "info"=>$this->info, "extraParams"=>$this->extraParams]);
    }

    private function run()
    {
        $cacheInstance = new ProcessCache("cache");
        $statusInstance = new ProcessCache("status");
        $providers = $this->generator->getProviders($this->info['priority'] ?? []);
        if(count($this->failedProviders) > 0) {
            $failedProviders = $this->generator->getProviders($this->failedProviders);
        } else {
            $failedProviders = [];
        }
        // TODO : providers failed diff
        $cache = $this->generator->getCache();
        $dates = $this->generator->getListDate();
        $total = count($dates);
        $dates = array_diff($dates, $this->datesGathered);
        $progress = $total - count($dates);
        foreach ($dates as $date) {
            $progress++;
            //echo $date;
            $this->date = $date." ($progress/$total)";
            $cacheKey = sprintf('%s_%s.xml', $this->channel, $date);

            Logger::log(sprintf("\e[95m[EPG GRAB] \e[39m%s (%d/%d) : %s", $this->channel, 0, 1, $date));

            if ($cache->has($cacheKey)) {
                Logger::log(" | \e[33mOK \e[39m- From Cache " . chr(10));
                $this->manager->setLogInfo($date, $this->channel, "success", true);
                $this->manager->setLogInfo($date, $this->channel, "cache",true);
                continue;
            }
            $channelFound = false;
            foreach ($providers as $provider) {
                if(in_array($provider, $failedProviders)) {
                    continue;
                }
                $providerClass = Utils::extractProviderName($provider);
                if(!$provider->channelExists($this->channel)) {
                    continue;
                } elseif(!$this->manager->canUseProvider($providerClass)) {
                    $this->manager->addChannel($this->channel, $this->failedProviders, $this->datesGathered);
                    return;
                } else {
                    $this->manager->addChannelToProvider($providerClass, $this->channel);
                    $this->provider = $providerClass;
                    $this->hasStarted = true;
                }
                $this->status = Utils::colorize("En cours...", "magenta");


                flush();
                $bytes = random_bytes(10);
                $fileName = bin2hex($bytes);
                $p = PHP_BINARY;
                $cmd = "$p src/manager.php $providerClass $date ".base64_encode($this->getChannelInfo())." $fileName";
                if (substr(php_uname(), 0, 7) == "Windows"){
                    pclose(popen("start /B ". $cmd, "r"));
                } else {
                    exec($cmd . " > /dev/null &");
                }
                //echo "$p src/manager.php $providerClass $date ".base64_encode($this->getChannelInfo());
                while (true) {
                    if(!$cacheInstance->exists($fileName)) {
                        if($statusInstance->exists($fileName)) {
                            $this->status = Utils::colorize($statusInstance->pop($fileName), "magenta");
                        }
                        delay(0.01);
                    } else {
                        $channel = $cacheInstance->pop(strval($fileName));
                        $this->manager->removeChannelFromProvider($providerClass, $this->channel);
                        break;
                    }
                }
                //echo $this->channel." ".$date." o\n";

                if ($channel == "false") {
                    $this->failedProviders[] = get_class($provider);
                    $this->manager->addFailedProvider(get_class($provider));

                    continue;
                }

                $channelFound = true;
                $this->manager->setLogInfo($date, $this->channel, "success", true);
                $this->manager->setLogInfo($date, $this->channel, "provider", get_class($provider));
                $this->manager->setLogInfo($date, $this->channel, "failed_providers", $this->failedProviders);
                $cache->store($cacheKey, $channel);
                //Logger::log(" | \e[32mOK\e[39m - " . Utils::extractProviderName($provider) . chr(10));

                break;
            }

            if (!$channelFound) {
                if ($this->generator->createEpgIfNotFound()) {
                    $cache->store($cacheKey, $this->generator->getFormatter()->formatChannel(new DummyChannel($this->channel, $date), null));
                }
                Logger::log(" | \e[31mHS\e[39m" . chr(10));
            }
            $this->datesGathered[] = $date;
            $this->failedProviders = [];
        }
        $this->manager->incrChannelsDone();
    }

    public function start() {
        if(!$this->isRunning) {
            $this->isRunning = true;
            $fn = function () {
                $this->run();
                $this->isRunning = false;
            };
            async($fn);
        }
    }
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * @return string|null
     */
    public function getChannel(): ?string
    {
        if(isset($this->channel)) {
            return $this->channel;
        }
        return null;
    }

    /**
     * @return string
     */
    public function getStatus(): ?string
    {
        if(isset($this->status)) {
            return $this->status;
        }
        return null;
    }

    /**
     * @return string
     */
    public function getDate(): ?string
    {
        if(isset($this->date)) {
            return $this->date;
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }
}