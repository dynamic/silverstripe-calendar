<?php

namespace Dynamic\Calendar\Tests\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\ArrayList;

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

        // Default (lean) shape: Calendar.ss renders none of the event data -
        // FullCalendar loads it from /events - so index() no longer
        // materialises the full feed just to discard it.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('Calendar', $result);
        $this->assertArrayHasKey('CurrentFromDate', $result);
        $this->assertArrayHasKey('CurrentToDate', $result);
        $this->assertArrayNotHasKey('Events', $result);
        $this->assertArrayNotHasKey('RecurringEventsCount', $result);
        $this->assertArrayNotHasKey('OneTimeEventsCount', $result);

        $this->assertEquals($this->calendar->ID, $result['Calendar']->ID);
    }

    public function testIndexActionProvidesEventListWhenOptedIn()
    {
        Config::modify()->set(CalendarController::class, 'index_provides_event_list', true);

        $request = new HTTPRequest('GET', '/');
        $result = $this->controller->index($request);

        // Legacy shape for project templates that render $Events etc.
        $this->assertArrayHasKey('Events', $result);
        $this->assertArrayHasKey('RecurringEventsCount', $result);
        $this->assertArrayHasKey('OneTimeEventsCount', $result);
        $this->assertArrayHasKey('AvailableCategories', $result);
        $this->assertInstanceOf('SilverStripe\ORM\PaginatedList', $result['Events']);
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
        Config::modify()->set(CalendarController::class, 'index_provides_event_list', true);

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
}
