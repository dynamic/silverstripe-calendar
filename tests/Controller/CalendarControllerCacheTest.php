<?php

namespace Dynamic\Calendar\Tests\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
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
     * AJAX events request carrying GET query vars.
     *
     * createAjaxRequest() puts its dates in the URL, which HTTPRequest does
     * not re-parse into query vars; these tests need getVar() to actually see
     * the filter, so the vars go through the constructor instead.
     *
     * @param array<string,mixed> $vars
     * @return HTTPRequest
     */
    protected function createAjaxRequestWithVars(array $vars = []): HTTPRequest
    {
        $request = new HTTPRequest('GET', 'events', array_merge([
            'start' => Carbon::now()->subMonth()->format('Y-m-d'),
            'end' => Carbon::now()->addMonth()->format('Y-m-d'),
        ], $vars));
        $request->addHeader('Accept', 'application/json');
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        return $request;
    }

    /**
     * Titles out of a response body, in feed order.
     *
     * @param string $body
     * @return array<int,string>
     */
    private function titlesFrom(string $body): array
    {
        return array_column(json_decode($body, true) ?? [], 'title');
    }

    /**
     * Test 11: events() returns the same HTTPResponse instance every call, so
     * capture the header and body immediately rather than retaining the object.
     *
     * @param HTTPRequest $request
     * @return array{cache: string|null, body: string}
     */
    private function snapshotResponse(HTTPRequest $request): array
    {
        $response = $this->controller->events($request);

        return [
            'cache' => $response->getHeader('X-Calendar-Cache'),
            'body' => $response->getBody(),
        ];
    }

    /**
     * Test 12 (#162): a live search filter must touch the CalendarJSON pool
     * neither by read nor by write.
     *
     * The reported hazard is cardinality: ?search=a1, ?search=a2, ... minted
     * one permanent entry each. The defence is that search-filtered responses
     * are never written, so a spy cache that records set() calls is the most
     * direct assertion of the pool not growing - stronger than inferring it
     * from a MISS header. expects(never()) on get() covers the other half:
     * the key no longer carries `search`, so reading for a search-filtered
     * request could serve some other request's payload.
     *
     * Caveat, so this is not mistaken for the only guard: a never() expectation
     * on a spy passes vacuously if the Injector registration above ever stops
     * taking effect. testSearchAfterWarmingUnfilteredStillReturnsOnlyMatches
     * covers the same invariant behaviourally through the real cache adapter -
     * the pair is deliberate, do not delete either one as redundant.
     */
    public function testSearchFilteredRequestsNeitherReadNorWriteTheCache()
    {
        $event = EventPage::create([
            'Title' => 'Cacheable Search Target',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('set');

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($cache),
                CacheFactory::class
            );

            $first = $this->snapshotResponse(
                $this->createAjaxRequestWithVars(['search' => 'Cacheable'])
            );
            $this->assertEquals('MISS', $first['cache']);
            $this->assertContains('Cacheable Search Target', $this->titlesFrom($first['body']));

            // Same search again: still no entry, so still MISS.
            $repeat = $this->snapshotResponse(
                $this->createAjaxRequestWithVars(['search' => 'Cacheable'])
            );
            $this->assertEquals('MISS', $repeat['cache'], 'A repeated search must find nothing');

            // Empty-result case: a needle matching nothing returns [] with no
            // error, and still writes nothing.
            $empty = $this->snapshotResponse(
                $this->createAjaxRequestWithVars(['search' => 'no-such-event-exists'])
            );
            $this->assertEquals('MISS', $empty['cache']);
            $this->assertSame([], json_decode($empty['body'], true), 'No match must be an empty feed');
        } finally {
            Injector::unnest();
        }
    }

    /**
     * Test 13 (#162): only the allowlisted filters may produce cache keys.
     *
     * Removing `search` from generateEventsCacheKey() must not collapse the
     * filters that remain - eventType and allDay still have to key distinctly,
     * and still have to round-trip (MISS then HIT). The recorded set() keys
     * make that mechanical rather than inferred from bodies, which can coincide
     * for legitimately different filter combinations. The final block asserts
     * the counter does not move at all for search-filtered requests, including
     * a repeat of an earlier value.
     */
    public function testOnlyAllowlistedFiltersProduceCacheKeys()
    {
        $timed = EventPage::create([
            'Title' => 'Keyed Timed One-off',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'StartTime' => '10:00:00',
            'AllDay' => 0,
            'Recursion' => 'NONE',
        ]);
        $timed->write();
        $timed->publishRecursive();

        $recurring = EventPage::create([
            'Title' => 'Keyed Recurring All-day',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'AllDay' => 1,
            'Recursion' => 'WEEKLY',
        ]);
        $recurring->write();
        $recurring->publishRecursive();

        $keys = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturnCallback(function (string $key) use (&$keys): bool {
            $keys[] = $key;
            return true;
        });

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($cache),
                CacheFactory::class
            );

            $cacheableSets = [
                'no filter' => [],
                'one-time' => ['eventType' => 'one-time'],
                'recurring' => ['eventType' => 'recurring'],
                'timed only' => ['allDay' => '0'],
                'all-day only' => ['allDay' => '1'],
                'both filters' => ['eventType' => 'recurring', 'allDay' => '1'],
            ];
            foreach ($cacheableSets as $vars) {
                $this->snapshotResponse($this->createAjaxRequestWithVars($vars));
            }

            $this->assertCount(6, $keys, 'Each cacheable filter combination writes one entry');
            $this->assertCount(
                6,
                array_unique($keys),
                'eventType and allDay must still produce distinct keys after search was removed'
            );

            $before = count($keys);
            $searchSets = [
                ['search' => 'a1'],
                ['search' => 'a2'],
                ['search' => 'a1'],
                ['search' => 'Keyed Timed One-off', 'eventType' => 'one-time'],
            ];
            foreach ($searchSets as $vars) {
                $this->snapshotResponse($this->createAjaxRequestWithVars($vars));
            }

            $this->assertCount(
                $before,
                $keys,
                'No search-filtered request - even one also carrying eventType - may write an entry'
            );
        } finally {
            Injector::unnest();
        }
    }

    /**
     * Test 14 (#162): the ordinary cacheable path is unchanged - MISS then HIT
     * on an identical second request, through the real cache adapter.
     *
     * Guards against the fix over-reaching: the issue asks to stop caching
     * search-filtered responses, not to disable the events cache. Every set
     * here has its own cache key, so each one really does start cold.
     */
    public function testNonSearchFilteredRequestsStillRoundTripThroughTheCache()
    {
        $category = Category::create(['Title' => 'Cache Filter Category ' . uniqid()]);
        $category->write();

        $event = EventPage::create([
            'Title' => 'Round Trip Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'StartTime' => '09:30:00',
            'AllDay' => 0,
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();
        $event->Categories()->add($category);

        $cacheableSets = [
            'date range only' => [],
            'eventType' => ['eventType' => 'one-time'],
            'allDay' => ['allDay' => '0'],
            'categories' => ['categories' => [$category->ID]],
        ];

        foreach ($cacheableSets as $label => $vars) {
            $first = $this->snapshotResponse($this->createAjaxRequestWithVars($vars));
            $second = $this->snapshotResponse($this->createAjaxRequestWithVars($vars));

            $this->assertEquals('MISS', $first['cache'], $label . ' must start as a MISS');
            $this->assertEquals('HIT', $second['cache'], $label . ' must hit on the identical repeat');
            $this->assertContains('Round Trip Event', $this->titlesFrom($second['body']), $label);
        }
    }

    /**
     * Test 15 (#162): ?search= (present but empty) is no filter at all, so it
     * must stay cacheable AND share the unfiltered entry rather than minting
     * its own. Without the fix it keyed separately; with it, the second
     * distinct-looking request is a HIT on the unfiltered payload.
     */
    public function testEmptySearchSharesTheUnfilteredCacheEntry()
    {
        $event = EventPage::create([
            'Title' => 'Empty Search Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $plainFirst = $this->snapshotResponse($this->createAjaxRequestWithVars());
        $this->assertEquals('MISS', $plainFirst['cache']);

        $emptySearch = $this->snapshotResponse($this->createAjaxRequestWithVars(['search' => '']));
        $this->assertEquals(
            'HIT',
            $emptySearch['cache'],
            'An empty search filters nothing, so it must be served the unfiltered entry'
        );
        $this->assertSame($plainFirst['body'], $emptySearch['body']);
        $this->assertContains('Empty Search Event', $this->titlesFrom($emptySearch['body']));
    }

    /**
     * Test 16 (#162): an array-typed ?search[]=x must not warn, fatal, or take
     * the uncached path.
     *
     * normaliseFilterParams() coerces a non-string search to '', so this
     * request genuinely filters nothing - its payload IS the ordinary feed,
     * which the key does describe. Asserting HIT here is deliberate: it pins
     * the branch the array value lands in, so a later change to either
     * normaliseFilterParams() or shouldCacheEvents() cannot quietly move it.
     */
    public function testArrayTypedSearchIsCoercedToNoSearchAndStaysCacheable()
    {
        $event = EventPage::create([
            'Title' => 'Array Guard Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $phpErrors = [];
        // Only warning/notice severities are collected; the handler is
        // installed solely around the two calls and restored in finally.
        $previous = set_error_handler(function (int $severity, string $message) use (&$phpErrors): bool {
            if (in_array($severity, [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE], true)) {
                $phpErrors[] = $severity . ': ' . $message;
            }

            return true;
        });

        try {
            $first = $this->snapshotResponse($this->createAjaxRequestWithVars(['search' => ['x']]));
            $second = $this->snapshotResponse($this->createAjaxRequestWithVars(['search' => ['x']]));
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $phpErrors, 'Array-typed search must not emit a warning or notice');
        $this->assertEquals('MISS', $first['cache']);
        $this->assertEquals('HIT', $second['cache'], '?search[]=x is coerced to no search, so it caches');
        $this->assertContains('Array Guard Event', $this->titlesFrom($first['body']));
    }

    /**
     * Test 17: the cache key must be derived from the date range the feed
     * actually queried, not from whichever raw param happened to be present.
     *
     * generateEventsCacheKey() used to prefer start/end while getFromDate()
     * and getToDate() prefer from/to, so a request carrying both names keyed
     * on one window and queried another. That is cache poisoning, not merely
     * wasted space: the poisoned (typically empty) body then served every
     * ordinary browser request for that window until the TTL expired.
     */
    public function testMixedDateParamNamesCannotPoisonTheFeed()
    {
        $event = EventPage::create([
            'Title' => 'June Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => '2025-06-10',
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        // Poison attempt: real window in start/end, empty window in from/to.
        // getFromDate()/getToDate() prefer from/to, so this queries nothing.
        $poisonAttempt = $this->snapshotResponse($this->createAjaxRequestWithVars([
            'start' => '2025-06-01',
            'end' => '2025-06-30',
            'from' => '1999-01-01',
            'to' => '1999-01-02',
        ]));
        $this->assertEquals('MISS', $poisonAttempt['cache']);
        $this->assertSame(
            [],
            json_decode($poisonAttempt['body'], true),
            'The mixed request must get the empty window it actually asked for'
        );

        // An ordinary browser request for the real window must NOT be served
        // that empty body - it keys on the window it queried.
        $normal = $this->snapshotResponse($this->createAjaxRequestWithVars([
            'start' => '2025-06-01',
            'end' => '2025-06-30',
        ]));
        $this->assertContains(
            'June Event',
            $this->titlesFrom($normal['body']),
            'A normal request must not be served the mixed-param request\'s empty payload'
        );
    }

    /**
     * Test 18: unparseable dates discard to the same no-filter window, so
     * they must share one entry instead of minting one permanent entry each.
     *
     * The params were hashed raw, so ?start=zzz1 and ?start=zzz2 - which the
     * query treats identically, as no date filter at all - still grew the pool.
     * This is the same cardinality growth #162 reports, through a sibling param.
     */
    public function testUnparseableDatesShareOneCacheEntry()
    {
        $event = EventPage::create([
            'Title' => 'Always Visible Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $keys = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturnCallback(function (string $key) use (&$keys): bool {
            $keys[] = $key;
            return true;
        });

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($cache),
                CacheFactory::class
            );

            foreach (['zzz1', 'zzz2', 'not-a-date', '2025-13-45'] as $garbage) {
                $this->snapshotResponse($this->createAjaxRequestWithVars([
                    'start' => $garbage,
                    'end' => $garbage,
                ]));
            }
        } finally {
            Injector::unnest();
        }

        $this->assertCount(4, $keys, 'Each garbage window should have written once');
        $this->assertCount(
            1,
            array_unique($keys),
            'All unparseable dates discard to the same no-filter window, so they '
                . 'must share a single cache entry rather than minting one each'
        );
        $this->assertStringContainsString('no-start', $keys[0]);
        $this->assertStringContainsString('no-end', $keys[0]);
    }

    /**
     * Test 19: spellings that resolve to the same category set share an entry
     * AND share a body.
     *
     * Key equality is only half the invariant - two spellings may share a key
     * only because they return the same events, so both halves are asserted.
     * Asserting keys alone is what let a poisoning hole through review: a local
     * (int) cast made ?categories[]=1.9 look like ?categories[]=1, but the ORM
     * binds '1.9' as a placeholder that matches no row, so it returns the
     * UNFILTERED feed. Same key, wider body. The key is now built from the IDs
     * the database matched, so the two cannot disagree, and the decimal spelling
     * is pinned below as a NON-member of this group with its wider body.
     */
    public function testEquivalentCategorySpellingsShareOneCacheEntryAndOneBody()
    {
        $category = Category::create(['Title' => 'Spellings Category ' . uniqid()]);
        $category->write();

        $event = EventPage::create([
            'Title' => 'Categorised Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();
        $event->Categories()->add($category);

        // A second event OUTSIDE the category. Any spelling that silently loses
        // the filter shows this title, so the body assertions can fail.
        $outside = EventPage::create([
            'Title' => 'Outside The Category',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $outside->write();
        $outside->publishRecursive();

        $captured = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturnCallback(
            function (string $key) use (&$captured): bool {
                $captured[] = ['key' => $key, 'titles' => null];
                return true;
            }
        );

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($cache),
                CacheFactory::class
            );

            $equivalent = [
                'plain' => [(string)$category->ID],
                'zero-padded' => ['0' . $category->ID],
                'space-padded' => [' ' . $category->ID . ' '],
                'duplicated' => [(string)$category->ID, (string)$category->ID],
            ];
            $results = [];
            foreach ($equivalent as $label => $rawCategories) {
                $snapshot = $this->snapshotResponse($this->createAjaxRequestWithVars([
                    'categories' => $rawCategories,
                ]));
                $results[$label] = [
                    'key' => $captured[count($captured) - 1]['key'],
                    'titles' => $this->titlesFrom($snapshot['body']),
                ];
            }

            // A subset of the same category set: 2 real categories.
            $second = Category::create(['Title' => 'Second Spellings Category ' . uniqid()]);
            $second->write();
            $other = EventPage::create([
                'Title' => 'Second Categorised Event',
                'ParentID' => $this->calendar->ID,
                // A day after $event's StartDate: getEventsFeed() sorts only by
                // StartDate, so an equal date makes the subset body's row order an
                // unspecified tie the DB is free to break differently per engine
                // (observed: MariaDB and MySQL disagree). A distinct date gives the
                // sort a total order, and the assertion below no longer needs to
                // tolerate either ordering.
                'StartDate' => Carbon::now()->addDay()->format('Y-m-d'),
                'Recursion' => 'NONE',
            ]);
            $other->write();
            $other->publishRecursive();
            $other->Categories()->add($second);

            $subset = $this->snapshotResponse($this->createAjaxRequestWithVars([
                'categories' => [(string)$second->ID, (string)$category->ID],
            ]));
            $subsetKey = $captured[count($captured) - 1]['key'];
            $subsetTitles = $this->titlesFrom($subset['body']);

            // Reverse order must agree with the forward order.
            $reversed = $this->snapshotResponse($this->createAjaxRequestWithVars([
                'categories' => [(string)$category->ID, (string)$second->ID],
            ]));
            $reversedKey = $captured[count($captured) - 1]['key'];

            // The decimal spelling: matches no row, so its feed is unfiltered.
            $decimal = $this->snapshotResponse($this->createAjaxRequestWithVars([
                'categories' => [(string)$category->ID . '.9'],
            ]));
            $decimalKey = $captured[count($captured) - 1]['key'];
            $decimalTitles = $this->titlesFrom($decimal['body']);
        } finally {
            Injector::unnest();
        }

        // Keys agree.
        $canonical = $results['plain']['key'];
        $this->assertCount(1, array_unique(array_column($results, 'key')), 'all spellings share one key');

        // Bodies agree - the half that keys alone cannot prove.
        foreach ($results as $label => $result) {
            $this->assertSame(
                ['Categorised Event'],
                $result['titles'],
                "spelling '{$label}' must return exactly the category's own event"
            );
        }

        // Order-insensitive subset, same key, same body.
        $this->assertSame($subsetKey, $reversedKey, 'category order must not matter');
        $this->assertNotSame($canonical, $subsetKey, 'a different set keys distinctly');
        $this->assertEquals(
            ['Categorised Event', 'Second Categorised Event'],
            $subsetTitles
        );

        // The poison shape, pinned as a non-member with its wider body.
        $this->assertNotSame(
            $canonical,
            $decimalKey,
            'A decimal spelling matches no row and must NOT key as the canonical ID'
        );
        $this->assertContains(
            'Outside The Category',
            $decimalTitles,
            'An unmatched category param leaves the feed unfiltered - which is exactly '
                . 'why it cannot share the canonical ID\'s key'
        );

        // Fixed-length keys: the resolved list is variable-length, so it is
        // digested rather than written out. Submitting a long list of
        // nonexistent IDs must not lengthen the key.
        $many = [(string)$category->ID];
        for ($i = 5000; $i <= 5500; $i++) {
            $many[] = (string)$i;
        }
        $before = count($captured);

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($cache),
                CacheFactory::class
            );
            $manySnapshot = $this->snapshotResponse($this->createAjaxRequestWithVars([
                'categories' => $many,
            ]));
        } finally {
            Injector::unnest();
        }

        $manyKey = $captured[$before]['key'];
        $this->assertSame(
            strlen($canonical),
            strlen($manyKey),
            'A 502-entry category list must not produce a longer key than a 1-entry list'
        );
        // And it collapses onto the single-real-ID key, because the 5500
        // nonexistent IDs matched nothing: same resolved set, same body, same
        // key. Previously each nonexistent ID minted its own permanent entry.
        $this->assertSame(
            $canonical,
            $manyKey,
            'Nonexistent IDs add nothing to the resolved set, so they must not mint '
                . 'their own entry - this is the cardinality bound'
        );
        $this->assertEquals(
            ['Categorised Event'],
            $this->titlesFrom($manySnapshot['body']),
            'Nonexistent IDs alongside a real one must still filter to the real one'
        );
    }

    /**
     * Test 19b (#162 review finding 3): the MAX_SUBMITTED_CATEGORIES cap
     * actually truncates real, matched categories - not just nonexistent IDs.
     *
     * The 502-entry assertion above only proves the cap bounds the KEY's
     * length. It submits 501 nonexistent IDs alongside one real one, and a
     * nonexistent ID is dropped by byIDs() regardless of any cap - that
     * assertion would pass identically even with array_slice() deleted
     * outright. This test submits MAX_SUBMITTED_CATEGORIES + 1 REAL, matching
     * category IDs instead, with the one that lands past the cap tied to its
     * own distinguishable event: it can only be excluded from the body if the
     * cap genuinely truncates matched IDs before the query runs, not merely
     * deduplicates misses.
     */
    public function testCategorySubmissionCapTruncatesRealCategoriesNotJustMisses()
    {
        $kept = Category::create(['Title' => 'Kept Category ' . uniqid()]);
        $kept->write();
        $keptEvent = EventPage::create([
            'Title' => 'Kept Category Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $keptEvent->write();
        $keptEvent->publishRecursive();
        $keptEvent->Categories()->add($kept);

        $truncated = Category::create(['Title' => 'Truncated Category ' . uniqid()]);
        $truncated->write();
        $truncatedEvent = EventPage::create([
            'Title' => 'Truncated Category Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $truncatedEvent->write();
        $truncatedEvent->publishRecursive();
        $truncatedEvent->Categories()->add($truncated);

        // Fillers: real, matched, but otherwise irrelevant - they exist purely
        // to occupy submission indices 1..MAX_SUBMITTED_CATEGORIES-1, so $kept
        // sits at index 0 and $truncated sits one past the cap.
        $fillerIDs = [];
        $fillerCount = CalendarController::MAX_SUBMITTED_CATEGORIES - 1;
        for ($i = 0; $i < $fillerCount; $i++) {
            $filler = Category::create(['Title' => 'Filler Category ' . $i . ' ' . uniqid()]);
            $filler->write();
            $fillerIDs[] = (string)$filler->ID;
        }

        $submitted = array_merge([(string)$kept->ID], $fillerIDs, [(string)$truncated->ID]);
        $this->assertCount(
            CalendarController::MAX_SUBMITTED_CATEGORIES + 1,
            $submitted,
            'the fixture itself must submit exactly one more than the cap, or this test proves nothing'
        );
        $keptSet = array_slice($submitted, 0, CalendarController::MAX_SUBMITTED_CATEGORIES);
        $this->assertNotContains(
            (string)$truncated->ID,
            $keptSet,
            'the fixture must place $truncated past the cap, or this test proves nothing'
        );

        $captured = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturnCallback(
            function (string $key) use (&$captured): bool {
                $captured[] = $key;
                return true;
            }
        );

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($cache),
                CacheFactory::class
            );

            $full = $this->snapshotResponse($this->createAjaxRequestWithVars([
                'categories' => $submitted,
            ]));
            $fullKey = $captured[count($captured) - 1];

            $capped = $this->snapshotResponse($this->createAjaxRequestWithVars([
                'categories' => $keptSet,
            ]));
            $cappedKey = $captured[count($captured) - 1];
        } finally {
            Injector::unnest();
        }

        $this->assertSame(
            $cappedKey,
            $fullKey,
            'a submission one over the cap must key identically to the capped set - the cap must '
                . 'apply before the key is built, the same way it applies before the query runs'
        );
        $this->assertContains(
            'Kept Category Event',
            $this->titlesFrom($full['body']),
            'the category at submission index 0 must survive the cap'
        );
        $this->assertNotContains(
            'Truncated Category Event',
            $this->titlesFrom($full['body']),
            'a real, matching category one past the cap must be excluded from the feed - if this '
                . 'assertion fails, the cap is not truncating real matches, only deduplicating misses'
        );
    }

    /**
     * Test 20: "no categories asked for" and "categories asked for, none
     * matched" produce different bodies, so they must not share a key.
     *
     * events() applies the calendar's default categories only in the else
     * branch - when the param is absent (or falsy). A param that is present but
     * matches nothing skips that fallback, and getEventsFeed() then skips
     * filtering too, so the feed comes back UNFILTERED. On a calendar WITH
     * default categories those bodies differ, so a shared 'no-cats' key would
     * serve the default-filtered feed to a request that turned filtering off.
     * Every unmatched spelling collapses to one 'cats-none' token, because they
     * all produce that same unfiltered body - which is also what bounds the pool
     * here: nonexistent IDs can no longer mint one entry each.
     */
    public function testUnmatchedCategoriesDoNotShareTheAbsentParamKey()
    {
        $defaultCategory = Category::create(['Title' => 'Default Category ' . uniqid()]);
        $defaultCategory->write();

        $event = EventPage::create([
            'Title' => 'Uncategorised Event',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        $this->calendar->DefaultCategories()->add($defaultCategory);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        $captured = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturnCallback(
            function (string $key) use (&$captured): bool {
                $captured[] = $key;
                return true;
            }
        );

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($cache),
                CacheFactory::class
            );

            $paramSets = [
                'absent' => null,
                'falsy scalar zero' => '0',
                'array containing zero' => ['0'],
                'array containing garbage' => ['nope'],
                'nonexistent positive id' => ['987654321'],
            ];
            $titles = [];
            foreach ($paramSets as $label => $rawCategories) {
                $vars = $rawCategories === null ? [] : ['categories' => $rawCategories];
                $snapshot = $this->snapshotResponse($this->createAjaxRequestWithVars($vars));
                $titles[$label] = $this->titlesFrom($snapshot['body']);
            }
        } finally {
            Injector::unnest();
        }

        $keys = [
            'absent' => $captured[0],
            'falsy scalar zero' => $captured[1],
            'array containing zero' => $captured[2],
            'array containing garbage' => $captured[3],
            'nonexistent positive id' => $captured[4],
        ];
        $this->assertCount(5, $captured, 'Each request wrote exactly one entry');

        // Absent and falsy-scalar take the default-category fallback.
        foreach (['absent', 'falsy scalar zero'] as $label) {
            $this->assertStringContainsString('no-cats', $keys[$label], $label);
        }
        $this->assertSame($keys['absent'], $keys['falsy scalar zero']);

        // Present-but-unmatched must NOT take that key, and must collapse
        // together - including nonexistent positive IDs, which previously minted
        // one permanent entry each.
        foreach (['array containing zero', 'array containing garbage', 'nonexistent positive id'] as $label) {
            $this->assertStringContainsString('cats-none', $keys[$label], $label);
            $this->assertNotSame($keys['absent'], $keys[$label], $label);
        }
        $this->assertSame($keys['array containing zero'], $keys['array containing garbage']);
        $this->assertSame($keys['array containing zero'], $keys['nonexistent positive id']);

        // And the bodies back the split up: the fallback filters by the default
        // category (this event has none, so nothing comes back), while an
        // unmatched param leaves the feed unfiltered.
        $this->assertSame([], $titles['absent'], 'absent filters by the default category');
        $this->assertSame([], $titles['falsy scalar zero'], 'falsy scalar filters by the default category');
        foreach (['array containing zero', 'array containing garbage', 'nonexistent positive id'] as $label) {
            $this->assertContains(
                'Uncategorised Event',
                $titles[$label],
                "{$label} must leave the feed unfiltered"
            );
        }
    }


    /**
     * Test 21: behavioural guard for the read gate, through the real cache.
     *
     * The spy-cache tests assert never()->get(), which proves the gate exists
     * only as long as the spy does. This one pins the BEHAVIOUR: warm the
     * unfiltered entry, then search. The searching visitor must see only the
     * matching event. If someone re-enables the read without putting `search`
     * back in the key, the warmed unfiltered payload is served instead and this
     * fails loudly rather than quietly widening a cached response.
     */
    public function testSearchAfterWarmingUnfilteredStillReturnsOnlyMatches()
    {
        $matching = EventPage::create([
            'Title' => 'Matched By Search',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $matching->write();
        $matching->publishRecursive();

        $unrelated = EventPage::create([
            'Title' => 'Must Never Leak Via Cache',
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::now()->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $unrelated->write();
        $unrelated->publishRecursive();

        // Warm the unfiltered entry (this one IS written and cached).
        $unfiltered = $this->snapshotResponse($this->createAjaxRequestWithVars());
        $unfilteredTitles = array_column(json_decode($unfiltered['body'], true) ?? [], 'title');
        $this->assertEquals('MISS', $unfiltered['cache']);
        $this->assertContains('Must Never Leak Via Cache', $unfilteredTitles);

        $warmed = $this->snapshotResponse($this->createAjaxRequestWithVars());
        $this->assertEquals('HIT', $warmed['cache'], 'Unfiltered feed must be cacheable');

        // Same cache pool, now a search request. It must NOT be handed the
        // warmed unfiltered body.
        $search = $this->snapshotResponse($this->createAjaxRequestWithVars([
            'search' => 'Matched By Search',
        ]));
        $searchTitles = array_column(json_decode($search['body'], true) ?? [], 'title');

        $this->assertEquals('MISS', $search['cache'], 'Search must bypass the cache');
        $this->assertSame(
            ['Matched By Search'],
            $searchTitles,
            'A search after a warm cache must see only its own matches'
        );
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
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::DRAFT);
            $draftKey = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());

            Versioned::set_stage(Versioned::LIVE);
            $liveKey = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());

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
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_reading_mode('Archive.2026-01-01.Stage');
            $archiveA = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());

            Versioned::set_reading_mode('Archive.2026-02-01.Stage');
            $archiveB = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());

            Versioned::set_stage(Versioned::LIVE);
            $liveKey = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());

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
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_reading_mode('');
            $emptyA = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());
            $emptyB = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());

            // null, not '', is the value the mode actually holds before anything
            // sets it (Versioned::$reading_mode's initial value), and md5(null)
            // is a PHP 8.1+ deprecation - so it must normalise to the same
            // component the empty string does rather than raising or diverging.
            Versioned::set_reading_mode(null);
            $nullKey = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());

            Versioned::set_stage(Versioned::LIVE);
            $liveKey = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());

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
        $bad = ['start' => 'not-a-date', 'end' => 'garbage/{}/\\@:'];
        $iso8601 = ['start' => '2026-08-13T00:00:00+00:00', 'end' => '2026-10-13T00:00:00+00:00'];
        $loose = ['start' => '2026-8-13', 'end' => '2026-10-13'];
        $previousMode = Versioned::get_reading_mode();
        try {
            Versioned::set_stage(Versioned::LIVE);
            $keyA = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($bad));
            $keyB = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($bad));
            $valid = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest());
            $noWindow = $this->generateEventsCacheKeyFor($this->createJsonFeedRequestWithoutWindow());

            $this->assertSame($keyA, $keyB, 'Identical bad input must produce an identical key');
            $this->assertNotSame($keyA, $valid, 'Bad input must not collide with a valid window');
            $this->assertSame(
                $keyA,
                $noWindow,
                'Unparseable dates resolve to no filter, so they must key with no window'
            );
            $this->assertSame(
                $keyA,
                $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($iso8601)),
                'ISO8601 datetimes fail the strict Y-m-d check and must share the no-window key'
            );
            // Components resolve independently: an unpadded start fails the strict
            // check while a well-formed end in the same request still keys, so the
            // request must not collapse onto the fully-unfiltered entry.
            $looseKey = $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($loose));
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
                $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($poisonParams)),
                $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($plainParams)),
                'A request carrying a legacy from must not share a key with one that does not'
            );
            $this->assertNotSame(
                $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($poisonToParams)),
                $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($plainParams)),
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
                    $this->generateEventsCacheKeyFor($this->createJsonFeedRequest($plainParams)),
                    $this->generateEventsCacheKeyFor($request),
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
                $this->generateEventsCacheKeyFor($onlyJunk),
                'An array-typed only value leaves that bound unset'
            );
            $onlyJunkResponse = $this->controller->events($onlyJunk);
            $this->assertIsArray(json_decode((string) $onlyJunkResponse->getBody(), true));
        } finally {
            Versioned::set_reading_mode($previousMode);
        }
    }

    /**
     * Reflection wrapper for generateEventsCacheKey(), which takes filters and
     * the resolved category IDs as parameters rather than deriving them itself
     * (issue #162 round 3) - so callers can't drift from what events() would
     * actually pass. Computes the same filters events() would via the public
     * normaliseFilterParams(); defaults resolvedCategoryIDs to null (no
     * categories param), which is correct for every test in this file that
     * isn't itself testing category resolution.
     *
     * @param HTTPRequest $request
     * @param int[]|null $resolvedCategoryIDs
     * @return string
     */
    protected function generateEventsCacheKeyFor(HTTPRequest $request, ?array $resolvedCategoryIDs = null): string
    {
        $reflection = new \ReflectionClass($this->controller);
        $generate = $reflection->getMethod('generateEventsCacheKey');
        $generate->setAccessible(true);
        $filters = CalendarController::normaliseFilterParams($request);

        return $generate->invoke($this->controller, $request, $filters, $resolvedCategoryIDs);
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
