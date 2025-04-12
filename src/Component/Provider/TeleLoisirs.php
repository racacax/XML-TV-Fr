<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

class TeleLoisirs extends AbstractProvider implements ProviderInterface
{
    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_teleloisirs.json'), $priority ?? 0.6);
    }

    public function constructEPG(string $channel, string $date): Channel | bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }
        $res1 = $this->getContentFromURL($this->generateUrl($channelObj, new \DateTimeImmutable($date)));
        $lis = explode('<div class="mainBroadcastCard reverse"', $res1);
        unset($lis[0]);
        $count = count($lis);

        foreach ($lis as $index => $li) {
            $this->setStatus(round($index * 100 / $count, 2).' %');
            preg_match('/href="(.*?)" title="(.*?)"/', $li, $titlehref);
            preg_match('/srcset="(.*?)"/', $li, $img);
            if (!isset($img[1])) {
                $img = '';
            } else {
                $img = str_replace('64x90', '640x360', explode(' ', $img[1])[0]);
            }
            $genre = trim(explode('</div>', explode('<div class="mainBroadcastCard-genre">', $li)[1] ?? '')[0]);
            $genreFormat = trim(explode('</p>', explode('<p class="mainBroadcastCard-format">', $li)[1] ?? '')[0]);
            $subtitle = @trim(explode('</p>', explode('<p class="mainBroadcastCard-subtitle">', $li)[1] ?? '')[0]);
            $hour = explode('<', explode('>', explode('<p class="mainBroadcastCard-startingHour"', $li)[1] ?? '')[1])[0];
            $duration = @explode('<', explode('<span class="mainBroadcastCard-durationContent">', $li)[1] ?? '')[0];
            if (empty($duration)) {
                return false;
            }
            $duration = str_replace('min', '', $duration);
            $duration = explode('h', $duration);
            if (count($duration) == 2) {
                $duration = 60 * intval($duration[1]) + 3600 * intval($duration[0]);
            } else {
                $duration = 60 * intval($duration[0]);
            }
            $startDate = strtotime($date . ' ' . str_replace('h', ':', $hour));
            $program = Program::withTimestamp($startDate, $startDate + $duration);
            //@todo: add async
            $detail = $this->getContentFromURL($titlehref[1]);
            $detailJson = @explode('<script type="application/ld+json">', $detail)[1];
            if (!empty($detailJson)) {
                $detailJson = json_decode(explode('</script>', $detailJson)[0], true);
                $synopsis = $detailJson['description'] ?? '';
                if (isset($detailJson['review'])) {
                    $synopsis .= "\nCritique : \n";
                    $synopsis .= @($detailJson['review']['description'] ?? $detailJson['review']['reviewBody']) ;
                    if (isset($detailJson['review']['reviewRating'])) {
                        $synopsis .= "\nNote : ".$detailJson['review']['reviewRating']['ratingValue'].'/5';
                    }
                }
                $program->setEpisodeNum(@$detailJson['partOfSeason']['seasonNumber'], @$detailJson['episodeNumber']);
                foreach ($detailJson as $key => $value) {
                    if (in_array($key, ['actor', 'director'])) {
                        foreach ($value as $person) {
                            $program->addCredit($person['name'], $key);
                        }
                    }
                }
            } else {
                $synopsis = @trim(explode('<', explode('<div class="defaultStyleContentTags">', $detail)[1] ?? '')[0]);
            }

            $participants = explode('figcaption class="personCard-mediaLegend', $detail);
            unset($participants[0]);
            if (!empty($participants)) {
                $synopsis .= "\nAvec :\n";
            }
            foreach ($participants as $participant) {
                $name = trim(explode('<', explode('>', $participant)[2] ?? '')[0]);
                $role = trim(explode('<', explode('"personCard-mediaLegendRole">', $participant)[1] ?? '')[0]);
                if ($role == 'Présentateur') {
                    $tag = 'presenter';
                } elseif ($role == 'Réalisateur') {
                    $tag = 'director';
                } else {
                    $tag = 'guest';
                }
                if (!isset($detailJson)) {
                    $program->addCredit($name, $tag);
                }
                $synopsis .= $name . " ($role), ";
            }
            $synopsis = rtrim($synopsis, ', ');
            $program->addTitle($titlehref[2]);
            $program->addSubtitle($subtitle);
            $program->addCategory($genre);
            $program->addCategory($genreFormat);
            $program->setIcon($img);
            $program->addDesc($synopsis);

            $channelObj->addProgram($program);
        }

        return $channelObj;
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $date): string
    {
        if ($date->format('Y-m-d') === date('Y-m-d')) {
            return sprintf(
                'https://www.programme-tv.net/programme/chaine/%s',
                $this->channelsList[$channel->getId()]
            );
        }

        return sprintf(
            'https://www.programme-tv.net/programme/chaine/%s/%s',
            $date->format('Y-m-d'),
            $this->channelsList[$channel->getId()]
        );
    }
}
