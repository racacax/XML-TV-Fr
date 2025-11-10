<?php

declare(strict_types=1);

namespace racacax\XmlTv\ValueObject;

use DateTimeImmutable;
use DateTime;
use DateTimeZone;

class Program
{
    /**
     * @var array<array{name: string, lang: string}>
     */
    private array $titles;

    /**
     * @var array<array{name: string, lang: string}>
     */
    private array $descs;

    /**
     * @var array<array{name: string, lang: string}>
     */
    private array $categories;
    private ?string $icon;
    private DateTime|DateTimeImmutable $start;
    private DateTime|DateTimeImmutable $end;
    private ?string $episodeNum;

    /**
     * @var array<array{name: string, lang: string}>
     */
    private array $subtitles;

    /**
     * @var array{string, string} | null
     */
    private ?array $rating;

    /**
     * @var array<array{name: string, type: string}>
     */
    private array $credits;

    private ?bool $isNew;
    /**
     * @var array{start: ?DateTimeImmutable, channel: ?string} | null
     */
    private ?array $previouslyShown;

    /**
     * @var array{name: string, value: string | null, attrs: array{key: string, value: string} | null} | null
     */
    private ?array $customTags;


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
        $this->titles = [];
        $this->categories = [];
        $this->descs = [];
        $this->subtitles = [];
        $this->credits = [];
        $this->episodeNum = null;
        $this->icon = null;
        $this->rating = null;
        $this->isNew = null;
        $this->previouslyShown = null;
        $this->customTags = [];
    }

    public function getIsNew(): ?bool
    {
        return $this->isNew;
    }

    public function setIsNew(?bool $isNew): void
    {
        $this->isNew = $isNew;
    }
    public function getPreviouslyShown(): ?array
    {
        return $this->previouslyShown;
    }

    public function addCustomTag(string $name, ?string $value = null, ?array $attrs = null): void
    {
        $this->customTags[] = ['name' => $name, 'value' => $value, 'attrs' => $attrs];
    }
    public function getCustomTags(): ?array
    {
        return $this->customTags;
    }

    public function setPreviouslyShown(bool $isPreviouslyShown, DateTime|DateTimeImmutable $start = null, ?string $channel = null): void
    {
        if (!$isPreviouslyShown) {
            $this->previouslyShown = null;
        } else {
            $this->previouslyShown = ['start' => $start, 'channel' => $channel];
        }
    }


    /**
     * @return array<array{name: string, lang: string}>
     */
    public function getTitles(): array
    {
        return $this->titles;
    }

    /**
     * Ajout d'un titre
     * @param string|null $title
     * @param string $lang
     */
    public function addTitle(?string $title, string $lang = 'fr'): void
    {
        if (!empty($title)) {
            $this->titles[] = ['name' => $title, 'lang' => $lang];
        }
    }

    /**
     * @return array<array{name: string, lang: string}>
     */
    public function getDescs(): array
    {
        return $this->descs;
    }

    /**
     * Ajout d'un crédit (acteur, présentateur, ...)
     */
    public function addCredit(?string $name, $type = 'guest'): void
    {
        if (!empty($name)) {
            if (!in_array($type, ['actor', 'director', 'writer', 'producer',
                'composer', 'editor', 'presenter', 'commentator', 'adapter'])) {
                $type = 'guest';
            }
            $this->credits[] = ['name' => $name, 'type' => $type];
        }
    }

    /**
     * @return array<array{name: string, type: string}>
     */
    public function getCredits(): array
    {
        usort($this->credits, function ($a, $b) {
            $priority = [
                'director' => 1,
                'actor' => 2,
                'writer' => 3,
                'adapter' => 4,
                'producer' => 5,
                'composer' => 6,
                'editor' => 7,
                'presenter' => 8,
                'commentator' => 9,
                'guest' => 10
            ];

            $priorityA = $priority[$a['type']] ?? PHP_INT_MAX;
            $priorityB = $priority[$b['type']] ?? PHP_INT_MAX;

            return $priorityA - $priorityB;
        });

        return $this->credits;
    }

    /**
     * Ajout d'un synopsis
     */
    public function addDesc(?string $desc, $lang = 'fr'): void
    {
        if (!empty($desc)) {
            $this->descs[] = ['name' => $desc, 'lang' => $lang];
        }
    }

    /**
     * @return array<array{name: string, lang: string}>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param string|null $category
     * @param $lang
     * @return void
     */
    public function addCategory(?string $category, $lang = 'fr'): void
    {
        if (!empty($category)) {
            $this->categories[] = ['name' => $category, 'lang' => $lang];
        }
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Définition de l'icone du programme
     */
    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
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
    public function getEpisodeNum(): ?string
    {
        return $this->episodeNum;
    }

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
        $this->episodeNum = $season . '.' . $episode;
    }

    /**
     * @return array<array{name: string, lang: string}>
     */
    public function getSubtitles(): array
    {
        return $this->subtitles;
    }

    /**
     * Ajout d'un sous-titre au programme
     */
    public function addSubtitle(?string $subtitle, $lang = 'fr'): void
    {
        if (!empty($subtitle)) {
            $this->subtitles[] = ['name' => $subtitle, 'lang' => $lang];
        }
    }

    /**
     * @return array{string, string}|null
     */
    public function getRating(): ?array
    {
        return $this->rating;
    }

    /**
     * Définition du rating du programme (CSA -10 ans par exemple)
     */
    public function setRating(string|int|null $rating, $system = 'CSA'): void
    {
        if (isset($rating)) {
            $this->rating = [$rating, $system];
        }
    }
}
