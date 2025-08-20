<?php

namespace Dynamic\Calendar\Tests\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
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

        // Create test category
        $this->testCategory = Category::create([
            'Title' => 'Test Category',
            'Color' => 'FF0000',
        ]);
        $this->testCategory->write();

        // Create test event
        $this->testEvent = EventPage::create([
            'Title' => 'Test ICS Event',
            'Content' => 'This is a test event for ICS generation',
            'Location' => 'Test Location',
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
        $this->assertStringContains('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContains('BEGIN:VEVENT', $icsContent);
        $this->assertStringContains('END:VEVENT', $icsContent);
        $this->assertStringContains('END:VCALENDAR', $icsContent);
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
        $this->assertStringContains('SUMMARY:Test ICS Event', $icsContent);
        
        // Check that location is included
        $this->assertStringContains('LOCATION:Test Location', $icsContent);
        
        // Check that description is included (without HTML tags)
        $this->assertStringContains('DESCRIPTION:This is a test event for ICS generation', $icsContent);
        
        // Check that category is included
        $this->assertStringContains('CATEGORIES:Test Category', $icsContent);
        
        // Check that it has proper UID
        $this->assertStringContains('UID:', $icsContent);
        
        // Check that it has proper timestamps
        $this->assertStringContains('DTSTAMP:', $icsContent);
        $this->assertStringContains('DTSTART:', $icsContent);
        $this->assertStringContains('DTEND:', $icsContent);
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
        $this->assertStringContains('Test ICS Event', $icsContent);
        
        // Should not include the future event (outside range)
        $this->assertStringNotContains('Future Event', $icsContent);
    }

    /**
     * Test ICS action with category filtering
     */
    public function testICSActionWithCategoryFiltering()
    {
        // Create another category and event
        $otherCategory = Category::create([
            'Title' => 'Other Category',
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
        $this->assertStringContains('Test ICS Event', $icsContent);
        
        // Should not include the other event (different category)
        $this->assertStringNotContains('Other Category Event', $icsContent);
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
        $this->assertStringContains('All Day Event', $icsContent);
    }

    /**
     * Test ICS action with recurring event
     */
    public function testICSActionWithRecurringEvent()
    {
        // Create a recurring event
        $recurringEvent = EventPage::create([
            'Title' => 'Weekly Recurring Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::today()->format('Y-m-d'),
            'StartTime' => '09:00:00',
            'EndDate' => Carbon::today()->format('Y-m-d'),
            'EndTime' => '10:00:00',
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => Carbon::today()->addWeeks(3)->format('Y-m-d'),
        ]);
        $recurringEvent->write();
        $recurringEvent->publishRecursive();

        $request = new HTTPRequest('GET', '/ical');
        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Should include the recurring event instances
        $this->assertStringContains('Weekly Recurring Event', $icsContent);
        
        // Should have multiple VEVENT entries for recurring instances
        $eventCount = substr_count($icsContent, 'BEGIN:VEVENT');
        $this->assertGreaterThan(1, $eventCount, 'Should have multiple event instances for recurring event');
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
        $this->assertStringContains('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContains('END:VCALENDAR', $icsContent);
        
        // Should not contain any events
        $this->assertStringNotContains('BEGIN:VEVENT', $icsContent);
    }
}