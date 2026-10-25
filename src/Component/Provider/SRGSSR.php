<?php

declare(strict_types=1);

namespace racacax\XmlTv\Component\Provider;

use GuzzleHttp\Client;
use racacax\XmlTv\Component\ProviderInterface;
use racacax\XmlTv\Component\ResourcePath;
use racacax\XmlTv\ValueObject\Channel;
use racacax\XmlTv\ValueObject\Program;

/*
 * SRG SSR (Swiss public broadcaster) TV program guide, through its Integration Layer API.
 * One request returns the programs of all the channels of a business unit (rts, srf, rsi) for a day: it is only sent
 * once per business unit and day, whatever the number of channels. The list of a day contains the programs that start
 * that day (the last one usually ends after midnight). Dates come with their offset.
 */
class SRGSSR extends AbstractProvider implements ProviderInterface
{
    private const API_URL = 'https://il.srgssr.ch/integrationlayer/2.0/%s/programGuide/tv/byDate/%s';
    // Roles of the credits are localized (French, German, Italian)
    private const CREDIT_ROLES = [
        'director' => ['un film de', 'réalisation', 'ein film von', 'regie', 'regia'],
        'actor' => ['avec', 'mit', 'con', 'distribution', 'besetzung', 'interpreti'],
        'presenter' => ['présentation', 'moderation', 'gastgeber', 'presentazione', 'conduzione'],
        'guest' => ['invités', 'gäste', 'experten', 'ospiti'],
        'commentator' => ['commentaires', 'commentaire', 'kommentar', 'commento'],
        'writer' => ['scénario', 'drehbuch', 'sceneggiatura'],
        'producer' => ['producteur', 'produktion', 'produzione'],
        'composer' => ['musique', 'musik', 'musica'],
        'editor' => ['montage', 'schnitt', 'montaggio'],
    ];
    // Language of the texts of each business unit
    private const LANGUAGES = ['rts' => 'fr', 'srf' => 'de', 'rsi' => 'it'];
    private const YOUTH_PROTECTION = ['YELLOW' => 'Jaune', 'RED' => 'Rouge'];

    public function __construct(Client $client, ?float $priority = null)
    {
        parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_srgssr.json'), $priority ?? 0.7);
    }

    public function constructEPG(string $channel, string $date): Channel|bool
    {
        $channelObj = parent::constructEPG($channel, $date);
        if (!$this->channelExists($channel)) {
            return false;
        }

        $businessUnit = $this->channelsList[$channel]['businessUnit'];
        $channelId = $this->channelsList[$channel]['id'];
        $json = json_decode($this->getContentFromURL(sprintf(self::API_URL, $businessUnit, $date), [], false, false), true);
        $programs = [];
        foreach ($json['programGuide'] ?? [] as $guide) {
            if (($guide['channel']['id'] ?? null) === $channelId) {
                $programs = $guide['programList'] ?? [];
            }
        }

        [$minDate, $maxDate] = $this->getMinMaxDate($date);
        foreach ($programs as $data) {
            $start = new \DateTimeImmutable($data['startTime']);
            $end = new \DateTimeImmutable($data['endTime']);
            if ($start < $minDate || $start > $maxDate || $end <= $start) {
                continue;
            }
            $program = new Program($start, $end);
            $this->addDetails($program, $data, self::LANGUAGES[$businessUnit] ?? 'fr');
            $channelObj->addProgram($program);
        }

        return $channelObj->getProgramCount() > 0 ? $channelObj : false;
    }

    private function addDetails(Program $program, array $data, string $lang): void
    {
        $program->addTitle($data['title'], $lang);
        $program->addSubTitle($data['subtitle'] ?? null, $lang);
        $program->addDesc($data['description'] ?? $data['show']['description'] ?? null, $lang);
        $program->addCategory($data['genre'] ?? null, $lang);
        // Fallback images are the logo of the channel
        if (empty($data['imageIsFallbackUrl'])) {
            $program->addIcon($data['imageUrl'] ?? null);
        }
        if (!empty($data['productionYear'])) {
            $program->setDate((string) $data['productionYear']);
        }
        if (!empty($data['productionCountry'])) {
            $program->setCountry($data['productionCountry']);
        }
        if (isset($data['episodeNumber'])) {
            $program->setEpisodeNum($data['seasonNumber'] ?? null, $data['episodeNumber']);
        }
        foreach ($data['creditList'] ?? [] as $credit) {
            $type = $this->getCreditType($credit['role'] ?? '');
            if ($type !== null) {
                $program->addCredit($credit['realName'] ?? null, $type);
            }
        }
        if (isset(self::YOUTH_PROTECTION[$data['youthProtectionColor'] ?? ''])) {
            $program->setRating(self::YOUTH_PROTECTION[$data['youthProtectionColor']], 'SRG');
        }
        if (!empty($data['subtitlesAvailable'])) {
            $program->addSubtitles('teletext');
        }
        if (!empty($data['hasVisualDescription'])) {
            $program->setAudioDescribed();
        }
        if (!empty($data['hasSignLanguage'])) {
            $program->addKeyword('langue des signes');
        }
        if (!empty($data['isLive'])) {
            $program->addKeyword('direct');
        }
        if (!empty($data['isRepetition'])) {
            $program->setPreviouslyShown();
        }
    }

    private function getCreditType(string $role): ?string
    {
        $role = mb_strtolower(trim($role));
        foreach (self::CREDIT_ROLES as $type => $roles) {
            if (in_array($role, $roles, true)) {
                return $type;
            }
        }

        return null;
    }
}
