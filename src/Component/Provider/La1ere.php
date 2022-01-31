<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class La1ere extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_1ere.json'), $priority ?? 0.3);
    }

    public function constructEPG(string $channel, string $date)
    {
        $channelObj = parent::constructEPG($channel, $date);
        if ($date != date('Y-m-d')) {
            return false;
        }
        if (!$this->channelExists($channel)) {
            return false;
        }
        $content = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
        $res1 = html_entity_decode($content, ENT_QUOTES); //TODO: @find if needed (next PR ?)
        // no more change TimeZone
        $timezone = new \DateTimeZone($this->channelsList[$channel]['timezone']);
        $days = explode('<div class="guide">', $res1);
        $infos = [];
        unset($days[0]);
        $days = array_values($days);
        foreach ($days as $key => $day) {
            $programs = explode('</li>', $day);
            foreach ($programs as $program) {
                preg_match('/\<span class=\"program-hour\".*?\>(.*?)\<\/span\>/', $program, $hour);
                preg_match('/\<span class=\"program-name\".*?\>(.*?)\<\/span\>/', $program, $name);
                preg_match('/\<div class=\"subtitle\".*?\>(.*?)\<\/div\>/', $program, $subtitle);
                if (isset($name[1])) {
                    $dateTime = new \DateTimeImmutable(date('Ymd', strtotime('now') + 86400 * $key) . ' ' . str_replace('H', ':', $hour[1]), $timezone);
                    $infos[] = [
                        'hour' => $dateTime->format('YmdHis O'),
                        'title' => $name[1],
                        'subtitle' => @$subtitle[1]
                    ];
                }
            }
        }
        for ($i=0; $i<count($infos)-1; $i++) {
            $program = new Program(strtotime($infos[$i]['hour']), strtotime($infos[$i+1]['hour']));
            if (strlen($infos[$i+1]['subtitle'])>0) {
                $program->addSubtitle($infos[$i+1]['subtitle']);
            }
            $program->addTitle($infos[$i]['title']);
            $program->addCategory('Inconnu');
            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        $channel_id = $this->channelsList[$channel->getId()]['id'];

        return "https://la1ere.francetvinfo.fr/$channel_id/emissions";
    }
}
