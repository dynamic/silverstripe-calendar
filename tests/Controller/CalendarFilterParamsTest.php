<?php

namespace Dynamic\Calendar\Tests\Controller;

use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\FunctionalTest;

/**
 * End-to-end coverage for the search / eventType / allDay feed filters
 * (issue #133). These params were always sent by the client and silently
 * dropped by the controller.
 */
class CalendarFilterParamsTest extends FunctionalTest
{
    protected $usesDatabase = true;

    /**
     * @var Calendar
     */
    protected $calendar;

    /**
     * @var CalendarController
     */
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calendar = Calendar::create([
            'Title' => 'Filter Test Calendar',
            'URLSegment' => 'filter-test-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        $this->controller = CalendarController::create($this->calendar);
    }

    /**
     * @param array<string,mixed> $vars extra GET vars
     * @return array<int,array<string,mixed>> decoded FullCalendar events
     */
    private function fetchEvents(array $vars = []): array
    {
        $request = new HTTPRequest('GET', 'events', array_merge([
            'start' => '2025-06-01',
            'end' => '2025-06-30',
        ], $vars));
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        return json_decode($this->controller->events($request)->getBody(), true);
    }

    private function titles(array $events): array
    {
        return array_values(array_unique(array_column($events, 'title')));
    }

    private function createOneTimeEvent(): EventPage
    {
        $event = EventPage::create([
            'Title' => 'Community Meeting',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2025-06-10',
            'StartTime' => '18:00:00',
            'AllDay' => 0,
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        return $event;
    }

    private function createRecurringEvent(): EventPage
    {
        $event = EventPage::create([
            'Title' => 'Weekly Standup',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2025-06-02',
            'StartTime' => '09:00:00',
            'AllDay' => 0,
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => '2025-06-30',
        ]);
        $event->write();
        $event->publishRecursive();

        return $event;
    }

    public function testSearchFiltersRegularAndRecurringEvents(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $titles = $this->titles($this->fetchEvents(['search' => 'Standup']));

        $this->assertContains('Weekly Standup', $titles);
        $this->assertNotContains('Community Meeting', $titles, 'search must exclude non-matching regular events');
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $this->createRecurringEvent();

        $titles = $this->titles($this->fetchEvents(['search' => 'standup']));

        $this->assertContains('Weekly Standup', $titles);
    }

    public function testSearchWithNoMatchesReturnsEmptyFeed(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $events = $this->fetchEvents(['search' => 'no-such-event-exists']);

        $this->assertSame([], $events);
    }

    public function testEventTypeOneTimeExcludesRecurring(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $titles = $this->titles($this->fetchEvents(['eventType' => 'one-time']));

        $this->assertContains('Community Meeting', $titles);
        $this->assertNotContains('Weekly Standup', $titles);
    }

    public function testEventTypeRecurringExcludesOneTime(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $titles = $this->titles($this->fetchEvents(['eventType' => 'recurring']));

        $this->assertContains('Weekly Standup', $titles);
        $this->assertNotContains('Community Meeting', $titles);
    }

    public function testAllDayZeroIsARealFilter(): void
    {
        $oneTime = $this->createOneTimeEvent();
        $this->createRecurringEvent();

        // Make 'Community Meeting' an all-day event; 'Weekly Standup' stays timed.
        $oneTime->AllDay = true;
        $oneTime->write();
        $oneTime->publishRecursive();

        $allDay = $this->titles($this->fetchEvents(['allDay' => '1']));
        $timed = $this->titles($this->fetchEvents(['allDay' => '0']));

        $this->assertContains('Community Meeting', $allDay);
        $this->assertNotContains('Weekly Standup', $allDay);

        // '0' previously fell through every truthiness check and meant
        // "no filter" - the actual bug this guards.
        $this->assertContains('Weekly Standup', $timed);
        $this->assertNotContains('Community Meeting', $timed);
    }

    public function testExceptionOverriddenOccurrenceRespectsAllDayFilter(): void
    {
        $recurring = $this->createRecurringEvent();

        // Parent event is NOT all-day, but one occurrence is overridden to be.
        // A parent-level SQL AllDay filter would wrongly drop this instance -
        // this is why the recurring branch filters per-occurrence.
        $exception = EventException::create([
            'OriginalEventID' => $recurring->ID,
            'InstanceDate' => '2025-06-16',
            'Action' => 'MODIFIED',
            'ModifiedAllDay' => true,
        ]);
        $exception->write();

        $allDayEvents = $this->fetchEvents(['allDay' => '1']);

        $overridden = array_filter(
            $allDayEvents,
            static fn(array $event): bool => str_contains((string) $event['url'], 'instance=2025-06-16')
        );

        $this->assertNotEmpty(
            $overridden,
            'An occurrence overridden to all-day must appear under allDay=1 even though its parent event is timed'
        );
    }

    public function testCombinedFiltersApplyTogether(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        // eventType narrows to recurring; search then narrows within that
        // branch. 'Weekly Standup' matches both; 'Community Meeting' matches
        // neither eventType nor would it match search alone once eventType
        // has already excluded it.
        $titles = $this->titles($this->fetchEvents([
            'eventType' => 'recurring',
            'search' => 'Weekly',
        ]));

        $this->assertContains('Weekly Standup', $titles);
        $this->assertNotContains('Community Meeting', $titles);
    }

    public function testGarbageEventTypeIsIgnored(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $unfiltered = $this->titles($this->fetchEvents());
        $garbage = $this->titles($this->fetchEvents(['eventType' => 'DROP TABLE']));
        $arrayParam = $this->titles($this->fetchEvents(['eventType' => ['recurring']]));

        $this->assertSame($unfiltered, $garbage, 'Unrecognized eventType must behave as no filter');
        $this->assertSame($unfiltered, $arrayParam, 'Array-typed eventType must behave as no filter');
    }

    public function testArrayTypedParamsDoNotError(): void
    {
        $this->createOneTimeEvent();

        // ?search[]=x previously hit a string cast; must not warn or crash.
        $events = $this->fetchEvents(['search' => ['x'], 'allDay' => ['1']]);

        $this->assertSame($this->titles($this->fetchEvents()), $this->titles($events));
    }

    public function testAllDayRejectsNonAllowlistedValues(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        // Anything outside the exact strings '0'/'1' must mean "no filter" -
        // previously allDay=banana coerced to an all-day-only filter.
        $unfiltered = $this->titles($this->fetchEvents());

        foreach (['banana', 'false', '0.0', '00', '-1'] as $garbage) {
            $this->assertSame(
                $unfiltered,
                $this->titles($this->fetchEvents(['allDay' => $garbage])),
                "allDay={$garbage} must behave as no filter"
            );
        }
    }

    public function testSearchIsBounded(): void
    {
        // Differential test: an event whose title is exactly 64 chars, then a
        // search of that title plus a garbage tail. WITH truncation the
        // search becomes exactly the title and matches; WITHOUT truncation a
        // 74-char needle cannot match a 64-char title, so the event
        // disappears and this test fails.
        $title64 = str_pad('Bounded Search Event', 64, 'x');
        $this->assertSame(64, strlen($title64));

        $event = EventPage::create([
            'Title' => $title64,
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2025-06-20',
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $events = $this->fetchEvents(['search' => $title64 . 'zzzzzzzzzz']);

        $this->assertContains(
            $title64,
            $this->titles($events),
            'Only the first 64 chars of search may participate in matching'
        );
    }

    public function testFilteredAndUnfilteredResponsesDoNotShareCacheEntries(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $all = $this->titles($this->fetchEvents());
        $filtered = $this->titles($this->fetchEvents(['eventType' => 'one-time']));
        $allAgain = $this->titles($this->fetchEvents());

        $this->assertContains('Weekly Standup', $all);
        $this->assertNotContains('Weekly Standup', $filtered);
        // If the cache key ignored the filters, this request would be served
        // the filtered payload.
        $this->assertContains('Weekly Standup', $allAgain);
    }

    public function testCategoryOrderDoesNotAffectCacheKey(): void
    {
        $categoryA = Category::create(['Title' => 'Category A ' . uniqid()]);
        $categoryA->write();
        $categoryB = Category::create(['Title' => 'Category B ' . uniqid()]);
        $categoryB->write();

        $event = $this->createOneTimeEvent();
        $event->Categories()->add($categoryA);
        $event->Categories()->add($categoryB);
        $event->write();
        $event->publishRecursive();

        $makeRequest = function (array $categoryIDs) {
            $request = new HTTPRequest('GET', 'events', [
                'start' => '2025-06-01',
                'end' => '2025-06-30',
                'categories' => $categoryIDs,
            ]);
            $request->addHeader('X-Requested-With', 'XMLHttpRequest');

            return $this->controller->events($request);
        };

        $first = $makeRequest([$categoryA->ID, $categoryB->ID]);
        $this->assertEquals('MISS', $first->getHeader('X-Calendar-Cache'));

        // Reversed order must hit the same cache entry.
        $reversed = $makeRequest([$categoryB->ID, $categoryA->ID]);
        $this->assertEquals(
            'HIT',
            $reversed->getHeader('X-Calendar-Cache'),
            '?categories[]=A&categories[]=B and its reverse must share a cache entry'
        );
    }

    /**
     * CalendarController::events() returns $this->getResponse() - the SAME
     * HTTPResponse instance on every call - so a caller holding two references
     * reads the later call's body from both. Snapshot the parts we assert on
     * immediately rather than retaining the object.
     *
     * @param array<string,mixed> $vars
     * @return array{cache: string|null, titles: array<int,string>}
     */
    private function fetchSnapshot(array $vars = []): array
    {
        $request = new HTTPRequest('GET', 'events', array_merge([
            'start' => '2025-06-01',
            'end' => '2025-06-30',
        ], $vars));
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        $response = $this->controller->events($request);

        return [
            'cache' => $response->getHeader('X-Calendar-Cache'),
            'titles' => $this->titles(json_decode($response->getBody(), true)),
        ];
    }

    /**
     * Guards the SECOND half of #133. Every other search test issues a single
     * request against a cold cache, so it always takes the MISS path and would
     * pass even with `search` deleted from generateEventsCacheKey() - at which
     * point ?search=Standup and ?search=Meeting share one entry for the whole
     * 30-minute TTL and serve each other's results.
     */
    public function testSearchDifferentiatesTheCacheKey(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $first = $this->fetchSnapshot(['search' => 'Standup']);
        $second = $this->fetchSnapshot(['search' => 'Meeting']);

        $this->assertEquals('MISS', $first['cache']);
        $this->assertEquals(
            'MISS',
            $second['cache'],
            'A different search term must not hit the previous search\'s cache entry'
        );

        $this->assertSame(['Weekly Standup'], $first['titles']);
        $this->assertSame(['Community Meeting'], $second['titles']);
    }

    /**
     * Sibling of the above for eventType. The two eventType tests are separate
     * methods with a cache clear between them, so a defect collapsing
     * 'one-time' and 'recurring' onto one key is invisible today.
     */
    public function testEventTypeDifferentiatesTheCacheKey(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $oneTime = $this->fetchSnapshot(['eventType' => 'one-time']);
        $recurring = $this->fetchSnapshot(['eventType' => 'recurring']);

        $this->assertEquals('MISS', $oneTime['cache']);
        $this->assertEquals(
            'MISS',
            $recurring['cache'],
            'eventType=recurring must not hit the eventType=one-time cache entry'
        );

        $this->assertSame(['Community Meeting'], $oneTime['titles']);
        $this->assertSame(['Weekly Standup'], $recurring['titles']);
    }

    /**
     * The regular branch ORs two PartialMatch predicates; the recurring branch
     * checks the two fields independently. Both must agree, so a needle
     * straddling the Title/Content join matches NEITHER event type.
     */
    public function testSearchMatchesContentAndDoesNotStraddleFields(): void
    {
        $oneTime = $this->createOneTimeEvent();
        $oneTime->Content = 'Budget review and planning';
        $oneTime->write();
        $oneTime->publishRecursive();

        $recurring = $this->createRecurringEvent();
        $recurring->Content = 'Budget review and planning';
        $recurring->write();
        $recurring->publishRecursive();

        // Content-only match works for both branches.
        $byContent = $this->titles($this->fetchEvents(['search' => 'Budget review']));
        $this->assertContains('Community Meeting', $byContent, 'regular branch must search Content');
        $this->assertContains('Weekly Standup', $byContent, 'recurring branch must search Content');

        // A needle spanning Title's TAIL and Content's HEAD. Under the old
        // concatenated haystack ('Weekly Standup' . ' ' . 'Budget review...')
        // 'Standup Budget' matched; per-field it matches neither field, which
        // is what the regular branch's filterAny already did. The needle must
        // genuinely straddle the join or this assertion is vacuous.
        $straddle = $this->titles($this->fetchEvents(['search' => 'Standup Budget']));
        $this->assertNotContains(
            'Weekly Standup',
            $straddle,
            'Recurring search must be per-field, not against Title . " " . Content'
        );

        // Same shape against the regular branch, which has always been per-field.
        $straddleRegular = $this->titles($this->fetchEvents(['search' => 'Meeting Budget']));
        $this->assertNotContains('Community Meeting', $straddleRegular);
    }

    /**
     * The SQL branch's :nocase modifiers are only exercised by a one-time
     * event; the recurring branch uses mb_stripos and would mask their removal
     * under a case-sensitive collation.
     */
    public function testSearchIsCaseInsensitiveOnTheSqlBranch(): void
    {
        $this->createOneTimeEvent();

        $titles = $this->titles($this->fetchEvents(['search' => 'community meeting']));
        $this->assertContains('Community Meeting', $titles);
    }

    public function testIcalHonoursFilters(): void
    {
        $this->createOneTimeEvent();
        $this->createRecurringEvent();

        $request = new HTTPRequest('GET', 'ical', [
            'from' => '2025-06-01',
            'to' => '2025-06-30',
            'eventType' => 'one-time',
        ]);
        $body = $this->controller->ical($request)->getBody();

        $this->assertStringContainsString('Community Meeting', $body);
        $this->assertStringNotContainsString(
            'Weekly Standup',
            $body,
            'ical() must honour the same filters as the JSON feed'
        );
    }
}
