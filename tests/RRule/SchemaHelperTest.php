<?php

namespace Dynamic\Calendar\Tests\RRule;

use Dynamic\Calendar\RRule\SchemaHelper;
use SilverStripe\Dev\SapphireTest;

/**
 * Class SchemaHelperTest
 * @package Dynamic\Calendar\Tests\RRule
 */
class SchemaHelperTest extends SapphireTest
{
    /**
     *
     */
    public function testIsValidPattern()
    {
        $pattern = [
            'foo',
            'bar',
            'baz',
        ];

        $this->assertTrue(SchemaHelper::create($pattern)->isValidPattern());
    }
}
