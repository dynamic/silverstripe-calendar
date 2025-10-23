<?php

namespace Dynamic\Calendar\Tests\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\SapphireTest;

/**
 * Tests for date range filtering in Calendar::getEventsFeed()
 */
class CalendarDateFilteringTest extends SapphireTest
{
    protected static $fixture_file = '../fixtures.yml';

    protected static $extra_dataobjects = [
        EventPage::class,
        Calendar::class,
    ];

    public function testGetEventsFeedFiltersEventsByDateRange()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        // Create events with various dates
        $eventInRange = EventPage::create([
            'Title' => 'Event In Range',
            'StartDate' => '2025-10-15',
            'Recursion' => 'NONE',
            'ParentID' => $calendar->ID,
        ]);
        $eventInRange->write();
        $eventInRange->publishRecursive();

        $eventBeforeRange = EventPage::create([
            'Title' => 'Event Before Range',
            'StartDate' => '2025-08-15',
            'Recursion' => 'NONE',
            'ParentID' => $calendar->ID,
        ]);
        $eventBeforeRange->write();
        $eventBeforeRange->publishRecursive();

        $eventAfterRange = EventPage::create([
            'Title' => 'Event After Range',
            'StartDate' => '2025-12-15',
            'Recursion' => 'NONE',
            'ParentID' => $calendar->ID,
        ]);
        $eventAfterRange->write();
        $eventAfterRange->publishRecursive();

        // Test filtering
        $fromDate = Carbon::parse('2025-10-01');
        $toDate = Carbon::parse('2025-10-31');

        $events = $calendar->getEventsFeed(null, null, $fromDate, $toDate);

        // Should only return the event within range
        $this->assertEquals(1, $events->count(), 'Should return only 1 event within date range');
        $this->assertEquals('Event In Range', $events->first()->Title);
    }

    public function testGetEventsFeedFiltersOutNullDates()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        // Create event with null date
        $eventWithNullDate = EventPage::create([
            'Title' => 'Event With Null Date',
            'StartDate' => null,
            'Recursion' => 'NONE',
            'ParentID' => $calendar->ID,
        ]);
        $eventWithNullDate->write();
        $eventWithNullDate->publishRecursive();

        // Create event with valid date
        $eventWithDate = EventPage::create([
            'Title' => 'Event With Date',
            'StartDate' => '2025-10-15',
            'Recursion' => 'NONE',
            'ParentID' => $calendar->ID,
        ]);
        $eventWithDate->write();
        $eventWithDate->publishRecursive();

        $fromDate = Carbon::parse('2025-10-01');
        $toDate = Carbon::parse('2025-10-31');

        $events = $calendar->getEventsFeed(null, null, $fromDate, $toDate);

        // Should not include event with null date
        $this->assertEquals(1, $events->count(), 'Should filter out events with null dates');
        $this->assertEquals('Event With Date', $events->first()->Title);
    }

    public function testGetEventsFeedWithoutDateRange()
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        // Create events with various dates
        $event1 = EventPage::create([
            'Title' => 'Event 1',
            'StartDate' => '2025-08-15',
            'Recursion' => 'NONE',
            'ParentID' => $calendar->ID,
        ]);
        $event1->write();
        $event1->publishRecursive();

        $event2 = EventPage::create([
            'Title' => 'Event 2',
            'StartDate' => '2025-10-15',
            'Recursion' => 'NONE',
            'ParentID' => $calendar->ID,
        ]);
        $event2->write();
        $event2->publishRecursive();

        // Without date filtering, recurring events may be generated
        // So we just test that events are returned
        $events = $calendar->getEventsFeed();

        $this->assertGreaterThanOrEqual(2, $events->count(), 'Should return events when no date range specified');
    }
}
