<?php

namespace racacax\XmlTv\Component;

use racacax\XmlTv\StaticComponent\RatingPicto;

/**
 * @author benoit
 *
 * class Formatter
 * @package racacax\XmlTv\Component
 */
class XmlFormatter
{
    /**
     * @var RatingPicto
     */
    private $ratingPicto;

    public function __construct()
    {
        $this->ratingPicto = RatingPicto::getInstance();
    }

    public function formatChannel($channel, ?ProviderInterface $provider): string
    {
        $content = [];
        if (isset($provider)) {
            //todo add dummy provider
            $content[] = '<!-- ' . get_class($provider) . ' -->';
        }

        foreach ($channel->getPrograms() as $program) {
            $content[] = '<programme start="'.$program->getStartFormatted().'" stop="'. $program->getEndFormatted().'" channel="'.$channel->getId().'">';
            $content[] = $this->listToMark($program->getTitles(), 'title', 'Aucun titre');
            $content[] = $this->listToMark($program->getSubtitles(), 'sub-title');
            $content[] = $this->listToMark($program->getDescs(), 'desc', 'Aucune description');
            $content[] = $this->buildCredits($program->getCredits());
            $content[] = $this->listToMark($program->getCategories(), 'category', 'Inconnu');
            $content[] = $this->buildIcon($program->getIcon());
            $content[] = $this->buildEpisodeNum($program->getEpisodeNum());
            $content[] = $this->buildRating($program->getRating());
            //$content[] = $this->buildYear($program->getYear());

            $content[]= '</programme>';
        }

        return implode("\n", array_filter($content));
    }


    private function listToMark($list, $tagName, $stringIfEmpty = null)
    {
        $content = [];
        foreach ($list as $elem) {
            $content[]= '<'.$tagName.' lang="'.$elem['lang'].'">' . $this->stringAsXML($elem['name']) . "</$tagName>";
        }
        if (empty($content) && isset($stringIfEmpty)) {
            $content[]= '<'.$tagName.' lang="fr">' . $this->stringAsXML($stringIfEmpty) . "</$tagName>";
        }

        return trim(implode("\n", $content));
    }

    private function buildCredits(?array $credits): string
    {
        if (empty($credits)) {
            return '';
        }

        $str = '<credits>'.chr(10);
        foreach ($credits as $credit) {
            $str.= '    <'.$credit['type'].'>'.$this->stringAsXML($credit['name']).'</'.$credit['type'].'>'.chr(10);
        }
        $str.= '</credits>';

        return $str;
    }

    private function buildEpisodeNum($episodeNum): string
    {
        if (!empty($episodeNum)) {
            return '<episode-num system="xmltv_ns">'.$episodeNum.'.</episode-num>';
        }

        return '';
    }

    private function buildIcon(?string $icon): string
    {
        if (isset($icon) && strlen($icon) > 0) {
            return '<icon src="' . $this->stringAsXML($icon) . '" />';
        }

        return '';
    }

    public function buildYear(?string $year): string
    {
        if (!isset($year)) {
            return '';
        }

        return '<year>'.$this->stringAsXML($year).'</year>';
    }

    private function buildRating($rating): string
    {
        if (!isset($rating)) {
            return '';
        }
        $pictoUrl = $this->ratingPicto->getPictoFromRatingSystem($rating[0], $rating[1]);

        return '<rating system="'.$this->stringAsXML($rating[1]).'"><value>'.$this->stringAsXML($rating[0]).'</value>'
               .(!is_null($pictoUrl) ? '<icon src="'.$this->stringAsXML($pictoUrl).'" />' : '').
               '</rating>';
    }

    private function stringAsXML($string)
    {
        return str_replace('"', '&quot;', htmlspecialchars($string, ENT_XML1));
    }
}
