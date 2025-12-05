<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use Exception;
use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderCache;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\Component\Utils;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * @author Racacax
 * @version 0.1 : 15/02/2024
 */
class VirginPlus extends AbstractProvider implements ProviderInterface
{
    private \DateTimeImmutable $epgFromDate;
    private static array $HEADERS = ['X-Bell-API-Key' => 'fonse-web-2d842ffc', 'Referer' => 'https://tv.virginplus.ca/guide'];
    private static string $BASE_URL = 'https://tv.virginplus.ca/api/';
    private \DateTimeImmutable $epgToDate;
    private int $blockDuration;
    private array $epgInfo;
    private bool $isConfigured = false;
    private bool $disableDetails;
    public function __construct(Client $client, ?float $priority = null, array $extraParam = [])
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_virginplus.json'), $priority ?? 0.66);
        $this->gatherEpgInformation();
        $this->disableDetails = @$extraParam['virginplus_disable_details'] ?? false;
    }

    private function gatherEpgInformation(): void
    {
        /**
         * Get information about how blocks are divided for every channel
         */
        $cache = new ProviderCache('virginplusEpgInformation');
        $currentCache = $cache->getArray();

        try {
            if (empty($currentCache)) {
                $info = @json_decode($this->getContentFromURL(self::$BASE_URL.'epg/v3/epgInfo'), true);
                if ($info) {
                    $this->epgFromDate = new \DateTimeImmutable($info['minStartTime']);
                    $this->epgToDate = new \DateTimeImmutable($info['maxEndTime']);
                    $this->blockDuration = $info['schedulesBlockHoursDuration'];
                    $version = $info['version'];
                    $this->epgInfo = @json_decode($this->getContentFromURL(self::$BASE_URL."epg/v3/channels?tvService=volt&epgChannelMap=MAP_TORONTO&epgVersion=$version"), true) ?? [];
                    if ($this->epgInfo) {
                        $this->isConfigured = true;
                        $cache->setArrayKey('minStartTime', $info['minStartTime']);
                        $cache->setArrayKey('maxEndTime', $info['maxEndTime']);
                        $cache->setArrayKey('blockDuration', $info['schedulesBlockHoursDuration']);
                        $cache->setArrayKey('epgInfo', $this->epgInfo);

                        return;
                    }
                }
            } elseif (!@$currentCache['hasFailed']) {
                $this->epgFromDate = new \DateTimeImmutable($currentCache['minStartTime']);
                $this->epgToDate = new \DateTimeImmutable($currentCache['maxEndTime']);
                $this->blockDuration = $currentCache['blockDuration'];
                $this->epgInfo = $currentCache['epgInfo'];
                $this->isConfigured = true;

                return;
            }
            $message = 'Unknown error';
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }
        $cache->setArrayKey('hasFailed', true);
        // If we fail to configure provider, it will just get ignored
        if (!defined('CHANNEL_PROCESS')) {
            echo Utils::colorize(sprintf("Failed to configure Virgin Plus provider: %s\n", $message), 'red');
        }
    }

    /**
     * @throws Exception
     */
    private function getChannelInfo(string $channelId)
    {
        foreach ($this->epgInfo as $info) {
            if ($info['callSign'] === $channelId) {
                return $info;
            }
        }

        throw new Exception("Channel $channelId not found");
    }

    /**
     * @throws Exception
     */
    private function getBlocksInformation(string $channelId, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): array
    {
        /**
         * VirginPlus EPG is divided by channels and by blocks of X hours (8 at the time). They are sorted in order, time wise
         * and go from minStartTime to maxStartTime (defined in gatherEpgInformation). First block will be the first X hours (from minStartTime
         * to minStartTime + X hours). Second block will be the next X hours, ... We get all blocks that contain information about programs from
         * $fromDate to $toDate. Note: Unless you go from 00:00 UTC to 24:00 UTC, blocks will contain information before and after those times.
         */
        $channelInfo = $this->getChannelInfo($channelId);
        $cursor = $this->epgFromDate;
        $blocksInformation = [];
        $index = 0;
        $blockVersions = $channelInfo['schedulesBlockVersions'];
        $blocksLength = count($blockVersions);
        while ($cursor < $toDate && $cursor < $this->epgToDate && $index < $blocksLength) {
            $endCursor = $cursor->modify("+$this->blockDuration hours");
            if ($endCursor >= $fromDate) {
                $blocksInformation[] = ['fromDate' => $cursor, 'toDate' => $endCursor, 'blockVersion' => $blockVersions[$index]];
            }
            $cursor = $endCursor;
            $index++;
        }

        return $blocksInformation;
    }

    /**
     * @throws Exception
     */
    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        if (!$this->channelExists($channel) || !$this->isConfigured) {
            return false;
        }
        $minStart = (new \DateTimeImmutable($date))->modify('-1 day')->modify('+12 hours');
        date_default_timezone_set('America/Toronto'); // To stay consistent with other Canadian providers
        $channelId = $this->getChannelsList()[$channel];
        $maxStart = $minStart->modify('+2 days');
        $blocks = $this->getBlocksInformation($channelId, $minStart, $maxStart);
        $blockCount = count($blocks);
        foreach ($blocks as $blockIndex => $block) {
            $programs = @json_decode($this->getContentFromURL($this->generateUrl($channelObj, $block['fromDate'], $block['toDate'], $block['blockVersion']), self::$HEADERS), true);
            $programCount = count($programs);
            if (!$programs || $programCount == 0) {
                return false;
            }
            foreach ($programs as $index => $program) {
                $startDate = new \DateTimeImmutable($program['startTime']);
                if ($startDate < $minDate) {
                    continue;
                } elseif ($startDate > $maxDate) {
                    return $channelObj;
                }
                $programObj = Program::withTimestamp(strtotime($program['startTime']), strtotime($program['endTime']));
                $programObj->addTitle($program['title']);
                if (@$program['episodeTitle']) {
                    $programObj->addSubtitle($program['episodeTitle']);
                }
                if ($program['new']) {
                    $programObj->addCustomTag('premiere');
                }
                $rating = explode('-', $program['rating'] ?? '');
                $rating = end($rating);
                $ratingSystem = Utils::getCanadianRatingSystem($rating, $program['language']);
                if ($ratingSystem) {
                    $programObj->setRating($rating, $ratingSystem);
                }
                $programObj->addCategory(ucfirst(strtolower($program['showType'])));
                $programObj->setIcon(sprintf(self::$BASE_URL.'artwork/v3/artworks/artworkSelection/ASSET/%s/%s/SHOWCARD_BACKGROUND/2048x1024', $program['programSupplierId']['supplier'], $program['programSupplierId']['supplierId']));
                if (!$this->disableDetails) {
                    $this->addDetails($programObj, $index, $programCount, $blockIndex, $blockCount, $program['programId']);
                }
                $channelObj->addProgram($programObj);
            }
        }

        return $channelObj;
    }

    private function addDetails(Program $program, int $index, int $programCount, int $blockIndex, int $blockCount, string $programId): void
    {
        $blockNumber = $blockIndex + 1;
        $percent = "($blockNumber/$blockCount) ". round($index * 100 / ($programCount), 2) . ' %';
        $this->setStatus($percent);
        $programDetails = @json_decode($this->getContentFromURL(self::$BASE_URL.'epg/v3/programs/'.$programId, self::$HEADERS), true);
        if (!$programDetails) {
            // Not managing to retrieve details isn't fatal since necessary information are in the main request
            return;
        }
        $program->addDesc(@$programDetails['description']);
        $program->setEpisodeNum(@$programDetails['seasonNumber'], @$programDetails['episodeNumber']);
        foreach ($programDetails['categories'] as $category) {
            $program->addCategory($category['category']);
        }
        foreach ($programDetails['castAndCrew'] as $crew) {
            $program->addCredit($crew['name'], strtolower($crew['role']));
        }
    }

    public function generateUrl(Channel $channel, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate = null, int $blockVersion = 1): string
    {
        $channelId = $this->channelsList[$channel->getId()];
        $fromTime = $fromDate->format('Y-m-d\TH:i:s\Z');
        $toTime = $toDate->format('Y-m-d\TH:i:s\Z');

        return sprintf(
            self::$BASE_URL.'epg/v3/byBlockVersion/schedules?tvService=volt&epgChannelMap=MAP_TORONTO&callSign=%s&startTime=%s&endTime=%s&blockVersion=%s',
            urlencode($channelId),
            urlencode($fromTime),
            urlencode($toTime),
            $blockVersion
        );
    }
}
