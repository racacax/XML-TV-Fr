<?php
class Program {
    private $channel;
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
    private static $RATINGS_PICTO;

    /**
     * Program constructor.
     * @param $channel
     * @param $start
     * @param $end
     */
    public function __construct($channel, $start, $end)
    {
        if(!isset(self::$RATINGS_PICTO))
            self::$RATINGS_PICTO = json_decode(file_get_contents("resources/ratings_picto.json"), true);
        $this->channel = $channel;
        $this->titles = [];
        $this->categories = [];
        $this->descs = [];
        $this->subtitles = [];
        $this->credits = [];
        $this->start = $start;
        $this->end = $end;
    }

    private function getPictoFromRatingSystem($rating, $system) {
        return @self::$RATINGS_PICTO[strtolower($system)][strtolower($rating)];
    }

    /**
     * @return mixed
     */
    public function getTitles()
    {
        return self::listToMark($this->titles, "title", "Aucun titre");
    }

    /**
     * @param mixed $title
     * @param string $lang
     */
    public function addTitle($title, $lang="fr"): void
    {
        if(!empty($title))
            $this->titles[] = array("name"=>$title, "lang"=>$lang);
    }

    /**
     * @return mixed
     */
    public function getDescs()
    {
        return self::listToMark($this->descs, "desc", "Aucune description");
    }

    /**
     * @param $name
     * @param $type
     */
    public function addCredit($name, $type): void
    {
        if(!empty($name)) {
            if(empty($type))
                $type = "guest";
            $this->credits[] = array("name" => $name, "type" => $type);
        }
    }

    /**
     * @return mixed
     */
    public function getCredits()
    {
        if(!empty($this->credits)) {
            $str = '<credits>'.chr(10);
            foreach ($this->credits as $credit) {
                $str.= '    <'.$credit['type'].'>'.stringAsXML($credit['name']).'</'.$credit['type'].'>'.chr(10);
            }
            $str.= '</credits>';
            return $str;
        }
        return "";
    }

    /**
     * @param mixed $desc
     * @param string $lang
     */
    public function addDesc($desc, $lang="fr"): void
    {
        if(!empty($desc))
            $this->descs[] = array("name"=>$desc, "lang"=>$lang);
    }

    /**
     * @return mixed
     */
    public function getCategories()
    {
        return self::listToMark($this->categories, "category", "Inconnu");
    }

    /**
     * @param mixed $category
     */
    public function addCategory($category, $lang="fr"): void
    {
        if(!empty($category))
            $this->categories[] = array("name"=>$category, "lang"=>$lang);
    }

    /**
     * @return mixed
     */
    public function getIcon()
    {
        if(isset($this->icon) && strlen($this->icon) > 0) {
            return '<icon src="' . stringAsXML($this->icon) . '" />';
        }
        return "";
    }

    /**
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
        if(isset($this->episode_num)) {
            return '<episode-num system="xmltv_ns">'.$this->episode_num.".</episode-num>";
        }
        return "";
    }

    /**
     * @param $season
     * @param $episode
     */
    public function setEpisodeNum($season, $episode): void
    {
        if(!isset($season) && !isset($episode))
            return;
        $season = @(intval($season)-1);
        $episode = @(intval($episode)-1);
        if($season < 0)
            $season = 0;
        if($episode < 0)
            $episode = 0;
        $this->episode_num = $season.'.'.$episode;
    }

    /**
     * @return mixed
     */
    public function getSubtitles()
    {
        return self::listToMark($this->subtitles, "sub-title");
    }

    /**
     * @param mixed $subtitle
     * @param string $lang
     */
    public function addSubtitle($subtitle, $lang="fr"): void
    {
        if(!empty($subtitle))
            $this->subtitles[] = array('name'=>$subtitle, "lang"=>$lang);
    }

    /**
     * @return mixed
     */
    public function getRating()
    {
        if(isset($this->rating)) {
            $picto = $this->getPictoFromRatingSystem($this->rating[0], $this->rating[1]);
            return '<rating system="'.stringAsXML($this->rating[1]).'">
      <value>'.stringAsXML($this->rating[0]).'</value>'
      .(!is_null($picto) ? '<icon src="'.stringAsXML($picto).'" />' : '').
      '</rating>';
        }
        return "";
    }

    /**
     * @param mixed $rating
     */
    public function setRating($rating, $system="CSA"): void
    {
        $this->rating = [$rating, $system];
    }

    public function toString() {
        return '<programme start="'.date('YmdHis O',$this->getStart()).'" stop="'.date('YmdHis O',$this->getEnd()).'" channel="'.$this->channel->getId().'">
    '.$this->getTitles().'
    '.$this->getSubtitles().'
    '.$this->getCategories().'
    '.$this->getDescs().'
    '.$this->getEpisodeNum().'
    '.$this->getCredits().'
    '.$this->getIcon().'
    '.$this->getYear().'
    '.$this->getRating().'</programme>
';
    }

    public function save() {
        fputs($this->getFp(), $this->toString());
    }

    private function getFp() {
        return $this->channel->getFp();
    }

    public static function listToMark($list, $tagName, $stringIfEmpty = null) {
        $str = "";
        foreach($list as $elem) {
            $str .= '<'.$tagName.' lang="'.$elem["lang"].'">' . stringAsXML($elem['name']) . "</$tagName>\n";
        }
        if(empty($str) && isset($stringIfEmpty)) {
            $str.= '<'.$tagName.' lang="fr">' . stringAsXML($stringIfEmpty) . "</$tagName>\n";
        }
        return trim($str);
    }

    /**
     * @return mixed
     */
    public function getYear()
    {
        if(isset($year))
            return '<year>'.stringAsXML($this->year).'</year>';
    }

    /**
     * @param mixed $year
     */
    public function setYear($year): void
    {
        if(!empty($year))
            $this->year = $year;
    }

}