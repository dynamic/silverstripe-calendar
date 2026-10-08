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
     * An 8-character value is written alpha-first by branch 2, so the RGB part is what
     * callers feeding CSS should receive (changed by this fix: it used to return the raw
     * 'FF0000FF', which CSS reads as opaque red)
     */
    public function testEightCharacterHexColor()
    {
        $category = new Category();
        $category->Color = '#FF0000FF';

        // Alpha byte dropped, RGB kept
        $this->assertEquals('0000ff', $category->getColorHex());
    }

    /**
     * Same for a bare 8-character value stored by ColorField's looser validation
     */
    public function testBareEightCharacterHexColor()
    {
        $category = new Category();
        $category->Color = 'FF334597';

        $this->assertEquals('334597', $category->getColorHex());
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

    /**
     * Regression test for #319: ColorField stores bare 6-digit hex, which used to fall
     * through to the default blue in both getColorPreview() and getValidatedColor()
     */
    public function testBareSixDigitHexIsReadBackAsPrefixedHex()
    {
        $category = new Category();
        $category->Color = 'e91e63';

        $this->assertSame('#e91e63', $category->getColorPreview());
        $this->assertSame('#e91e63', $category->getValidatedColor());
    }

    /**
     * Regression test for #319: upper case bare hex is normalised to lower case
     */
    public function testBareUpperCaseSixDigitHexIsLowercased()
    {
        $category = new Category();
        $category->Color = 'E91E63';

        $this->assertSame('#e91e63', $category->getColorPreview());
        $this->assertSame('#e91e63', $category->getValidatedColor());
    }

    /**
     * Regression test for #319: bare 8-digit hex is stored alpha-first by the colorpicker
     * and must not be handed to CSS in that order
     */
    public function testBareEightDigitHex()
    {
        $category = new Category();
        // AARRGGBB: opaque (ff) blue (0000ff)
        $category->Color = 'ff0000ff';

        $this->assertSame('#0000ff', $category->getColorPreview());
        $this->assertSame('#0000ff', $category->getValidatedColor());
    }

    /**
     * An alpha-first legacy value written by branch 2 renders its RGB part, not red
     */
    public function testLegacyPrefixedEightDigitHex()
    {
        $category = new Category();
        $category->Color = '#FF334597';

        $this->assertSame('#334597', $category->getColorPreview());
        $this->assertSame('#334597', $category->getValidatedColor());
    }

    /**
     * Regression test for #319: bare 3-digit hex is expanded the same way a #-prefixed one is
     */
    public function testBareThreeDigitHexIsExpanded()
    {
        $category = new Category();
        $category->Color = 'F00';

        $this->assertSame('#ff0000', $category->getColorPreview());
        $this->assertSame('#ff0000', $category->getValidatedColor());
    }

    /**
     * Legacy #-prefixed values written by branch 2 still render as before
     */
    public function testLegacyPrefixedHexStillRenders()
    {
        $category = new Category();
        $category->Color = '#FF0000';

        $this->assertSame('#ff0000', $category->getColorPreview());
        $this->assertSame('#ff0000', $category->getValidatedColor());
    }

    /**
     * Legacy palette names from ColorPaletteField still map to their hex values
     */
    public function testLegacyPaletteNameStillMaps()
    {
        $category = new Category();
        $category->Color = 'Blue';

        $this->assertSame('#334597', $category->getColorPreview());
        $this->assertSame('#334597', $category->getValidatedColor());
    }

    /**
     * No color at all keeps the default preview and a null validated color
     */
    public function testEmptyColorUsesDefaults()
    {
        $category = new Category();
        $category->Color = '';

        $this->assertSame('#334597', $category->getColorPreview());
        $this->assertNull($category->getValidatedColor());
    }

    /**
     * Non-hex, non-palette values still get the default preview and a null validated color
     */
    public function testInvalidColorStillFallsBack()
    {
        $category = new Category();
        $category->Color = 'zzzzzz';

        $this->assertSame('#334597', $category->getColorPreview());
        $this->assertNull($category->getValidatedColor());
    }

    /**
     * Four- and five-digit values are not a hex color in any supported format
     */
    public function testFourAndFiveDigitValuesAreRejected()
    {
        $category = new Category();
        $category->Color = 'abcd';

        $this->assertSame('#334597', $category->getColorPreview());
        $this->assertNull($category->getValidatedColor());

        $category->Color = '#abcde';

        $this->assertSame('#334597', $category->getColorPreview());
        $this->assertNull($category->getValidatedColor());
    }
}
