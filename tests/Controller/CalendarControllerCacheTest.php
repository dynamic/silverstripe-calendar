<?php

namespace Dynamic\Calendar\Tests\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Cache\CacheFactory;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
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
     * Declared explicitly rather than relied on by default: this class has no
     * $fixture_file, and FunctionalTest does not default $usesDatabase to
     * true, so run standalone (not after a fixture-bearing class in the same
     * process) it errors on every test with "Table 'db.SiteTree' doesn't
     * exist." These tests are the #149 security guards - they should not
     * depend on suite ordering to have a database at all.
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
     * Setup test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

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

        // Add category to event (many-to-many relationship change)
        // Note: add() should trigger onAfterLink automatically
        $event->Categories()->add($category);

        // Force a write to ensure the relationship is saved and hooks are triggered
        $event->write();

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
     * Build a CacheFactory stub whose create() always returns the given cache,
     * regardless of the requested service name/params.
     *
     * @param CacheInterface $cache
     * @return CacheFactory
     */
    protected function makeCacheFactoryReturning(CacheInterface $cache): CacheFactory
    {
        $factory = $this->createStub(CacheFactory::class);
        $factory->method('create')->willReturn($cache);

        return $factory;
    }

    /**
     * Test 9: a failing cache write logs a warning and still returns the
     * built JSON. PSR-16 set() returns false on failure - previously this
     * was silently discarded, leaving every request an indistinguishable
     * permanent MISS with no signal that caching had stopped working
     * (issue #152).
     */
    public function testFailingCacheWriteLogsWarningAndStillReturnsJson()
    {
        $event = EventPage::create([
            'Title' => 'Event With Broken Cache',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $brokenCache = $this->createStub(CacheInterface::class);
        $brokenCache->method('get')->willReturn(null);
        $brokenCache->method('set')->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('failed to write events cache entry'));

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($brokenCache),
                CacheFactory::class
            );
            Injector::inst()->registerService($logger, LoggerInterface::class);

            $request = $this->createAjaxRequest();
            $response = $this->controller->events($request);

            $this->assertEquals('MISS', $response->getHeader('X-Calendar-Cache'));

            $data = json_decode($response->getBody(), true);
            $this->assertIsArray($data);
            $found = false;
            foreach ($data as $eventData) {
                if ($eventData['title'] === 'Event With Broken Cache') {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, 'Built JSON must still be returned when the cache write fails');
        } finally {
            Injector::unnest();
        }
    }

    /**
     * Test 10: if the logger service itself is unavailable/broken, a failing
     * cache write must still return the built JSON rather than surfacing a
     * fatal error - the guarding try/catch, now in LoggerFallback::logWithFallback()
     * behind logCacheWriteFailure(), exists specifically for this combined failure mode.
     */
    public function testFailingCacheWriteWithBrokenLoggerStillReturnsJson()
    {
        $event = EventPage::create([
            'Title' => 'Event With Broken Cache And Logger',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $brokenCache = $this->createStub(CacheInterface::class);
        $brokenCache->method('get')->willReturn(null);
        $brokenCache->method('set')->willReturn(false);

        $brokenLogger = $this->createStub(LoggerInterface::class);
        $brokenLogger->method('warning')->willThrowException(new \RuntimeException('logger unavailable'));

        $errorLogFile = tempnam(sys_get_temp_dir(), 'calendar-error-log-');
        $previousErrorLog = ini_set('error_log', $errorLogFile);

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($brokenCache),
                CacheFactory::class
            );
            Injector::inst()->registerService($brokenLogger, LoggerInterface::class);

            $request = $this->createAjaxRequest();
            $response = $this->controller->events($request);

            $this->assertEquals('MISS', $response->getHeader('X-Calendar-Cache'));

            $data = json_decode($response->getBody(), true);
            $this->assertIsArray($data);
            $found = false;
            foreach ($data as $eventData) {
                if ($eventData['title'] === 'Event With Broken Cache And Logger') {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue(
                $found,
                'Built JSON must still be returned even when both the cache write and the logger fail'
            );

            $loggedContent = file_get_contents($errorLogFile);
            $this->assertStringContainsString(
                'failed to write events cache entry',
                $loggedContent,
                'The error_log() fallback must actually fire when the logger service throws'
            );
            $this->assertStringContainsString('RuntimeException', $loggedContent);
        } finally {
            Injector::unnest();
            ini_set('error_log', $previousErrorLog);
            unlink($errorLogFile);
        }
    }

    /**
     * Helper method to create an AJAX request
     *
     * Deliberately does NOT carry date parameters. HTTPRequest::setUrl() does
     * not populate get-vars, so the requests built here key as
     * 'no-start'/'no-end' and every test using this helper exercises only that
     * part of the key. Passing real get-vars - and so covering the date part
     * of the key - is createJsonFeedRequest()'s job, and extending this
     * helper to do the same is tracked separately as #198.
     *
     * @return HTTPRequest
     */
    protected function createAjaxRequest(): HTTPRequest
    {
        $request = new HTTPRequest('GET', '/events');
        $request->addHeader('Accept', 'application/json');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        return $request;
    }

    /**
     * Test 11: A draft-stage request must never be served to a live-stage request
     *
     * End-to-end guard on acceptance criterion 2 of #149: a draft-stage
     * request followed by a live-stage request with identical params must not
     * return draft content, and the live request must be a MISS rather than
     * hitting the entry the draft request seeded.
     *
     * This passes on the default CacheFactory wiring with or without this
     * module's own key partition, because VersionedCacheAdapter::getKeyID()
     * already separates the two physical keys. Test 12 is what pins the
     * partition added for #149.
     */
    public function testDraftStageEntryIsNeverServedToLiveStageRequest()
    {
        $draftTitle = 'Unpublished Draft Event ' . uniqid();
        $event = EventPage::create([
            'Title' => $draftTitle,
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        // Draft only - deliberately never published.
        $event->write();

        // A published control event, so the live feed is provably not empty and
        // "does not contain the draft title" cannot be satisfied by an accident
        // of an empty calendar (wrong parent, unpublished ancestor, a broken
        // feed) rather than by correct stage filtering.
        $controlTitle = 'Published Control Event ' . uniqid();
        $control = EventPage::create([
            'Title' => $controlTitle,
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $control->write();
        $control->publishRecursive();

        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::DRAFT);
            $draft = $this->controller->events($this->createJsonFeedRequest());
            $this->assertEquals('MISS', $draft->getHeader('X-Calendar-Cache'));
            $this->assertStringContainsString(
                $draftTitle,
                (string) $draft->getBody(),
                'A draft-stage request must see the unpublished event'
            );

            // The draft entry is really written: an identical draft request hits it.
            $draftRepeat = $this->controller->events($this->createJsonFeedRequest());
            $this->assertEquals(
                'HIT',
                $draftRepeat->getHeader('X-Calendar-Cache'),
                'The draft-stage response must have been cached'
            );

            Versioned::set_stage(Versioned::LIVE);
            $live = $this->controller->events($this->createJsonFeedRequest());
            $this->assertEquals(
                'MISS',
                $live->getHeader('X-Calendar-Cache'),
                'A live-stage request must not hit the entry the draft request wrote'
            );
            $liveBody = (string) $live->getBody();
            $this->assertStringContainsString(
                $controlTitle,
                $liveBody,
                'The live feed must contain the published event - otherwise the '
                    . 'negative assertion below proves nothing'
            );
            $this->assertStringNotContainsString(
                $draftTitle,
                $liveBody,
                'A live-stage request must never receive draft content'
            );
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Test 12: The cache key itself differs between draft and live reading modes
     *
     * The partition must be a property of this module's key, not only of the
     * VersionedCacheAdapter the default CacheFactory wiring happens to wrap the
     * backend in. Reached by reflection so the key stays out of the public API.
     *
     * This is the discriminating test for #149: it fails on pre-fix code.
     */
    public function testCacheKeyDiffersBetweenDraftAndLiveReadingModes()
    {
        $reflection = new \ReflectionClass($this->controller);
        $generate = $reflection->getMethod('generateEventsCacheKey');
        $generate->setAccessible(true);
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::DRAFT);
            $draftKey = $generate->invoke($this->controller, $this->createJsonFeedRequest());

            Versioned::set_stage(Versioned::LIVE);
            $liveKey = $generate->invoke($this->controller, $this->createJsonFeedRequest());

            $this->assertStringStartsWith('calendar_json_' . $this->calendar->ID . '_', $draftKey);
            $this->assertStringStartsWith('calendar_json_' . $this->calendar->ID . '_', $liveKey);
            $this->assertNotSame(
                $draftKey,
                $liveKey,
                'Draft and live must never share a cache key (issue #149)'
            );
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Test 13: Distinct archive reading modes get distinct keys
     *
     * Versioned::get_stage() returns null for every Archive.<date>.<stage> mode,
     * so keying on the stage alone would collapse all archive dates together.
     * Discriminating: fails on pre-fix code.
     */
    public function testCacheKeyDistinguishesArchiveReadingModes()
    {
        $reflection = new \ReflectionClass($this->controller);
        $generate = $reflection->getMethod('generateEventsCacheKey');
        $generate->setAccessible(true);
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_reading_mode('Archive.2026-01-01.Stage');
            $archiveA = $generate->invoke($this->controller, $this->createJsonFeedRequest());

            Versioned::set_reading_mode('Archive.2026-02-01.Stage');
            $archiveB = $generate->invoke($this->controller, $this->createJsonFeedRequest());

            Versioned::set_stage(Versioned::LIVE);
            $liveKey = $generate->invoke($this->controller, $this->createJsonFeedRequest());

            $this->assertNotSame($archiveA, $archiveB, 'Distinct archive dates must not share a key');
            $this->assertNotSame($archiveA, $liveKey, 'Archive and live must not share a key');
            $this->assertNotSame($archiveB, $liveKey, 'The second archive date must not share a live key');
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Test 14: An empty reading mode is still deterministic and still partitioned
     *
     * VersionedCacheAdapter::getKeyID() appends nothing when the reading mode is
     * falsy (Versioned::reset() sets it to ''), which is the case where this
     * module's own key is the only partition - so it must still differ per mode.
     * Discriminating: fails on pre-fix code.
     */
    public function testCacheKeyHandlesEmptyReadingMode()
    {
        $reflection = new \ReflectionClass($this->controller);
        $generate = $reflection->getMethod('generateEventsCacheKey');
        $generate->setAccessible(true);
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_reading_mode('');
            $emptyA = $generate->invoke($this->controller, $this->createJsonFeedRequest());
            $emptyB = $generate->invoke($this->controller, $this->createJsonFeedRequest());

            // null, not '', is the value the mode actually holds before anything
            // sets it (Versioned::$reading_mode's initial value), and md5(null)
            // is a PHP 8.1+ deprecation - so it must normalise to the same
            // component the empty string does rather than raising or diverging.
            Versioned::set_reading_mode(null);
            $nullKey = $generate->invoke($this->controller, $this->createJsonFeedRequest());

            Versioned::set_stage(Versioned::LIVE);
            $liveKey = $generate->invoke($this->controller, $this->createJsonFeedRequest());

            $this->assertSame($emptyA, $emptyB, 'An empty reading mode must key deterministically');
            $this->assertSame($emptyA, $nullKey, 'A null mode must key as the empty mode does');
            $this->assertNotSame($emptyA, $liveKey, 'An empty reading mode must not share a live key');
            $this->assertNotSame($nullKey, $liveKey, 'A null mode must not share a live key');
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Test 15: Same-stage caching still works (no regression in hit behaviour)
     */
    public function testSameStageRequestsStillHitCacheOnLiveStage()
    {
        $liveEvent = EventPage::create([
            'Title' => 'Published Live Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $liveEvent->write();
        // Must be published, or the live-stage feed is empty and the body
        // comparison below compares '[]' with '[]'.
        $liveEvent->publishRecursive();

        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::LIVE);
            $first = $this->controller->events($this->createJsonFeedRequest());
            $this->assertEquals('MISS', $first->getHeader('X-Calendar-Cache'));
            // Snapshot the body: Controller::getResponse() hands back the same
            // response object on every call, so comparing $first against
            // $second later would compare one object with itself.
            $firstBody = (string) $first->getBody();
            $this->assertStringContainsString(
                'Published Live Event',
                $firstBody,
                'The fixture must be visible on the live stage or this test is vacuous'
            );

            $second = $this->controller->events($this->createJsonFeedRequest());
            $this->assertEquals('HIT', $second->getHeader('X-Calendar-Cache'));
            $this->assertSame($firstBody, (string) $second->getBody());
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Test 16: An empty calendar caches and re-serves per stage
     *
     * Integration coverage for the empty result set; not discriminating, it
     * passes on pre-fix code too. Tests 12-14 and 18 are the guards on the
     * #149 partition.
     */
    public function testEmptyCalendarCachesEmptyFeedPerStage()
    {
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::DRAFT);
            $draftFirst = $this->controller->events($this->createJsonFeedRequest());
            $this->assertEquals('[]', trim((string) $draftFirst->getBody()));
            $this->assertEquals('MISS', $draftFirst->getHeader('X-Calendar-Cache'));

            $draftRepeat = $this->controller->events($this->createJsonFeedRequest());
            $this->assertEquals('HIT', $draftRepeat->getHeader('X-Calendar-Cache'));

            Versioned::set_stage(Versioned::LIVE);
            $live = $this->controller->events($this->createJsonFeedRequest());
            $this->assertEquals('MISS', $live->getHeader('X-Calendar-Cache'));
            $this->assertEquals('[]', trim((string) $live->getBody()));
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Test 17: Unparseable date parameters key deterministically and do not fatal
     *
     * getFromDate()/getToDate() accept only a strict Y-m-d, so anything else -
     * garbage, ISO8601 datetimes, unpadded days - resolves to null, i.e. no
     * filter. All of those must share the no-window key, because that is the
     * identical unfiltered body they produce, while a real window must not.
     * Also asserts the request path itself survives such input, not just the
     * key builder.
     *
     * String input only; array-typed parameters are test 19's case.
     */
    public function testCacheKeyHandlesUnparseableDateParameters()
    {
        $reflection = new \ReflectionClass($this->controller);
        $generate = $reflection->getMethod('generateEventsCacheKey');
        $generate->setAccessible(true);
        $bad = ['start' => 'not-a-date', 'end' => 'garbage/{}/\\@:'];
        $iso8601 = ['start' => '2026-08-13T00:00:00+00:00', 'end' => '2026-10-13T00:00:00+00:00'];
        $loose = ['start' => '2026-8-13', 'end' => '2026-10-13'];
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::LIVE);
            $keyA = $generate->invoke($this->controller, $this->createJsonFeedRequest($bad));
            $keyB = $generate->invoke($this->controller, $this->createJsonFeedRequest($bad));
            $valid = $generate->invoke($this->controller, $this->createJsonFeedRequest());
            $noWindow = $generate->invoke($this->controller, $this->createJsonFeedRequestWithoutWindow());

            $this->assertSame($keyA, $keyB, 'Identical bad input must produce an identical key');
            $this->assertNotSame($keyA, $valid, 'Bad input must not collide with a valid window');
            $this->assertSame(
                $keyA,
                $noWindow,
                'Unparseable dates resolve to no filter, so they must key with no window'
            );
            $this->assertSame(
                $keyA,
                $generate->invoke($this->controller, $this->createJsonFeedRequest($iso8601)),
                'ISO8601 datetimes fail the strict Y-m-d check and must share the no-window key'
            );
            // Components resolve independently: an unpadded start fails the strict
            // check while a well-formed end in the same request still keys, so the
            // request must not collapse onto the fully-unfiltered entry.
            $looseKey = $generate->invoke($this->controller, $this->createJsonFeedRequest($loose));
            $this->assertStringContainsString(
                'no-start',
                $looseKey,
                'An unpadded start fails the strict Y-m-d check'
            );
            $this->assertStringNotContainsString(
                'no-end',
                $looseKey,
                'A well-formed end in the same request must still key on its own'
            );
            $this->assertNotSame($keyA, $looseKey, 'Partially valid input must not collide with no filter at all');

            // And the request path must not fatal on it either.
            $response = $this->controller->events($this->createJsonFeedRequest($bad));
            $this->assertEquals('MISS', $response->getHeader('X-Calendar-Cache'));
            $this->assertIsArray(json_decode((string) $response->getBody(), true));
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Test 18: The legacy from/to parameters cannot collide with start/end
     *
     * getFromDate()/getToDate() prefer the legacy 'from'/'to' over
     * 'start'/'end'; reading the raw parameters instead prefers 'start'/'end'.
     * Keying on the raw values would therefore give '?start=A&end=B&from=C'
     * (window C..B) and '?start=A&end=B' (window A..B) the same key, and on a
     * public unauthenticated endpoint either body could be served to the other
     * for the whole TTL. Keying through the accessors prevents it.
     * Discriminating: fails on pre-#149 code.
     */
    public function testLegacyFromToParamsDoNotCollideWithStartEnd()
    {
        $near = EventPage::create([
            'Title' => 'NearEvent',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $near->write();
        $near->publishRecursive();

        $far = EventPage::create([
            'Title' => 'FarEvent',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->addMonths(8)->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $far->write();
        $far->publishRecursive();

        $reflection = new \ReflectionClass($this->controller);
        $generate = $reflection->getMethod('generateEventsCacheKey');
        $generate->setAccessible(true);
        $windowStart = Carbon::now()->subMonth()->format('Y-m-d');
        $windowEnd = Carbon::now()->addMonths(18)->format('Y-m-d');
        $shiftedFrom = Carbon::now()->addMonths(6)->format('Y-m-d');

        $poisonParams = ['start' => $windowStart, 'end' => $windowEnd, 'from' => $shiftedFrom];
        $plainParams = ['start' => $windowStart, 'end' => $windowEnd];
        $shiftedTo = Carbon::now()->addMonths(3)->format('Y-m-d');
        // Same collision on the other end: 'to' wins over 'end' in getToDate().
        $poisonToParams = ['start' => $windowStart, 'end' => $windowEnd, 'to' => $shiftedTo];

        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::LIVE);

            $this->assertNotSame(
                $generate->invoke($this->controller, $this->createJsonFeedRequest($poisonParams)),
                $generate->invoke($this->controller, $this->createJsonFeedRequest($plainParams)),
                'A request carrying a legacy from must not share a key with one that does not'
            );
            $this->assertNotSame(
                $generate->invoke($this->controller, $this->createJsonFeedRequest($poisonToParams)),
                $generate->invoke($this->controller, $this->createJsonFeedRequest($plainParams)),
                'A request carrying a legacy to must not share a key with one that does not'
            );

            // Key-level only for the to/end mirror. A behavioural pair would need
            // the plain request to be a MISS, and any control request with the
            // same resolved window as the poisoned one is by definition a HIT
            // after it - which is the correct behaviour, not a leak. The
            // behavioural half is covered by the from/start case below.

            // Seed the cache with the narrowed window, then ask for the plain one.
            $narrowed = $this->controller->events($this->createJsonFeedRequest($poisonParams));
            $narrowedBody = (string) $narrowed->getBody();
            $this->assertEquals('MISS', $narrowed->getHeader('X-Calendar-Cache'));
            $this->assertStringNotContainsString(
                'NearEvent',
                $narrowedBody,
                'The narrowed window must not include the near event'
            );

            $plain = $this->controller->events($this->createJsonFeedRequest($plainParams));
            $this->assertEquals(
                'MISS',
                $plain->getHeader('X-Calendar-Cache'),
                'The plain window must not be served the narrowed window\'s cached body'
            );
            $this->assertStringContainsString(
                'NearEvent',
                (string) $plain->getBody(),
                'The plain window must still contain the near event'
            );
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Test 19: Array-typed date parameters neither fatal nor widen the window
     *
     * getFromDate()/getToDate() hand the request var to the string-typed
     * Carbon::hasFormat(), so an array is an uncaught TypeError - and since the
     * cache key resolves dates through those accessors, that lands on the cache
     * path ahead of the cache read, where a warm cache used to answer first.
     * The is_string() guards stop the fatal; the fall-through in the same
     * methods keeps a junk `?to[]=` from discarding a valid `&end=` and so
     * silently dropping the upper bound of the feed.
     *
     * Two cases, both pinned: junk alongside a valid value for the same bound
     * must be ignored entirely (same key, served from cache, same body); junk as
     * the only value for a bound leaves that bound unset, unfiltered, no fatal.
     * Regression guard for this change - fails without the guards.
     */
    public function testArrayTypedDateParametersDoNotFatalOrWidenTheWindow()
    {
        $event = EventPage::create([
            'Title' => 'ArrayParamEvent',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $windowStart = Carbon::now()->subMonth()->format('Y-m-d');
        $windowEnd = Carbon::now()->addMonth()->format('Y-m-d');
        $plainParams = ['start' => $windowStart, 'end' => $windowEnd];

        $reflection = new \ReflectionClass($this->controller);
        $generate = $reflection->getMethod('generateEventsCacheKey');
        $generate->setAccessible(true);
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::LIVE);

            // Warm the cache with a well-formed request.
            $warm = $this->controller->events($this->createJsonFeedRequest($plainParams));
            $this->assertEquals('MISS', $warm->getHeader('X-Calendar-Cache'));
            $warmBody = (string) $warm->getBody();

            // Junk supplied alongside a valid value for the same bound: the
            // valid one must survive, so the window is unchanged.
            $alongsideValid = [
                'array-typed to' => ['to' => ['2026-01-01']],
                'array-typed from' => ['from' => ['2026-01-01']],
            ];
            foreach ($alongsideValid as $label => $junk) {
                $params = array_merge($plainParams, $junk);
                $request = $this->createJsonFeedRequest($params);

                $this->assertSame(
                    $generate->invoke($this->controller, $this->createJsonFeedRequest($plainParams)),
                    $generate->invoke($this->controller, $request),
                    $label . ' must be ignored, leaving the window and its key unchanged'
                );

                $response = $this->controller->events($request);
                $this->assertEquals(
                    'HIT',
                    $response->getHeader('X-Calendar-Cache'),
                    $label . ' must be served from the entry the clean request wrote'
                );
                $this->assertSame(
                    $warmBody,
                    (string) $response->getBody(),
                    $label . ' must not change the body'
                );
            }

            // Junk as the ONLY value for a bound: nothing valid remains to fall
            // back to, so that bound is dropped rather than fataling. Pinned so
            // it reads as deliberate rather than incidental.
            $onlyJunk = $this->createJsonFeedRequest([
                'end' => $windowEnd,
                'start' => ['2026-01-01'],
            ]);
            $this->assertStringContainsString(
                'no-start',
                $generate->invoke($this->controller, $onlyJunk),
                'An array-typed only value leaves that bound unset'
            );
            $onlyJunkResponse = $this->controller->events($onlyJunk);
            $this->assertIsArray(json_decode((string) $onlyJunkResponse->getBody(), true));
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Helper: an AJAX feed request with real get-vars
     *
     * HTTPRequest::getVar() only reads the constructor's get-vars, so unlike
     * createAjaxRequest() this actually populates start/end (setUrl() alone
     * leaves them null, which keys as 'no-start'/'no-end').
     *
     * Note that Controller::getResponse() returns the controller's single shared
     * HTTPResponse, so two calls to events() return the same object - snapshot
     * any body you need to compare across calls.
     *
     * @param array $params
     * @return HTTPRequest
     */
    protected function createJsonFeedRequest(array $params = []): HTTPRequest
    {
        $params = array_merge([
            'start' => Carbon::now()->subMonth()->format('Y-m-d'),
            'end' => Carbon::now()->addMonth()->format('Y-m-d'),
        ], $params);

        $request = new HTTPRequest('GET', '/events', $params);
        $request->addHeader('Accept', 'application/json');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        return $request;
    }

    /**
     * Helper: an AJAX feed request carrying no date parameters at all
     *
     * createJsonFeedRequest() supplies a default window, so a request with no
     * dates at all - the state unparseable dates resolve to - needs its own
     * constructor call.
     *
     * @return HTTPRequest
     */
    protected function createJsonFeedRequestWithoutWindow(): HTTPRequest
    {
        $request = new HTTPRequest('GET', '/events', []);
        $request->addHeader('Accept', 'application/json');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        return $request;
    }
}
