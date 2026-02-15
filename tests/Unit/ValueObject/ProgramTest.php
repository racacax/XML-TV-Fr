<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\ValueObject\Program;
use DateTimeImmutable;
use DateTime;
use DateTimeZone;

class ProgramTest extends TestCase
{
    /**
     * Test creating a program with DateTime objects
     */
    public function testCreateProgramWithDateTime(): void
    {
        $start = new DateTimeImmutable('2026-02-14 20:00:00', new DateTimeZone('Europe/Paris'));
        $end = new DateTimeImmutable('2026-02-14 22:00:00', new DateTimeZone('Europe/Paris'));

        $program = new Program($start, $end);

        $this->assertInstanceOf(Program::class, $program);
        $this->assertEquals($start->format('YmdHis O'), $program->getStart()->format('YmdHis O'));
        $this->assertEquals($end->format('YmdHis O'), $program->getEnd()->format('YmdHis O'));
    }

    /**
     * Test creating a program with Unix timestamp
     */
    public function testCreateProgramWithTimestamp(): void
    {
        $start = strtotime('2026-02-14 20:00:00');
        $end = strtotime('2026-02-14 22:00:00');

        $program = Program::withTimestamp($start, $end);

        $this->assertInstanceOf(Program::class, $program);
    }

    /**
     * Test that start time must be before end time
     */
    public function testStartMustBeBeforeEnd(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Start date must be before end date');

        $start = new DateTimeImmutable('2026-02-14 22:00:00');
        $end = new DateTimeImmutable('2026-02-14 20:00:00');

        new Program($start, $end);
    }

    /**
     * Test program generates proper XML with start and stop attributes
     */
    public function testProgramGeneratesProperXmlStructure(): void
    {
        $start = new DateTimeImmutable('2026-02-14 20:00:00', new DateTimeZone('Europe/Paris'));
        $end = new DateTimeImmutable('2026-02-14 22:00:00', new DateTimeZone('Europe/Paris'));

        $program = new Program($start, $end);
        $program->addTitle('Test Program');
        $program->addAttribute('channel', 'test.channel');

        $xml = $program->asXML();

        $this->assertStringContainsString('<programme', $xml);
        $this->assertStringContainsString('start="20260214200000 +0100"', $xml);
        $this->assertStringContainsString('stop="20260214220000 +0100"', $xml);
        $this->assertStringContainsString('channel="test.channel"', $xml);
        $this->assertStringContainsString('</programme>', $xml);
    }

    /**
     * Test adding a title to program
     */
    public function testAddTitle(): void
    {
        $program = $this->createBasicProgram();
        $program->addTitle('My Awesome Show', 'en');

        $xml = $program->asXML();

        $this->assertStringContainsString('<title lang="en">My Awesome Show</title>', $xml);
    }

    /**
     * Test adding multiple titles in different languages
     */
    public function testAddMultipleTitles(): void
    {
        $program = $this->createBasicProgram();
        $program->addTitle('My Show', 'en');
        $program->addTitle('Mon Émission', 'fr');

        $xml = $program->asXML();

        $this->assertStringContainsString('<title lang="en">My Show</title>', $xml);
        $this->assertStringContainsString('<title lang="fr">Mon Émission</title>', $xml);
    }

    /**
     * Test adding null title is ignored
     */
    public function testAddNullTitleIsIgnored(): void
    {
        $program = $this->createBasicProgram();
        $program->addTitle(null);
        $program->addTitle('Valid Title');

        $titles = $program->getChildren('title');
        $this->assertCount(1, $titles);
    }

    /**
     * Test adding subtitle
     */
    public function testAddSubTitle(): void
    {
        $program = $this->createBasicProgram();
        $program->addSubTitle('Episode Title', 'en');

        $xml = $program->asXML();

        $this->assertStringContainsString('<sub-title lang="en">Episode Title</sub-title>', $xml);
    }

    /**
     * Test adding description
     */
    public function testAddDesc(): void
    {
        $program = $this->createBasicProgram();
        $program->addDesc('This is a long description of the program.', 'en');

        $xml = $program->asXML();

        $this->assertStringContainsString('<desc lang="en">This is a long description of the program.</desc>', $xml);
    }

    /**
     * Test adding credits (director, actor, etc.)
     */
    public function testAddCredits(): void
    {
        $program = $this->createBasicProgram();
        $program->addCredit('Steven Spielberg', 'director');
        $program->addCredit('Tom Hanks', 'actor');
        $program->addCredit('John Williams', 'composer');

        $xml = $program->asXML();

        $this->assertStringContainsString('<credits>', $xml);
        $this->assertStringContainsString('<director>Steven Spielberg</director>', $xml);
        $this->assertStringContainsString('<actor>Tom Hanks</actor>', $xml);
        $this->assertStringContainsString('<composer>John Williams</composer>', $xml);
        $this->assertStringContainsString('</credits>', $xml);
    }

    /**
     * Test credits are sorted according to DTD order
     */
    public function testCreditsSortedCorrectly(): void
    {
        $program = $this->createBasicProgram();

        // Add in random order
        $program->addCredit('Guest Person', 'guest');
        $program->addCredit('Director Name', 'director');
        $program->addCredit('Actor Name', 'actor');

        $xml = $program->asXML();

        // Director should come before actor, actor before guest
        $directorPos = strpos($xml, '<director>');
        $actorPos = strpos($xml, '<actor>');
        $guestPos = strpos($xml, '<guest>');

        $this->assertLessThan($actorPos, $directorPos);
        $this->assertLessThan($guestPos, $actorPos);
    }

    /**
     * Test invalid credit type defaults to guest
     */
    public function testInvalidCreditTypeDefaultsToGuest(): void
    {
        $program = $this->createBasicProgram();
        $program->addCredit('Unknown Person', 'invalid-type');

        $xml = $program->asXML();

        $this->assertStringContainsString('<guest>Unknown Person</guest>', $xml);
    }

    /**
     * Test adding category
     */
    public function testAddCategory(): void
    {
        $program = $this->createBasicProgram();
        $program->addCategory('Drama', 'en');
        $program->addCategory('Crime', 'en');

        $xml = $program->asXML();

        $this->assertStringContainsString('<category lang="en">Drama</category>', $xml);
        $this->assertStringContainsString('<category lang="en">Crime</category>', $xml);
    }

    /**
     * Test adding keyword
     */
    public function testAddKeyword(): void
    {
        $program = $this->createBasicProgram();
        $program->addKeyword('prison-drama', 'en');
        $program->addKeyword('based-on-novel', 'en');

        $xml = $program->asXML();

        $this->assertStringContainsString('<keyword lang="en">prison-drama</keyword>', $xml);
        $this->assertStringContainsString('<keyword lang="en">based-on-novel</keyword>', $xml);
    }

    /**
     * Test adding icon
     */
    public function testAddIcon(): void
    {
        $program = $this->createBasicProgram();
        $program->addIcon('http://example.com/icon.png', '100', '100');

        $xml = $program->asXML();

        $this->assertStringContainsString('<icon src="http://example.com/icon.png" width="100" height="100"/>', $xml);
    }

    /**
     * Test adding icon without dimensions
     */
    public function testAddIconWithoutDimensions(): void
    {
        $program = $this->createBasicProgram();
        $program->addIcon('http://example.com/icon.png');

        $xml = $program->asXML();

        $this->assertStringContainsString('<icon src="http://example.com/icon.png"/>', $xml);
    }

    /**
     * Test set date
     */
    public function testSetDate(): void
    {
        $program = $this->createBasicProgram();
        $program->setDate('20200515');

        $xml = $program->asXML();

        $this->assertStringContainsString('<date>20200515</date>', $xml);
    }

    /**
     * Test set country
     */
    public function testSetCountry(): void
    {
        $program = $this->createBasicProgram();
        $program->setCountry('FR');

        $xml = $program->asXML();

        $this->assertStringContainsString('<country>FR</country>', $xml);
    }

    /**
     * Test set country with language
     */
    public function testSetCountryWithLanguage(): void
    {
        $program = $this->createBasicProgram();
        $program->setCountry('France', 'en');

        $xml = $program->asXML();

        $this->assertStringContainsString('<country lang="en">France</country>', $xml);
    }

    /**
     * Test set episode number (xmltv_ns format)
     */
    public function testSetEpisodeNum(): void
    {
        $program = $this->createBasicProgram();
        $program->setEpisodeNum(2, 5); // Season 2, Episode 5

        $xml = $program->asXML();

        // xmltv_ns format is 0-indexed, so season 2 = 1, episode 5 = 4
        $this->assertStringContainsString('<episode-num system="xmltv_ns">1.4.</episode-num>', $xml);
    }

    /**
     * Test set episode number with season only
     */
    public function testSetEpisodeNumSeasonOnly(): void
    {
        $program = $this->createBasicProgram();
        $program->setEpisodeNum(1, null);

        $xml = $program->asXML();

        $this->assertStringContainsString('<episode-num system="xmltv_ns">0.0.</episode-num>', $xml);
    }

    /**
     * Test set episode number handles negative values
     */
    public function testSetEpisodeNumHandlesNegativeValues(): void
    {
        $program = $this->createBasicProgram();
        $program->setEpisodeNum(-1, -5);

        $xml = $program->asXML();

        // Negative values should be converted to 0
        $this->assertStringContainsString('<episode-num system="xmltv_ns">0.0.</episode-num>', $xml);
    }

    /**
     * Test set episode number with null values is ignored
     */
    public function testSetEpisodeNumWithNullValuesIsIgnored(): void
    {
        $program = $this->createBasicProgram();
        $program->setEpisodeNum(null, null);

        $xml = $program->asXML();

        $this->assertStringNotContainsString('<episode-num', $xml);
    }

    /**
     * Test set rating
     */
    public function testSetRating(): void
    {
        $program = $this->createBasicProgram();
        $program->setRating('-10', 'CSA');

        $xml = $program->asXML();

        $this->assertStringContainsString('<rating system="CSA">', $xml);
        $this->assertStringContainsString('<value>-10</value>', $xml);
    }

    /**
     * Test add star rating
     */
    public function testAddStarRating(): void
    {
        $program = $this->createBasicProgram();
        $program->addStarRating(4, 5);

        $xml = $program->asXML();

        $this->assertStringContainsString('<star-rating>', $xml);
        $this->assertStringContainsString('<value>4/5</value>', $xml);
    }

    /**
     * Test add star rating with system
     */
    public function testAddStarRatingWithSystem(): void
    {
        $program = $this->createBasicProgram();
        $program->addStarRating(3.5, 5, 'IMDB');

        $xml = $program->asXML();

        $this->assertStringContainsString('<star-rating system="IMDB">', $xml);
        $this->assertStringContainsString('<value>3.5/5</value>', $xml);
    }

    /**
     * Test add review
     */
    public function testAddReview(): void
    {
        $program = $this->createBasicProgram();
        $program->addReview('An excellent film!', 'The Times', 'John Doe');

        $xml = $program->asXML();

        $this->assertStringContainsString('<review', $xml);
        $this->assertStringContainsString('type="text"', $xml);
        $this->assertStringContainsString('source="The Times"', $xml);
        $this->assertStringContainsString('reviewer="John Doe"', $xml);
        $this->assertStringContainsString('An excellent film!', $xml);
    }

    /**
     * Test add subtitles
     */
    public function testAddSubtitles(): void
    {
        $program = $this->createBasicProgram();
        $program->addSubtitles('teletext', 'en');

        $xml = $program->asXML();

        $this->assertStringContainsString('<subtitles type="teletext" lang="en"/>', $xml);
    }

    /**
     * Test set previously shown
     */
    public function testSetPreviouslyShown(): void
    {
        $program = $this->createBasicProgram();
        $previousStart = new DateTimeImmutable('2025-01-15 20:00:00', new DateTimeZone('Europe/Paris'));
        $program->setPreviouslyShown($previousStart, 'test.channel');

        $xml = $program->asXML();

        $this->assertStringContainsString('<previously-shown', $xml);
        $this->assertStringContainsString('start="20250115200000 +0100"', $xml);
        $this->assertStringContainsString('channel="test.channel"', $xml);
    }

    /**
     * Test set previously shown without details
     */
    public function testSetPreviouslyShownWithoutDetails(): void
    {
        $program = $this->createBasicProgram();
        $program->setPreviouslyShown();

        $xml = $program->asXML();

        $this->assertStringContainsString('<previously-shown/>', $xml);
    }

    /**
     * Test set premiere
     */
    public function testSetPremiere(): void
    {
        $program = $this->createBasicProgram();
        $program->setPremiere('First showing on national TV', 'en');

        $xml = $program->asXML();

        $this->assertStringContainsString('<premiere lang="en">First showing on national TV</premiere>', $xml);
    }

    /**
     * Test set premiere without text
     */
    public function testSetPremiereWithoutText(): void
    {
        $program = $this->createBasicProgram();
        $program->setPremiere();

        $xml = $program->asXML();

        $this->assertStringContainsString('<premiere/>', $xml);
    }

    /**
     * Test set audio described
     */
    public function testSetAudioDescribed(): void
    {
        $program = $this->createBasicProgram();
        $program->setAudioDescribed();

        $xml = $program->asXML();

        $this->assertStringContainsString('<audio-described/>', $xml);
        $this->assertStringContainsString('<keyword>audio-description</keyword>', $xml);
    }

    /**
     * Test set audio described doesn't duplicate keyword
     */
    public function testSetAudioDescribedDoesNotDuplicateKeyword(): void
    {

        $program = $this->createBasicProgram();
        $program->addKeyword('audio-description');
        $program->setAudioDescribed();

        $xml = $program->asXML();

        // Should only appear once (but currently appears twice due to bug)
        $this->assertEquals(1, substr_count($xml, '<keyword>audio-description</keyword>'));
    }

    /**
     * Test all program elements are in correct DTD order
     */
    public function testProgramElementsInCorrectOrder(): void
    {
        $program = $this->createBasicProgram();
        $program->addAttribute('channel', 'test.channel');

        // Add elements in random order - NOTE: rating must be added last as it's complex
        $program->addTitle('Test Program');
        $program->addSubTitle('Episode 1');
        $program->addDesc('Description');
        $program->setDate('20200101');
        $program->addCategory('Drama');
        $program->setEpisodeNum(1, 1);
        $program->setRating('-10', 'CSA');

        $xml = $program->asXML();

        // Check DTD order: title, sub-title, desc, (credits), date, category, ..., rating
        $titlePos = strpos($xml, '<title');
        $subTitlePos = strpos($xml, '<sub-title');
        $descPos = strpos($xml, '<desc');
        $datePos = strpos($xml, '<date');
        $categoryPos = strpos($xml, '<category');
        $episodePos = strpos($xml, '<episode-num');
        $ratingPos = strpos($xml, '<rating');

        // Verify all elements are present
        $this->assertNotFalse($titlePos, 'title should be present in XML');
        $this->assertNotFalse($subTitlePos, 'sub-title should be present in XML');
        $this->assertNotFalse($descPos, 'desc should be present in XML');
        $this->assertNotFalse($datePos, 'date should be present in XML');
        $this->assertNotFalse($categoryPos, 'category should be present in XML');
        $this->assertNotFalse($episodePos, 'episode-num should be present in XML');
        $this->assertNotFalse($ratingPos, 'rating should be present in XML');

        // Check order
        $this->assertLessThan($subTitlePos, $titlePos, 'title should come before sub-title');
        $this->assertLessThan($descPos, $subTitlePos, 'sub-title should come before desc');
        $this->assertLessThan($datePos, $descPos, 'desc should come before date');
        $this->assertLessThan($categoryPos, $datePos, 'date should come before category');
        $this->assertLessThan($episodePos, $categoryPos, 'category should come before episode-num');
        $this->assertLessThan($ratingPos, $episodePos, 'episode-num should come before rating');
    }

    /**
     * Test program with all possible elements
     */
    public function testCompleteProgram(): void
    {
        $start = new DateTimeImmutable('2026-02-14 20:00:00', new DateTimeZone('Europe/Paris'));
        $end = new DateTimeImmutable('2026-02-14 22:00:00', new DateTimeZone('Europe/Paris'));

        $program = new Program($start, $end);
        $program->addAttribute('channel', 'test.channel');
        $program->addTitle('Complete Program', 'en');
        $program->addSubTitle('Episode 1', 'en');
        $program->addDesc('A complete program with all elements', 'en');
        $program->addCredit('Director Name', 'director');
        $program->addCredit('Actor Name', 'actor');
        $program->setDate('20200515');
        $program->addCategory('Drama', 'en');
        $program->addKeyword('prison-drama', 'en');
        $program->addIcon('http://example.com/icon.png');
        $program->setCountry('FR');
        $program->setEpisodeNum(1, 5);
        $program->setPreviouslyShown();
        $program->setPremiere();
        $program->addSubtitles('teletext', 'en');
        $program->setRating('-10', 'CSA');
        $program->addStarRating(4, 5);
        $program->addReview('Great show!', 'Source', 'Reviewer');
        $program->setAudioDescribed();

        $xml = $program->asXML();

        // Verify it's valid XML
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml);

        $this->assertTrue($loaded, 'Generated XML should be valid');
    }

    /**
     * Test timezone conversion to Europe/Paris
     */
    public function testTimezoneConversionToParis(): void
    {
        // Create in UTC
        $start = new DateTimeImmutable('2026-02-14 19:00:00', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable('2026-02-14 21:00:00', new DateTimeZone('UTC'));

        $program = new Program($start, $end);
        $xml = $program->asXML();

        // Should be converted to Paris time (+1 hour in winter)
        $this->assertStringContainsString('start="20260214200000 +0100"', $xml);
        $this->assertStringContainsString('stop="20260214220000 +0100"', $xml);
    }

    /**
     * Helper method to create a basic program
     */
    private function createBasicProgram(): Program
    {
        $start = new DateTimeImmutable('2026-02-14 20:00:00', new DateTimeZone('Europe/Paris'));
        $end = new DateTimeImmutable('2026-02-14 22:00:00', new DateTimeZone('Europe/Paris'));

        return new Program($start, $end);
    }
}
