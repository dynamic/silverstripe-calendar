<?php

namespace Dynamic\Calendar\Tests\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventException;
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
        $this->calendar = $this->objFromFixture(Calendar::class, 'one');
    }

    /**
     * Test controller name
     */
    public function testGetControllerName()
    {
        $this->assertEquals('Dynamic\\Calendar\\Controller\\CalendarController', $this->calendar->getControllerName());
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
     * Test getEventsFeed with regular (non-recurring) events
     */
    public function testGetEventsFeedWithRegularEvents()
    {
        $event = $this->objFromFixture(EventPage::class, 'two');
        $events = $this->calendar->getEventsFeed();
        $found = $events->find('Title', 'All Day Event');
        $this->assertNotNull($found, 'All Day Event should be present');
        $this->assertEquals('All Day Event', $found->Title);
    }

    /**
     * Test getEventsFeed with recurring events
     */
    public function testGetEventsFeedWithRecurringEvents()
    {
        $event = $this->objFromFixture(EventPage::class, 'one');
        $fromDate = Carbon::parse($event->StartDate);
        $toDate = Carbon::parse($event->RecursionEndDate);
        $events = $this->calendar->getEventsFeed(null, null, $fromDate, $toDate);
        $this->assertGreaterThan(1, $events->count());
    }

    /**
     * Test getEventsFeed with category filtering
     */
    public function testGetEventsFeedWithCategoryFiltering()
    {
        $category = Category::get()->first();
        $categories = ArrayList::create([$category]);
        $events = $this->calendar->getEventsFeed(null, $categories);
        $this->assertIsIterable($events);
    }

    /**
     * Test getEventsFeed with limit parameter
     */
    public function testGetEventsFeedWithLimit()
    {
        $events = $this->calendar->getEventsFeed(1);
        $this->assertCount(1, $events);
    }

    /**
     * Test getEventsFeed with EventException deletions
     */
    public function testGetEventsFeedWithEventExceptionDeletions()
    {
        $event = $this->objFromFixture(EventPage::class, 'one');
        $fromDate = Carbon::parse($event->StartDate);
        $toDate = Carbon::parse($event->RecursionEndDate);
        $events = $this->calendar->getEventsFeed(null, null, $fromDate, $toDate);
        $deletedDate = '2025-06-25';
        foreach ($events as $instance) {
            if (method_exists($instance, 'getInstanceDate')) {
                $this->assertNotEquals($deletedDate, $instance->getInstanceDate()->format('Y-m-d'));
            }
        }
    }

    /**
     * Test getEventsFeed with EventException modifications
     */
    public function testGetEventsFeedWithEventExceptionModifications()
    {
        $event = $this->objFromFixture(EventPage::class, 'one');
        $fromDate = Carbon::parse($event->StartDate);
        $toDate = Carbon::parse($event->RecursionEndDate);
        $events = $this->calendar->getEventsFeed(null, null, $fromDate, $toDate);
        $modified = false;
        foreach ($events as $instance) {
            if ($instance->Title === 'Modified Event Title') {
                $modified = true;
                break;
            }
        }
        $this->assertTrue($modified, 'Modified instance should be present');
    }
}
