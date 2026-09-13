<?php

namespace Dynamic\Calendar\Tests\Page;

use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Model\EventInstance;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Model\List\ArrayList;
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
     * getGridFieldDate() renders the StartDate through the DBDate field object, so the
     * grid column must show the day/month/year the record actually holds.
     */
    public function testGridFieldDateRendersStartDate()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->assertSame('Jun 18th, 2025', $event->getGridFieldDate());
    }

    /**
     * getGridFieldDate() must render an empty string when the event has no StartDate, matching the
     * empty-value convention getGridFieldTime() establishes, rather than a stray separator fragment.
     */
    public function testGridFieldDateWithoutStartDateRendersEmptyString()
    {
        $event = EventPage::create();

        $this->assertSame('', $event->getGridFieldDate());
    }

    /**
     * getGridFieldTime() renders a timed event's StartTime through the DBTime field object.
     */
    public function testGridFieldTimeRendersNiceTimeForTimedEvent()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $time = $event->getGridFieldTime();

        // ICU renders the AM/PM marker after a narrow no-break space (U+202F); normalise
        // only the space so the assertion still pins the exact clock time it renders.
        $this->assertSame('9:00:00 AM', str_replace("\u{202F}", ' ', $time));
    }

    /**
     * An all-day event must render the translated label rather than a clock time,
     * both for a fixture that carries a StartTime of its own and for a fresh record that has none.
     */
    public function testGridFieldTimeRendersAllDayLabel()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'two');

        $this->assertSame('All Day', $event->getGridFieldTime());

        $event = EventPage::create();
        $event->AllDay = 1;
        $this->assertSame('All Day', $event->getGridFieldTime());
    }

    /**
     * A timed event with no StartTime has nothing to format: the grid column must fall
     * back to an empty string rather than throwing or emitting "12:00:00 AM".
     */
    public function testGridFieldTimeIsEmptyWithoutStartTime()
    {
        $event = EventPage::create();
        $event->AllDay = 0;

        $this->assertSame('', $event->getGridFieldTime());
    }

    /**
     * A simple DB column is exposed through the magic property accessor as the raw scalar the
     * database holds, never as a DBField instance. onBeforeWrite() relies on this when it assigns
     * a formatted string to EndTime, and derives EndDate from StartDate when none was given.
     */
    public function testEndTimeIsRawStringAfterWriteDerivesIt()
    {
        /** @var Calendar $calendar */
        $calendar = $this->objFromFixture(Calendar::class, 'one');

        $event = EventPage::create();
        $event->Title = 'Derived end time';
        $event->ParentID = $calendar->ID;
        $event->StartDate = '2025-06-18';
        $event->StartTime = '09:00:00';
        $event->AllDay = 0;

        $this->assertNull($event->EndTime, 'EndTime must be unset before the write derives it');
        $this->assertNull($event->EndDate, 'EndDate must be unset before the write derives it');

        $event->write();

        $stored = EventPage::get()->byID($event->ID)->EndTime;
        $this->assertIsString($stored, 'The EndTime accessor must hand back the raw scalar');
        $this->assertSame('10:00:00', $stored);
        $this->assertSame('2025-06-18', $event->EndDate);

        // A second write must not keep stacking hours onto a derived end time.
        $event->write();
        $this->assertSame('10:00:00', EventPage::get()->byID($event->ID)->EndTime);
    }

    /**
     * canEdit()/canPublish()/canDelete()/canUnpublish() accept an explicit Member as well as the
     * null "whoever is current" default, and pass it straight to SiteTree: denied anonymously,
     * granted to a member holding ADMIN. isCopy() is hardcoded false by the Carbon system, so
     * this passthrough is the only branch these methods have.
     */
    public function testCanMethodsHonourNullAndExplicitMember()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->logOut();
        $this->assertFalse($event->canEdit(null));
        $this->assertFalse($event->canPublish(null));
        $this->assertFalse($event->canUnpublish(null));
        $this->assertFalse($event->canDelete(null));

        $member = $this->createMemberWithPermission('ADMIN');
        $this->assertTrue($event->canEdit($member));
        $this->assertTrue($event->canPublish($member));
        $this->assertTrue($event->canUnpublish($member));
        $this->assertTrue($event->canDelete($member));

        $this->logInAs($member);
        $this->assertTrue($event->canEdit(), 'canEdit() with the default null must see the logged-in member');
        $this->logOut();
    }

    /**
     * getPatternSource() feeds the recursion dropdown and must be a flat string => string
     * map with "Does not repeat" first.
     */
    public function testGetPatternSourceIsStringMapWithNoneFirst()
    {
        $event = EventPage::create();
        $source = $event->getPatternSource();

        $this->assertIsArray($source);
        $this->assertSame(['NONE', 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], array_keys($source));
        $this->assertSame('Does not repeat', $source['NONE']);
    }

    /**
     * allChildren() returns an ArrayList of virtual EventInstance records for the recurring
     * occurrences only: the original occurrence and any occurrence cancelled by a DELETED
     * EventException are both excluded.
     */
    public function testAllChildrenReturnsEventInstances()
    {
        Config::modify()->set(EventPage::class, 'recursion', true);

        $event = EventPage::get()->byID($this->objFromFixture(EventPage::class, 'one')->ID);

        $children = $event->allChildren();

        $this->assertInstanceOf(ArrayList::class, $children);
        // Weekly from 2025-06-18 to 2025-07-30 is 7 occurrences; the original (06-18) is
        // excluded by allChildren() itself and 06-25 by the DELETED EventException fixture.
        $dates = [];
        foreach ($children as $child) {
            $this->assertInstanceOf(EventInstance::class, $child);
            $dates[] = (string) $child->StartDate;
        }

        $this->assertSame(
            ['2025-07-02', '2025-07-09', '2025-07-16', '2025-07-23', '2025-07-30'],
            $dates,
            'Only recurring, non-deleted occurrences may be listed'
        );
    }

    /**
     * EventPage's property annotations document the scalar types each simple column yields, and
     * these assertions hold that accessor contract at runtime. They cannot detect an annotation
     * being reverted to DBDate/DBTime - phpstan runs at level 1 in CI, and docblocks are not
     * executable - so the value here is that a change to what the accessors actually return, in
     * this module or in the framework, fails loudly instead of silently invalidating the docs.
     */
    public function testSimpleColumnsExposeRawScalars()
    {
        $event = EventPage::get()->byID($this->objFromFixture(EventPage::class, 'one')->ID);

        $this->assertSame('2025-06-18', $event->StartDate);
        $this->assertSame('09:00:00', $event->StartTime);
        $this->assertSame('2025-06-18', $event->EndDate);
        $this->assertSame('10:00:00', $event->EndTime);
        $this->assertSame('2025-07-30', $event->RecursionEndDate);
        $this->assertSame('WEEKLY', $event->Recursion);
        $this->assertSame(EventPage::class, $event->EventType);
        // NOT NULL columns with DB defaults of 0, and never a DBField object. Compared loosely:
        // the PHP box these arrive in depends on the connector/driver (MySQLiConnector asks mysqlnd
        // for native ints and the framework warns that numerics arrive as strings without it),
        // while the module consumes them only numerically. The null arm of the documented type is
        // pinned by the unsaved-record assertions below, which are driver-independent.
        $this->assertNotNull($event->AllDay, 'A NOT NULL column must never read back null');
        $this->assertEquals(0, $event->AllDay);
        $this->assertEquals(0, $event->Interval);

        // Unset columns are null rather than an empty DBField.
        $fresh = EventPage::create();
        $this->assertNull($fresh->StartDate);
        $this->assertNull($fresh->StartTime);
        $this->assertNull($fresh->EndTime);
        $this->assertNull($fresh->Interval);
        $this->assertNull($fresh->FeaturedImageID);
        $this->assertSame('NONE', $fresh->Recursion);

        // Assigning a bool in PHP reads back as that bool until the write stores an int.
        $fresh->AllDay = true;
        $this->assertTrue($fresh->AllDay);
    }

    /**
     * Write-boundary guard (issue #150): the CMS only hideIf()s the time fields when
     * AllDay is on, so a record can be saved as all-day while still carrying a
     * StartTime. That divergent state is what makes the feed's filter and its
     * serialised allDay disagree, so onBeforeWrite() must null both times.
     */
    public function testAllDayWriteClearsStartTimeAndEndTime(): void
    {
        /** @var Calendar $calendar */
        $calendar = $this->objFromFixture(Calendar::class, 'one');

        $event = EventPage::create();
        $event->Title = 'Becomes all day';
        $event->ParentID = $calendar->ID;
        $event->StartDate = '2025-09-01';
        $event->StartTime = '18:30:00';
        $event->EndTime = '20:00:00';
        $event->AllDay = 0;
        $event->write();

        $this->assertSame(
            '18:30:00',
            EventPage::get()->byID($event->ID)->StartTime,
            'Precondition: timed before the flag flips'
        );

        // Flip to all-day and save, as the CMS would after hiding the time fields.
        $event->AllDay = 1;
        $event->write();

        $stored = EventPage::get()->byID($event->ID);
        $this->assertNull($stored->StartTime, 'An all-day write must clear a leftover StartTime');
        $this->assertNull($stored->EndTime, 'An all-day write must clear a leftover EndTime');
        $this->assertEquals(1, $stored->AllDay);
        $this->assertSame('2025-09-01', $stored->StartDate, 'The date must survive');
    }

    /**
     * The clear must happen before the 1-hour derivation, otherwise an all-day record
     * with a StartTime and no EndTime would gain an EndTime it must not have.
     */
    public function testAllDayWriteDoesNotDeriveEndTime(): void
    {
        /** @var Calendar $calendar */
        $calendar = $this->objFromFixture(Calendar::class, 'one');

        $event = EventPage::create();
        $event->Title = 'All day no end';
        $event->ParentID = $calendar->ID;
        $event->StartDate = '2025-09-02';
        $event->StartTime = '09:00:00';
        $event->AllDay = 1;
        $event->write();

        $stored = EventPage::get()->byID($event->ID);
        $this->assertNull($stored->StartTime);
        $this->assertNull($stored->EndTime, 'An all-day record must not gain a derived EndTime');
        $this->assertSame('2025-09-02', $stored->EndDate, 'EndDate derivation still applies');
    }

    /**
     * The guard must not reach past all-day records: a timed event keeps both times and
     * still derives its default 1-hour EndTime.
     */
    public function testTimedWriteKeepsTimesAndDerivation(): void
    {
        /** @var Calendar $calendar */
        $calendar = $this->objFromFixture(Calendar::class, 'one');

        $event = EventPage::create();
        $event->Title = 'Stays timed';
        $event->ParentID = $calendar->ID;
        $event->StartDate = '2025-09-03';
        $event->StartTime = '09:00:00';
        $event->AllDay = 0;
        $event->write();

        $stored = EventPage::get()->byID($event->ID);
        $this->assertSame('09:00:00', $stored->StartTime);
        $this->assertSame('10:00:00', $stored->EndTime, 'The 1-hour default must still apply to timed events');
    }
}
