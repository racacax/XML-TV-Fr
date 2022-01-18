<?php

declare(strict_types=1);

namespace racacax\XmlTv\ValueObject;

class Program
{
    private $titles;
    private $descs;
    private $categories;
    private $icon;
    private $start;
    private $end;
    private $episode_num;
    private $subtitles;
    private $rating;
    private $credits;
    private $year;

    /**
     * Program constructor.
     * @param $start
     * @param $end
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
     * @param $name
     * @param $type
     */
    public function addCredit($name, $type): void
    {
        if (!empty($name)) {
            if (empty($type)) {
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


    /**
     * @return mixed
     */
    public function getEnd()
    {
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
     * @param $season
     * @param $episode
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
