<?php

namespace Dynamic\Calendar\Tests\Model;

use Dynamic\Calendar\Model\Category;
use SilverStripe\Dev\SapphireTest;

/**
 * Test Category color handling and hex code support
 */
class CategoryColorTest extends SapphireTest
{
    /**
     * Test 6-character hex color handling
     */
    public function testSixCharacterHexColor()
    {
        $category = new Category();
        $category->Color = '#FF0000';

        $this->assertEquals('ff0000', $category->getColorHex());
        $this->assertEquals('#ff0000', $category->getColorPreview());
    }

    /**
     * Test 3-character hex color handling
     */
    public function testThreeCharacterHexColor()
    {
        $category = new Category();
        $category->Color = '#F00';

        // Should expand to 6 characters
        $this->assertEquals('ff0000', $category->getColorHex());
        $this->assertEquals('#ff0000', $category->getColorPreview());
    }

    /**
     * Test hex color without # prefix
     */
    public function testHexColorWithoutPrefix()
    {
        $category = new Category();
        $category->Color = 'FF0000';

        $this->assertEquals('ff0000', $category->getColorHex());
    }

    /**
     * Test 3-character hex color without # prefix
     */
    public function testThreeCharacterHexColorWithoutPrefix()
    {
        $category = new Category();
        $category->Color = 'F00';

        // Should expand to 6 characters
        $this->assertEquals('ff0000', $category->getColorHex());
    }

    /**
     * Test 8-character hex color with alpha (future support)
     */
    public function testEightCharacterHexColor()
    {
        $category = new Category();
        $category->Color = '#FF0000FF';

        // Should return as-is (8 characters for alpha support)
        $this->assertEquals('FF0000FF', $category->getColorHex());
    }

    /**
     * Test legacy color names
     */
    public function testLegacyColorNames()
    {
        $category = new Category();
        $category->Color = 'Blue';

        $this->assertEquals('334597', $category->getColorHex());
        $this->assertEquals('#334597', $category->getColorPreview());
    }

    /**
     * Test default color when no color is set
     */
    public function testDefaultColorWhenEmpty()
    {
        $category = new Category();
        $category->Color = '';

        $this->assertEquals('334597', $category->getColorHex());
        $this->assertEquals('#334597', $category->getColorPreview());
    }

    /**
     * Test invalid color falls back to default
     */
    public function testInvalidColorFallback()
    {
        $category = new Category();
        $category->Color = 'InvalidColor';

        $this->assertEquals('334597', $category->getColorHex());
        $this->assertEquals('#334597', $category->getColorPreview());
    }

    /**
     * Test case insensitive hex color validation
     */
    public function testCaseInsensitiveHexColors()
    {
        $category = new Category();

        // Test uppercase
        $category->Color = '#ABCDEF';
        $this->assertEquals('abcdef', $category->getColorHex());

        // Test lowercase
        $category->Color = '#abcdef';
        $this->assertEquals('abcdef', $category->getColorHex());

        // Test mixed case
        $category->Color = '#AbCdEf';
        $this->assertEquals('abcdef', $category->getColorHex());
    }
}
