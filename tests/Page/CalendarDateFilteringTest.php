<?php

namespace Dynamic\Calendar\Tests\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataList;

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

    /**
     * Regression test for dynamic/silverstripe-calendar#267.
     *
     * getEventsFeed() bounded its lower end with 'StartDate >= :fromDate', so a single
     * (non-recurring) event that started before the window and was still running when the
     * window opened was dropped - a feed window inside a week-long festival returned no
     * events at all. The lower bound is now the interval-overlap predicate on EndDate,
     * which returns the spanning event while an event that finished before the window,
     * and an event that has not started yet, stay out.
     */
    public function testGetEventsFeedIncludesEventOverlappingWindowStart(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $this->makeEvent($calendar, 'Festival Spanning Window Start', '2025-09-25', '2025-10-05');
        $this->makeEvent($calendar, 'Ends Exactly On Window Start', '2025-09-28', '2025-10-01');
        $this->makeEvent($calendar, 'Ended Before Window', '2025-08-01', '2025-08-20');
        $this->makeEvent($calendar, 'Starts After Window', '2025-12-15', '2025-12-20');

        $titles = $this->titles(
            $calendar->getEventsFeed(null, null, Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'))
        );

        $this->assertContains(
            'Festival Spanning Window Start',
            $titles,
            'An event that started before the window and has not ended must be returned'
        );
        $this->assertContains(
            'Ends Exactly On Window Start',
            $titles,
            'An EndDate equal to the window start still overlaps it'
        );
        $this->assertNotContains(
            'Ended Before Window',
            $titles,
            'An event that finished before the window start must stay out of the feed'
        );
        $this->assertNotContains(
            'Starts After Window',
            $titles,
            'The StartDate upper bound on toDate is unchanged'
        );
    }

    /**
     * The overlap predicate's "no EndDate" half, for rows written before
     * EventPage::onBeforeWrite() began deriving EndDate from StartDate. Such a row must
     * not be dropped from a window it starts inside, and, having no known end, is treated
     * as ending on its own StartDate - the same rule EventInstance applies.
     */
    public function testGetEventsFeedTreatsMissingEndDateAsEndedOnStartDate(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $beforeWindow = $this->makeEvent($calendar, 'Legacy Row Before Window', '2025-08-15', '2025-08-15');
        $inWindow = $this->makeEvent($calendar, 'Legacy Row In Window', '2025-10-10', '2025-10-10');

        $this->assertNotNull($beforeWindow->EndDate, 'onBeforeWrite() derives EndDate, so blank it directly');
        $this->clearEndDate($beforeWindow);
        $this->clearEndDate($inWindow);

        $titles = $this->titles(
            $calendar->getEventsFeed(null, null, Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'))
        );

        $this->assertContains(
            'Legacy Row In Window',
            $titles,
            'A row with no EndDate must not be dropped by the overlap predicate'
        );
        $this->assertNotContains(
            'Legacy Row Before Window',
            $titles,
            'With no EndDate the event is treated as ending on its StartDate'
        );
    }

    /**
     * Bad input: an EndDate that precedes its own StartDate is nonsensical rather than
     * fatal. It falls back to the StartDate rule - the event is still listed - instead of
     * silently disappearing from a window its StartDate sits inside.
     */
    public function testGetEventsFeedFallsBackToStartDateForInvertedEndDate(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $this->makeEvent($calendar, 'Inverted Date Range', '2025-10-15', '2025-09-01');

        $titles = $this->titles(
            $calendar->getEventsFeed(null, null, Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'))
        );

        $this->assertSame(
            ['Inverted Date Range'],
            $titles,
            'An EndDate before StartDate must fall back to the StartDate rule, not drop or fatal'
        );
    }

    /**
     * The documented filters keep applying to an event that only overlaps the window
     * rather than starting inside it: allDay and the category list are evaluated against
     * the spanning event exactly as they are against one that starts in the window.
     */
    public function testGetEventsFeedFiltersStillApplyToOverlappingEvents(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $category = Category::create(['Title' => 'Festival Category']);
        $category->write();

        $spanningAllDay = $this->makeEvent($calendar, 'Spanning All Day Event', '2025-09-25', '2025-10-05');
        $spanningAllDay->AllDay = true;
        $spanningAllDay->write();
        $spanningAllDay->publishRecursive();

        $spanningTimed = $this->makeEvent($calendar, 'Spanning Timed Event', '2025-09-26', '2025-10-04');
        $spanningTimed->StartTime = '10:00:00';
        $spanningTimed->write();
        $spanningTimed->Categories()->add($category);
        $spanningTimed->publishRecursive();

        $this->assertSame(
            ['Spanning All Day Event'],
            $this->titles(
                $calendar->getEventsFeed(
                    null,
                    null,
                    Carbon::parse('2025-10-01'),
                    Carbon::parse('2025-10-31'),
                    ['allDay' => '1']
                )
            ),
            'The allDay filter must still exclude the timed spanning event'
        );

        $this->assertSame(
            ['Spanning Timed Event'],
            $this->titles(
                $calendar->getEventsFeed(
                    null,
                    ArrayList::create([$category]),
                    Carbon::parse('2025-10-01'),
                    Carbon::parse('2025-10-31')
                )
            ),
            'The category filter must still select the spanning event that carries it'
        );

        // Empty result: a window nothing overlaps returns an empty feed rather than an
        // error or the neighbouring events.
        $this->assertSame(
            [],
            $this->titles(
                $calendar->getEventsFeed(null, null, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'))
            ),
            'A window no event overlaps must be empty'
        );
    }

    /**
     * Persist and publish a non-recurring event on the given calendar.
     *
     * @param Calendar $calendar
     * @param string $title
     * @param string|null $startDate
     * @param string|null $endDate
     * @return EventPage
     */
    private function makeEvent(Calendar $calendar, string $title, ?string $startDate, ?string $endDate): EventPage
    {
        $event = EventPage::create([
            'Title' => $title,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'Recursion' => 'NONE',
            'ParentID' => $calendar->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        return $event;
    }

    /**
     * Blank the EndDate column behind the ORM's back, the way a row written before
     * EventPage::onBeforeWrite() derived EndDate would look in the database.
     *
     * @param EventPage $event
     * @return void
     */
    private function clearEndDate(EventPage $event): void
    {
        DB::query(sprintf('UPDATE EventPage SET EndDate = NULL WHERE ID = %d', (int)$event->ID));
        $event->flushCache();
        // The raw UPDATE bypasses the ORM, so drop any cached result set that still
        // holds the derived EndDate for this class.
        DataList::reset(EventPage::class);
    }

    /**
     * Distinct event titles, sorted, so an assertion names the event that went missing
     * rather than reporting an opaque count difference.
     *
     * @param ArrayList $events
     * @return array<int,string>
     */
    private function titles($events): array
    {
        $titles = [];
        foreach ($events as $event) {
            $titles[(string)$event->Title] = true;
        }
        $result = array_keys($titles);
        sort($result);

        return $result;
    }
}
