<?php

namespace Dynamic\Calendar\Tests\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\FunctionalTest;

/**
 * Regression tests for ISO-8601 date parameters on the events feed (issue #255,
 * ported from branch 2's #128).
 *
 * FullCalendar sends info.startStr / info.endStr, which are plain Y-m-d only in
 * dayGridMonth; timeGrid and list views send full ISO-8601 with a UTC offset.
 * Branch 3 rejected those, returned null from the date accessors, and so
 * silently disabled the date filter - every week/day/list request expanded the
 * entire event corpus. The pre-existing parameter tests only ever fed Y-m-d,
 * the one format the broken path handled.
 *
 * Everything is driven through the request-level accessors getFromDate() /
 * getToDate() and the events() action rather than the new parser directly, so
 * the same assertions reach the bug on pre-fix code instead of erroring on a
 * method that did not exist yet.
 */
class CalendarControllerIsoDateTest extends FunctionalTest
{
    /**
     * Declared explicitly rather than relied on by default: this class has no
     * $fixture_file, and FunctionalTest does not default $usesDatabase to true,
     * so run standalone it would error on every test with "Table ... doesn't
     * exist" (issue #211 records the same finding in this directory).
     *
     * @var bool
     */
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
            'Title' => 'ISO Date Test Calendar',
            'URLSegment' => 'iso-date-test-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        $this->controller = CalendarController::create($this->calendar);
    }

    /**
     * Call a protected date accessor on the controller for a request.
     *
     * @param array<string,mixed> $vars
     */
    private function callDateAccessor(string $method, array $vars): ?Carbon
    {
        $reflection = new \ReflectionMethod(CalendarController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->controller, new HTTPRequest('GET', 'events', $vars));
    }

    /**
     * Resolve a single 'start' parameter through the lower-bound accessor.
     */
    private function resolveStart(mixed $value): ?Carbon
    {
        return $this->callDateAccessor('getFromDate', ['start' => $value]);
    }

    /**
     * @param array<string,mixed> $vars
     * @return array<int,array<string,mixed>> decoded FullCalendar events
     */
    private function fetchEvents(array $vars): array
    {
        $request = new HTTPRequest('GET', 'events', $vars);
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        $body = $this->controller->events($request)->getBody();
        $this->assertIsString($body, 'A JSON feed body must come back, not an error page');

        return json_decode($body, true) ?? [];
    }

    /**
     * @param array<int,array<string,mixed>> $events
     * @return array<int,string>
     */
    private function titles(array $events): array
    {
        $titles = array_values(array_unique(array_column($events, 'title')));
        sort($titles);

        return $titles;
    }

    private function createEvent(string $title, string $startDate): EventPage
    {
        $event = EventPage::create([
            'Title' => $title,
            'ParentID' => $this->calendar->ID,
            'StartDate' => $startDate,
            'StartTime' => '09:00:00',
            'AllDay' => 0,
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        return $event;
    }

    public function testPlainDateIsAccepted(): void
    {
        $date = $this->resolveStart('2026-08-17');

        $this->assertNotNull($date);
        $this->assertEquals('2026-08-17', $date->format('Y-m-d'));
        // The '!' prefix must zero unspecified fields, not inherit wall-clock time.
        $this->assertEquals('00:00:00', $date->format('H:i:s'));
    }

    public function testIsoStringWithOffsetKeepsItsOwnCalendarDate(): void
    {
        $date = $this->resolveStart('2026-08-17T00:00:00-05:00');

        $this->assertNotNull($date, 'An ISO-8601 bound must not be dropped');
        $this->assertEquals('2026-08-17', $date->format('Y-m-d'));
        // Time of day is pinned even for ISO input, which is what lets the
        // events cache key describe the window with a Ymd component (#207).
        $this->assertEquals('00:00:00', $date->format('H:i:s'));
    }

    public function testIsoStringWithZuluIsAccepted(): void
    {
        $date = $this->resolveStart('2026-08-17T00:00:00Z');

        $this->assertNotNull($date);
        $this->assertEquals('2026-08-17', $date->format('Y-m-d'));
    }

    public function testIsoShapesWithPositiveOffsetFractionalSecondsAndNoOffsetAreAccepted(): void
    {
        foreach (['2026-08-17T06:15:00+02:00', '2026-08-17T06:15:30.500Z', '2026-08-17T06:15'] as $value) {
            $date = $this->resolveStart($value);

            $this->assertNotNull($date, $value . ' is a shape FullCalendar can send');
            $this->assertEquals('2026-08-17', $date->format('Y-m-d'), $value . ' must key on its own date');
        }
    }

    public function testRelativeStringsAreRejected(): void
    {
        // Bare Carbon::parse() accepts these; letting them through would hand
        // visitors control of the cache key.
        $this->assertNull($this->resolveStart('yesterday'));
        $this->assertNull($this->resolveStart('+1 week'));
        $this->assertNull($this->resolveStart('now'));
    }

    public function testGarbageIsRejected(): void
    {
        $this->assertNull($this->resolveStart('invalid-date'));
        $this->assertNull($this->resolveStart(''));
        $this->assertNull($this->resolveStart(str_repeat('2', 60)));
        // An array-typed param must not reach the string-typed parsing.
        $this->assertNull($this->resolveStart(['2026-08-17']));
    }

    /**
     * A parameter whose only flaw is a trailing newline used to fail the request
     * outright rather than discard to "no date filter": it passes Carbon's
     * shape test (whose pattern allows the newline) and then throws on the
     * trailing data, and nothing caught that before parseRequestDate(). The ISO
     * branch has to give the same answer, which is what the regex's D modifier
     * is for - PCRE's '$' alone would have matched the newline and filtered the
     * feed, so one stray byte would flip a request between filtered and
     * unfiltered depending on which date shape it was sent in.
     */
    public function testTrailingNewlineIsNotSilentlyAccepted(): void
    {
        $this->assertNull($this->resolveStart("2026-08-17\n"));
        $this->assertNull($this->resolveStart("2026-08-17T00:00:00Z\n"));

        // And the request path must answer, not fatal.
        $this->createEvent('In Window Event', '2025-06-18');
        $titles = $this->titles($this->fetchEvents(['start' => "2025-06-15\n"]));
        $this->assertContains(
            'In Window Event',
            $titles,
            'A malformed bound means the whole corpus, which is what it must return'
        );
    }

    public function testImpossibleDateRollsOverTheSameWayThePlainFormAlreadyDoes(): void
    {
        // Carbon's hasFormat() is a shape test, not a calendar one, so a day
        // that does not exist rolls over instead of failing. That is pre-existing
        // Y-m-d behaviour; the point here is that the ISO form must roll over to
        // the SAME bound - two shapes of one window may never key differently.
        $iso = $this->resolveStart('2026-02-30T00:00:00Z');
        $plain = $this->resolveStart('2026-02-30');

        $this->assertNotNull($plain);
        $this->assertNotNull($iso, 'The ISO form must not be dropped where the plain form parses');
        $this->assertEquals($plain->format('Y-m-d H:i:s'), $iso->format('Y-m-d H:i:s'));
    }

    public function testGetFromDateAcceptsIsoStart(): void
    {
        $date = $this->callDateAccessor('getFromDate', ['start' => '2026-08-01T00:00:00-05:00']);

        $this->assertNotNull($date);
        $this->assertEquals('2026-08-01', $date->format('Y-m-d'));
    }

    public function testGetToDatePinsIsoEndToEndOfDay(): void
    {
        $date = $this->callDateAccessor('getToDate', ['end' => '2026-09-01T00:00:00-05:00']);

        $this->assertNotNull($date, 'An ISO-8601 upper bound must not be dropped');
        $this->assertEquals('2026-09-01', $date->format('Y-m-d'));
        // Issue #207: the upper bound is compared inclusively, so it has to be
        // pushed to the end of its day whatever the input format was.
        $this->assertEquals('23:59:59', $date->format('H:i:s'));
    }

    public function testIsoAndPlainFormsResolveToTheSameWindow(): void
    {
        $iso = ['start' => '2026-08-01T00:00:00-05:00', 'end' => '2026-09-01T00:00:00-05:00'];
        $plain = ['start' => '2026-08-01', 'end' => '2026-09-01'];

        $fromPlain = $this->callDateAccessor('getFromDate', $plain);
        $fromIso = $this->callDateAccessor('getFromDate', $iso);
        $this->assertNotNull($fromPlain);
        $this->assertNotNull($fromIso);
        $this->assertEquals(
            $fromPlain->format('Y-m-d H:i:s'),
            $fromIso->format('Y-m-d H:i:s'),
            'Both date shapes must resolve to the same lower bound'
        );

        $toPlain = $this->callDateAccessor('getToDate', $plain);
        $toIso = $this->callDateAccessor('getToDate', $iso);
        $this->assertNotNull($toPlain);
        $this->assertNotNull($toIso);
        $this->assertEquals(
            $toPlain->format('Y-m-d H:i:s'),
            $toIso->format('Y-m-d H:i:s'),
            'Both date shapes must resolve to the same upper bound'
        );
    }

    public function testEventsActionAppliesIsoDateWindow(): void
    {
        $this->createEvent('In Window Event', '2025-06-18');
        $this->createEvent('Far Future Event', '2027-01-05');

        // Exactly what FullCalendar's timeGrid / list views emit.
        $titles = $this->titles($this->fetchEvents([
            'start' => '2025-06-15T00:00:00-05:00',
            'end' => '2025-06-22T00:00:00-05:00',
        ]));

        $this->assertContains('In Window Event', $titles);
        $this->assertNotContains(
            'Far Future Event',
            $titles,
            'An ISO-8601 start/end must apply the date filter, not silently disable it'
        );
    }

    public function testIsoAndPlainRequestsReturnTheSameEvents(): void
    {
        $this->createEvent('In Window Event', '2025-06-18');
        $this->createEvent('Far Future Event', '2027-01-05');

        $iso = $this->titles($this->fetchEvents([
            'start' => '2025-06-15T00:00:00-05:00',
            'end' => '2025-06-22T00:00:00-05:00',
        ]));
        $plain = $this->titles($this->fetchEvents([
            'start' => '2025-06-15',
            'end' => '2025-06-22',
        ]));

        $this->assertEquals(['In Window Event'], $iso);
        $this->assertEquals(
            $iso,
            $plain,
            'The two date shapes describe one window and must return one body'
        );
    }

    public function testIsoWindowWithNoEventsReturnsAnEmptyFeed(): void
    {
        $this->createEvent('In Window Event', '2025-06-18');

        $titles = $this->titles($this->fetchEvents([
            'start' => '2030-01-01T00:00:00Z',
            'end' => '2030-01-08T00:00:00Z',
        ]));

        $this->assertSame([], $titles, 'An empty window is an empty feed, not an error');
    }

    public function testRelativeStringLeavesTheFeedUnfilteredWithoutError(): void
    {
        $this->createEvent('In Window Event', '2025-06-18');
        $this->createEvent('Far Future Event', '2027-01-05');

        // 'yesterday' must not be parsed - it would make the cache key depend on
        // the wording of the request; it falls back to "no date filter".
        $titles = $this->titles($this->fetchEvents(['start' => 'yesterday']));

        $this->assertContains('In Window Event', $titles);
        $this->assertContains('Far Future Event', $titles);
    }

    public function testArrayTypedDateParamLeavesTheFeedUnfilteredWithoutTypeError(): void
    {
        $this->createEvent('In Window Event', '2025-06-18');
        $this->createEvent('Far Future Event', '2027-01-05');

        // ?start[]=…&end[]=… - an array where a string is expected. It must be
        // treated as "no date filter", not as an uncaught TypeError on the
        // string-typed parsing (and not one on the cache-key path either).
        $titles = $this->titles($this->fetchEvents([
            'start' => ['2025-06-15'],
            'end' => ['2025-06-22'],
        ]));

        $this->assertContains('In Window Event', $titles);
        $this->assertContains('Far Future Event', $titles);
    }
}
