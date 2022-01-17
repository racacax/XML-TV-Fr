<?php

declare(strict_types=1);

namespace racacax\XmlTv\StaticComponent;

use racacax\XmlTv\Component\ResourcePath;

final class RatingPicto
{
    private static $instance;
    private $ratingPictoInfo;

    private function __construct()
    {
        $this->ratingPictoInfo = json_decode(file_get_contents(ResourcePath::getInstance()->getRatingPictoPath()), true);
    }

    public static function getInstance(): self
    {
        return self::$instance ?? self::$instance = new self();
    }

    public function getPictoFromRatingSystem(?string $rating, ?string $system): ?string
    {
        if (!isset($rating) || !isset($system)) {
            return null;
        }

        return $this->ratingPictoInfo[strtolower($system)][strtolower($rating)] ?? null;
    }
}
