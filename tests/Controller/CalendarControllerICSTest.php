<?php

namespace Dynamic\Calendar\Tests\Controller;

use BadMethodCallException;
use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Dev\FunctionalTest;

/**
 * Test class for ICS feed functionality in CalendarController
 * @package Dynamic\Calendar\Tests\Controller
 */
class CalendarControllerICSTest extends FunctionalTest
{
    /**
     * Declared explicitly rather than relied on by default: this class has no
     * $fixture_file, and FunctionalTest does not default $usesDatabase to
     * true, so run standalone (not after a fixture-bearing class in the same
     * process) it errors on every test with "Table 'db.SiteTree' doesn't
     * exist." The setUp() and test methods below write DataObjects directly,
     * so they need a schema of their own rather than one inherited from suite
     * ordering.
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

    /**
     * @var EventPage
     */
    protected $testEvent;

    /**
     * @var Category
     */
    protected $testCategory;

    /**
     * @var array
     */
    protected $additionalEvents = [];

    /**
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test calendar
        $this->calendar = Calendar::create([
            'Title' => 'Test Calendar for ICS',
            'URLSegment' => 'test-ics-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        // Create test category with unique title
        $this->testCategory = Category::create([
            'Title' => 'Test Category ICS ' . uniqid(),
            'Color' => 'FF0000',
        ]);
        $this->testCategory->write();

        // Create test event
        $this->testEvent = EventPage::create([
            'Title' => 'Test ICS Event',
            'Content' => 'This is a test event for ICS generation',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->format('Y-m-d'),
            'StartTime' => '14:00:00',
            'EndDate' => Carbon::tomorrow()->format('Y-m-d'),
            'EndTime' => '16:00:00',
            'Recursion' => 'NONE',
        ]);
        $this->testEvent->write();
        $this->testEvent->Categories()->add($this->testCategory);
        $this->testEvent->publishRecursive();

        $this->controller = CalendarController::create($this->calendar);
    }

    /**
     * Cleanup test environment
     */
    protected function tearDown(): void
    {
        // Clean up additional events first
        foreach ($this->additionalEvents as $event) {
            if ($event && $event->exists()) {
                try {
                    $event->delete();
                } catch (BadMethodCallException $e) {
                    // EventInstance objects don't have delete method, skip
                    if (strpos($e->getMessage(), 'EventInstance') === false) {
                        throw $e;
                    }
                }
            }
        }
        $this->additionalEvents = [];

        if ($this->testEvent && $this->testEvent->exists()) {
            $this->testEvent->delete();
        }
        if ($this->testCategory && $this->testCategory->exists()) {
            $this->testCategory->delete();
        }
        if ($this->calendar && $this->calendar->exists()) {
            $this->calendar->delete();
        }

        parent::tearDown();
    }

    /**
     * Test ICS action returns proper HTTP response
     */
    public function testICSActionReturnsProperResponse()
    {
        $request = new HTTPRequest('GET', '/ical');
        $response = $this->controller->ical($request);

        // Check response headers
        $this->assertEquals('text/calendar; charset=utf-8', $response->getHeader('Content-Type'));
        $this->assertEquals('attachment; filename="calendar.ics"', $response->getHeader('Content-Disposition'));
        $this->assertEquals('no-cache, must-revalidate', $response->getHeader('Cache-Control'));

        // Check that response body contains ICS content
        $icsContent = $response->getBody();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContainsString('BEGIN:VEVENT', $icsContent);
        $this->assertStringContainsString('END:VEVENT', $icsContent);
        $this->assertStringContainsString('END:VCALENDAR', $icsContent);
    }

    /**
     * Test that event data is properly transformed to ICS format
     */
    public function testEventDataTransformation()
    {
        $request = new HTTPRequest('GET', '/ical');
        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Check that event title is included
        $this->assertStringContainsString('SUMMARY:Test ICS Event', $icsContent);

        // Check that description is included (without HTML tags)
        $this->assertStringContainsString('DESCRIPTION:This is a test event for ICS generation', $icsContent);

        // Check that category is included
        $this->assertStringContainsString('CATEGORIES:' . $this->testCategory->Title, $icsContent);

        // Check that it has proper UID
        $this->assertStringContainsString('UID:', $icsContent);

        // Check that it has proper timestamps
        $this->assertStringContainsString('DTSTAMP:', $icsContent);
        $this->assertStringContainsString('DTSTART:', $icsContent);
        $this->assertStringContainsString('DTEND:', $icsContent);
    }

    /**
     * Test ICS action with date filtering
     */
    public function testICSActionWithDateFiltering()
    {
        // Create an event outside the filter range
        $futureEvent = EventPage::create([
            'Title' => 'Future Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->addYear()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'EndDate' => Carbon::now()->addYear()->format('Y-m-d'),
            'EndTime' => '11:00:00',
            'Recursion' => 'NONE',
        ]);
        $futureEvent->write();
        $futureEvent->publishRecursive();

        // Request ICS with specific date range that excludes future event
        $fromDate = Carbon::today()->format('Y-m-d');
        $toDate = Carbon::today()->addWeek()->format('Y-m-d');

        $request = new HTTPRequest('GET', '/ical', [
            'from' => $fromDate,
            'to' => $toDate,
        ]);

        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Should include the test event (within range)
        $this->assertStringContainsString('Test ICS Event', $icsContent);

        // Should not include the future event (outside range)
        $this->assertStringNotContainsString('Future Event', $icsContent);
    }

    /**
     * Test ICS action with category filtering
     */
    public function testICSActionWithCategoryFiltering()
    {
        // Create another category and event
        $otherCategory = Category::create([
            'Title' => 'Other Category ' . uniqid(),
            'Color' => '00FF00',
        ]);
        $otherCategory->write();

        $otherEvent = EventPage::create([
            'Title' => 'Other Category Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'EndDate' => Carbon::tomorrow()->format('Y-m-d'),
            'EndTime' => '11:00:00',
            'Recursion' => 'NONE',
        ]);
        $otherEvent->write();
        $otherEvent->Categories()->add($otherCategory);
        $otherEvent->publishRecursive();

        // Request ICS with specific category filter
        $request = new HTTPRequest('GET', '/ical', [
            'categories' => [$this->testCategory->ID],
        ]);

        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Should include the test event (matching category)
        $this->assertStringContainsString('Test ICS Event', $icsContent);

        // Should not include the other event (different category)
        $this->assertStringNotContainsString('Other Category Event', $icsContent);
    }

    /**
     * Test ICS action with all-day event
     */
    public function testICSActionWithAllDayEvent()
    {
        // Create an all-day event
        $allDayEvent = EventPage::create([
            'Title' => 'All Day Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->format('Y-m-d'),
            'EndDate' => Carbon::tomorrow()->format('Y-m-d'),
            'AllDay' => true,
            'Recursion' => 'NONE',
        ]);
        $allDayEvent->write();
        $allDayEvent->publishRecursive();

        $request = new HTTPRequest('GET', '/ical');
        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Should include the all-day event
        $this->assertStringContainsString('All Day Event', $icsContent);
    }

    /**
     * Test ICS action with recurring event
     */
    public function testICSActionWithRecurringEvent()
    {
        // Create multiple events to simulate recurring behavior (without actual recursion)
        $event1 = EventPage::create([
            'Title' => 'Weekly Event Instance 1',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::today()->format('Y-m-d'),
            'StartTime' => '09:00:00',
            'EndDate' => Carbon::today()->format('Y-m-d'),
            'EndTime' => '10:00:00',
            'Recursion' => 'NONE',
        ]);
        $event1->write();
        $event1->publishRecursive();

        $event2 = EventPage::create([
            'Title' => 'Weekly Event Instance 2',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'StartTime' => '09:00:00',
            'EndDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'EndTime' => '10:00:00',
            'Recursion' => 'NONE',
        ]);
        $event2->write();
        $event2->publishRecursive();

        // Track for cleanup
        $this->additionalEvents[] = $event1;
        $this->additionalEvents[] = $event2;

        $request = new HTTPRequest('GET', '/ical');
        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Should include both event instances
        $this->assertStringContainsString('Weekly Event Instance 1', $icsContent);
        $this->assertStringContainsString('Weekly Event Instance 2', $icsContent);

        // Should have multiple VEVENT entries for the instances
        $eventCount = substr_count($icsContent, 'BEGIN:VEVENT');
        $this->assertGreaterThan(2, $eventCount, 'Should have multiple event instances including our test events');
    }

    /**
     * Test ICS action with empty calendar
     */
    public function testICSActionWithEmptyCalendar()
    {
        // Remove all events
        foreach (EventPage::get()->filter('ParentID', $this->calendar->ID) as $event) {
            $event->delete();
        }

        $request = new HTTPRequest('GET', '/ical');
        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Should still return valid ICS structure
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContainsString('END:VCALENDAR', $icsContent);

        // Should not contain any events
        $this->assertStringNotContainsString('BEGIN:VEVENT', $icsContent);
    }

    /**
     * An event that raises while being transformed must be reported through the injected
     * logger at the error level - the module's logging convention - rather than through
     * the bare error_log() this issue reports, and the feed must still render without it.
     *
     * The double raises deterministically from a property read inside transformEventToICS(),
     * so the failure under test does not depend on any particular library's parse behaviour.
     *
     * This is the reachable call-site proof for #165: pre-fix the message went straight to
     * error_log() and the injected logger received nothing, so the once() expectation below is
     * what fails on the unfixed code. It also asserts the fallback sink stayed clean, so a
     * regression that logged through both sinks would not pass.
     */
    public function testUntransformableEventLogsErrorThroughInjectedLogger()
    {
        // Assert on the cause, not just the "Error transforming event ... to ICS" prefix:
        // the controller emits that prefix for an exception raised anywhere in the method,
        // so matching only the prefix would stay green if the transform started dying
        // upstream of the trigger this double is built to hit.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->logicalAnd(
                $this->stringContains('Error transforming event 123 to ICS'),
                $this->stringContains('simulated transform failure')
            ));
        $logger->expects($this->never())->method('warning');

        $broken = new class {
            public function hasMethod(string $method): bool
            {
                return false;
            }

            /**
             * @param string $property
             * @return mixed
             */
            public function __get(string $property)
            {
                switch ($property) {
                    case 'ID':
                        return 123;
                    case 'Title':
                        return 'Broken Event';
                    case 'Content':
                        throw new \RuntimeException('simulated transform failure');
                    default:
                        return null;
                }
            }
        };

        // transformEventToICS() reads $_SERVER['HTTP_HOST'] directly when building the UID,
        // and this test drives the generator by reflection rather than through a request, so
        // the key is set here to pin a deterministic host in the output.
        $hadHost = array_key_exists('HTTP_HOST', $_SERVER);
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'localhost';

        // Captured with ini_get() before being replaced, so the real prior value is restored
        // even when ini_set() signals the change by returning false rather than the old value.
        $errorLogFile = tempnam(sys_get_temp_dir(), 'ics-error-log-');
        $previousErrorLog = (string) ini_get('error_log');
        ini_set('error_log', $errorLogFile !== false ? $errorLogFile : '/dev/null');

        Injector::nest();

        try {
            Injector::inst()->registerService($logger, LoggerInterface::class);

            $generate = new \ReflectionMethod(CalendarController::class, 'generateICSContent');
            $ics = $generate->invoke($this->controller, ArrayList::create([$broken]));

            // The failing event is skipped, but the feed itself still renders.
            $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
            $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);

            // The injected logger must be the only sink. The annotation checks pin that the
            // trait's own fallback did not fire; the message check closes the wider hole,
            // because a call site that regressed to a bare error_log() of this message would
            // carry neither annotation and would otherwise slip through both assertions above.
            $logged = is_file($errorLogFile) ? (string) file_get_contents($errorLogFile) : '';
            $this->assertStringNotContainsString('logging failed', $logged);
            $this->assertStringNotContainsString('logger service unavailable', $logged);
            $this->assertStringNotContainsString('Error transforming event 123 to ICS', $logged);
        } finally {
            Injector::unnest();
            ini_set('error_log', $previousErrorLog);
            if ($hadHost) {
                $_SERVER['HTTP_HOST'] = $previousHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
            if ($errorLogFile !== false && is_file($errorLogFile)) {
                unlink($errorLogFile);
            }
        }
    }

    /**
     * When HTTP_HOST is absent the UID must fall back to 'calendar.local' instead of
     * ending in a bare '@'. This regression was tracked as
     * dynamic/silverstripe-calendar#185: concatenation binds tighter than ?? so the
     * fallback literal was never reachable.
     *
     * The double has no getInstanceDate() method so the ID portion is just the integer ID
     * with no instance-date suffix, making the assertion on the UID straightforward.
     */
    public function testICSUidFallsBackToCalendarLocalWhenHostUnset()
    {
        // Save and restore HTTP_HOST in a finally block
        $hadHost = array_key_exists('HTTP_HOST', $_SERVER);
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;

        $broken = new class {
            public function hasMethod(string $method): bool
            {
                return false;
            }

            /**
             * @param string $property
             * @return mixed
             */
            public function __get(string $property)
            {
                switch ($property) {
                    case 'ID':
                        return 42;
                    case 'Title':
                        return 'X';
                    case 'Content':
                    case 'Location':
                    case 'StartDate':
                    case 'EndDate':
                    case 'StartTime':
                    case 'EndTime':
                    case 'AllDay':
                        return null;
                    default:
                        return null;
                }
            }

            public function Categories()
            {
                return new ArrayList();
            }

            public function AbsoluteLink()
            {
                return '';
            }
        };

        try {
            // Remove HTTP_HOST to exercise the fallback
            unset($_SERVER['HTTP_HOST']);

            $generate = new \ReflectionMethod(CalendarController::class, 'generateICSContent');
            $ics = $generate->invoke($this->controller, ArrayList::create([$broken]));

            // The UID must contain the fallback host, not end in a bare '@'
            $this->assertStringContainsString('UID:42@calendar.local', $ics);
            $this->assertStringNotContainsString("UID:42@\r\n", $ics);
        } finally {
            if ($hadHost) {
                $_SERVER['HTTP_HOST'] = $previousHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
        }
    }

    /**
     * When HTTP_HOST is present the UID must use it verbatim - the happy path must
     * not be affected by the #185 fix.
     */
    public function testICSUidUsesHttpHostWhenSet()
    {
        // Save and restore HTTP_HOST
        $hadHost = array_key_exists('HTTP_HOST', $_SERVER);
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;

        $broken = new class {
            public function hasMethod(string $method): bool
            {
                return false;
            }

            /**
             * @param string $property
             * @return mixed
             */
            public function __get(string $property)
            {
                switch ($property) {
                    case 'ID':
                        return 42;
                    case 'Title':
                        return 'X';
                    case 'Content':
                    case 'Location':
                    case 'StartDate':
                    case 'EndDate':
                    case 'StartTime':
                    case 'EndTime':
                    case 'AllDay':
                        return null;
                    default:
                        return null;
                }
            }

            public function Categories()
            {
                return new ArrayList();
            }

            public function AbsoluteLink()
            {
                return '';
            }
        };

        try {
            $_SERVER['HTTP_HOST'] = 'example.test';

            $generate = new \ReflectionMethod(CalendarController::class, 'generateICSContent');
            $ics = $generate->invoke($this->controller, ArrayList::create([$broken]));

            $this->assertStringContainsString('UID:42@example.test', $ics);
            $this->assertStringNotContainsString('UID:42@calendar.local', $ics);
        } finally {
            if ($hadHost) {
                $_SERVER['HTTP_HOST'] = $previousHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
        }
    }

    /**
     * An event that raises an \Error - not an \Exception - while being transformed must be
     * skipped like any other bad event, so the rest of the feed still renders.
     *
     * This is the dynamic/silverstripe-calendar#184 regression: the call site advertised
     * 'Log error and continue with other events' but caught only \Exception, so the Error
     * escaped generateICSContent() and aborted the whole feed. The double below hands
     * escapeICSValue() a non-string Title, so the TypeError comes from PHP's own parameter
     * check rather than from a hand-thrown exception - which is precisely the failure the
     * narrow catch was blind to.
     */
    public function testUntransformableEventRaisingErrorIsSkippedAndFeedSurvives()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->logicalAnd(
                $this->stringContains('Error transforming event 123 to ICS'),
                $this->stringContains('must be of type string')
            ));
        $logger->expects($this->never())->method('warning');

        $errorRaiser = new class {
            public function hasMethod(string $method): bool
            {
                return false;
            }

            /**
             * @param string $property
             * @return mixed
             */
            public function __get(string $property)
            {
                switch ($property) {
                    case 'ID':
                        return 123;
                    case 'Title':
                        // Not a string: escapeICSValue(string $value) raises a TypeError.
                        return new \stdClass();
                    default:
                        return null;
                }
            }
        };

        // transformEventToICS() reads $_SERVER['HTTP_HOST'] directly when building the UID,
        // and this test drives the generator by reflection rather than through a request.
        $hadHost = array_key_exists('HTTP_HOST', $_SERVER);
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'localhost';

        $errorLogFile = tempnam(sys_get_temp_dir(), 'ics-error-log-');
        $previousErrorLog = (string) ini_get('error_log');
        ini_set('error_log', $errorLogFile !== false ? $errorLogFile : '/dev/null');

        Injector::nest();

        try {
            Injector::inst()->registerService($logger, LoggerInterface::class);

            $generate = new \ReflectionMethod(CalendarController::class, 'generateICSContent');
            // The healthy event trails the raiser: the loop must carry on to it.
            $ics = $generate->invoke($this->controller, ArrayList::create([$errorRaiser, $this->testEvent]));

            // The feed renders, without the failing event and with the one behind it.
            // 'UID:123@' is what the raiser itself would have emitted, so its absence is a
            // statement about the skipped event; the VEVENT count pins that exactly the one
            // healthy event made it through.
            $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
            $this->assertStringNotContainsString('UID:123@', $ics);
            $this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
            $this->assertStringContainsString('SUMMARY:Test ICS Event', $ics);

            // The injected logger must be the only sink.
            $logged = is_file($errorLogFile) ? (string) file_get_contents($errorLogFile) : '';
            $this->assertStringNotContainsString('logging failed', $logged);
            $this->assertStringNotContainsString('logger service unavailable', $logged);
            $this->assertStringNotContainsString('Error transforming event 123 to ICS', $logged);
        } finally {
            Injector::unnest();
            ini_set('error_log', $previousErrorLog);
            if ($hadHost) {
                $_SERVER['HTTP_HOST'] = $previousHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
            if ($errorLogFile !== false && is_file($errorLogFile)) {
                unlink($errorLogFile);
            }
        }
    }

    /**
     * Create and publish an EventPage under the fixture calendar, tracked for
     * tearDown cleanup.
     *
     * @param array $data Field values merged over the defaults
     * @return EventPage
     */
    private function createPublishedICSEvent(array $data): EventPage
    {
        $event = EventPage::create(array_merge([
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'EndDate' => Carbon::tomorrow()->format('Y-m-d'),
            'EndTime' => '11:00:00',
            'Recursion' => 'NONE',
        ], $data));
        $event->write();
        $event->publishRecursive();
        $this->additionalEvents[] = $event;

        return $event;
    }

    /**
     * Run a callable with PHP's default timezone forced, restoring the previous value
     * in a finally block so a failure inside the callable cannot leak the timezone
     * into the rest of the suite.
     *
     * @return mixed Whatever the callable returned, captured before the restore
     */
    private function withPhpTimezone(string $timezone, callable $callable)
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set($timezone);

        try {
            return $callable();
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /**
     * Return the (unfolded) VEVENT block whose SUMMARY matches the given title.
     *
     * The feed carries the setUp() event alongside anything a test adds, so an
     * assertion on a bare DTSTART substring could be satisfied by the wrong event;
     * scoping to the block pins it to the event under test.
     */
    private function veventBlockForSummary(string $body, string $summary): string
    {
        $unfolded = $this->unfoldICS($body);
        foreach (explode("BEGIN:VEVENT", $unfolded) as $block) {
            if (strpos($block, 'SUMMARY:' . $summary) !== false) {
                return explode("END:VEVENT", $block)[0];
            }
        }
        $this->fail('No VEVENT block found for SUMMARY: ' . $summary);
    }

    /**
     * Fetch the /ical feed body through the controller action.
     */
    private function fetchICSBody(): string
    {
        $request = new HTTPRequest('GET', '/ical');

        return $this->controller->ical($request)->getBody();
    }

    /**
     * Undo RFC 5545 line folding: a CRLF followed by a single linear whitespace
     * character is insignificant, so stripping it restores the logical line.
     */
    private function unfoldICS(string $ics): string
    {
        return str_replace("\r\n ", '', $ics);
    }

    /**
     * RFC 5545 3.1: content lines SHOULD NOT be longer than 75 octets (excluding
     * the CRLF), and long lines are folded by inserting CRLF plus one space. This
     * is the #297 regression: pre-fix the generator emitted the long DESCRIPTION,
     * SUMMARY and URL lines unfolded, so the per-line length assertion below
     * fails on the unfixed code. The Title is deliberately longer than 67
     * characters so its SUMMARY line itself exceeds 75 octets and is folded,
     * not just checked for surviving unfolding.
     */
    public function testLongLinesAreFoldedAt75Octets()
    {
        $longContent = 'Start marker ' . str_repeat('abcdefghij ', 30) . 'end marker';
        $longTitle = 'A Summary Long Enough That It Must Be Folded In The ICS Output For Consumers';
        $this->createPublishedICSEvent([
            'Title' => $longTitle,
            'Content' => $longContent,
        ]);

        $body = $this->fetchICSBody();

        // Folding actually happened somewhere in the feed.
        $this->assertGreaterThan(0, substr_count($body, "\r\n "), 'Long lines must be folded');

        // Every physical line is within the 75-octet limit, and every
        // continuation is a folded line (the unfold below must restore the
        // exact logical content, checked next).
        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(
                75,
                strlen($line),
                'Content line exceeds the RFC 5545 75-octet limit: ' . $line
            );
        }

        // Unfolding is lossless: the full DESCRIPTION comes back.
        $unfolded = $this->unfoldICS($body);
        $this->assertStringContainsString(
            'DESCRIPTION:' . $longContent,
            $unfolded
        );
        // The SUMMARY line was over the limit, so the raw body must NOT hold it
        // contiguously while the unfolded body must restore it exactly.
        $this->assertStringNotContainsString('SUMMARY:' . $longTitle, $body);
        $this->assertStringContainsString(
            'SUMMARY:' . $longTitle,
            $unfolded
        );
    }

    /**
     * foldICSLine() must terminate on malformed UTF-8 and still respect the
     * fold contract: a run of 74+ stray continuation bytes makes mb_strcut()
     * return an empty string at a mid-sequence offset (a hang without
     * handling), and a naive raw-octet workaround re-emits bytes when valid
     * multi-byte text follows the stray run (a C3 C3 A9 corruption). The line
     * is scrubbed to valid UTF-8 before cutting, so every case below
     * terminates, stays within 75 octets per line, and comes back valid
     * UTF-8. max_execution_time is pinned low so a regression that hangs
     * fails as a timeout fatal instead of stalling the suite.
     */
    public function testFoldingTerminatesOnMalformedUtf8Input()
    {
        // 74 consecutive continuation bytes (0x80..0xBF are never valid lead
        // bytes), long enough to trigger the empty-segment path at the
        // 75-octet fold boundary.
        $strayRun = str_repeat("\x80", 74);

        // [input, expected lead-in after unfolding, expected tail after unfolding]
        // The lead-in is pinned per case so the assertion below confirms the
        // scrub preserved the whole ASCII run, not just its first 70 octets.
        $cases = [
            // ASCII trails the stray run
            [
                'X:' . str_repeat('a', 73) . $strayRun . str_repeat('b', 40),
                'X:' . str_repeat('a', 73),
                str_repeat('b', 40),
            ],
            // Valid multi-byte text trails it: the exact input a raw-octet
            // fallback corrupted by stranding a lead byte at the cut.
            [
                'X:' . str_repeat('a', 73) . $strayRun . str_repeat("\xc3\xa9", 60),
                'X:' . str_repeat('a', 73),
                str_repeat("\xc3\xa9", 60),
            ],
            [
                'X:' . str_repeat('a', 70) . str_repeat("\x80", 80) . str_repeat("\xe2\x98\x95", 40),
                'X:' . str_repeat('a', 70),
                str_repeat("\xe2\x98\x95", 40),
            ],
        ];

        $previous = (int) ini_get('max_execution_time');
        ini_set('max_execution_time', '10');
        try {
            $fold = new \ReflectionMethod(CalendarController::class, 'foldICSLine');
            foreach ($cases as [$case, $lead, $tail]) {
                $folded = $fold->invoke($this->controller, $case);

                foreach (explode("\r\n", $folded) as $line) {
                    $this->assertLessThanOrEqual(75, strlen($line), 'Folded line exceeds 75 octets');
                }

                // The output is valid UTF-8 end to end - this is what the
                // byte-duplicating fallback broke (a stranded C3 lead byte).
                $this->assertTrue(mb_check_encoding($folded, 'UTF-8'), 'Folded output is not valid UTF-8');
                $unfolded = str_replace("\r\n ", '', $folded);
                $this->assertStringNotContainsString("\x80", $unfolded, 'Stray continuation bytes survived scrubbing');

                // The good text around the stray run is preserved: ASCII lead-in
                // at the front, the tail (ASCII or multi-byte) intact at the end.
                $this->assertStringStartsWith($lead, $unfolded, 'ASCII lead-in was not preserved intact');
                $this->assertStringEndsWith($tail, $unfolded, 'Tail text after the stray run was not preserved intact');
            }
        } finally {
            ini_set('max_execution_time', (string) $previous);
        }
    }

    /**
     * A CRLF inside an event's Content must produce exactly one \n escape in
     * DESCRIPTION, not two. Pre-fix escapeICSValue() mapped "\n" and "\r" each
     * to a "\\n" escape, so one visual line break carried as CRLF was escaped
     * twice and readers showed a blank line (#297).
     *
     * Driven through a double so the exact byte sequence under test survives the
     * trip into the generator: the DB HTML field round-trip normalises CRLF, so a
     * stored page cannot carry it to the controller. Measured on this module's
     * ddev MySQL test DB - writing "a\r\nb" as a page's Content and reading it
     * back yields "a\nb" (column hex 610a62, input hex 610d0a62) - which is why
     * the sequence has to be injected rather than stored.
     */
    public function testCRLFInContentIsEscapedOnce()
    {
        $double = new class {
            public function hasMethod(string $method): bool
            {
                return false;
            }

            /**
             * @param string $property
             * @return mixed
             */
            public function __get(string $property)
            {
                switch ($property) {
                    case 'ID':
                        return 555;
                    case 'Title':
                        return 'CRLF Event';
                    case 'Content':
                        // One CRLF break and one lone-CR break: each must escape once.
                        return "alpha\r\nbeta\rgamma";
                    default:
                        return null;
                }
            }

            public function Categories()
            {
                return new ArrayList();
            }

            public function AbsoluteLink()
            {
                return '';
            }
        };

        $hadHost = array_key_exists('HTTP_HOST', $_SERVER);
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'localhost';

        try {
            $generate = new \ReflectionMethod(CalendarController::class, 'generateICSContent');
            $ics = $generate->invoke($this->controller, ArrayList::create([$double]));

            // Exactly one \n escape between each pair of words (the doubled form
            // is what the unfixed escaper produced).
            $this->assertStringContainsString('DESCRIPTION:alpha' . "\\n" . 'beta' . "\\n" . 'gamma', $ics);
            $this->assertStringNotContainsString('alpha' . "\\n\\n" . 'beta', $ics);
            $this->assertStringNotContainsString('beta' . "\\n\\n" . 'gamma', $ics);

            // No raw CR or LF survives inside the feed at all - a stray one
            // would break the CRLF-only line structure.
            $stripped = str_replace("\r\n", '', $ics);
            $this->assertStringNotContainsString("\r", $stripped);
            $this->assertStringNotContainsString("\n", $stripped);
        } finally {
            if ($hadHost) {
                $_SERVER['HTTP_HOST'] = $previousHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
        }
    }

    /**
     * The DESCRIPTION must be html_entity_decode()'d after strip_tags(), so an
     * entity such as &amp; shows up as '&' in the feed instead of the raw
     * entity text (#297). The order matters: decoding before stripping would
     * turn escaped markup into real markup and drop it; stripping first (this
     * assertion's second half) keeps the decoded <b> as literal text.
     */
    public function testHTMLEntitiesAreDecodedInDescription()
    {
        $this->createPublishedICSEvent([
            'Title' => 'Entity Event',
            'Content' => 'Cats &amp; Dogs &lt;b&gt;bold&lt;/b&gt;',
        ]);

        $unfolded = $this->unfoldICS($this->fetchICSBody());

        $this->assertStringContainsString('DESCRIPTION:Cats & Dogs <b>bold</b>', $unfolded);
        $this->assertStringNotContainsString('&amp;', $unfolded);
        $this->assertStringNotContainsString('&lt;b&gt;', $unfolded);
    }

    /**
     * Folding must cut with mb_strcut() so a multi-byte UTF-8 character is
     * never split across a fold boundary: cutting at an arbitrary octet would
     * emit invalid UTF-8 inside the continuation line. Content is chosen so
     * multi-byte sequences straddle the 75-octet boundary repeatedly (#297).
     */
    public function testMultibyteCharactersAreNotSplitAtFoldBoundary()
    {
        // 'é' is 2 octets and '☕' is 3 (E2 98 95), spread through a >75-octet line
        // boundaries land mid-sequence on any naive octet cut.
        $content = str_repeat('héllo wörld ☕ ', 20) . 'done';
        $this->createPublishedICSEvent([
            'Title' => 'Multibyte Event',
            'Content' => $content,
        ]);

        $body = $this->fetchICSBody();

        $this->assertTrue(mb_check_encoding($body, 'UTF-8'), 'Folded output must remain valid UTF-8');
        $this->assertGreaterThan(0, substr_count($body, "\r\n "), 'The multi-byte line must have been folded');
        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'Content line exceeds 75 octets: ' . $line);
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'Physical line is not valid UTF-8: ' . $line);
        }

        // Unfolding restores the full description byte-for-byte.
        $this->assertStringContainsString('DESCRIPTION:' . $content, $this->unfoldICS($body));
    }

    /**
     * A timed event is authored in venue-local wall-clock time, so it must be resolved
     * in the site's own timezone before it is converted to UTC. Pre-fix
     * CalendarController::$timezone defaulted to the literal 'UTC', so on a site whose
     * PHP timezone is America/Chicago a 10:00 event emitted DTSTART:20261015T100000Z -
     * five hours early from the subscriber's point of view (#329).
     */
    public function testICSTimedEventDefaultsToSiteTimezone()
    {
        $this->createPublishedICSEvent([
            'Title' => 'Site Timezone Event',
            'StartDate' => '2026-10-15',
            'StartTime' => '10:00:00',
            'EndDate' => '2026-10-15',
            'EndTime' => '11:00:00',
        ]);

        $body = $this->withPhpTimezone('America/Chicago', function () {
            return $this->fetchICSBody();
        });

        $vevent = $this->veventBlockForSummary($body, 'Site Timezone Event');

        // 10:00 CDT is 15:00Z; 11:00 CDT is 16:00Z.
        $this->assertStringContainsString('DTSTART:20261015T150000Z', $vevent);
        $this->assertStringContainsString('DTEND:20261015T160000Z', $vevent);
        // The pre-fix output, stated explicitly so a change that merely dropped the
        // field could not pass the assertions above.
        $this->assertStringNotContainsString('DTSTART:20261015T100000Z', $vevent);
    }

    /**
     * An explicit CalendarController.timezone config value still wins over the site
     * default - the existing override path must keep working for sites that store
     * events in UTC while their PHP default is something else (#329 changed only the
     * default, not the override).
     */
    public function testICSTimedEventHonoursConfiguredTimezoneOverride()
    {
        $this->createPublishedICSEvent([
            'Title' => 'Configured Timezone Event',
            'StartDate' => '2026-10-15',
            'StartTime' => '10:00:00',
            'EndDate' => '2026-10-15',
            'EndTime' => '11:00:00',
        ]);

        Config::nest();

        try {
            $body = $this->withPhpTimezone('America/Chicago', function () {
                Config::modify()->set(CalendarController::class, 'timezone', 'UTC');

                return $this->fetchICSBody();
            });
        } finally {
            Config::unnest();
        }

        $vevent = $this->veventBlockForSummary($body, 'Configured Timezone Event');

        // The override is UTC, so the wall-clock time passes through unchanged.
        $this->assertStringContainsString('DTSTART:20261015T100000Z', $vevent);
        $this->assertStringContainsString('DTEND:20261015T110000Z', $vevent);
    }

    /**
     * A timed event that carries a StartTime but no EndDate/EndTime gets the default
     * one-hour DTEND, and that hour must land on the instant the event actually starts -
     * with the site timezone at America/Chicago a 10:00 start emits DTSTART 15:00Z and
     * DTEND 16:00Z, not 10:00Z/11:00Z (#329).
     *
     * Driven with a double rather than a stored page because EventPage::onBeforeWrite()
     * derives EndTime (and EndDate) from StartTime whenever EndTime is blank, so a saved
     * record can never reach the transform's 'Default 1 hour duration' branch - passing
     * nulls to createPublishedICSEvent() would silently exercise the explicit-end branch
     * a different test already covers. Note the implementation adds the hour to the
     * already-UTC-converted start value (Carbon mutates in place on utc()), which is the
     * same instant as start-plus-one-hour in the source timezone because a one-hour span
     * has the same length either side of a conversion.
     */
    public function testICSTimedEventWithoutEndTimeGetsOneHourInSiteTimezone()
    {
        $noEnd = new class {
            public function hasMethod(string $method): bool
            {
                return false;
            }

            /**
             * @param string $property
             * @return mixed
             */
            public function __get(string $property)
            {
                switch ($property) {
                    case 'ID':
                        return 777;
                    case 'Title':
                        return 'Open Ended Event';
                    case 'StartDate':
                        return '2026-10-15';
                    case 'StartTime':
                        return '10:00:00';
                    default:
                        // EndDate, EndTime, AllDay, Content, Location: nothing to resolve.
                        return null;
                }
            }

            public function Categories()
            {
                return new ArrayList();
            }

            public function AbsoluteLink()
            {
                return '';
            }
        };

        $hadHost = array_key_exists('HTTP_HOST', $_SERVER);
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'localhost';

        try {
            $body = $this->withPhpTimezone('America/Chicago', function () use ($noEnd) {
                $generate = new \ReflectionMethod(CalendarController::class, 'transformEventToICS');

                return implode("\r\n", $generate->invoke($this->controller, $noEnd));
            });
        } finally {
            if ($hadHost) {
                $_SERVER['HTTP_HOST'] = $previousHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
        }

        $this->assertStringContainsString('DTSTART:20261015T150000Z', $body);
        $this->assertStringContainsString('DTEND:20261015T160000Z', $body);
        // The pre-fix pair, stated so a transform that simply omitted DTEND could not
        // pass the assertion above.
        $this->assertStringNotContainsString('DTSTART:20261015T100000Z', $body);
    }

    /**
     * All-day events are date-valued (DTSTART;VALUE=DATE) and carry no time to resolve,
     * so the timezone change must not touch them: they stay bare dates with no UTC
     * conversion, offset and all (#329 regression guard on the untouched branch).
     */
    public function testICSAllDayEventIsUnaffectedBySiteTimezone()
    {
        $this->createPublishedICSEvent([
            'Title' => 'All Day Zone Event',
            'StartDate' => '2026-10-15',
            'EndDate' => '2026-10-15',
            'StartTime' => null,
            'EndTime' => null,
            'AllDay' => true,
        ]);

        $body = $this->withPhpTimezone('America/Chicago', function () {
            return $this->fetchICSBody();
        });

        $vevent = $this->veventBlockForSummary($body, 'All Day Zone Event');

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261015', $vevent);
        // DTEND for an all-day event is the day after, still date-valued.
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261016', $vevent);
        $this->assertStringNotContainsString('DTSTART:2026', $vevent);
    }

    /**
     * A calendar with no events still renders a well-formed feed: header and
     * footer only, CRLF-joined, and nothing long enough to have been folded.
     * Guards that the folding pass is a no-op on short lines (#297).
     */
    public function testEmptyCalendarFeedIsCRLFJoinedAndUnfolded()
    {
        foreach (EventPage::get()->filter('ParentID', $this->calendar->ID) as $event) {
            $event->delete();
        }

        $body = $this->fetchICSBody();

        $expected = "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//Dynamic SilverStripe Calendar//EN\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "METHOD:PUBLISH\r\n"
            . "END:VCALENDAR";
        $this->assertSame($expected, $body);
        $this->assertStringNotContainsString("\r\n ", $body);
    }
}
