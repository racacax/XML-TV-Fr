<?php
declare(strict_types=1);

namespace racacax\XmlTv;

use racacax\XmlTv\Component\CacheFile;
use racacax\XmlTv\Component\Generator;
use racacax\XmlTv\Component\Logger;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\Component\XmlExporter;

class Configurator
{
    /**
     * @var int
     */
    private $nbDays;
    /**
     * @var string
     */
    private $outputPath;
    /**
     * @var int
     */
    private $cacheMaxDays;
    /**
     * @var bool
     */
    private $deleteRawXml;
    /**
     * @var bool
     */
    private $enableGz;
    /**
     * @var bool
     */
    private $enableZip;
    /**
     * @var bool
     */
    private $enableXz;
    /**
     * @var int
     */
    private $xmlCacheDays;
    /**
     * @var bool
     */
    private $enableDummy;
    /**
     * @var array
     */
    private $customPriorityOrders;
    /**
     * @var array|\string[][]
     */
    private $guidesToGenerate;
    /**
     * @var string|null
     */
    private $zipBinPath;
    /**
     * @var bool
     */
    private $forceTodayGrab;
    /**
     * @var array
     */
    private $extraParams;

    /**
     * @param int $nbDays Number of days XML TV will try to get EPG
     * @param string $outputPath Where xmltv files are stored
     * @param null|int $timeLimit time limit for the EPG grab (0 = unlimited)
     * @param null|int $memoryLimit memory limit for the EPG grab (-1 = unlimited)
     * @param int $cache_max_days after how many days do we clear cache (0 = no cache)
     * @param bool $deleteRawXml delete xmltv.xml after EPG grab (if you want to provide only compressed XMLTV)
     * @param bool $enableGz enable gz compression for the XMLTV
     * @param bool $enableZip enable zip compression for the XMLTV
     * @param bool $enableXz enable XZ compression for the XMLTV (need 7zip)
     * @param int $xmlCacheDays How many days old XML are stored
     * @param bool $enableDummy Add a dummy EPG if channel not found
     * @param array $customPriorityOrders Add a custom priority order for a provider globally
     * @param array|\string[][] $guides_to_generate list of xmltv to generate
     * @param string|null $zipBinPath path of 7zip binary
     * @param bool $forceTodayGrab ignore cache for today
     */
    public function __construct(
        int $nbDays = 8,
        string $outputPath = './xmltv',
        ?int $timeLimit = null,
        ?int $memoryLimit = null,
        int $cache_max_days = 8,
        bool $deleteRawXml = false,
        bool $enableGz  = true,
        bool $enableZip  = true,
        bool $enableXz  = false,
        int $xmlCacheDays = 5,
        bool $enableDummy  = false,
        array $customPriorityOrders = [],
        array $guides_to_generate = [array("channels"=>"config/channels.json", "filename"=>"xmltv.xml")],
        ?string $zipBinPath = null,
        bool $forceTodayGrab =false,
        array $extraParams = []
    ) {
        if (isset($timeLimit)) {
            set_time_limit($timeLimit);
        }
        if (isset($memoryLimit)) {
            ini_set('memory_limit', (string) $memoryLimit);
        }

        $this->nbDays = $nbDays;
        $this->outputPath = $outputPath;
        $this->cacheMaxDays = $cache_max_days;
        $this->deleteRawXml = $deleteRawXml;
        $this->enableGz = $enableGz;
        $this->enableZip = $enableZip;
        $this->enableXz = $enableXz;
        $this->xmlCacheDays = $xmlCacheDays;
        $this->enableDummy = $enableDummy;
        $this->customPriorityOrders = $customPriorityOrders;
        $this->guidesToGenerate = $guides_to_generate;
        $this->zipBinPath = $zipBinPath;
        $this->forceTodayGrab = $forceTodayGrab;
        $this->extraParams = $extraParams;
    }

    public static function initFromConfigFile(string $filePath): self
    {

        if (!file_exists($filePath)){
            throw new \Exception('Config file not found');
        }
        $data = json_decode(file_get_contents($filePath), true);

        Logger::log("\e[36m[CHARGEMENT] \e[39mChargement du fichier de config\n");

        Logger::log("\e[36m[CHARGEMENT] \e[39mListe des paramètres : ");
        foreach ($data as $key => $value) {
            if(is_array($value)) {
                $value = json_encode($value);
            }
            if(is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            Logger::log("\e[95m($key) \e[39m=> \e[33m$value\e[39m, ");
        }
        Logger::log("\n");

        return new Configurator(
            $data['days']?? 8,
            $data['output_path']?? './xmltv',
            $data['time_limit']??  null,
            $data['memory_limit']??  null,
            $data['cache_max_days']?? 8,
            $data['delete_raw_xml']??   false,
            $data['enable_gz']??   true,
            $data['enable_zip']??   true,
            $data['enable_xz']??   false,
            $data['xml_cache_days']??   5,
            $data['enable_dummy']??   false,
            $data['custom_priority_orders']??   [],
            $data['guides_to_generate']?? [array('channels'=>'config/channels.json', 'filename'=>'xmltv.xml')],
            $data['7zip_path']??   null,
            $data['force_todays_grab']??  false,
            []
        );
    }

    public function getGenerator()
    {
        $begin = new \DateTimeImmutable(date('Y-m-d'));
        if (!$this->forceTodayGrab) {
            $begin = $begin->add(new \DateInterval('P1D'));
        }
        $generator = new Generator($begin, $begin->add(new \DateInterval('P' . $this->nbDays . 'D')), $this->enableDummy);
        $generator->setProviders($this->getProviders());

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
        $generator->setCache(new CacheFile('var/cache'));
        $generator->addGuides($this->guidesToGenerate ?? []);


        return $generator;
    }

    /**
     * @return ProviderInterface[]
     */
    public function getProviders():array
    {
        $providersClass = Utils::getProviders();
        $providersObject = [];
        foreach($providersClass as $providerClass) {
            $tmp = explode('\\', $providerClass);
            $name = end($tmp);
            $providersObject[] = new $providerClass($this->customPriorityOrders[$name] ?? null, $this->extraParams);

        }

        usort($providersObject, function (ProviderInterface $providerA, ProviderInterface $providerB){
            return $providerB::getPriority() <=> $providerA::getPriority();
        });

        return $providersObject;
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



}