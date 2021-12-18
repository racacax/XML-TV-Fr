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

    /**
     * Program constructor.
     * @param $channel
     * @param $start
     * @param $end
     */
    public function __construct($channel, $start, $end)
    {
        $this->channel = $channel;
        $this->titles = [];
        $this->categories = [];
        $this->descs = [];
        $this->subtitles = [];
        $this->credits = [];
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * @return mixed
     */
    public function getTitles()
    {
        return self::listToMark($this->titles, "title");
    }

    /**
     * @param mixed $title
     * @param string $lang
     */
    public function addTitle($title, $lang="fr"): void
    {
        $this->titles[] = array("name"=>$title, "lang"=>$lang);
    }

    /**
     * @return mixed
     */
    public function getDescs()
    {
        return self::listToMark($this->descs, "desc");
    }

    /**
     * @param $name
     * @param $type
     */
    public function addCredit($name, $type): void
    {
        $this->titles[] = array("name"=>$name, "type"=>$type);
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
        $this->descs[] = array("name"=>$desc, "lang"=>$lang);
    }

    /**
     * @return mixed
     */
    public function getCategories()
    {
        return self::listToMark($this->categories, "category");
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
        $this->episode_num = @($season-1).'.'.@($episode-1);
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
            return '<rating system="csa">
      <value>'.stringAsXML($this->rating).'</value>
    </rating>';
        }
        return "";
    }

    /**
     * @param mixed $rating
     */
    public function setRating($rating): void
    {
        $this->rating = $rating;
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
    '.$this->getRating().'</programme>
';
    }

    public function save() {
        fputs($this->getFp(), $this->toString());
    }

    private function getFp() {
        return $this->channel->getFp();
    }

    public static function listToMark($list, $tagName) {
        $str = "";
        foreach($list as $elem) {
            $str .= '<'.$tagName.' lang="'.$elem["lang"].'">' . stringAsXML($elem['name']) . "</$tagName>\n";
        }
        return trim($str);
    }

}