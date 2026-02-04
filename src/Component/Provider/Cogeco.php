<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use Exception;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.2 : 15/02/2024
 */
class Cogeco extends AbstractProvider implements ProviderInterface
{
    protected static string $COOKIE_VALUE = '823D'; // for Toronto Postal Code
    protected static array $CATEGORIES_BY_CSS = ['tvm_td_grd_s' => 'Sport', 'tvm_td_grd_r' => 'Télé-Réalité', 'tvm_td_grd_m' => 'Cinéma']; // Color codes for these categories
    protected static array $CATEGORIES_IN_TITLE = ['Cinéma']; // Some titles are only present in subtitles and has the category as a title
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_cogeco.json'), $priority ?? 0.61);
    }

    protected function hasCategoryAsTitle(string $title)
    {
        return in_array($title, self::$CATEGORIES_IN_TITLE);
    }
    protected function getCategory(string $html)
    {
        foreach (self::$CATEGORIES_BY_CSS as $cssClass => $category) {
            if (str_contains($html, $cssClass)) {
                return $category;
            }
        }

        return 'Inconnu';
    }

    private function getEPGData(\DateTimeImmutable $start): ?string
    {
        $content = $this->getContentFromURL($this->generateUrl($start), ['Cookie' => 'TVMDS_Cookie='.self::$COOKIE_VALUE]);
        $json = json_decode($content, true);

        return  @$json['data'];
    }
    /**
     * @throws Exception
     */
    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $channelId = $this->getChannelsList()[$channel];
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        date_default_timezone_set('America/Toronto');
        $minStart = new \DateTimeImmutable($date);
        if ($minStart < new \DateTimeImmutable(date('Y-m-d'))) {
            return false;
        }
        $count = 6;
        $span = 6;
        $minStart = $minStart->modify('-1 day')->modify('+'.($span * 2).' hours');
        $programsPaths = [];
        $programCategories = [];
        for ($i = 0; $i < $count; $i++) {
            $percent = 'Main data (1/2) : '.round($i * 100 / ($count), 2) . ' %';
            $this->setStatus($percent);
            $start = $minStart->modify('+'.($span * $i).' hours');
            $html = $this->getEPGData($start);
            if (!$html) {
                return false;
            }

            $channelRows = explode('<!-- channel row -->', $html);
            unset($channelRows[0]);
            $found = false;
            foreach ($channelRows as $channelRow) {
                preg_match('/tvm_txt_chan_name">(.*?)<\/span>/', $channelRow, $channelIdName);
                preg_match('/tvm_txt_chan_num">(.*?)<\/span>/', $channelRow, $channelNumber);
                $channelIdName = $channelIdName[1];
                $channelNumber = str_replace('&nbsp;', '', $channelNumber[1]);
                if ($channelIdName == $channelId || $channelNumber == $channelId) {
                    $found = true;
                    preg_match_all('/class=\"(.*?)\".*?onclick=\"prgm_details\((.*?)\)/', $channelRow, $paths);
                    foreach ($paths[2] as $key => $path) {
                        if (!in_array($path, $programsPaths)) {
                            $programsPaths[] = $path;
                            $programCategories[] = $this->getCategory($paths[1][$key]);
                        }
                    }

                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        // first program of the day may have started the day before. However if it started at 00:00, the switch to next day will be done
        $currentCursor = $minStart->modify('-1 day')->modify('+1 minute');
        $count = count($programsPaths);
        foreach ($programsPaths as $index => $path) {
            $percent = 'Details (2/2) : '.round($index * 100 / $count, 2) . ' %';
            $this->setStatus($percent);
            $content = $this->getContentFromURL($this->generateProgramDetailsUrl($path));
            preg_match('/txt_showtitle bold">(.*?)<\/h3>/s', $content, $title);
            if (!@$title[1]) {
                continue;
            }
            preg_match('/txt_showname bold">(.*?)<\/p>/s', $content, $subtitle);
            preg_match_all('/tvm_td_detailsbot">(.*?)<\/span>/s', $content, $details);
            preg_match('/details_tvm_td_detailsbot">(.*?)<\/p>/s', $content, $description);
            preg_match("/img id='show_graphic' src=\"(.*?)\"/s", $content, $img);
            $startTime = explode('h', $details[1][1]);
            $startTimeObj = $currentCursor->setTime(intval($startTime[0]), intval($startTime[1]));
            if ($startTimeObj < $currentCursor) {
                $startTimeObj = $startTimeObj->modify('+1 day');
            }
            $currentCursor = $startTimeObj;
            if ($currentCursor < $minStart || $currentCursor < $minDate) { // program is from the day before
                continue;
            } elseif ($currentCursor > $maxDate) {
                return $channelObj;
            }
            $duration = intval(explode('(', explode(' ', $details[1][2])[0])[1]);
            $endTimeObj = $startTimeObj->modify('+'.$duration.' minutes');
            $program = new Program($startTimeObj, $endTimeObj);
            $title = trim($title[1]);
            $subtitle = trim($subtitle[1] ?? '');
            if ($this->hasCategoryAsTitle($title) && strlen($subtitle) > 0) {
                $program->addTitle($subtitle);
            } else {
                $program->addTitle($title);
                if (strlen($subtitle) > 0) {
                    $program->addSubTitle($subtitle);
                }
            }
            $program->addIcon('https:'.(str_replace('240x135', '1280x720', $img[1] ?? '')));
            $program->addCategory($programCategories[$index]);
            $program->addDesc($description[1] ?? 'Aucune description');
            if (str_contains($content, '(NOUVEAU)')) {
                $program->setPremiere();
            }
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    private function generateProgramDetailsUrl($path): string
    {
        $splitPath = explode(', ', $path);

        return sprintf(
            'https://tvmds.tvpassport.com/tvmds/cogeco/grid_v3/program_details/program_details.php?subid=tvpassport&ltid=%s&stid=%s&luid=%s&lang=fr-ca&mode=json',
            $splitPath[0],
            $splitPath[1],
            self::$COOKIE_VALUE
        );
    }
    public function generateUrl(\DateTimeImmutable $date): string
    {
        return sprintf(
            'https://tvmds.tvpassport.com/tvmds/cogeco/grid_v3/grid.php?subid=tvpassport&lu=%s&wd=1138&ht=100000&mode=json&style=blue&wid=wh&st=%s&ch=1&tz=EST5EDT&lang=fr-ca&ctrlpos=top&items=99999&filter=',
            self::$COOKIE_VALUE,
            $date->getTimestamp()
        );
    }

    public function getLogo(string $channelId): ?string
    {
        parent::getLogo($channelId);
        $channelInfo = $this->channelsList[$channelId];
        $data = $this->getEPGData((new \DateTimeImmutable())->setTime(0, 0, 0));
        $splitContent = explode('<span class="hidden-phone tvm_txt_chan_name">'.$channelInfo.'</span>', $data)[0];
        $expl = explode(' class="tvm_channel_row">', $splitContent);
        $channelRow = end($expl);
        preg_match('/img src="(.*?)"/s', $channelRow, $logo);
        $logo = @$logo[1];
        if ($logo && !str_contains($logo, 'spacer')) {
            return str_replace('76x28', '640x480', 'https:'.$logo);
        }

        return null;
    }
}
