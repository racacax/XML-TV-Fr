<?php

declare(strict_types=1);

namespace racacax\XmlTv\ValueObject;

use DateTimeImmutable;
use DateTime;
use DateTimeZone;
use racacax\XmlTv\StaticComponent\RatingPicto;

class Program extends Tag
{
    private DateTime|DateTimeImmutable $start;
    private DateTime|DateTimeImmutable $end;

    private static array $SORTED_CHILDREN = [
        'title', 'sub-title', 'desc', 'credits', 'date',
        'category', 'keyword', 'language', 'orig-language',
        'length', 'icon', 'url', 'country', 'episode-num',
        'video', 'audio', 'previously-shown', 'premiere',
        'last-chance', 'new', 'subtitles', 'rating',
        'star-rating', 'review', 'audio-described'
    ];

    private static array $SORTED_CREDITS = [
        'director', 'actor', 'writer', 'adapter', 'producer',
        'composer', 'editor', 'presenter', 'commentator', 'guest'
    ];

    /**
     * Constructs a program with Unix timestamps as parameters
     * @param int $start
     * @param int $end
     * @return Program
     */
    public static function withTimestamp(int $start, int $end): Program
    {
        $startDate = new DateTimeImmutable("@$start");
        $endDate = new DateTimeImmutable("@$end");

        return new Program($startDate, $endDate);
    }

    /**
     * Program constructor.
     */
    public function __construct(DateTime|DateTimeImmutable $start, DateTime|DateTimeImmutable $end)
    {
        if ($start > $end) {
            throw new \ValueError('Start date must be before end date');
        }
        $this->start = (clone $start)->setTimezone(new DateTimeZone('Europe/Paris'));
        $this->end = (clone $end)->setTimezone(new DateTimeZone('Europe/Paris'));

        $attributes = [
            'start' => $this->start->format('YmdHis O'),
            'stop' => $this->end->format('YmdHis O')
        ];
        parent::__construct('programme', [], $attributes, self::$SORTED_CHILDREN);
    }

    public function addStarRating(int|float $stars, int $totalStars, ?string $system = null): void
    {
        $this->addChild(new Tag('star-rating', ['value' => [new Tag('value', "$stars/$totalStars")]], ['system' => $system]));
    }

    public function addReview(string $review, ?string $source = null, ?string $reviewer = null): void
    {
        $this->addChild(new Tag('review', $review, [
            'source' => $source,
            'reviewer' => $reviewer,
            'type' => 'text'
        ]));
    }
    public function addSubtitles(string $type, ?string $lang = null): void
    {
        $attrs = ['type' => $type, 'lang' => $lang];
        $this->addChild(new Tag('subtitles', null, $attrs));
    }
    public function addKeyword(string $keyword, ?string $lang = null): void
    {
        $attrs = ['lang' => $lang];
        $this->addChild(new Tag('keyword', $keyword, $attrs));
    }

    /**
     * Define program as audio described
     * Note: audio-described tag is not officially part of the XMLTV DTD. We add audio-description as
     * keyword as well for compatibility with applications that do not support this tag.
     * @return void
     */
    public function setAudioDescribed(): void
    {
        $this->setChild(new Tag('audio-described', null, []));
        $keywords = $this->getChildren('keyword');
        foreach ($keywords as $keyword) {
            if ($keyword instanceof Tag) {
                $xml = $keyword->asXML();
                if (str_contains($xml, 'audio-description')) {
                    return;
                }
            }
        }
        $this->addKeyword('audio-description');
    }




    public function setPreviouslyShown(DateTime|DateTimeImmutable|null $start = null, ?string $channel = null): void
    {
        $this->setChild(new Tag('previously-shown', null, [
            'start' => $start?->setTimezone(new DateTimeZone('Europe/Paris'))->format('YmdHis O'),
            'channel' => $channel
        ]));
    }


    public function setPremiere(?string $value = null, ?string $lang = null): void
    {
        $this->setChild(new Tag('premiere', $value, [
            'lang' => $lang
        ]));
    }


    /**
     * Ajout d'un titre
     * @param string|null $title
     * @param string $lang
     */
    public function addTitle(?string $title, string $lang = 'fr'): void
    {
        if (!empty($title)) {
            $this->addChild(new Tag('title', $title, ['lang' => $lang]));
        }
    }

    /**
     * Ajout d'un crédit (acteur, présentateur, ...)
     */
    public function addCredit(?string $name, $type = 'guest'): void
    {
        if (!empty($name)) {
            if (!in_array($type, self::$SORTED_CREDITS)) {
                $type = 'guest';
            }
            $credits = $this->getChildren('credits');
            if (!empty($credits)) {
                $creditTag = $credits[0];
            } else {
                $creditTag = new Tag('credits', null, [], self::$SORTED_CREDITS);
                $this->setChild($creditTag);
            }
            $creditTag->addChild(new Tag($type, $name));
        }
    }

    /**
     * Ajout d'un synopsis
     */
    public function addDesc(?string $desc, $lang = 'fr'): void
    {
        if (!empty($desc)) {
            $this->addChild(new Tag('desc', $desc, ['lang' => $lang]));
        }
    }

    public function setDate(string $date): void
    {
        $this->setChild(new Tag('date', $date, []));
    }

    public function setCountry(string $country, ?string $lang = null): void
    {
        $this->setChild(new Tag('country', $country, ['lang' => $lang]));
    }


    /**
     * @param string|null $category
     * @param $lang
     * @return void
     */
    public function addCategory(?string $category, $lang = 'fr'): void
    {
        if (!empty($category)) {
            $this->addChild(new Tag('category', $category, ['lang' => $lang]));
        }
    }

    /**
     * Définition de l'icone du programme
     */
    public function addIcon(?string $icon, ?string $width = null, ?string $height = null): void
    {
        if (!empty($icon)) {
            $this->addChild(new Tag('icon', null, ['src' => $icon, 'width' => $width, 'height' => $height]));
        }
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getStart(): DateTime|DateTimeImmutable
    {
        return $this->start;
    }

    /**
     * @return DateTime|DateTimeImmutable
     */
    public function getEnd(): DateTime|DateTimeImmutable
    {
        return $this->end;
    }

    /**
     * @return string|null
     */

    /**
     * Définition de la saison et de l'épisode du programme
     */
    public function setEpisodeNum(string|int|null $season, string|int|null $episode): void
    {
        if (!isset($season) && !isset($episode)) {
            return;
        }
        $season = @(intval($season) - 1);
        $episode = @(intval($episode) - 1);
        if ($season < 0) {
            $season = 0;
        }
        if ($episode < 0) {
            $episode = 0;
        }
        $this->setChild(new Tag('episode-num', "$season.$episode.", ['system' => 'xmltv_ns']));
    }

    /**
     * Ajout d'un sous-titre au programme
     */
    public function addSubTitle(?string $subtitle, $lang = 'fr'): void
    {
        if (!empty($subtitle)) {
            $this->addChild(new Tag('sub-title', $subtitle, ['lang' => $lang]));
        }
    }

    /**
     * Définition du rating du programme (CSA -10 ans par exemple)
     */
    public function setRating(string|int|null $rating, $system = 'CSA'): void
    {
        if (isset($rating)) {
            $pictos = RatingPicto::getInstance();
            $picto = $pictos->getPictoFromRatingSystem((string)$rating, $system);
            $children = ['value' => [new Tag('value', (string) $rating)]];
            if (isset($picto)) {
                $children['icon'] = [new Tag('icon', null, ['src' => $picto])];
            }
            $this->setChild(new Tag('rating', $children, ['system' => $system]));
        }
    }
}
