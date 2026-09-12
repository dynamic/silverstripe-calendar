<?php

namespace Dynamic\Calendar\Tests\Controller;

use BadMethodCallException;
use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Psr\Log\LoggerInterface;
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
    // This test creates its own fixtures in setUp()

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
        // the key is set here to keep the test independent of bootstrap side effects. Note
        // that the 'calendar.local' default on that line is currently unreachable - the
        // concatenation binds tighter than the ?? - which is tracked as
        // dynamic/silverstripe-calendar#185 and is not what this test covers.
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
}
