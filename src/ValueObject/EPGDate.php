<?php

namespace racacax\XmlTv\ValueObject;

use DateTimeImmutable;

class EPGDate
{
    public static int $CACHE_ONLY = 0;
    public static int $CACHE_FIRST = 1;
    public static int $NETWORK_FIRST = 2;

    private DateTimeImmutable $date;
    private int $cachePolicy;

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }
    public function getFormattedDate(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function getCachePolicy(): int
    {
        return $this->cachePolicy;
    }

    public function __construct(DateTimeImmutable $date, int $cachePolicy)
    {
        $this->date = $date;
        $this->cachePolicy = $cachePolicy;
    }

    /**
     * @param array $configEntry
     * @return array<EPGDate>
     * @throws \DateMalformedStringException
     */
    public static function createFromConfigEntry(array $configEntry): array
    {
        $epgDates = [];
        $baseDate = (new DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->setTime(0, 0, 0);
        foreach ($configEntry as $key => $dates) {
            if ($key === 'cache-only') {
                $cachePolicy = self::$CACHE_ONLY;
            } elseif ($key === 'cache-first') {
                $cachePolicy = self::$CACHE_FIRST;
            } elseif ($key === 'network-first') {
                $cachePolicy = self::$NETWORK_FIRST;
            } else {
                throw new \InvalidArgumentException("Invalid configuration key: $key");
            }
            foreach ($dates as $date) {
                $epgDates[] = new EPGDate($baseDate->modify("$date days"), $cachePolicy);
            }
        }
        usort($epgDates, function (EPGDate $a, EPGDate $b): int {
            return $a->getDate() > $b->getDate() ? 1 : -1;
        });

        return $epgDates;
    }
    public function __toString(): string
    {
        return $this->getFormattedDate().' - '.$this->getCachePolicy();
    }
}
