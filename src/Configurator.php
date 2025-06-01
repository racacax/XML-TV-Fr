<?php

declare(strict_types=1);

namespace racacax\XmlTv;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\CacheFile;
use racacax\XmlTv\Component\Generator;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\MultiThreadedGenerator;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\UI\MultiColumnUI;
use racacax\XmlTv\Component\UI\UI;
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\Component\XmlExporter;
use racacax\XmlTv\ValueObject\EPGDate;

class Configurator
{
    /**
     * @var array<EPGDate>
     */
    private array $epgDates;

    private string $outputPath;

    private int $cacheMaxDays;

    private bool $deleteRawXml;

    private bool $enableGz;

    private bool $enableZip;

    private bool $enableXz;

    private bool $enableDummy;

    private array $customPriorityOrders;

    private array $guidesToGenerate;

    private ?string $zipBinPath;

    private bool $forceTodayGrab;

    private array $extraParams;

    /**
     * @var ProviderInterface[]
     */
    private array $providerList;

    private int $nbThreads;
    private int $minTimeRange;
    private UI $ui;

    /**
     * @param array<EPGDate> $epgDates Fetch policy for each day gathered by XMLTV
     * @param string $outputPath Where xmltv files are stored
     * @param null|int $timeLimit time limit for the EPG grab (0 = unlimited)
     * @param null|int $memoryLimit memory limit for the EPG grab (-1 = unlimited)
     * @param int $cache_max_days after how many days do we clear cache (0 = no cache)
     * @param bool $deleteRawXml delete xmltv.xml after EPG grab (if you want to provide only compressed XMLTV)
     * @param bool $enableGz enable gz compression for the XMLTV
     * @param bool $enableZip enable zip compression for the XMLTV
     * @param bool $enableXz enable XZ compression for the XMLTV (need 7zip)
     * @param bool $enableDummy Add a dummy EPG if channel not found
     * @param array $customPriorityOrders Add a custom priority order for a provider globally
     * @param array|string[][] $guides_to_generate list of xmltv to generate
     * @param string|null $zipBinPath path of 7zip binary
     * @param bool $forceTodayGrab ignore cache for today
     */
    public function __construct(
        array   $epgDates = [],
        string  $outputPath = './var/export/',
        ?int    $timeLimit = null,
        ?int    $memoryLimit = null,
        int     $cache_max_days = 8,
        bool    $deleteRawXml = false,
        bool    $enableGz = true,
        bool    $enableZip = true,
        bool    $enableXz = false,
        bool    $enableDummy = false,
        array   $customPriorityOrders = [],
        array   $guides_to_generate = [['channels' => 'config/channels.json', 'filename' => 'xmltv.xml']],
        ?string $zipBinPath = null,
        bool    $forceTodayGrab = false,
        int     $nbThreads = 1,
        int     $minTimeRange = 22 * 3600,
        array   $extraParams = [],
        ?UI   $ui = null
    ) {
        if (isset($timeLimit)) {
            set_time_limit($timeLimit);
        }
        if (isset($memoryLimit)) {
            ini_set('memory_limit', (string)$memoryLimit);
        }

        $this->epgDates = $epgDates;
        $this->outputPath = $outputPath;
        $this->cacheMaxDays = $cache_max_days;
        $this->deleteRawXml = $deleteRawXml;
        $this->enableGz = $enableGz;
        $this->enableZip = $enableZip;
        $this->enableXz = $enableXz;
        $this->enableDummy = $enableDummy;
        $this->customPriorityOrders = $customPriorityOrders;
        $this->guidesToGenerate = $guides_to_generate;
        $this->zipBinPath = $zipBinPath;
        $this->forceTodayGrab = $forceTodayGrab;
        $this->extraParams = $extraParams;
        $this->nbThreads = $nbThreads;
        $this->minTimeRange = $minTimeRange;
        $this->ui = $ui ?? new MultiColumnUI();
    }

    /**
     * @throws \DateMalformedStringException
     */
    public static function initFromConfigFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Config file not found');
        }
        $data = json_decode(file_get_contents($filePath), true);

        Logger::log("\e[36m[CHARGEMENT] \e[39mChargement du fichier de config\n");

        Logger::log("\e[36m[CHARGEMENT] \e[39mListe des paramètres : ");
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            Logger::log("\e[95m($key) \e[39m=> \e[33m$value\e[39m, ");
        }
        Logger::log("\n");

        return new Configurator(
            EPGDate::createFromConfigEntry($data['fetch_policies'] ?? []),
            $data['output_path'] ?? './xmltv',
            $data['time_limit'] ?? null,
            $data['memory_limit'] ?? null,
            $data['cache_max_days'] ?? 8,
            $data['delete_raw_xml'] ?? false,
            $data['enable_gz'] ?? true,
            $data['enable_zip'] ?? true,
            $data['enable_xz'] ?? false,
            $data['enable_dummy'] ?? false,
            $data['custom_priority_orders'] ?? [],
            $data['guides_to_generate'] ?? [['channels' => 'config/channels.json', 'filename' => 'xmltv.xml']],
            $data['7zip_path'] ?? null,
            $data['force_todays_grab'] ?? false,
            $data['nb_threads'] ?? 1,
            $data['min_timerange'] ?? 22 * 3600, # 22h
            $data['extra_params'] ?? [],
            Utils::getUI($data['ui'] ?? 'MultiColumnUI')
        );
    }


    public function getUI(): UI
    {
        return $this->ui;
    }

    /**
     * @return bool
     */
    public function isDeleteRawXml(): bool
    {
        return $this->deleteRawXml;
    }

    /**
     * @return bool
     */
    public function isEnableGz(): bool
    {
        return $this->enableGz;
    }

    /**
     * @return bool
     */
    public function isEnableZip(): bool
    {
        return $this->enableZip;
    }

    /**
     * @return bool
     */
    public function isEnableXz(): bool
    {
        return $this->enableXz;
    }

    /**
     * @return bool
     */
    public function isEnableDummy(): bool
    {
        return $this->enableDummy;
    }

    /**
     * @return array
     */
    public function getCustomPriorityOrders(): array
    {
        return $this->customPriorityOrders;
    }

    /**
     * @return array|string[][]
     */
    public function getGuidesToGenerate()
    {
        return $this->guidesToGenerate;
    }

    /**
     * @return string|null
     */
    public function getZipBinPath(): ?string
    {
        return $this->zipBinPath;
    }

    /**
     * @return bool
     */
    public function isForceTodayGrab(): bool
    {
        return $this->forceTodayGrab;
    }

    /**
     * @return array
     */
    public function getExtraParams(): array
    {
        return $this->extraParams;
    }

    /**
     * @return string
     */
    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    /**
     * @return int
     */
    public function getCacheMaxDays(): int
    {
        return $this->cacheMaxDays;
    }

    /**
     * @return int
     */
    public function getNbThreads(): int
    {
        return $this->nbThreads;
    }

    public function getMinTimeRange(): int
    {
        return $this->minTimeRange;
    }

    public function getGenerator(): Generator
    {
        $begin = new \DateTimeImmutable(date('Y-m-d', strtotime('-1 day')));

        $generator = new MultiThreadedGenerator($this);
        $generator->setProviders(
            $this->getProviders(
                $this->getDefaultClient()
            )
        );

        $outputFormat = [];
        if (!$this->deleteRawXml) {
            $outputFormat[] = 'xml';
        }
        if ($this->enableGz) {
            $outputFormat[] = 'gz';
        }
        if ($this->enableXz && $this->zipBinPath) {
            $outputFormat[] = 'xz';
        }
        if ($this->enableZip) {
            $outputFormat[] = 'zip';
        }

        $generator->setExporter(new XmlExporter($outputFormat, $this->zipBinPath));
        $generator->setCache(new CacheFile('var/cache', $this));
        $generator->addGuides($this->guidesToGenerate);


        return $generator;
    }

    public function getEpgDates(): array
    {
        return $this->epgDates;
    }

    /**
     * @return ProviderInterface[]
     */
    public function getProviders(Client $client): array
    {
        if (isset($this->providerList)) {
            return $this->providerList;
        }

        $providersClass = Utils::getProviders();
        $providersObject = [];
        foreach ($providersClass as $providerClass) {
            $tmp = explode('\\', $providerClass);
            $name = end($tmp);
            $providersObject[] = new $providerClass($client, $this->customPriorityOrders[$name] ?? null, $this->extraParams);
        }

        usort($providersObject, function (ProviderInterface $providerA, ProviderInterface $providerB) {
            return $providerB::getPriority() <=> $providerA::getPriority();
        });

        return $this->providerList = $providersObject;
    }

    public static function getDefaultClient(): Client
    {
        return new Client(
            [
                'verify' => false,
                'http_errors' => false,
                'cookies' => true,
                'connect_timeout' => 3,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0',
                ],
            ]
        );
    }
}
