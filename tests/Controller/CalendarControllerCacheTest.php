<?php

namespace Dynamic\Calendar\Tests\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Cache\CalendarCacheVersion;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Versioned\Versioned;

/**
 * Calendar Controller Cache Test
 *
 * Tests configurable cache TTL and automatic cache invalidation
 * for calendar event feeds.
 *
 * @package Dynamic\Calendar\Tests\Controller
 */
class CalendarControllerCacheTest extends FunctionalTest
{
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

        // The feed cache is Live-stage-only by design (draft requests bypass
        // it), and real front-end traffic reads Live. SapphireTest defaults to
        // draft, so pin Live for these tests.
        Versioned::set_stage(Versioned::LIVE);

        $this->calendar = Calendar::create([
            'Title' => 'Cache Test Calendar',
            'URLSegment' => 'cache-test-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        $this->controller = CalendarController::create($this->calendar);
    }

    /**
     * Test 1: Cache TTL respects configuration
     */
    public function testCacheTTLRespectsConfiguration()
    {
        // Set custom TTL in config (15 minutes)
        Config::modify()->set(CalendarController::class, 'json_cache_ttl', 900);

        // Verify the config is set correctly
        $ttl = Config::inst()->get(CalendarController::class, 'json_cache_ttl');
        $this->assertEquals(900, $ttl, 'Cache TTL should be configurable');

        // Reset to default
        Config::modify()->set(CalendarController::class, 'json_cache_ttl', 1800);
    }

    /**
     * Test 2: Default cache TTL is 30 minutes (1800 seconds)
     */
    public function testDefaultCacheTTLIs30Minutes()
    {
        $ttl = Config::inst()->get(CalendarController::class, 'json_cache_ttl');
        $this->assertEquals(1800, $ttl, 'Default cache TTL should be 1800 seconds (30 minutes)');
    }

    /**
     * Test 3: EventPage changes invalidate cache
     */
    public function testEventPageChangesInvalidateCache()
    {
        // Create an event
        $event = EventPage::create([
            'Title' => 'Test Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        // Make initial AJAX request (cache MISS)
        $request1 = $this->createAjaxRequest();
        $response1 = $this->controller->events($request1);
        $this->assertEquals('MISS', $response1->getHeader('X-Calendar-Cache'));
        $json1 = $response1->getBody();
        $data1 = json_decode($json1, true);

        // Make second request (should be cache HIT)
        $request2 = $this->createAjaxRequest();
        $response2 = $this->controller->events($request2);
        $this->assertEquals('HIT', $response2->getHeader('X-Calendar-Cache'));

        // Modify the event
        $event->Title = 'Modified Event Title';
        $event->write();
        $event->publishRecursive();

        // Make third request (should be cache MISS - cache was invalidated)
        $request3 = $this->createAjaxRequest();
        $response3 = $this->controller->events($request3);
        $this->assertEquals('MISS', $response3->getHeader('X-Calendar-Cache'));
        $json3 = $response3->getBody();
        $data3 = json_decode($json3, true);

        // Verify the modified title appears
        $this->assertNotEquals($json1, $json3, 'Cached data should be different after modification');
        $foundModified = false;
        foreach ($data3 as $eventData) {
            if ($eventData['title'] === 'Modified Event Title') {
                $foundModified = true;
                break;
            }
        }
        $this->assertTrue($foundModified, 'Modified event title should appear in response');
    }

    /**
     * Test 4: New events appear immediately (cache invalidation on create)
     */
    public function testNewEventsAppearImmediately()
    {
        // Create initial event
        $event1 = EventPage::create([
            'Title' => 'Initial Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event1->write();
        $event1->publishRecursive();

        // Get event feed (populate cache)
        $request1 = $this->createAjaxRequest();
        $response1 = $this->controller->events($request1);
        $json1 = $response1->getBody();
        $data1 = json_decode($json1, true);
        $initialCount = count($data1);

        // Make second request to ensure cache is working
        $request2 = $this->createAjaxRequest();
        $response2 = $this->controller->events($request2);
        $this->assertEquals('HIT', $response2->getHeader('X-Calendar-Cache'));

        // Add new event
        $event2 = EventPage::create([
            'Title' => 'New Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->addDay()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event2->write();
        $event2->publishRecursive();

        // Get event feed again (should be cache MISS due to invalidation)
        $request3 = $this->createAjaxRequest();
        $response3 = $this->controller->events($request3);
        $this->assertEquals('MISS', $response3->getHeader('X-Calendar-Cache'));
        $json3 = $response3->getBody();
        $data3 = json_decode($json3, true);

        // Assert new event is present
        $this->assertGreaterThan($initialCount, count($data3), 'New event should be present immediately');
        $foundNew = false;
        foreach ($data3 as $eventData) {
            if ($eventData['title'] === 'New Event') {
                $foundNew = true;
                break;
            }
        }
        $this->assertTrue($foundNew, 'New event should appear in response');
    }

    /**
     * Test 5: EventException changes invalidate cache
     */
    public function testExceptionChangesInvalidateCache()
    {
        // Create a recurring event
        $event = EventPage::create([
            'Title' => 'Recurring Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'DAILY',
            'Interval' => 1,
            'RecursionEndDate' => Carbon::now()->addWeek()->format('Y-m-d'),
        ]);
        $event->write();
        $event->publishRecursive();

        // Get event feed (populate cache)
        $request1 = $this->createAjaxRequest();
        $response1 = $this->controller->events($request1);
        $this->assertEquals('MISS', $response1->getHeader('X-Calendar-Cache'));

        // Make second request to ensure cache is working
        $request2 = $this->createAjaxRequest();
        $response2 = $this->controller->events($request2);
        $this->assertEquals('HIT', $response2->getHeader('X-Calendar-Cache'));

        // Create EventException (delete an instance)
        $exceptionDate = Carbon::now()->addDay()->format('Y-m-d');
        $exception = EventException::create([
            'OriginalEventID' => $event->ID,
            'InstanceDate' => $exceptionDate,
            'Action' => 'DELETED',
            'Reason' => 'Test deletion',
        ]);
        $exception->write();

        // Get event feed again (should be cache MISS due to invalidation)
        $request3 = $this->createAjaxRequest();
        $response3 = $this->controller->events($request3);
        $this->assertEquals(
            'MISS',
            $response3->getHeader('X-Calendar-Cache'),
            'Cache should be invalidated after exception creation'
        );
    }

    /**
     * Test 6: Calendar changes invalidate cache
     */
    public function testCalendarChangesInvalidateCache()
    {
        // Create an event
        $event = EventPage::create([
            'Title' => 'Test Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        // Get event feed (populate cache)
        $request1 = $this->createAjaxRequest();
        $response1 = $this->controller->events($request1);
        $this->assertEquals('MISS', $response1->getHeader('X-Calendar-Cache'));

        // Make second request to ensure cache is working
        $request2 = $this->createAjaxRequest();
        $response2 = $this->controller->events($request2);
        $this->assertEquals('HIT', $response2->getHeader('X-Calendar-Cache'));

        // Modify calendar settings
        $this->calendar->EventsPerPage = 20;
        $this->calendar->write();
        $this->calendar->publishRecursive();

        // Get event feed again (should be cache MISS due to invalidation)
        $request3 = $this->createAjaxRequest();
        $response3 = $this->controller->events($request3);
        $this->assertEquals(
            'MISS',
            $response3->getHeader('X-Calendar-Cache'),
            'Cache should be invalidated after calendar modification'
        );
    }

    /**
     * Test 7: EventPage deletion invalidates cache
     */
    public function testEventPageDeletionInvalidatesCache()
    {
        // Create events
        $event1 = EventPage::create([
            'Title' => 'Event to Delete',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event1->write();
        $event1->publishRecursive();

        $event2 = EventPage::create([
            'Title' => 'Event to Keep',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->addDay()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event2->write();
        $event2->publishRecursive();

        // Get event feed (populate cache)
        $request1 = $this->createAjaxRequest();
        $response1 = $this->controller->events($request1);
        $json1 = $response1->getBody();
        $data1 = json_decode($json1, true);
        $initialCount = count($data1);

        // Make second request to ensure cache is working
        $request2 = $this->createAjaxRequest();
        $response2 = $this->controller->events($request2);
        $this->assertEquals('HIT', $response2->getHeader('X-Calendar-Cache'));

        // Delete event
        $event1->delete();

        // Get event feed again (should be cache MISS due to invalidation)
        $request3 = $this->createAjaxRequest();
        $response3 = $this->controller->events($request3);
        $this->assertEquals('MISS', $response3->getHeader('X-Calendar-Cache'));
        $json3 = $response3->getBody();
        $data3 = json_decode($json3, true);

        // Assert event count decreased
        $this->assertLessThan($initialCount, count($data3), 'Event count should decrease after deletion');
        $foundDeleted = false;
        foreach ($data3 as $eventData) {
            if ($eventData['title'] === 'Event to Delete') {
                $foundDeleted = true;
                break;
            }
        }
        $this->assertFalse($foundDeleted, 'Deleted event should not appear in response');
    }

    /**
     * Test 8: Category relationship changes invalidate cache
     */
    public function testCategoryRelationshipChangesInvalidateCache()
    {
        // Create a category with unique title to avoid validation errors
        $categoryTitle = 'Test Category ' . uniqid();
        $category = \Dynamic\Calendar\Model\Category::create([
            'Title' => $categoryTitle,
            'URLSegment' => 'test-category-' . uniqid(),
        ]);
        $category->write();

        // Create an event without categories
        $event = EventPage::create([
            'Title' => 'Event Without Category',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        // Get event feed (populate cache)
        $request1 = $this->createAjaxRequest();
        $response1 = $this->controller->events($request1);
        $this->assertEquals('MISS', $response1->getHeader('X-Calendar-Cache'));

        // Make second request to ensure cache is working
        $request2 = $this->createAjaxRequest();
        $response2 = $this->controller->events($request2);
        $this->assertEquals('HIT', $response2->getHeader('X-Calendar-Cache'));

        // Add category to event WITHOUT a trailing write(). ManyManyList::add()
        // fires no extension hooks, so no stamp bump happens here - the MISS
        // below is produced by the relation fingerprint in the cache key
        // observing the join-table change directly.
        $event->Categories()->add($category);

        // Get event feed again (should be cache MISS due to invalidation)
        $request3 = $this->createAjaxRequest();
        $response3 = $this->controller->events($request3);
        $this->assertEquals(
            'MISS',
            $response3->getHeader('X-Calendar-Cache'),
            'Cache should be invalidated after category added'
        );

        // Verify the cache works again after being repopulated
        $request4 = $this->createAjaxRequest();
        $response4 = $this->controller->events($request4);
        $this->assertEquals('HIT', $response4->getHeader('X-Calendar-Cache'), 'Cache should be repopulated');
    }

    /**
     * Helper method to create an AJAX request
     *
     * @return HTTPRequest
     */
    protected function createAjaxRequest(): HTTPRequest
    {
        $request = new HTTPRequest('GET', '/events');
        $request->addHeader('Accept', 'application/json');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        // Add date parameters to ensure consistent cache keys
        $start = Carbon::now()->subMonth()->format('Y-m-d');
        $end = Carbon::now()->addMonth()->format('Y-m-d');
        $request->setUrl('/events?start=' . $start . '&end=' . $end);

        return $request;
    }

    public function testRelationRemovalWithoutWriteInvalidatesCache()
    {
        $category = Category::create(['Title' => 'Removal Cat ' . uniqid()]);
        $category->write();

        $event = EventPage::create([
            'Title' => 'Removal Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->Categories()->add($category);
        $event->publishRecursive();

        $this->assertEquals('MISS', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));
        $this->assertEquals('HIT', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));

        // Pure relation removal, no write() - only the fingerprint sees this.
        $event->Categories()->remove($category);

        $this->assertEquals(
            'MISS',
            $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'),
            'A relation removal without an accompanying write() must invalidate the feed'
        );
    }

    public function testEventExceptionWriteInvalidatesCache()
    {
        $event = EventPage::create([
            'Title' => 'Exception Cache Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'WEEKLY',
            'Interval' => 1,
            'RecursionEndDate' => Carbon::now()->addMonths(2)->format('Y-m-d'),
        ]);
        $event->write();
        $event->publishRecursive();

        $this->assertEquals('MISS', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));
        $this->assertEquals('HIT', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));

        // An exception (deleted occurrence) must reach the feed cache via
        // OriginalEvent()->ParentID.
        $event->createException(Carbon::now()->addWeek()->format('Y-m-d'), 'DELETED');

        $this->assertEquals(
            'MISS',
            $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'),
            'An EventException write must invalidate the owning calendar\'s cached feeds'
        );
    }

    public function testFlushClearsWarmCache()
    {
        $event = EventPage::create([
            'Title' => 'Flush Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $this->assertEquals('MISS', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));
        $this->assertEquals('HIT', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));

        CalendarCacheVersion::flush();

        $this->assertEquals(
            'MISS',
            $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'),
            'flush() must invalidate warm feed caches'
        );

        // And caching must work again immediately after a flush (stamps are
        // re-seeded, not left absent).
        $this->assertEquals('HIT', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));
    }

    public function testDraftStageRequestBypassesCache()
    {
        $event = EventPage::create([
            'Title' => 'Stage Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        // Warm the Live cache.
        $this->assertEquals('MISS', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));
        $this->assertEquals('HIT', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));

        // A draft-stage request must neither serve the Live entry nor write
        // its own draft content into the shared pool.
        $bypass = Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::DRAFT);

            return $this->controller->events($this->createAjaxRequest());
        });

        $this->assertEquals(
            'BYPASS',
            $bypass->getHeader('X-Calendar-Cache'),
            'Draft-stage requests must bypass the feed cache entirely'
        );

        // The Live entry must be untouched.
        $this->assertEquals('HIT', $this->controller->events($this->createAjaxRequest())->getHeader('X-Calendar-Cache'));
    }
}
