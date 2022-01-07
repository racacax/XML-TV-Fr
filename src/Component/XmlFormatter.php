<?php


namespace racacax\XmlTv\Component;


/**
 * @author benoit
 *
 * class Formatter
 * @package racacax\XmlTv\Component
 */
class XmlFormatter
{

    /**
     * Formatter constructor
     */
    public function __construct()
    {
        // TODO:
    }

    public function formatChannel($channel, ?ProviderInterface $provider): string
    {
        $content = [];
        if (isset($provider)) {
            //todo add dummy provider
            $content[] = '<!-- ' . get_class($provider) . ' -->';
        }

        foreach($channel->getPrograms() as $program) {
            $content[] = '<programme start="'.date('YmdHis O',$program->getStart()).'" stop="'.date('YmdHis O',$program->getEnd()).'" channel="'.$channel->getId().'">';
            $content[] = $this->listToMark($program->getTitles(), "title", "Aucun titre");
            $content[] = $this->listToMark($program->getSubtitles(), "sub-title");
            $content[] = $this->listToMark($program->getCategories(), "category", "Inconnu");
            $content[] = $this->listToMark($program->getDescs(), "desc", "Aucune description");
            $content[] = $this->buildEpisodeNum($program->getEpisodeNum());

            $content[] = $this->buildCredits($program->getCredits());
            $content[] = $this->buildIcon($program->getIcon());
            $content[] = $this->buildYear($program->getYear());
            $content[] = $this->buildYear($program->getYear());
            $content[] = $this->buildRating($program->getRating());

            $content[]= '</programme>';

        }

        return implode("\n", array_filter($content));
    }


    private function listToMark($list, $tagName, $stringIfEmpty = null) {
        $content = [];
        foreach($list as $elem) {
            $content[]= '<'.$tagName.' lang="'.$elem["lang"].'">' . $this->stringAsXML($elem['name']) . "</$tagName>";
        }
        if(empty($content) && isset($stringIfEmpty)) {
            $content[]= '<'.$tagName.' lang="fr">' . $this->stringAsXML($stringIfEmpty) . "</$tagName>";
        }
        return trim(implode("\n", $content));
    }

    public function buildCredits(array $credits): string
    {
        if(empty($this->credits)) {
            return '';
        }

        $str = '<credits>'.chr(10);
        foreach ($credits as $credit) {
            $str.= '    <'.$credit['type'].'>'.$this->stringAsXML($credit['name']).'</'.$credit['type'].'>'.chr(10);
        }
        $str.= '</credits>';

        return $str;
    }

    /**
     * @return mixed
     */
    public function buildEpisodeNum($episodeNum): string
    {
        if(isset($episodeNum)) {
            return '<episode-num system="xmltv_ns">'.$episodeNum.".</episode-num>";
        }
        return '';
    }

    /**
     * @return mixed
     */
    public function buildIcon(string $icon): string
    {
        if(isset($icon) && strlen($icon) > 0) {
            return '<icon src="' . $this->stringAsXML($icon) . '" />';
        }
        return "";
    }

    /**
     * @return mixed
     */
    public function buildYear(?string $year): string
    {
        if(!isset($year)){
            return '';
        }

        return '<year>'.$this->stringAsXML($year).'</year>';
    }

    public function buildRating($rating): string
    {
        if(!isset($rating)) {
            return '';
        }
        $picto = $this->getPictoFromRatingSystem($rating[0], $rating[1]);

        return '<rating system="'.$this->stringAsXML($rating[1]).'"><value>'.$this->stringAsXML($rating[0]).'</value>'
               .(!is_null($picto) ? '<icon src="'.$this->stringAsXML($picto).'" />' : '').
               '</rating>';
    }

    private function getPictoFromRatingSystem($rating, $system): ?string
    {
        return $this->ratting[strtolower($system)][strtolower($rating)] ?? null;
    }
    private function stringAsXML($string) {
        return str_replace('"','&quot;',htmlspecialchars($string, ENT_XML1));
    }
}