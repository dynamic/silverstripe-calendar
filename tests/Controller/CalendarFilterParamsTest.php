<?php

namespace Dynamic\Calendar\Tests\Controller;

use Dynamic\Calendar\Cache\CalendarCacheVersion;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

/**
 * End-to-end coverage for the search / eventType / allDay feed filters
 * (issue #133). These params were always sent by the client and silently
 * dropped by the controller.
 */
class CalendarFilterParamsTest extends SapphireTest
{
    protected static $fixture_file = '../MultiCalendar.yml';

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        CalendarCacheVersion::flush();
    }

    /**
     * @param array<string,string> $vars extra GET vars
     * @return array<int,array<string,mixed>> decoded FullCalendar events
     */
    private function fetchEvents(Calendar $calendar, array $vars = []): array
    {
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', 'events', array_merge([
            'start' => '2025-06-01',
            'end' => '2025-07-31',
        ], $vars));
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        return json_decode($controller->events($request)->getBody(), true);
    }

    private function titles(array $events): array
    {
        return array_values(array_unique(array_column($events, 'title')));
    }

    public function testSearchFiltersRegularAndRecurringEvents(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $titles = $this->titles($this->fetchEvents($primary, ['search' => 'Recurring']));

        $this->assertContains('Mine Recurring', $titles);
        $this->assertNotContains('Mine', $titles, 'search must exclude non-matching regular events');
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $titles = $this->titles($this->fetchEvents($primary, ['search' => 'recurring']));

        $this->assertContains('Mine Recurring', $titles);
    }

    public function testEventTypeOneTimeExcludesRecurring(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $titles = $this->titles($this->fetchEvents($primary, ['eventType' => 'one-time']));

        $this->assertContains('Mine', $titles);
        $this->assertNotContains('Mine Recurring', $titles);
    }

    public function testEventTypeRecurringExcludesOneTime(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $titles = $this->titles($this->fetchEvents($primary, ['eventType' => 'recurring']));

        $this->assertContains('Mine Recurring', $titles);
        $this->assertNotContains('Mine', $titles);
    }

    public function testAllDayZeroIsARealFilter(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        // Make 'Mine' an all-day event; 'Mine Recurring' stays timed.
        $mine = $this->objFromFixture(EventPage::class, 'mine');
        $mine->AllDay = true;
        $mine->write();

        $allDay = $this->titles($this->fetchEvents($primary, ['allDay' => '1']));
        $timed = $this->titles($this->fetchEvents($primary, ['allDay' => '0']));

        $this->assertContains('Mine', $allDay);
        $this->assertNotContains('Mine Recurring', $allDay);

        // '0' previously fell through every truthiness check and meant
        // "no filter" - the actual bug this guards.
        $this->assertContains('Mine Recurring', $timed);
        $this->assertNotContains('Mine', $timed);
    }

    public function testExceptionOverriddenOccurrenceRespectsAllDayFilter(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $recurring = $this->objFromFixture(EventPage::class, 'mineRecurring');

        // Parent event is NOT all-day, but one occurrence is overridden to be.
        // A parent-level SQL AllDay filter would wrongly drop this instance -
        // this is why the recurring branch filters per-occurrence.
        $exception = EventException::create([
            'OriginalEventID' => $recurring->ID,
            'InstanceDate' => '2025-06-25',
            'Action' => 'MODIFIED',
            'ModifiedAllDay' => true,
        ]);
        $exception->write();

        $allDayEvents = $this->fetchEvents($primary, ['allDay' => '1']);

        $overridden = array_filter(
            $allDayEvents,
            static fn(array $event): bool => str_contains((string) $event['url'], 'instance=2025-06-25')
        );

        $this->assertNotEmpty(
            $overridden,
            'An occurrence overridden to all-day must appear under allDay=1 even though its parent event is timed'
        );
    }

    public function testFilteredAndUnfilteredResponsesDoNotShareCacheEntries(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $all = $this->titles($this->fetchEvents($primary));
        $filtered = $this->titles($this->fetchEvents($primary, ['eventType' => 'one-time']));
        $allAgain = $this->titles($this->fetchEvents($primary));

        $this->assertContains('Mine Recurring', $all);
        $this->assertNotContains('Mine Recurring', $filtered);
        // If the cache key ignored the filters, this request would be served
        // the filtered payload.
        $this->assertContains('Mine Recurring', $allAgain);
    }

    public function testLimitStillCorrectUnderEventTypeFilter(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $all = $primary->getEventsFeed(null, null, '2025-06-01', '2025-07-31', null, ['eventType' => 'recurring']);
        $limited = $primary->getEventsFeed(2, null, '2025-06-01', '2025-07-31', null, ['eventType' => 'recurring']);

        $this->assertCount(2, $limited);

        $key = static fn($event): string => $event->Title . '|' . $event->getInstanceDate()->format('Y-m-d');

        $this->assertSame(
            array_map($key, array_slice($all->toArray(), 0, 2)),
            array_map($key, $limited->toArray())
        );
    }

    public function testCombinedFiltersApplyTogether(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        // eventType narrows to recurring; search then narrows within that
        // branch. 'Mine Recurring' matches both; 'Mine' matches search only.
        $events = $this->fetchEvents($primary, [
            'eventType' => 'recurring',
            'search' => 'Mine',
        ]);
        $titles = $this->titles($events);

        $this->assertContains('Mine Recurring', $titles);
        $this->assertNotContains('Mine', $titles, 'eventType=recurring must exclude one-time events even when search matches');
    }

    public function testGarbageEventTypeIsIgnored(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $unfiltered = $this->titles($this->fetchEvents($primary));
        $garbage = $this->titles($this->fetchEvents($primary, ['eventType' => 'DROP TABLE']));
        $arrayParam = $this->titles($this->fetchEvents($primary, ['eventType' => ['recurring']]));

        $this->assertSame($unfiltered, $garbage, 'Unrecognized eventType must behave as no filter');
        $this->assertSame($unfiltered, $arrayParam, 'Array-typed eventType must behave as no filter');
    }

    public function testArrayTypedParamsDoNotError(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        // ?search[]=x previously hit a string cast; must not warn or filter.
        $events = $this->fetchEvents($primary, ['search' => ['x'], 'allDay' => ['1']]);

        $this->assertSame($this->titles($this->fetchEvents($primary)), $this->titles($events));
    }

    public function testSearchIsBounded(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        // Only the first 64 chars participate: a 64-char prefix of 'Mine'
        // padded out still matches nothing extra, and two strings identical
        // in their first 64 chars behave identically (cache-key safety).
        $long = str_repeat('z', 100);
        $eventsA = $this->fetchEvents($primary, ['search' => $long]);
        $eventsB = $this->fetchEvents($primary, ['search' => substr($long, 0, 64) . 'different-tail']);

        $this->assertSame($this->titles($eventsA), $this->titles($eventsB));
    }

    public function testIcalHonoursFilters(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $controller = CalendarController::create($primary);

        $request = new HTTPRequest('GET', 'ical', [
            'from' => '2025-06-01',
            'to' => '2025-07-31',
            'eventType' => 'one-time',
        ]);
        $body = $controller->ical($request)->getBody();

        $this->assertStringContainsString('Mine', $body);
        $this->assertStringNotContainsString(
            'Mine Recurring',
            $body,
            'ical() must honour the same filters as the JSON feed'
        );
    }
}
