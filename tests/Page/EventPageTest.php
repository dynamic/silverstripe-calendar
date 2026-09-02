<?php

namespace Dynamic\Calendar\Tests\Page;

use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Versioned\Versioned;

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
    public function testRecursiveEventCreation()
    {
        Config::modify()->set(EventPage::class, 'recursion', true);

        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        // Ensure event has proper Calendar parent for validation
        $calendar = $this->objFromFixture(Calendar::class, 'one');
        $calendar->write();
        $calendar->publishRecursive();

        $event->ParentID = $calendar->ID;
        $event->write();

        $tomorrow = strtotime('tomorrow');

        $event->StartDate = date('Y-m-d', $tomorrow);
        $event->Recursion = 'DAILY';
        $event->Interval = 2;
        $event->RecursionEndDate = date('Y-m-d', strtotime("+7 day", $tomorrow));
        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $event = EventPage::get()->byID($event->ID);

        $this->assertEquals(3, $event->allChildren()->count());

        $event->Interval = 1;
        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $this->assertEquals(7, $event->allChildren()->count());

        $event->Interval = 2;
        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $this->assertEquals(3, $event->allChildren()->count());


        Config::modify()->set(EventPage::class, 'recursion', false);
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
     * Test that ParentID dropdown field is added to CMS fields
     */
    public function testParentIDDropdownExists()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $fields = $event->getCMSFields();

        // Check that ParentID field exists
        $parentField = $fields->dataFieldByName('ParentID');
        $this->assertNotNull($parentField, 'ParentID field should exist in CMS fields');
        $this->assertInstanceOf(DropdownField::class, $parentField, 'ParentID should be a DropdownField');

        // Check field configuration
        $this->assertEquals('Calendar', $parentField->Title(), 'ParentID field should be titled "Calendar"');
        $this->assertEquals(
            'Select a Calendar...',
            $parentField->getEmptyString(),
            'ParentID field should have helpful empty string'
        );
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

    /**
     * getHasRecurringEvents() backs the CMS summary_fields listing and must describe
     * the recursion pattern without generating occurrences.
     */
    public function testGetHasRecurringEventsForNonRecurringEvent()
    {
        Config::modify()->set(EventPage::class, 'recursion', true);

        /** @var EventPage $event */
        $event = EventPage::create();
        $event->Recursion = 'NONE';

        $this->assertSame('Does not repeat', $event->getHasRecurringEvents());
    }

    /**
     *
     */
    public function testGetHasRecurringEventsForWeeklyEvent()
    {
        Config::modify()->set(EventPage::class, 'recursion', true);

        /** @var EventPage $event */
        $event = EventPage::create();
        $event->Recursion = 'WEEKLY';
        $event->Interval = 1;

        $this->assertSame('Weekly', $event->getHasRecurringEvents());
    }

    /**
     *
     */
    public function testGetHasRecurringEventsForWeeklyEventWithEndDate()
    {
        Config::modify()->set(EventPage::class, 'recursion', true);

        /** @var EventPage $event */
        $event = EventPage::create();
        $event->Recursion = 'WEEKLY';
        $event->Interval = 1;
        $event->RecursionEndDate = '2025-07-30';

        $this->assertSame('Weekly until Jul 30, 2025', $event->getHasRecurringEvents());
    }

    /**
     * An unrecognized Recursion value must resolve to the trait's default match
     * arm rather than throwing.
     */
    public function testGetHasRecurringEventsForUnknownRecursionPattern()
    {
        Config::modify()->set(EventPage::class, 'recursion', true);

        /** @var EventPage $event */
        $event = EventPage::create();
        $event->Recursion = 'FORTNIGHTLY';

        $this->assertSame('Unknown pattern', $event->getHasRecurringEvents());
    }
}
