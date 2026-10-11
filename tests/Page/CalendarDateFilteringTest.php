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
        RecurringFeedSpyEventPage::class,
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
     * Regression test for dynamic/silverstripe-calendar#299.
     *
     * The recurring branch of getEventsFeed() selected every recurring event in the
     * calendar and expanded each one, however narrow the requested window was. The query
     * now carries the window, so a single-day series that starts after the window ends, or
     * whose recurrence ended before the window opened, is never handed to the occurrence
     * generator at all - while a series that overlaps the window, and an open-ended one,
     * still are, and still appear in the feed.
     */
    public function testGetEventsFeedExcludesRecurringEventsOutsideTheWindow(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $startsAfterWindow = $this->makeRecurringEvent(
            $calendar,
            'Recurring Starts After Window',
            '2025-12-15',
            '2026-02-28'
        );
        $endedBeforeWindow = $this->makeRecurringEvent(
            $calendar,
            'Recurring Ended Before Window',
            '2025-06-01',
            '2025-09-20'
        );
        $inWindow = $this->makeRecurringEvent($calendar, 'Recurring In Window', '2025-10-10', '2025-11-30');
        $openEnded = $this->makeRecurringEvent($calendar, 'Recurring Open Ended', '2025-01-05', null);

        RecurringFeedSpyEventPage::resetExpansions();
        $titles = $this->titles(
            $calendar->getEventsFeed(null, null, Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'))
        );
        $expanded = RecurringFeedSpyEventPage::$expanded;

        $this->assertArrayNotHasKey(
            (int)$startsAfterWindow->ID,
            $expanded,
            'A series whose first occurrence follows the window end must not be expanded'
        );
        $this->assertArrayNotHasKey(
            (int)$endedBeforeWindow->ID,
            $expanded,
            'A series whose recurrence ended before the window start must not be expanded'
        );
        $this->assertArrayHasKey(
            (int)$inWindow->ID,
            $expanded,
            'A series that overlaps the window must still be expanded'
        );
        $this->assertArrayHasKey(
            (int)$openEnded->ID,
            $expanded,
            'An open-ended series has no known end, so it stays a candidate'
        );

        // The pruning is a query-level optimisation, not a change in what the feed returns.
        $this->assertContains('Recurring In Window', $titles);
        $this->assertContains('Recurring Open Ended', $titles);
        $this->assertNotContains('Recurring Starts After Window', $titles);
        $this->assertNotContains('Recurring Ended Before Window', $titles);
    }

    /**
     * The lower bound prunes the recurrence, not the individual occurrence, so the two
     * documented eventType filters keep working alongside it: 'one-time' expands nothing,
     * 'recurring' expands the in-window series and still prunes the out-of-window one.
     */
    public function testGetEventsFeedEventTypeFilterSurvivesRecurringPruning(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $this->makeEvent($calendar, 'Plain Event', '2025-10-15', '2025-10-15');
        $inWindow = $this->makeRecurringEvent($calendar, 'Recurring In Window', '2025-10-10', '2025-11-30');
        $endedBeforeWindow = $this->makeRecurringEvent(
            $calendar,
            'Recurring Ended Before Window',
            '2025-06-01',
            '2025-09-20'
        );

        RecurringFeedSpyEventPage::resetExpansions();
        $oneTime = $this->titles(
            $calendar->getEventsFeed(
                null,
                null,
                Carbon::parse('2025-10-01'),
                Carbon::parse('2025-10-31'),
                ['eventType' => 'one-time']
            )
        );
        $expandedForOneTime = RecurringFeedSpyEventPage::$expanded;

        $this->assertSame(['Plain Event'], $oneTime, 'The one-time filter must return only the plain event');
        $this->assertSame([], $expandedForOneTime, 'The one-time filter must expand no recurring event');

        RecurringFeedSpyEventPage::resetExpansions();
        $recurring = $this->titles(
            $calendar->getEventsFeed(
                null,
                null,
                Carbon::parse('2025-10-01'),
                Carbon::parse('2025-10-31'),
                ['eventType' => 'recurring']
            )
        );
        $expandedForRecurring = RecurringFeedSpyEventPage::$expanded;

        $this->assertNotContains('Plain Event', $recurring, 'The recurring filter must exclude the plain event');
        $this->assertContains('Recurring In Window', $recurring);
        $this->assertArrayHasKey((int)$inWindow->ID, $expandedForRecurring);
        $this->assertArrayNotHasKey(
            (int)$endedBeforeWindow->ID,
            $expandedForRecurring,
            'The window predicate must still prune a closed series that precedes the window'
        );
    }

    /**
     * Empty result: a window that no recurring series overlaps returns an empty feed
     * rather than an error, and expands nothing on the way.
     */
    public function testGetEventsFeedExpandsNothingForAWindowNoSeriesOverlaps(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $this->makeRecurringEvent($calendar, 'Closed Long Before', '2020-01-05', '2021-12-31');
        $this->makeRecurringEvent($calendar, 'Starts Long After', '2030-01-05', '2031-12-31');

        RecurringFeedSpyEventPage::resetExpansions();
        $titles = $this->titles(
            $calendar->getEventsFeed(null, null, Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'))
        );

        $this->assertSame([], $titles, 'A window no series overlaps must produce an empty feed');
        $this->assertSame([], RecurringFeedSpyEventPage::$expanded, 'An empty feed must cost no expansion at all');
    }

    /**
     * The default window is used just as faithfully when the caller passes no dates: the
     * feed then spans the current year through the configured recurring window years, so
     * a far-future and a long-closed series are pruned while this year's open-ended
     * series is expanded and listed.
     */
    public function testGetEventsFeedBoundsRecurringExpansionByTheDefaultWindow(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $farFuture = $this->makeRecurringEvent(
            $calendar,
            'Open Ended Far Future',
            Carbon::now()->copy()->addYears(10)->format('Y-m-d'),
            null
        );
        $longClosed = $this->makeRecurringEvent(
            $calendar,
            'Closed Long Before',
            Carbon::now()->copy()->subYears(6)->format('Y-m-d'),
            Carbon::now()->copy()->subYears(5)->format('Y-m-d')
        );
        $this->makeRecurringEvent(
            $calendar,
            'Open Ended This Year',
            Carbon::now()->copy()->startOfYear()->format('Y-m-d'),
            null
        );

        RecurringFeedSpyEventPage::resetExpansions();
        $titles = $this->titles($calendar->getEventsFeed());
        $expanded = RecurringFeedSpyEventPage::$expanded;

        $this->assertArrayNotHasKey(
            (int)$farFuture->ID,
            $expanded,
            'A series starting beyond the default window end must not be expanded'
        );
        $this->assertArrayNotHasKey(
            (int)$longClosed->ID,
            $expanded,
            'A series that closed before the default window start must not be expanded'
        );
        $this->assertContains(
            'Open Ended This Year',
            $titles,
            'A series inside the default window must still reach the feed'
        );
    }

    /**
     * The window bounds are inclusive on both sides, and the two boundary dates are where an
     * off-by-one would hide: a series whose RecursionEndDate is exactly the window start
     * still overlaps that first day, and a series starting exactly on the window end still
     * lands inside it. Flipping either comparison to exclusive prunes both, and nothing else
     * in this file's tests would notice.
     */
    public function testGetEventsFeedWindowBoundsAreInclusive(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $endsOnWindowStart = $this->makeRecurringEvent(
            $calendar,
            'Series Ending On Window Start',
            '2025-09-01',
            '2025-10-01'
        );
        $startsOnWindowEnd = $this->makeRecurringEvent(
            $calendar,
            'Series Starting On Window End',
            '2025-10-31',
            null
        );

        RecurringFeedSpyEventPage::resetExpansions();
        $calendar->getEventsFeed(null, null, Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'));
        $expanded = RecurringFeedSpyEventPage::$expanded;

        $this->assertArrayHasKey(
            (int)$endsOnWindowStart->ID,
            $expanded,
            'A recurrence ending exactly on the window start still overlaps the window'
        );
        $this->assertArrayHasKey(
            (int)$startsOnWindowEnd->ID,
            $expanded,
            'A series starting exactly on the window end is inside the window'
        );
    }

    /**
     * Guard for the EndDate > StartDate arm of the window predicate. A multi-day series
     * whose RecursionEndDate sits just before the window can still produce an occurrence
     * overlapping the window start, because occurrence generation widens the generation
     * window by the occurrence duration (dynamic/silverstripe-calendar#312). Pruning it on
     * the recurrence end alone would undo that, so it stays a candidate.
     *
     * Deliberately asserts the expansion, not the feed output: without #312 the generator
     * yields no such occurrence, so the feed says nothing either way about this fix.
     */
    public function testGetEventsFeedKeepsMultiDaySeriesEndingJustBeforeTheWindow(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $multiDay = RecurringFeedSpyEventPage::create([
            'Title' => 'Multi Day Series',
            'StartDate' => '2025-09-20',
            'EndDate' => '2025-09-23',
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-09-30',
            'ParentID' => $calendar->ID,
        ]);
        $multiDay->write();
        $multiDay->publishRecursive();

        $this->assertGreaterThan(
            $multiDay->StartDate,
            $multiDay->EndDate,
            'onBeforeWrite() must keep the multi-day EndDate for this test to mean anything'
        );

        RecurringFeedSpyEventPage::resetExpansions();
        $calendar->getEventsFeed(null, null, Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'));

        $this->assertArrayHasKey(
            (int)$multiDay->ID,
            RecurringFeedSpyEventPage::$expanded,
            'A multi-day series must stay a candidate even when its recurrence ends before the window'
        );
    }

    /**
     * Bad input: a recurring row the CMS can save but cannot date - a NULL StartDate. The
     * window predicate keeps it a candidate (SQL compares NULL to NULL, so a bare
     * 'StartDate <= ?' would silently drop such rows beyond what the issue asks for) and
     * expanding it stays non-fatal.
     */
    public function testGetEventsFeedToleratesRecurringEventWithNoStartDate(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'calendar1');
        $calendar->publishRecursive();

        $undated = $this->makeRecurringEvent($calendar, 'Recurring Without StartDate', null, null);

        // A window five years in the past rather than a fixed year: a NULL StartDate is read
        // as "now" by Carbon, so a hard-coded window would only stay empty until the calendar
        // caught up with it.
        $windowFrom = Carbon::now()->subYears(5)->startOfMonth();
        $windowTo = $windowFrom->copy()->addMonth();

        RecurringFeedSpyEventPage::resetExpansions();
        $titles = $this->titles($calendar->getEventsFeed(null, null, $windowFrom, $windowTo));

        $this->assertArrayHasKey(
            (int)$undated->ID,
            RecurringFeedSpyEventPage::$expanded,
            'An undated recurring row must not be pruned by the new window predicate'
        );
        $this->assertNotContains(
            'Recurring Without StartDate',
            $titles,
            'Keeping the row a candidate must not put an undated event into a dated feed'
        );
    }

    /**
     * Persist and publish a single-day recurring spy event on the given calendar.
     *
     * EndDate is pinned to StartDate so the fixtures are single-day: the multi-day case
     * is deliberately different and built by the test that covers it.
     *
     * @param Calendar $calendar
     * @param string $title
     * @param string|null $startDate
     * @param string|null $recursionEndDate Null for an open-ended series
     * @return RecurringFeedSpyEventPage
     */
    private function makeRecurringEvent(
        Calendar $calendar,
        string $title,
        ?string $startDate,
        ?string $recursionEndDate
    ): RecurringFeedSpyEventPage {
        $event = RecurringFeedSpyEventPage::create([
            'Title' => $title,
            'StartDate' => $startDate,
            'EndDate' => $startDate,
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => $recursionEndDate,
            'ParentID' => $calendar->ID,
        ]);
        $event->write();
        $event->publishRecursive();

        $this->assertSame(
            $startDate,
            $event->EndDate,
            sprintf('Test fixture "%s" must stay single-day for the window predicate to apply', $title)
        );

        return $event;
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
