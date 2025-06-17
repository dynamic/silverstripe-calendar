<?php

namespace Dynamic\Calendar\Tests\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;

/**
 * Class CalendarTest
 * @package Dynamic\Calendar\Tests\Page
 */
class CalendarTest extends SapphireTest
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
    }

    /**
     * Test controller name
     */
    public function testControllerName()
    {
        $this->assertEquals(CalendarController::class, Calendar::singleton()->getControllerName());
    }

    /**
     * Test getEventsFeed method returns ArrayList
     */
    public function testGetEventsFeedReturnsArrayList()
    {
        $events = $this->calendar->getEventsFeed();
        $this->assertInstanceOf(ArrayList::class, $events);
    }

    /**
     * Test getEventsFeed with no events returns empty list
     */
    public function testGetEventsFeedWithNoEventsReturnsEmptyList()
    {
        $events = $this->calendar->getEventsFeed();
        $this->assertEquals(0, $events->count());
    }

    /**
     * Test getEventsFeed with regular (non-recurring) events
     */
    public function testGetEventsFeedWithRegularEvents()
    {
        // Create a regular event
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

        $events = $this->calendar->getEventsFeed();
        $this->assertEquals(1, $events->count());
        $this->assertEquals('Test Event', $events->first()->Title);
    }

    /**
     * Test getEventsFeed with recurring events
     */
    public function testGetEventsFeedWithRecurringEvents()
    {
        // Create a daily recurring event with proper configuration
        $event = EventPage::create([
            'Title' => 'Daily Recurring Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::today()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'EndDate' => Carbon::today()->format('Y-m-d'),
            'EndTime' => '11:00:00',
            'Recursion' => 'DAILY',
            'Interval' => 1, // Correct field name for interval
            'RecursionEndDate' => Carbon::today()->addWeek()->format('Y-m-d'),
        ]);
        $event->write();
        $event->publishRecursive();

        // Get events for the next week
        $fromDate = Carbon::today();
        $toDate = Carbon::today()->addWeek();
        $events = $this->calendar->getEventsFeed(null, null, $fromDate, $toDate);

        // Should have multiple instances (at least 2 for daily recurring)
        $this->assertGreaterThan(1, $events->count());
        $this->assertLessThanOrEqual(8, $events->count());
    }

    /**
     * Test getEventsFeed with limit parameter
     */
    public function testGetEventsFeedWithLimit()
    {
        // Create multiple events
        for ($i = 1; $i <= 5; $i++) {
            $event = EventPage::create([
                'Title' => "Event $i",
                'ParentID' => $this->calendar->ID,
                'StartDate' => Carbon::today()->addDays($i)->format('Y-m-d'),
                'StartTime' => '14:00:00',
                'EndDate' => Carbon::today()->addDays($i)->format('Y-m-d'),
                'EndTime' => '16:00:00',
                'Recursion' => 'NONE',
            ]);
            $event->write();
            $event->publishRecursive();
        }

        $events = $this->calendar->getEventsFeed(3);
        $this->assertEquals(3, $events->count());
    }

    /**
     * Test getEventsFeed with category filtering
     */
    public function testGetEventsFeedWithCategoryFiltering()
    {
        // Create categories
        $category1 = Category::create(['Title' => 'Sports']);
        $category1->write();

        $category2 = Category::create(['Title' => 'Music']);
        $category2->write();

        // Create events with different categories
        $event1 = EventPage::create([
            'Title' => 'Football Game',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->format('Y-m-d'),
            'StartTime' => '14:00:00',
            'EndDate' => Carbon::tomorrow()->format('Y-m-d'),
            'EndTime' => '16:00:00',
            'Recursion' => 'NONE',
        ]);
        $event1->write();
        $event1->Categories()->add($category1);
        $event1->publishRecursive();

        $event2 = EventPage::create([
            'Title' => 'Concert',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::tomorrow()->addDay()->format('Y-m-d'),
            'StartTime' => '19:00:00',
            'EndDate' => Carbon::tomorrow()->addDay()->format('Y-m-d'),
            'EndTime' => '21:00:00',
            'Recursion' => 'NONE',
        ]);
        $event2->write();
        $event2->Categories()->add($category2);
        $event2->publishRecursive();

        // Create category list for filtering
        $sportsCategories = ArrayList::create([$category1]);

        $events = $this->calendar->getEventsFeed(null, $sportsCategories);
        $this->assertEquals(1, $events->count());
        $this->assertEquals('Football Game', $events->first()->Title);
    }

    /**
     * Test getEventsFeed with date range filtering
     */
    public function testGetEventsFeedWithDateRangeFiltering()
    {
        // Create events across different dates
        $event1 = EventPage::create([
            'Title' => 'Past Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::yesterday()->format('Y-m-d'),
            'StartTime' => '14:00:00',
            'EndDate' => Carbon::yesterday()->format('Y-m-d'),
            'EndTime' => '16:00:00',
            'Recursion' => 'NONE',
        ]);
        $event1->write();
        $event1->publishRecursive();

        $event2 = EventPage::create([
            'Title' => 'Future Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'StartTime' => '14:00:00',
            'EndDate' => Carbon::today()->addWeek()->format('Y-m-d'),
            'EndTime' => '16:00:00',
            'Recursion' => 'NONE',
        ]);
        $event2->write();
        $event2->publishRecursive();

        // Get events only for today and tomorrow
        $fromDate = Carbon::today();
        $toDate = Carbon::tomorrow();
        $events = $this->calendar->getEventsFeed(null, null, $fromDate, $toDate);

        // Should not include yesterday's or next week's events
        $this->assertEquals(0, $events->count());
    }
}
