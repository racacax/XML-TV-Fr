<?php
declare(strict_types=1);

namespace racacax\XmlTv\StaticComponent;

final class RatingPicto
{
    private static $instance;
    private $ratingPictoInfo = [];

    private function __construct()
    {
        $this->ratingPictoInfo = json_decode(file_get_contents('resources/information/ratings_picto.json'), true);
    }

    public static function getInstance(): self
    {
        return self::$instance ?? self::$instance = new self();
    }

}