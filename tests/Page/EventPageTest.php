<?php

namespace Dynamic\Calendar\Tests\Page;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;

/**
 * Class EventPageTest
 * @package Dynamic\Calendar\Tests\Page
 */
class EventPageTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = '../Calendar.yml';

    /**
     *
     */
    public function testGridFieldDate()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->assertEquals('Jul 4th, 2019', $event->getGridFieldDate());
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
