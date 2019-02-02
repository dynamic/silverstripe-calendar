<?php

namespace Dynamic\Calendar\Tests;

use Carbon\Carbon;
use Dynamic\Calendar\Factory\DateFactory;
use SilverStripe\Dev\FunctionalTest;

/**
 * Class DateFactoryTest
 * @package Dynamic\Calendar\Tests
 */
class DateFactoryTest extends FunctionalTest
{
    /**
     *
     */
    public function testGetTomorrow()
    {
        $date = '2019-02-01 21:00:00';
        $tomorrow = Carbon::parse('2019-02-02 21:00:00');

        $dateFactory = DateFactory::singleton();

        $this->assertEquals($tomorrow, $dateFactory->getTomorrow($date));
    }

    /**
     *
     */
    public function testGetYesterday()
    {
        $date = '2019-02-01 21:00:00';
        $yesterday = Carbon::parse('2019-01-31 21:00:00');

        $dateFactory = DateFactory::singleton();

        $this->assertEquals($yesterday, $dateFactory->getYesterday($date));
    }
}
