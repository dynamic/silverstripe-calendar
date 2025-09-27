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
 * Test class for Calendar Subscription functionality
 * @package Dynamic\Calendar\Tests\Controller
 */
class CalendarSubscriptionTest extends FunctionalTest
{
    public const TEST_CATEGORY_RED = 'FF0000';
    public const TEST_CATEGORY_GREEN = '00FF00';
    public const TEST_CATEGORY_BLUE = '0000FF';

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
            'Title' => 'Test Calendar for Subscription',
            'URLSegment' => 'test-subscription-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        // Create test category
        $this->testCategory = Category::create([
            'Title' => 'Test Category Subscription',
            'Color' => self::TEST_CATEGORY_RED,
        ]);
        $this->testCategory->write();

        // Create test event
        $this->testEvent = EventPage::create([
            'Title' => 'Test Subscription Event',
            'Content' => 'This is a test event for subscription',
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
     * Test that subscription button is present in calendar template
     */
    public function testSubscriptionButtonPresent()
    {
        // Create a GET request to the calendar page
        $response = $this->get($this->calendar->Link());

        // Check that the response is successful
        $this->assertEquals(200, $response->getStatusCode());

        // Check that the subscribe button is present
        $this->assertStringContainsString('js-subscribe-calendar', $response->getBody());
        $this->assertStringContainsString('Subscribe to Calendar', $response->getBody());
        $this->assertStringContainsString('data-bs-toggle="modal"', $response->getBody());
    }

    /**
     * Test that subscription modal is present in calendar template
     */
    public function testSubscriptionModalPresent()
    {
        // Create a GET request to the calendar page
        $response = $this->get($this->calendar->Link());

        // Check that the subscription modal is present
        $this->assertStringContainsString('id="subscribeModal"', $response->getBody());
        $this->assertStringContainsString('subscription-url', $response->getBody());
        $this->assertStringContainsString('Google Calendar', $response->getBody());
        $this->assertStringContainsString('Microsoft Outlook', $response->getBody());
        $this->assertStringContainsString('Apple Calendar', $response->getBody());
    }

    /**
     * Test ICS feed with category filtering works correctly
     */
    public function testICSFeedWithCategoryFiltering()
    {
        // Create another category and event to ensure filtering works
        $otherCategory = Category::create([
            'Title' => 'Other Test Category',
            'Color' => self::TEST_CATEGORY_GREEN,
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
        $this->assertStringContainsString('Test Subscription Event', $icsContent);

        // Should not include the other event (different category)
        $this->assertStringNotContainsString('Other Category Event', $icsContent);

        // Cleanup
        $otherEvent->delete();
        $otherCategory->delete();
    }

    /**
     * Test ICS feed with multiple category filtering
     */
    public function testICSFeedWithMultipleCategoryFiltering()
    {
        // Create additional categories and events
        $category1 = Category::create([
            'Title' => 'Test Category 1',
            'Color' => self::TEST_CATEGORY_GREEN,
        ]);
        $category1->write();

        $category2 = Category::create([
            'Title' => 'Test Category 2',
            'Color' => self::TEST_CATEGORY_BLUE,
        ]);
        $category2->write();

        $event1 = EventPage::create([
            'Title' => 'Event Category 1',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->format('Y-m-d'),
            'StartTime' => '09:00:00',
            'EndDate' => Carbon::tomorrow()->format('Y-m-d'),
            'EndTime' => '10:00:00',
            'Recursion' => 'NONE',
        ]);
        $event1->write();
        $event1->Categories()->add($category1);
        $event1->publishRecursive();

        $event2 = EventPage::create([
            'Title' => 'Event Category 2',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->format('Y-m-d'),
            'StartTime' => '11:00:00',
            'EndDate' => Carbon::tomorrow()->format('Y-m-d'),
            'EndTime' => '12:00:00',
            'Recursion' => 'NONE',
        ]);
        $event2->write();
        $event2->Categories()->add($category2);
        $event2->publishRecursive();

        // Request ICS with multiple category filter
        $request = new HTTPRequest('GET', '/ical', [
            'categories' => [$category1->ID, $category2->ID],
        ]);

        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Should include events from both categories
        $this->assertStringContainsString('Event Category 1', $icsContent);
        $this->assertStringContainsString('Event Category 2', $icsContent);

        // Should not include the original test event (different category)
        $this->assertStringNotContainsString('Test Subscription Event', $icsContent);

        // Cleanup
        $event1->delete();
        $event2->delete();
        $category1->delete();
        $category2->delete();
    }

    /**
     * Test ICS feed with date range filtering
     */
    public function testICSFeedWithDateRangeFiltering()
    {
        // Create an event outside the filter range
        $futureEvent = EventPage::create([
            'Title' => 'Future Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->addMonth()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'EndDate' => Carbon::now()->addMonth()->format('Y-m-d'),
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
        $this->assertStringContainsString('Test Subscription Event', $icsContent);

        // Should not include the future event (outside range)
        $this->assertStringNotContainsString('Future Event', $icsContent);

        // Cleanup
        $futureEvent->delete();
    }

    /**
     * Test ICS feed with combined category and date filtering
     */
    public function testICSFeedWithCombinedFiltering()
    {
        // Create another category and event
        $otherCategory = Category::create([
            'Title' => 'Combined Test Category',
            'Color' => self::TEST_CATEGORY_GREEN,
        ]);
        $otherCategory->write();

        // Event in different category but within date range
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

        // Event in same category but outside date range
        $futureEvent = EventPage::create([
            'Title' => 'Future Same Category Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->addMonth()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'EndDate' => Carbon::now()->addMonth()->format('Y-m-d'),
            'EndTime' => '11:00:00',
            'Recursion' => 'NONE',
        ]);
        $futureEvent->write();
        $futureEvent->Categories()->add($this->testCategory);
        $futureEvent->publishRecursive();

        // Request ICS with category and date filtering
        $fromDate = Carbon::today()->format('Y-m-d');
        $toDate = Carbon::today()->addWeek()->format('Y-m-d');

        $request = new HTTPRequest('GET', '/ical', [
            'categories' => [$this->testCategory->ID],
            'from' => $fromDate,
            'to' => $toDate,
        ]);

        $response = $this->controller->ical($request);
        $icsContent = $response->getBody();

        // Should include only the test event (matching both category and date range)
        $this->assertStringContainsString('Test Subscription Event', $icsContent);

        // Should not include other events
        $this->assertStringNotContainsString('Other Category Event', $icsContent);
        $this->assertStringNotContainsString('Future Same Category Event', $icsContent);

        // Cleanup
        $otherEvent->delete();
        $futureEvent->delete();
        $otherCategory->delete();
    }
}
