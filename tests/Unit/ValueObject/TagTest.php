<?php

declare(strict_types=1);

namespace racacax\XmlTvTest\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use racacax\XmlTv\ValueObject\Tag;

class TagTest extends TestCase
{
    /**
     * Test creating a simple tag with text content
     */
    public function testCreateSimpleTagWithTextContent(): void
    {
        $tag = new Tag('title', 'Test Title');
        $xml = $tag->asXML();

        $this->assertStringContainsString('<title>Test Title</title>', $xml);
    }

    /**
     * Test creating a self-closing tag (null value)
     */
    public function testCreateSelfClosingTag(): void
    {
        $tag = new Tag('icon', null);
        $xml = $tag->asXML();

        $this->assertEquals("<icon/>\n", $xml);
    }

    /**
     * Test tag with attributes
     */
    public function testTagWithAttributes(): void
    {
        $tag = new Tag('title', 'Test', ['lang' => 'fr']);
        $xml = $tag->asXML();

        $this->assertStringContainsString('<title lang="fr">Test</title>', $xml);
    }

    /**
     * Test tag with multiple attributes
     */
    public function testTagWithMultipleAttributes(): void
    {
        $tag = new Tag('icon', null, [
            'src' => 'http://example.com/icon.png',
            'width' => '100',
            'height' => '200'
        ]);
        $xml = $tag->asXML();

        $this->assertStringContainsString('src="http://example.com/icon.png"', $xml);
        $this->assertStringContainsString('width="100"', $xml);
        $this->assertStringContainsString('height="200"', $xml);
    }

    /**
     * Test special characters are properly escaped in content
     */
    public function testSpecialCharactersEscapedInContent(): void
    {
        $tag = new Tag('title', 'Test & <Special> "Characters"');
        $xml = $tag->asXML();

        $this->assertStringContainsString('Test &amp; &lt;Special&gt;', $xml);
    }

    /**
     * Test special characters are properly escaped in attributes
     */
    public function testSpecialCharactersEscapedInAttributes(): void
    {
        $tag = new Tag('title', 'Test', ['attr' => 'Value & "Quote"']);
        $xml = $tag->asXML();

        $this->assertStringContainsString('attr="Value &amp; &quot;Quote&quot;"', $xml);
    }

    /**
     * Test adding children to a tag
     */
    public function testAddingChildrenToTag(): void
    {
        $parent = new Tag('credits');
        $parent->addChild(new Tag('director', 'Director Name'));
        $parent->addChild(new Tag('actor', 'Actor Name'));

        $xml = $parent->asXML();

        $this->assertStringContainsString('<credits>', $xml);
        $this->assertStringContainsString('<director>Director Name</director>', $xml);
        $this->assertStringContainsString('<actor>Actor Name</actor>', $xml);
        $this->assertStringContainsString('</credits>', $xml);
    }

    /**
     * Test children are sorted according to sortedChildren array
     */
    public function testChildrenAreSortedCorrectly(): void
    {
        $sortOrder = ['actor', 'director'];
        $parent = new Tag('credits', null, [], $sortOrder);

        // Add in reverse order
        $parent->addChild(new Tag('director', 'Director Name'));
        $parent->addChild(new Tag('actor', 'Actor 1'));
        $parent->addChild(new Tag('actor', 'Actor 2'));

        $xml = $parent->asXML();

        // Actors should appear before director due to sort order
        $actorPos = strpos($xml, '<actor>Actor 1</actor>');
        $directorPos = strpos($xml, '<director>Director Name</director>');

        $this->assertLessThan($directorPos, $actorPos);
    }

    /**
     * Test getChildren returns correct children
     */
    public function testGetChildrenReturnsCorrectChildren(): void
    {
        $parent = new Tag('credits');
        $parent->addChild(new Tag('director', 'Director 1'));
        $parent->addChild(new Tag('director', 'Director 2'));
        $parent->addChild(new Tag('actor', 'Actor 1'));

        $directors = $parent->getChildren('director');
        $actors = $parent->getChildren('actor');

        $this->assertCount(2, $directors);
        $this->assertCount(1, $actors);
    }

    /**
     * Test getChildren returns empty array for non-existent tag
     */
    public function testGetChildrenReturnsEmptyArrayForNonExistent(): void
    {
        $parent = new Tag('credits');
        $writers = $parent->getChildren('writer');

        $this->assertIsArray($writers);
        $this->assertEmpty($writers);
    }

    /**
     * Test setChild replaces existing child
     */
    public function testSetChildReplacesExisting(): void
    {
        $parent = new Tag('programme');
        $parent->addChild(new Tag('title', 'First Title'));
        $parent->addChild(new Tag('title', 'Second Title'));

        // Should have 2 titles
        $this->assertCount(2, $parent->getChildren('title'));

        // Set a new title (should replace all)
        $parent->setChild(new Tag('title', 'Replacement Title'));

        // Should have only 1 title now
        $this->assertCount(1, $parent->getChildren('title'));
    }

    /**
     * Test getAllChildren returns all children
     */
    public function testGetAllChildrenReturnsAllChildren(): void
    {
        $parent = new Tag('credits');
        $parent->addChild(new Tag('director', 'Director'));
        $parent->addChild(new Tag('actor', 'Actor 1'));
        $parent->addChild(new Tag('actor', 'Actor 2'));
        $parent->addChild(new Tag('writer', 'Writer'));

        $allChildren = $parent->getAllChildren();

        $this->assertCount(4, $allChildren);
    }

    /**
     * Test getAllChildren returns null for tag with string value
     */
    public function testGetAllChildrenReturnsNullForStringValue(): void
    {
        $tag = new Tag('title', 'Simple Text');
        $children = $tag->getAllChildren();

        $this->assertNull($children);
    }

    /**
     * Test nested tags
     */
    public function testNestedTags(): void
    {
        $rating = new Tag('rating', null, ['system' => 'CSA']);
        $rating->addChild(new Tag('value', '-10'));
        $rating->addChild(new Tag('icon', null, ['src' => 'csa_icon.png']));

        $xml = $rating->asXML();

        $this->assertStringContainsString('<rating system="CSA">', $xml);
        $this->assertStringContainsString('<value>-10</value>', $xml);
        $this->assertStringContainsString('<icon src="csa_icon.png"/>', $xml);
        $this->assertStringContainsString('</rating>', $xml);
    }

    /**
     * Test empty string value is treated as text content, not null
     */
    public function testEmptyStringValueIsTreatedAsText(): void
    {
        $tag = new Tag('title', '');
        $xml = $tag->asXML();

        // Empty string should create opening/closing tags, not self-closing
        $this->assertStringContainsString('<title></title>', $xml);
        $this->assertStringNotContainsString('<title/>', $xml);
    }

    /**
     * Test addAttribute method
     */
    public function testAddAttribute(): void
    {
        $tag = new Tag('title', 'Test');
        $tag->addAttribute('lang', 'en');
        $tag->addAttribute('type', 'primary');

        $xml = $tag->asXML();

        $this->assertStringContainsString('lang="en"', $xml);
        $this->assertStringContainsString('type="primary"', $xml);
    }

    /**
     * Test setValue method
     */
    public function testSetValue(): void
    {
        $tag = new Tag('title', 'Original');
        $tag->setValue('Modified');

        $xml = $tag->asXML();

        $this->assertStringContainsString('<title>Modified</title>', $xml);
        $this->assertStringNotContainsString('Original', $xml);
    }

    /**
     * Test getName method
     */
    public function testGetName(): void
    {
        $tag = new Tag('my-custom-tag', 'content');

        $this->assertEquals('my-custom-tag', $tag->getName());
    }

    /**
     * Test UTF-8 content is properly handled
     */
    public function testUtf8ContentHandled(): void
    {
        $tag = new Tag('title', 'Émission spéciale: Noël en français');
        $xml = $tag->asXML();

        $this->assertStringContainsString('Émission spéciale: Noël en français', $xml);
    }

    /**
     * Test very long content is handled
     */
    public function testLongContentHandled(): void
    {
        $longContent = str_repeat('Lorem ipsum dolor sit amet. ', 100);
        $tag = new Tag('desc', $longContent);
        $xml = $tag->asXML();

        $this->assertStringContainsString($longContent, $xml);
    }

    /**
     * Test multiple children of same type
     */
    public function testMultipleChildrenOfSameType(): void
    {
        $parent = new Tag('programme');
        $parent->addChild(new Tag('category', 'Drama', ['lang' => 'en']));
        $parent->addChild(new Tag('category', 'Crime', ['lang' => 'en']));
        $parent->addChild(new Tag('category', 'Thriller', ['lang' => 'en']));

        $xml = $parent->asXML();
        $categories = $parent->getChildren('category');

        $this->assertCount(3, $categories);
        $this->assertStringContainsString('<category lang="en">Drama</category>', $xml);
        $this->assertStringContainsString('<category lang="en">Crime</category>', $xml);
        $this->assertStringContainsString('<category lang="en">Thriller</category>', $xml);
    }
}
