<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.1 : 08/02/2024
 */
class Cogeco extends AbstractProvider implements ProviderInterface
{
    protected ProviderCache $jsonPerDay;
    protected static string $COOKIE_VALUE = '823D';
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_cogeco.json'), $priority ?? 0.61);
        $this->jsonPerDay = new ProviderCache('cogecoCache');
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        date_default_timezone_set('America/Toronto');
        $channelId = $this->getChannelsList()[$channel];
        $minStart = new \DateTimeImmutable($date);
        if ($minStart < new \DateTimeImmutable(date('Y-m-d'))) {
            return false;
        }
        $programsPaths = [];
        $count = 4;
        $span = 6;
        for ($i = 0; $i < $count; $i++) {
            $percent = '| Main data (1/2) : '.round($i * 100 / ($count), 2) . ' %';
            $this->setStatus($percent);
            $arrayPerDay = $this->jsonPerDay->getArray();
            $start = $minStart->modify('+'.($span * $i).' hours');
            $key = strval($start->getTimestamp());
            if (!isset($arrayPerDay[$key])) {
                $content = $this->getContentFromURL($this->generateUrl($channelObj, $start), ['Cookie' => 'TVMDS_Cookie='.self::$COOKIE_VALUE]);
                $json = json_decode($content, true);
                $this->jsonPerDay->setArrayKey($key, $json);
            } else {
                $json = $arrayPerDay[$key];
            }
            $html = @$json['data'];
            if (!$html) {
                return false;
            }

            $channelRows = explode('<!-- channel row -->', $html);
            unset($channelRows[0]);
            $found = false;
            foreach ($channelRows as $channelRow) {
                preg_match('/tvm_txt_chan_name">(.*?)<\/span>/', $channelRow, $channelIdName);
                $channelIdName = $channelIdName[1];
                if ($channelIdName == $channelId) {
                    $found = true;
                    preg_match_all('/prgm_details\((.*?)\)/', $channelRow, $paths);
                    foreach ($paths[1] as $path) {
                        if (!in_array($path, $programsPaths)) {
                            $programsPaths[] = $path;
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
            $percent = '| Details (2/2) : '.round($index * 100 / $count, 2) . ' %';
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
            if ($currentCursor < $minStart) { // program is from the day before
                continue;
            }
            $duration = intval(explode('(', explode(' ', $details[1][2])[0])[1]);
            $endTimeObj = $startTimeObj->modify('+'.$duration.' minutes');
            $program = new Program($startTimeObj->getTimestamp(), $endTimeObj->getTimestamp());
            $program->addTitle(trim($title[1] ?? 'Aucun titre'));
            $program->addSubtitle(trim($subtitle[1] ?? 'Aucun sous-titre'));
            $program->setIcon('https:'.($img[1] ?? ''));
            $program->addDesc($description[1] ?? 'Aucune description');
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
    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        return sprintf(
            'https://tvmds.tvpassport.com/tvmds/cogeco/grid_v3/grid.php?subid=tvpassport&lu=%s&wd=1138&ht=100000&mode=json&style=blue&wid=wh&st=%s&ch=1&tz=EST5EDT&lang=fr-ca&ctrlpos=top&items=99999&filter=',
            self::$COOKIE_VALUE,
            $date->getTimestamp()
        );
    }
}
