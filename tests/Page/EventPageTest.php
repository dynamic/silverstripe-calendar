<?php

namespace Dynamic\Calendar\Tests\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use PHP_CodeSniffer\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Versioned\Versioned;

/**
 * Class EventPageTest
 * @package Dynamic\Calendar\Tests\Page
 */
class EventPageTest extends SapphireTest
{
    protected static $fixture_file = '../EventTest.yml';

    /**
     *
     */
    public function testGridFieldDate()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->assertEquals('Jul 4, 2019', $event->getGridFieldDate());
    }

    /**
     *
     */
    public function testHasRecurringEvents()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->assertEquals('No', $event->getHasRecurringEvents());
    }

    /**
     *
     */
    public function testLumberjackPagesForGridfield()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->assertFalse($event->getLumberjackPagesForGridfield()->exists());
    }

    /**
     *
     */
    public function testCMSFields()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->assertInstanceOf(FieldList::class, $event->getCMSFields());
    }
}
