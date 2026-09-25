<?php

namespace Dynamic\Calendar\Tests\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DB;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Model\List\ArrayList;

/**
 * Class CalendarControllerTest
 * @package Dynamic\Calendar\Tests\Controller
 */
class CalendarControllerTest extends FunctionalTest
{
    /**
     * @var string
     */
    protected static $fixture_file = '../Calendar.yml';

    /**
     * @var Calendar
     */
    protected $calendar;

    /**
     * @var CalendarController
     */
    protected $controller;

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->calendar = Calendar::create([
            'Title' => 'Test Calendar',
            'URLSegment' => 'test-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        $this->controller = CalendarController::create($this->calendar);
    }

    /**
     * Test controller construction
     */
    public function testControllerConstruction()
    {
        $this->assertInstanceOf(CalendarController::class, $this->controller);
        $this->assertEquals($this->calendar->ID, $this->controller->data()->ID);
    }

    /**
     * Test index action returns proper array structure
     */
    public function testIndexActionReturnsProperStructure()
    {
        $request = new HTTPRequest('GET', '/');
        $result = $this->controller->index($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Calendar', $result);
        $this->assertArrayHasKey('Events', $result);
        $this->assertArrayHasKey('CurrentFromDate', $result);
        $this->assertArrayHasKey('CurrentToDate', $result);
        $this->assertArrayHasKey('RecurringEventsCount', $result);
        $this->assertArrayHasKey('OneTimeEventsCount', $result);

        $this->assertEquals($this->calendar->ID, $result['Calendar']->ID);
        $this->assertInstanceOf(\SilverStripe\Model\List\PaginatedList::class, $result['Events']);
    }

    /**
     * Test events action returns proper array structure
     */
    public function testEventsActionReturnsProperStructure()
    {
        $request = new HTTPRequest('GET', '/events');
        $result = $this->controller->events($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Events', $result);
        $this->assertArrayHasKey('TotalEvents', $result);

        $this->assertInstanceOf(ArrayList::class, $result['Events']);
        $this->assertIsInt($result['TotalEvents']);
    }

    /**
     * Test date filtering in events action
     */
    public function testEventsActionWithDateFiltering()
    {
        // Create a test event
        $event = EventPage::create([
            'Title' => 'Test Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->format('Y-m-d'),
            'StartTime' => '14:00:00',
            'EndDate' => Carbon::tomorrow()->format('Y-m-d'),
            'EndTime' => '16:00:00',
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        // Request events with specific date range
        $fromDate = Carbon::today()->format('Y-m-d');
        $toDate = Carbon::today()->addWeek()->format('Y-m-d');

        $request = new HTTPRequest('GET', '/events', [
            'from' => $fromDate,
            'to' => $toDate,
        ]);

        $result = $this->controller->events($request);

        $this->assertEquals(1, $result['TotalEvents']);
        $this->assertEquals('Test Event', $result['Events']->first()->Title);
    }

    /**
     * Test date parsing with invalid format
     */
    public function testDateParsingWithInvalidFormat()
    {
        $request = new HTTPRequest('GET', '/events', [
            'from' => 'invalid-date',
            'to' => 'another-invalid-date',
        ]);

        $result = $this->controller->events($request);

        // Should still work with default dates
        $this->assertIsArray($result);
        $this->assertArrayHasKey('Events', $result);
        $this->assertArrayHasKey('TotalEvents', $result);
    }

    /**
     * Test index action with no events
     */
    public function testIndexActionWithNoEvents()
    {
        $request = new HTTPRequest('GET', '/');
        $result = $this->controller->index($request);

        $this->assertEquals(0, $result['Events']->getTotalItems());
        $this->assertEquals(0, $result['RecurringEventsCount']);
        $this->assertEquals(0, $result['OneTimeEventsCount']);
    }

    /**
     * Test contrast color calculation method
     */
    public function testGetContrastColor()
    {
        // Using reflection to test private method
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getContrastColor');
        $method->setAccessible(true);

        // Test with dark color (should return white)
        $darkColor = '#000000';
        $result = $method->invokeArgs($this->controller, [$darkColor]);
        $this->assertEquals('#FFFFFF', $result);

        // Test with light color (should return black)
        $lightColor = '#FFFFFF';
        $result = $method->invokeArgs($this->controller, [$lightColor]);
        $this->assertEquals('#000000', $result);

        // Test with color that has # prefix (method should handle it)
        $colorWithPrefix = '#334597';
        $result = $method->invokeArgs($this->controller, [$colorWithPrefix]);
        $this->assertContains($result, ['#000000', '#FFFFFF']);

        // Test with color that doesn't have # prefix
        $colorWithoutPrefix = '334597';
        $result = $method->invokeArgs($this->controller, [$colorWithoutPrefix]);
        $this->assertContains($result, ['#000000', '#FFFFFF']);
    }

    /**
     * Fetch the FullCalendar JSON feed for a date window.
     *
     * Each test uses its own window so two tests in the same process cannot share
     * a cache entry (generateEventsCacheKey() keys on calendar ID + window +
     * categories + filters, and a shared entry would serve a stale body).
     *
     * @param array<string,mixed> $vars extra GET vars merged over the window
     * @return array<int,array<string,mixed>> decoded FullCalendar events
     */
    private function fetchFeed(string $from, string $to, array $vars = []): array
    {
        $request = new HTTPRequest('GET', 'events', array_merge([
            'start' => $from,
            'end' => $to,
        ], $vars));
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        return json_decode($this->controller->events($request)->getBody(), true) ?? [];
    }

    /**
     * Create a timed event and return it.
     */
    private function createFeedEvent(string $title, string $startDate, ?string $startTime, int $allDay): EventPage
    {
        $event = EventPage::create([
            'Title' => $title,
            'ParentID' => $this->calendar->ID,
            'StartDate' => $startDate,
            'AllDay' => $allDay,
            'Recursion' => 'NONE',
        ]);
        if ($startTime !== null) {
            $event->StartTime = $startTime;
        }
        $event->write();
        $event->publishRecursive();

        return $event;
    }

    /**
     * Put a row into a divergent state directly in the database, bypassing
     * onBeforeWrite(), so the state the write-side guard now prevents is still
     * reachable by the rows this fix has to cope with (issue #150).
     *
     * Both the draft and Live tables are written: which one the feed reads depends on
     * the current reading mode, so leaving Live untouched would make the fixture's
     * meaning depend on that.
     */
    private function forceDivergentRow(int $id, int $allDay): void
    {
        DB::query(sprintf('UPDATE "EventPage" SET "AllDay" = %d WHERE "ID" = %d', $allDay, $id));

        if (DB::get_schema()->hasTable('EventPage_Live')) {
            DB::query(sprintf('UPDATE "EventPage_Live" SET "AllDay" = %d WHERE "ID" = %d', $allDay, $id));
        }
    }

    /**
     * Pick one event out of a decoded feed by its ID.
     *
     * Used instead of counting the feed, so the assertions stay valid if the shared
     * Calendar.yml fixture ever gains an event inside the window a test opens.
     *
     * Compares the raw string key: a recurring occurrence carries the string ID
     * "virtual_<id>_<date>", which would cast to 0 and never match an int comparison.
     *
     * @param array<int,array<string,mixed>> $feed
     * @return array<string,mixed>|null
     */
    private function findFeedEvent(array $feed, int $id): ?array
    {
        foreach ($feed as $item) {
            if ((string) $item['id'] === (string) $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * The serializer must read the AllDay column, not StartTime presence: a legacy
     * row flagged all-day but still carrying a StartTime must serialise allDay true
     * with a date-only start, because the filter returns it for ?allDay=1.
     */
    public function testAllDayIsSerialisedFromColumnNotStartTime(): void
    {
        $event = $this->createFeedEvent('Divergent Gala', '2027-03-04', '19:30:00', 0);
        $this->forceDivergentRow($event->ID, 1);

        $feed = $this->fetchFeed('2027-03-01', '2027-03-31');
        $serialised = $this->findFeedEvent($feed, $event->ID);

        $this->assertNotNull($serialised, 'The all-day row must appear in the feed');
        $this->assertTrue($serialised['allDay'], 'allDay must follow the AllDay column, not StartTime');
        $this->assertSame('2027-03-04', $serialised['start'], 'An all-day event must serialise a date-only start');
        // The write path derived EndTime 20:30:00 before the row was flagged all-day. The
        // far edge must go date-only too, or FullCalendar gets an all-day start with a
        // timed end. Asserted against the value as it is emitted today: the feed does not
        // make an all-day end exclusive the way the ICS export does, which issue #187
        // tracks. Update this alongside that fix, not before it.
        $this->assertSame(
            '2027-03-04',
            $serialised['end'],
            'An all-day event must serialise a date-only end (exclusive boundary: see #187)'
        );
    }

    /**
     * The other row of the issue's table: AllDay = 0 with no StartTime is the
     * default state of any event whose editor never entered a time. It must now
     * serialise allDay false - the value the ?allDay=0 filter matched it on.
     */
    public function testTimedEventWithoutStartTimeSerialisesAllDayFalse(): void
    {
        $event = $this->createFeedEvent('Untimed Kickoff', '2027-04-06', null, 0);

        $feed = $this->fetchFeed('2027-04-01', '2027-04-30');
        $serialised = $this->findFeedEvent($feed, $event->ID);

        $this->assertNotNull($serialised);
        $this->assertFalse($serialised['allDay']);
        $this->assertSame('2027-04-06', $serialised['start']);
        $this->assertSame('2027-04-06', $serialised['end'], 'No time was ever entered, so end stays date-only');
    }

    /**
     * Happy path must be unchanged: a timed event keeps allDay false and the
     * composite StartDate + 'T' + StartTime start.
     */
    public function testTimedEventKeepsCompositeStart(): void
    {
        $event = $this->createFeedEvent('Morning Standup', '2027-05-05', '09:15:00', 0);

        $feed = $this->fetchFeed('2027-05-01', '2027-05-31');
        $serialised = $this->findFeedEvent($feed, $event->ID);

        $this->assertNotNull($serialised);
        $this->assertFalse($serialised['allDay']);
        $this->assertSame('2027-05-05T09:15:00', $serialised['start']);
        // Pins the derived 1-hour EndTime reaching the feed as a composite, not just
        // sitting in the column.
        $this->assertSame('2027-05-05T10:15:00', $serialised['end']);
    }

    /**
     * The actual invariant: for the same record, the ?allDay filter verdict and the
     * serialised allDay value agree. Asserted on both filter values over a set that
     * contains a genuine all-day row, a timed row with a time, and a legacy row
     * forced into each divergent state.
     */
    public function testFilterAndSerialisedAllDayAgreeForSameRecord(): void
    {
        $this->createFeedEvent('Real All Day', '2027-06-03', null, 1);
        $this->createFeedEvent('Real Timed', '2027-06-04', '11:00:00', 0);
        $legacyAllDay = $this->createFeedEvent('Legacy All Day', '2027-06-05', '08:00:00', 0);
        $this->forceDivergentRow($legacyAllDay->ID, 1);
        $legacyTimed = $this->createFeedEvent('Legacy Timed', '2027-06-06', null, 1);
        $this->forceDivergentRow($legacyTimed->ID, 0);

        // Explicit list, not ['1' => true]: PHP casts numeric-string array keys to
        // ints, and the controller allowlists '0'/'1' with a strict in_array(), so an
        // int would silently mean "no filter" and this test would prove nothing.
        foreach ([['1', true], ['0', false]] as [$param, $expected]) {
            $feed = $this->fetchFeed('2027-06-01', '2027-06-30', ['allDay' => $param]);
            $this->assertNotEmpty($feed, sprintf('allDay=%s must match at least one event', $param));
            foreach ($feed as $item) {
                $this->assertSame(
                    $expected,
                    $item['allDay'],
                    sprintf(
                        '%s matched allDay=%s but serialised allDay=%s',
                        $item['title'],
                        $param,
                        var_export($item['allDay'], true)
                    )
                );
                if ($expected) {
                    $this->assertStringNotContainsString(
                        'T',
                        (string) $item['start'],
                        sprintf('%s is all-day, so start must stay date-only', $item['title'])
                    );
                }
            }
        }
    }

    /**
     * A filter that matches nothing returns an empty feed, not an error.
     */
    public function testAllDayFilterWithNoMatchesReturnsEmptyFeed(): void
    {
        $this->createFeedEvent('Only Timed', '2027-07-02', '14:00:00', 0);

        $this->assertSame([], $this->fetchFeed('2027-07-01', '2027-07-31', ['allDay' => '1']));
    }

    /**
     * Pins a documented limitation rather than a fix (issue #143): ModifiedAllDay is a NOT
     * NULL Boolean, so an exception cannot demote one occurrence of an all-day parent to
     * timed. A ModifiedStartTime entered on such an occurrence is dropped, and the README's
     * "Per-occurrence all-day overrides" section says so. If #143 makes the column
     * nullable this test must be updated alongside that README section, which is the point.
     */
    public function testModifiedStartTimeOnAllDayParentIsDroppedUntilIssue143(): void
    {
        // Must be recurring: only the recurring branch of getEventsFeed() loads
        // EventExceptions, so a Recursion NONE parent would never consult the override and
        // this test would pass without exercising anything.
        $parent = EventPage::create([
            'Title' => 'All Day Retreat',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2027-09-01',
            'AllDay' => 1,
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2027-09-29',
        ]);
        $parent->write();
        $parent->publishRecursive();

        $exception = EventException::create([
            'OriginalEventID' => $parent->ID,
            'InstanceDate' => '2027-09-08',
            'Action' => 'MODIFIED',
            // ModifiedAllDay deliberately left at its NOT NULL default of 0.
            'ModifiedStartTime' => '14:00:00',
        ]);
        $exception->write();

        $feed = $this->fetchFeed('2027-09-01', '2027-09-30');
        $overridden = array_values(array_filter(
            $feed,
            static fn(array $item): bool => str_contains((string) $item['url'], 'instance=2027-09-08')
        ));

        $this->assertCount(1, $overridden, 'The overridden occurrence must be in the feed');
        $this->assertTrue(
            $overridden[0]['allDay'],
            'An occurrence cannot be demoted to timed while ModifiedAllDay is NOT NULL (#143)'
        );
        $this->assertStringNotContainsString(
            '14:00:00',
            (string) $overridden[0]['start'],
            'The override time is dropped, as documented in README.md'
        );
        $this->assertSame('2027-09-08', $overridden[0]['start']);
    }

    /**
     * Per-occurrence overrides resolve through EventInstance::__get() to the parent's
     * AllDay when no override is set, and to ModifiedAllDay when one is. The
     * serializer must honour the resolved value, so an occurrence promoted to all-day
     * on a timed parent serialises allDay true with a date-only start.
     */
    public function testExceptionOverrideOfAllDayIsHonouredBySerializer(): void
    {
        $recurring = EventPage::create([
            'Title' => 'Weekly Workshop',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2027-08-03',
            'StartTime' => '10:00:00',
            'AllDay' => 0,
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2027-08-31',
        ]);
        $recurring->write();
        $recurring->publishRecursive();

        // Promote one occurrence to all-day. Parent stays timed.
        $exception = EventException::create([
            'OriginalEventID' => $recurring->ID,
            'InstanceDate' => '2027-08-17',
            'Action' => 'MODIFIED',
            'ModifiedAllDay' => true,
        ]);
        $exception->write();

        $feed = $this->fetchFeed('2027-08-01', '2027-08-31');

        $overridden = array_values(array_filter(
            $feed,
            static fn(array $item): bool => str_contains((string) $item['url'], 'instance=2027-08-17')
        ));
        $this->assertCount(1, $overridden, 'The promoted occurrence must appear in the feed');
        $this->assertTrue($overridden[0]['allDay'], 'ModifiedAllDay must reach the serialised payload');
        $this->assertSame('2027-08-17', $overridden[0]['start'], 'An overridden all-day occurrence stays date-only');
        // EventInstance resolves EndDate through __get() to a DBField, which json_encode()
        // used to emit as {}. The far edge of an occurrence must be a plain date string.
        $this->assertSame('2027-08-17', $overridden[0]['end'], 'Occurrence end must be a date string, not {}');

        // The parent's other occurrences remain timed - the override is per-occurrence.
        $timedSiblings = array_values(array_filter(
            $feed,
            static fn(array $item): bool => $item['allDay'] === false
                && str_contains((string) $item['url'], 'instance=2027-08-10')
        ));
        $this->assertNotEmpty($timedSiblings, 'Non-overridden occurrences of a timed parent stay timed');
        $this->assertSame(
            '2027-08-10T11:00:00',
            $timedSiblings[0]['end'],
            'A timed occurrence must serialise a composite end built from the derived EndTime'
        );
    }

    /**
     * A recurring occurrence's feed url is the parent event's absolute link plus the
     * instance parameter. EventInstance::AbsoluteLink() used to hand its own fully built
     * link to the parent's AbsoluteLink() as $action, which emitted the event's path twice
     * ("/calendar/my-event/calendar/my-event?instance=..."). Assert the exact url rather
     * than the substring the pre-existing assertions match on, which any doubled prefix
     * satisfies (issue #189).
     */
    public function testRecurringOccurrenceUrlDoesNotDuplicateTheEventPath(): void
    {
        $recurring = EventPage::create([
            'Title' => 'Friday Standup',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2027-10-01',
            'StartTime' => '09:30:00',
            'AllDay' => 0,
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2027-10-29',
        ]);
        $recurring->write();
        $recurring->publishRecursive();

        // A non-recurring row travels the same serializer line ('url' => AbsoluteLink())
        // through a different class, so pin that it is untouched by the occurrence fix.
        $single = $this->createFeedEvent('One Off Review', '2027-10-06', '13:00:00', 0);

        $feed = $this->fetchFeed('2027-10-01', '2027-10-31');

        $occurrences = array_values(array_filter(
            $feed,
            static fn(array $item): bool => str_contains((string) $item['url'], 'instance=2027-10-08')
        ));
        $this->assertCount(1, $occurrences, 'The occurrence must appear in the feed');

        $url = (string) $occurrences[0]['url'];
        $this->assertSame(
            $recurring->AbsoluteLink() . '?instance=2027-10-08',
            $url,
            'The occurrence url must be the parent absolute link plus the instance parameter'
        );
        // Name the reported symptom in its own assertion, so a regression reads as a doubled
        // path rather than as an opaque string mismatch.
        $this->assertSame(
            1,
            substr_count($url, '/' . ltrim((string) $recurring->RelativeLink(), '/')),
            'The event path must occur exactly once in the occurrence url'
        );

        $singleItems = array_values(array_filter(
            $feed,
            static fn(array $item): bool => (string) $item['id'] === (string) $single->ID
        ));
        $this->assertCount(1, $singleItems, 'The non-recurring event must appear in the feed');
        $this->assertSame(
            $single->AbsoluteLink(),
            (string) $singleItems[0]['url'],
            'A non-recurring event url is the page absolute link with no instance parameter'
        );
        $this->assertStringNotContainsString(
            'instance=',
            (string) $singleItems[0]['url'],
            'Only occurrences of a recurring event carry an instance parameter'
        );
    }

    /**
     * The $action argument of the occurrence link survives the fix, and an underlying link
     * that already carries a query string is extended with '&instance=' rather than a
     * second '?'.
     */
    public function testOccurrenceLinkAppendsInstanceParameterToAPassedAction(): void
    {
        $recurring = EventPage::create([
            'Title' => 'Monday Sync',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2027-11-01',
            'AllDay' => 1,
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2027-11-29',
        ]);
        $recurring->write();
        $recurring->publishRecursive();

        $instance = EventInstance::create($recurring, Carbon::parse('2027-11-08'));

        $this->assertSame(
            $recurring->Link('ics') . '?instance=2027-11-08',
            $instance->Link('ics'),
            'An action is appended to the event path before the instance parameter'
        );
        $this->assertSame(
            $recurring->AbsoluteLink('ics') . '?instance=2027-11-08',
            $instance->AbsoluteLink('ics'),
            'The absolute occurrence link keeps the action and adds the instance parameter once'
        );

        $queryLink = $instance->Link('print?format=1');
        $this->assertStringContainsString(
            '&instance=2027-11-08',
            $queryLink,
            'A link already carrying a query string gets &instance=, not a second ?'
        );
        $this->assertSame(
            1,
            substr_count($queryLink, '?'),
            'The occurrence link must contain exactly one query separator'
        );
    }
}
