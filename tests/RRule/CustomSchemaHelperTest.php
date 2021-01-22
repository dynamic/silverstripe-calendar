<?php

namespace Dynamic\Calendar\Tests\RRule;

use Dynamic\Calendar\RRule\CustomSchemaHelper;
use SilverStripe\Dev\SapphireTest;

/**
 * Class CustomSchemaHelperTest
 * @package Dynamic\Calendar\Tests\RRule
 */
class CustomSchemaHelperTest extends SapphireTest
{
    /**
     *
     */
    public function testIsValidPattern()
    {
        $pass = [
            'FREQ' => 'foo',
            'INTERVAL' => 'bar',
            'DTSTART' => 'baz',
            'UNTIL' => 'bing',
            'COUNT' => 'bang',
        ];

        $fail = [
            'FREQ' => 'foo',
        ];

        $this->assertTrue(CustomSchemaHelper::create($pass)->isValidPattern());
        $this->assertFalse(CustomSchemaHelper::create($fail)->isValidPattern());
    }
}
