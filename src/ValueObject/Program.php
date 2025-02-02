<?php

declare(strict_types=1);

namespace racacax\XmlTv\ValueObject;

class Program
{
    private $titles;
    private $descs;
    private $categories;
    private $icon;
    /**
     * @var \DateTimeImmutable|string|number
     */
    private $start;
    /**
     * @var \DateTimeImmutable|string|number
     */
    private $end;
    private $episode_num;
    private $subtitles;
    private $rating;
    private $credits;
    private $year;

    /**
     * Program constructor.
     * @param \DateTimeImmutable|string|number $start
     * @param \DateTimeImmutable|string|number $end
     */
    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
        $this->titles = [];
        $this->categories = [];
        $this->descs = [];
        $this->subtitles = [];
        $this->credits = [];
    }


    /**
     * @return mixed
     */
    public function getTitles()
    {
        return $this->titles;
    }

    /**
     * Ajout d'un titre
     * @param string $title
     * @param string $lang
     */
    public function addTitle($title, $lang = 'fr'): void
    {
        if (!empty($title)) {
            $this->titles[] = ['name' => $title, 'lang' => $lang];
        }
    }

    /**
     * @return mixed
     */
    public function getDescs()
    {
        return $this->descs;
    }

    /**
     * Ajout d'un crédit (acteur, présentateur, ...)
     * @param mixed $name
     * @param mixed $type
     */
    public function addCredit($name, $type = 'guest'): void
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
     * @return mixed
     */
    public function getCredits()
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

            $priorityA = isset($priority[$a['type']]) ? $priority[$a['type']] : PHP_INT_MAX;
            $priorityB = isset($priority[$b['type']]) ? $priority[$b['type']] : PHP_INT_MAX;

            return $priorityA - $priorityB;
        });

        return $this->credits;
    }

    /**
     * Ajout d'un synopsis
     * @param mixed $desc
     * @param string $lang
     */
    public function addDesc($desc, $lang = 'fr'): void
    {
        if (!empty($desc)) {
            $this->descs[] = ['name' => $desc, 'lang' => $lang];
        }
    }

    /**
     * @return mixed
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @param mixed $category
     */
    public function addCategory($category, $lang = 'fr'): void
    {
        if (!empty($category)) {
            $this->categories[] = ['name' => $category, 'lang' => $lang];
        }
    }

    /**
     * @return mixed
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * Définition de l'icone du programme
     * @param mixed $icon
     */
    public function setIcon($icon): void
    {
        $this->icon = $icon;
    }

    /**
     * @return mixed
     */
    public function getStart()
    {
        return $this->start;
    }

    public function getStartFormatted(): string
    {
        if (is_int($this->start)) {
            return date('YmdHis O', $this->start);
        } elseif (is_string($this->start)) {
            if (intval($this->start) == $this->start) {
                $this->start = intval($this->start);

                return $this->getStartFormatted();
            }
        } else {
            if (!$this->start) {
                return '20001212121200 +0100';
            }
            if (\DateTimeImmutable::class === get_class($this->start)) {
                return $this->start->format('YmdHis O');
            }
        }

        return $this->start;
    }

    /**
     * @return mixed
     */
    public function getEnd()
    {
        return $this->end;
    }

    public function getEndFormatted(): string
    {
        if (is_int($this->end)) {
            //Timezone issue
            return date('YmdHis O', $this->end);
        } elseif (is_string($this->end)) {
            if (intval($this->end) == $this->end) {
                $this->end = intval($this->end);

                return $this->getEndFormatted();
            }
        } else {
            if (\DateTimeImmutable::class === get_class($this->end)) {
                return $this->end->format('YmdHis O');
            }
        }

        return $this->end;
    }

    /**
     * @return mixed
     */
    public function getEpisodeNum()
    {
        return $this->episode_num;
    }

    /**
     * Définition de la saison et de l'épisode du programme
     * @param mixed $season
     * @param mixed $episode
     */
    public function setEpisodeNum($season, $episode): void
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
        $this->episode_num = $season . '.' . $episode;
    }

    /**
     * @return mixed
     */
    public function getSubtitles()
    {
        return $this->subtitles;
    }

    /**
     * Ajout d'un sous-titre au programme
     * @param mixed $subtitle
     * @param string $lang
     */
    public function addSubtitle($subtitle, $lang = 'fr'): void
    {
        if (!empty($subtitle)) {
            $this->subtitles[] = ['name' => $subtitle, 'lang' => $lang];
        }
    }

    /**
     * @return mixed
     */
    public function getRating()
    {
        return $this->rating;
    }

    /**
     * Définition du rating du programme (CSA -10 ans par exemple)
     * @param mixed $rating
     */
    public function setRating($rating, $system = 'CSA'): void
    {
        $this->rating = [$rating, $system];
    }

    /**
     * @return mixed
     */
    public function getYear()
    {
        return $this->year;
    }

    /**
     * Définition de l'année du programme
     * @param mixed $year
     */
    public function setYear($year): void
    {
        if (!empty($year)) {
            $this->year = $year;
        }
    }
}
