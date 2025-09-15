<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use DateTimeImmutable;
use Exception;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class SudInfo extends AbstractProvider implements ProviderInterface
{
    private static string $BUILD_ID;
    private static array $HEADERS = ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Encoding' => 'gzip, deflate, br, zstd',
        'Accept-Language' => 'fr-FR,fr-CA;q=0.8,en;q=0.5,en-US;q=0.3',
        'Connection' => 'keep-alive',
        'DNT' => '1',
        'Host' => 'programmestv.sudinfo.be',
        'Priority' => 'u=0, i',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'cross-site',
        'Sec-GPC' => '1',
        'TE' => 'trailers',
        'Upgrade-Insecure-Requests' => '1',
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:137.0) Gecko/20100101 Firefox/137.0'];
    private static array $DAYS = ['Mon' => 'lundi', 'Tue' => 'mardi', 'Wed', 'mercredi', 'Thu' => 'jeudi', 'Fri' => 'vendredi', 'Sat' => 'samedi', 'Sun' => 'dimanche'];
    private bool $enableDetails;
    private static string $BASE_URL = 'https://programmestv.sudinfo.be';
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_sudinfo.json'), $priority ?? 0.5);
        $this->enableDetails = $extraParam['sudinfo_enable_details'] ?? true;
    }
    private function getBuildId(): string
    {
        if (!isset(self::$BUILD_ID)) {
            $content = $this->getContentFromURL(self::$BASE_URL.'/programme-tv/ce-soir', self::$HEADERS);
            preg_match('/"buildId":"(.*?)"/', $content, $match);
            if (empty($match[1])) {
                throw new Exception('Cannot retrieve build id');
            }
            self::$BUILD_ID = $match[1];
        }

        return self::$BUILD_ID;
    }

    private function getDayLabel(DateTimeImmutable $date): string
    {
        $date = $date->setTime(0, 0, 0);
        $dateYmd = $date->format('Y-m-d');
        $today = (new DateTimeImmutable())->setTime(0, 0, 0);
        $weekAfter = $today->modify('+6 days');
        $yesterday = $today->modify('-1 day');
        if ($date < $yesterday) {
            throw new Exception('Date is too early !');
        } elseif ($dateYmd == $yesterday->format('Y-m-d')) {
            return 'hier';
        } elseif ($dateYmd == $today->format('Y-m-d')) {
            return 'aujourdhui';
        } else {
            $dateDay = @self::$DAYS[$date->format('D')];
            if (empty($dateDay)) {
                throw new Exception('Invalid day date !');
            }
            if ($date > $weekAfter) {
                $dateDay .= 'prochain';
            }

            return $dateDay;
        }
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        $dayLabels = [$this->getDayLabel($minDate->modify('-1 day')), $this->getDayLabel($minDate)];
        $buildId = $this->getBuildId();
        $programsWithSlug = [];
        foreach ($dayLabels as $dayLabel) {
            $this->setStatus('Informations principales');
            $json = json_decode($this->getContentFromURL($this->generateUrl($channelObj, $dayLabel, $buildId), self::$HEADERS), true);
            foreach (@($json['pageProps'] ?? [])['content'] ?? [] as $program) {
                $startDate = new DateTimeImmutable('@'.strtotime($program['airingStartDateTime']));
                $endDate = new DateTimeImmutable('@'.strtotime($program['airingEndDateTime']));
                if ($startDate < $minDate) {
                    continue;
                } elseif ($startDate > $maxDate) {
                    break;
                }
                $programObj = new Program($startDate, $endDate);
                $programsWithSlug[] = ['slug' => $program['slug'], 'obj' => $programObj];
                $channelObj->addProgram($programObj);
                $programObj->addTitle($program['title']);
                $programObj->addSubtitle($program['subTitle']);
                $programObj->addCategory(@($program['contentSubCategory'] ?? [])['name']);
                $images = @$program['images'] ?? [];
                $programObj->setIcon(@($images[1] ?? $images[0])['url']);
            }
        }
        if ($this->enableDetails) {
            $this->addDetails($programsWithSlug, $buildId);
        }


        return $channelObj;
    }

    private function addCasting(array $casting, Program $programObj): void
    {
        foreach ($casting as $cast) {
            $str = '';
            if (isset($cast['firstname'])) {
                $str .= $cast['firstname'];
            }
            if (isset($cast['lastname'])) {
                $str .= ' '.$cast['lastname'];
            }
            if (isset($cast['role'])) {
                $str .= ' ('.$cast['role'].')';
            }
            $castFunction = @$cast['castFunction']['name'] ?? '';
            $credit = $this->getCreditFromCastFunction($castFunction);
            if ($credit !== 'guest') {
                $programObj->addCredit($str, $credit);
            }
        }

    }

    private function getCreditFromCastFunction(string $castFunction)
    {
        return match($castFunction) {
            'Acteur' => 'actor',
            'Producteur' => 'producer',
            'Maison de Production' => 'producer',
            'Réalisateur' => 'director',
            'Scénario' => 'writer',
            'Présentateur' => 'presenter',
            default => 'guest'
        };
    }
    private function addDetails(array $programsWithSlug, string $buildId)
    {
        $count = count($programsWithSlug);
        foreach ($programsWithSlug as $key => $programWithSlug) {
            $this->setStatus('Details | ('.$key.'/'.$count.')');
            $programObj = $programWithSlug['obj'];
            $slug = $programWithSlug['slug'];
            $json = json_decode($this->getContentFromURL($this->generateUrlFromSlug($slug, $buildId), self::$HEADERS), true);
            $props = @$json['pageProps'] ?? [];
            $content = @$props['content'] ?? [];
            $programObj->addCategory(@($content['category'] ?? [])['name']);
            $programObj->addDesc(@(($content['texts'] ?? [])[0] ?? [])['detail']);
            $this->addCasting(@$content['casting'] ?? [], $programObj);
        }
        $this->setStatus('Terminé');
    }

    public function generateUrl(Channel $channel, string $dayLabel, string $buildId): string
    {
        $channelInfos = $this->channelsList[$channel->getId()];
        $slug = Utils::slugify($channelInfos['name']);
        $id = $channelInfos['id'];

        return self::$BASE_URL."/_next/data/$buildId/programme-tv/chaine/$slug/$id/$dayLabel.json?slug=$slug&slug=$id&slug=$dayLabel";
    }
    public function generateUrlFromSlug(string $slug, string $buildId): string
    {
        $splited = explode('/', $slug);
        $slug1 = $splited[2];
        $slug2 = $splited[3];

        return self::$BASE_URL."/_next/data/$buildId$slug.json?slug=$slug1&slug=$slug2";
    }

}
