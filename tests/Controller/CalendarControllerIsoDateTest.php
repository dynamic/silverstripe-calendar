<?php

namespace Dynamic\Calendar\Tests\Controller;

use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use ReflectionMethod;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

/**
 * Regression tests for ISO-8601 date parameter handling.
 *
 * FullCalendar sends info.startStr/info.endStr, which are plain Y-m-d only in
 * dayGridMonth; timeGrid and list views send full ISO-8601 with a UTC offset.
 * The controller previously rejected those and silently disabled the date
 * filter, returning the entire event corpus. The old parameter tests only ever
 * fed Y-m-d - the one format the broken path handled.
 */
class CalendarControllerIsoDateTest extends SapphireTest
{
    protected static $fixture_file = '../MultiCalendar.yml';

    protected function parseDate(?string $value)
    {
        $calendar = $this->objFromFixture(Calendar::class, 'primary');
        $controller = CalendarController::create($calendar);

        $method = new ReflectionMethod(CalendarController::class, 'parseRequestDate');
        $method->setAccessible(true);

        return $method->invoke($controller, $value);
    }

    public function testPlainDateIsAccepted(): void
    {
        $date = $this->parseDate('2026-08-17');
        $this->assertNotNull($date);
        $this->assertEquals('2026-08-17', $date->format('Y-m-d'));
        // The '!' prefix must zero unspecified fields, not inherit wall-clock time.
        $this->assertEquals('00:00:00', $date->format('H:i:s'));
    }

    public function testIsoStringWithOffsetKeepsItsOwnCalendarDate(): void
    {
        $date = $this->parseDate('2026-08-17T00:00:00-05:00');
        $this->assertNotNull($date);
        $this->assertEquals('2026-08-17', $date->format('Y-m-d'));
    }

    public function testIsoStringWithZuluIsAccepted(): void
    {
        $date = $this->parseDate('2026-08-17T00:00:00Z');
        $this->assertNotNull($date);
        $this->assertEquals('2026-08-17', $date->format('Y-m-d'));
    }

    public function testRelativeStringsAreRejected(): void
    {
        // Bare Carbon::parse() accepts these; letting them through would hand
        // visitors control of the cache key.
        $this->assertNull($this->parseDate('yesterday'));
        $this->assertNull($this->parseDate('+1 week'));
        $this->assertNull($this->parseDate('now'));
    }

    public function testGarbageIsRejected(): void
    {
        $this->assertNull($this->parseDate('invalid-date'));
        $this->assertNull($this->parseDate(''));
        $this->assertNull($this->parseDate(str_repeat('2', 60)));
    }

    public function testEventsActionAppliesIsoDateWindow(): void
    {
        $calendar = $this->objFromFixture(Calendar::class, 'primary');

        $outOfWindow = EventPage::create([
            'Title' => 'Far Future Event',
            'URLSegment' => 'far-future-event',
            'ParentID' => $calendar->ID,
            'StartDate' => '2027-01-05',
            'Recursion' => 'NONE',
        ]);
        $outOfWindow->write();

        $controller = CalendarController::create($calendar);

        // Exactly what FullCalendar's listWeek/timeGrid views emit.
        $request = new HTTPRequest('GET', 'events', [
            'start' => '2025-06-15T00:00:00-05:00',
            'end' => '2025-06-22T00:00:00-05:00',
        ]);
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        $response = $controller->events($request);
        $titles = array_column(json_decode($response->getBody(), true), 'title');

        $this->assertContains('Mine', $titles);
        $this->assertNotContains(
            'Far Future Event',
            $titles,
            'An ISO-8601 start/end must apply the date filter, not silently disable it'
        );
    }
}
