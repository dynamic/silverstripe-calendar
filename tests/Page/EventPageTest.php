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

    /**
     * With the module's recursion system disabled, eventRecurs() is false regardless
     * of the Recursion value, so the summary must report no recurrence rather than
     * describing a pattern that isn't actually active.
     */
    public function testGetHasRecurringEventsWhenRecursionConfigDisabled()
    {
        Config::modify()->set(EventPage::class, 'recursion', false);

        /** @var EventPage $event */
        $event = EventPage::create();
        $event->Recursion = 'WEEKLY';

        $this->assertSame('Does not repeat', $event->getHasRecurringEvents());
    }

    /**
     * getGridFieldDate() renders StartDate as "Month Day, Year" for the GridField
     * summary column (DBDate::ShortMonth/DayOfMonth/Year).
     */
    public function testGetGridFieldDate()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        // Fixture 'one' has StartDate 2025-06-18. Relaxed to a pattern (rather than
        // an exact string) so the ordinal suffix can't vary by ICU version.
        $this->assertMatchesRegularExpression('/^Jun 18(st|nd|rd|th), 2025$/', $event->getGridFieldDate());
    }

    /**
     * All-day events must show the localised "All Day" label in the GridField time
     * column, even though a StartTime is present in the fixture.
     */
    public function testGetGridFieldTimeForAllDayEvent()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'two');

        $this->assertTrue((bool)$event->AllDay, 'Fixture two should be an all-day event');
        $this->assertSame('All Day', $event->getGridFieldTime());
    }

    /**
     * A timed event must show the DBTime::Nice() formatted start time, not the
     * all-day label. The "9:00" fragment is checked rather than the full string so
     * the assertion holds across en variants of ICU's medium time format.
     */
    public function testGetGridFieldTimeForTimedEvent()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        // Fixture 'one' has StartTime 09:00:00, AllDay 0
        $time = $event->getGridFieldTime();

        $this->assertNotSame('All Day', $time);
        // SapphireTest pins en_US so Nice() renders "9:00:00 AM"; matched as a
        // fragment with a non-digit boundary so 19:00 can't false-match.
        $this->assertMatchesRegularExpression('/(^|[^0-9])9:00/', $time);
    }

    /**
     * A timed event with no StartTime falls into the empty-value branch of
     * DBTime::Nice() and must render an empty string rather than throwing.
     */
    public function testGetGridFieldTimeForEmptyStartTime()
    {
        /** @var EventPage $event */
        $event = EventPage::create();
        $event->AllDay = false;
        $event->StartTime = '';

        $this->assertSame('', $event->getGridFieldTime());
    }

    /**
     * onBeforeWrite() defaults EndTime to StartTime + 1 hour when a StartTime is
     * set but no EndTime, and mirrors StartDate into EndDate when EndDate is empty.
     */
    public function testOnBeforeWriteDefaultsEndTimeAndEndDate()
    {
        /** @var Calendar $calendar */
        $calendar = $this->objFromFixture(Calendar::class, 'one');

        /** @var EventPage $event */
        $event = EventPage::create();
        $event->ParentID = $calendar->ID;
        $event->Title = 'Defaults Test';
        $event->StartDate = '2025-06-18';
        $event->StartTime = '09:00:00';
        $event->write();

        $this->assertSame('10:00:00', $event->EndTime);

        $fresh = EventPage::get()->byID($event->ID);
        $this->assertSame('10:00:00', $fresh->EndTime);
        $this->assertSame('2025-06-18', $fresh->EndDate);
    }

    /**
     * An explicitly set EndTime must not be overwritten by the +1 hour default,
     * and an explicitly set EndDate must not be mirrored from StartDate.
     */
    public function testOnBeforeWriteKeepsExplicitEndTimeAndEndDate()
    {
        /** @var Calendar $calendar */
        $calendar = $this->objFromFixture(Calendar::class, 'one');

        /** @var EventPage $event */
        $event = EventPage::create();
        $event->ParentID = $calendar->ID;
        $event->Title = 'Explicit Time Test';
        $event->StartDate = '2025-06-18';
        $event->EndDate = '2025-06-19';
        $event->StartTime = '09:00:00';
        $event->EndTime = '09:45:00';
        $event->write();

        $fresh = EventPage::get()->byID($event->ID);
        $this->assertSame('09:45:00', $fresh->EndTime);
        $this->assertSame('2025-06-19', $fresh->EndDate);
    }

    /**
     * onBeforeWrite() stamps EventType with the record's own class. With no
     * StartTime/StartDate set, the EndTime/EndDate defaults must not fire, so
     * those columns stay empty.
     */
    public function testOnBeforeWriteSetsEventType()
    {
        /** @var Calendar $calendar */
        $calendar = $this->objFromFixture(Calendar::class, 'one');

        /** @var EventPage $event */
        $event = EventPage::create();
        $event->ParentID = $calendar->ID;
        $event->Title = 'Event Type Test';
        $event->write();

        $this->assertSame(EventPage::class, $event->EventType);

        $fresh = EventPage::get()->byID($event->ID);
        $this->assertNull($fresh->EndTime, 'EndTime must stay NULL without a StartTime');
        $this->assertNull($fresh->EndDate, 'EndDate must stay NULL without a StartDate');
    }
}
