<?php

namespace Dynamic\Calendar\Tests\Page;

use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Page\Calendar;
use SilverStripe\Dev\SapphireTest;

/**
 * Class CalendarTest
 * @package Dynamic\Calendar\Tests\Page
 */
class CalendarTest extends SapphireTest
{
    /**
     *
     */
    public function testControllerName()
    {
        $this->assertEquals(CalendarController::class, Calendar::singleton()->getControllerName());
    }
}
