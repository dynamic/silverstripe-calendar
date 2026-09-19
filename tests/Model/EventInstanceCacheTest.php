<?php

namespace Dynamic\Calendar\Tests\Model;

use Carbon\Carbon;
use Dynamic\Calendar\Model\EventInstanceCache;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use SilverStripe\Core\Cache\CacheFactory;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Event Instance Cache Test
 *
 * Covers the cache-write failure signal for issue #160. PSR-16 set() returns false
 * on failure and setCachedInstances() discarded the value, so a persistent backend
 * that stopped persisting looked exactly like ordinary cache misses - no warning,
 * no error, a permanent miss with nothing recorded.
 *
 * Also covers the two properties that make the signal safe to emit at all: the
 * in-request memory cache is populated whatever the backend answers, so a failed
 * persistent write cannot degrade the request in flight; and emission is bounded to
 * once per request, because setCachedInstances() is reached once per recurring event
 * from Calendar::getEventsFeed() via CarbonRecursion::getCachedOccurrences().
 *
 * @package Dynamic\Calendar\Tests\Model
 */
class EventInstanceCacheTest extends SapphireTest
{
    /**
     * Declared explicitly rather than relied on by default: this class has no
     * $fixture_file, and SapphireTest declares `protected $usesDatabase = null`,
     * so without it the temp database is never provisioned and setUp()'s
     * Calendar/EventPage writes go to the live dev database and are never rolled
     * back. Declared untyped and non-static to match SapphireTest's own
     * declaration - a typed or static redeclaration is a compile-time fatal.
     *
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var Calendar
     */
    protected $calendar;

    /**
     * @var EventPage
     */
    protected $event;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetInstanceCacheState();

        $this->calendar = Calendar::create([
            'Title' => 'Instance Cache Test Calendar',
            'URLSegment' => 'instance-cache-test-calendar',
        ]);
        $this->calendar->write();
        $this->calendar->publishRecursive();

        $this->event = $this->createEvent('Instance Cache Event', 'instance-cache-event');

        // Fixture writes memoise the real backend through EventPage's own cache
        // invalidation hooks; drop it again so the doubles installed by each test
        // are the ones actually resolved.
        $this->resetInstanceCacheState();
    }

    /**
     * Tear down the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->resetInstanceCacheState();

        parent::tearDown();
    }

    /**
     * Create a non-recurring EventPage under this test's calendar.
     *
     * @param string $title
     * @param string $urlSegment
     * @return EventPage
     */
    protected function createEvent(string $title, string $urlSegment): EventPage
    {
        $event = EventPage::create([
            'Title' => $title,
            'URLSegment' => $urlSegment,
            'ParentID' => $this->calendar->ID,
            'StartDate' => Carbon::parse('2026-04-01')->format('Y-m-d'),
            'Recursion' => 'NONE',
        ]);
        $event->write();
        $event->publishRecursive();

        return $event;
    }

    /**
     * Drop EventInstanceCache's memoised backend and its per-request state.
     *
     * Both are private statics, which survive SapphireTest's per-test Injector
     * reset: an un-dropped backend leaks one test's double into the next, and an
     * un-dropped $write_failure_logged makes a later test's expected warning look
     * like it was never emitted.
     *
     * @return void
     */
    protected function resetInstanceCacheState(): void
    {
        $reflection = new ReflectionClass(EventInstanceCache::class);
        $reflection->getProperty('cache_instance')->setValue(null, null);
        $reflection->getProperty('memory_cache')->setValue(null, []);

        // Guarded rather than assumed: $write_failure_logged is introduced by this
        // fix, so the pre-fix implementation this suite is also run against, to
        // prove the tests discriminate, has no such property. An unguarded reset
        // would then throw in setUp() - a structural error that would be reported
        // alongside the real failures and make the falsifiability run ambiguous
        // about whether it detected the missing warning at all.
        if ($reflection->hasProperty('write_failure_logged')) {
            $reflection->getProperty('write_failure_logged')->setValue(null, false);
        }
    }

    /**
     * Read EventInstanceCache's private per-request memory cache.
     *
     * @return array
     */
    protected function getMemoryCache(): array
    {
        return (new ReflectionClass(EventInstanceCache::class))
            ->getProperty('memory_cache')
            ->getValue(null);
    }

    /**
     * Build a CacheFactory stub whose create() always returns the given cache,
     * regardless of the requested service name - same helper shape as
     * CalendarControllerCacheTest::makeCacheFactoryReturning().
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
     * Logger mock that records every warning() message instead of just counting.
     *
     * @param array<int,string> $collected Passed by reference; filled with the messages.
     * @return LoggerInterface
     */
    protected function recordingLogger(array &$collected): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(
            function (string $message) use (&$collected): void {
                $collected[] = $message;
            }
        );

        return $logger;
    }

    /**
     * The cache key EventInstanceCache would generate for this event and range.
     *
     * Generated by the class's own private generateCacheKey() rather than
     * reimplemented here: the key embeds a hash of LastEdited/Recursion/Interval/
     * RecursionEndDate, so a local copy would drift and the assertions below would
     * fail for the wrong reason.
     *
     * @param EventPage $event
     * @param string $start
     * @param string $end
     * @return string
     */
    protected function generateCacheKey(EventPage $event, string $start, string $end): string
    {
        $method = new ReflectionClass(EventInstanceCache::class);

        return $method->getMethod('generateCacheKey')->invoke(null, $event, $start, $end);
    }

    /**
     * Test 1: a successful write logs nothing, and the instances are readable.
     *
     * The control case - the new guard must not turn a working cache into noise.
     *
     * @return void
     */
    public function testSuccessfulWriteLogsNothingAndStaysReadable()
    {
        $workingCache = $this->createStub(CacheInterface::class);
        $workingCache->method('get')->willReturn(null);
        $workingCache->method('set')->willReturn(true);

        $warnings = [];
        $logger = $this->recordingLogger($warnings);

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($workingCache),
                CacheFactory::class
            );
            Injector::inst()->registerService($logger, LoggerInterface::class);

            $instances = [['title' => 'Occurrence One']];
            EventInstanceCache::setCachedInstances($this->event, '2026-04-01', '2026-04-30', $instances);

            $this->assertSame([], $warnings, 'A successful cache write must not log a warning');
            $this->assertSame(
                $instances,
                EventInstanceCache::getCachedInstances($this->event, '2026-04-01', '2026-04-30'),
                'Written instances must be readable back'
            );
        } finally {
            Injector::unnest();
        }
    }

    /**
     * Test 2: a false-returning set() logs exactly one warning naming the class
     * and the cache key - the defect in issue #160.
     *
     * @return void
     */
    public function testFailedWriteLogsWarningNamingClassAndKey()
    {
        $brokenCache = $this->createStub(CacheInterface::class);
        $brokenCache->method('get')->willReturn(null);
        $brokenCache->method('set')->willReturn(false);

        $warnings = [];
        $logger = $this->recordingLogger($warnings);

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($brokenCache),
                CacheFactory::class
            );
            Injector::inst()->registerService($logger, LoggerInterface::class);

            EventInstanceCache::setCachedInstances(
                $this->event,
                '2026-04-01',
                '2026-04-30',
                [['title' => 'Occurrence One']]
            );

            $this->assertCount(1, $warnings, 'A failed cache write must log exactly one warning');
            $this->assertStringContainsString('EventInstanceCache', $warnings[0]);
            $this->assertStringContainsString(
                $this->generateCacheKey($this->event, '2026-04-01', '2026-04-30'),
                $warnings[0],
                'The warning must name the cache key that could not be written'
            );
        } finally {
            Injector::unnest();
        }
    }

    /**
     * Test 3: the memory cache is populated even when the persistent write fails.
     *
     * Ordering property, not incidental: the in-flight request must be served from
     * memory whatever the backend answers, so a dead backend costs noise, not a
     * degraded response.
     *
     * @return void
     */
    public function testFailedWriteStillPopulatesMemoryCache()
    {
        $brokenCache = $this->createStub(CacheInterface::class);
        $brokenCache->method('get')->willReturn(null);
        $brokenCache->method('set')->willReturn(false);

        $warnings = [];

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($brokenCache),
                CacheFactory::class
            );
            Injector::inst()->registerService($this->recordingLogger($warnings), LoggerInterface::class);

            $instances = [['title' => 'Served From Memory']];
            EventInstanceCache::setCachedInstances($this->event, '2026-04-01', '2026-04-30', $instances);

            $this->assertSame(
                $instances,
                EventInstanceCache::getCachedInstances($this->event, '2026-04-01', '2026-04-30'),
                'A failed persistent write must not degrade the in-flight request'
            );
            $this->assertCount(1, $warnings, 'Serving from memory must not suppress the failure signal');
            $this->assertNotEmpty(
                $this->getMemoryCache(),
                'The per-request memory cache must hold the entry the backend rejected'
            );
        } finally {
            Injector::unnest();
        }
    }

    /**
     * Test 4: repeated failures across distinct events collapse to one warning per
     * request, and clearAllCache() re-arms the guard.
     *
     * Without the bound this is N warnings per request - one per recurring event -
     * which is what stopped the previous attempt on this issue at review.
     *
     * @return void
     */
    public function testRepeatedFailuresAreBoundedAndReArmedByClearAllCache()
    {
        $brokenCache = $this->createStub(CacheInterface::class);
        $brokenCache->method('get')->willReturn(null);
        $brokenCache->method('set')->willReturn(false);

        $second = $this->createEvent('Bounded Event Two', 'bounded-event-two');
        $third = $this->createEvent('Bounded Event Three', 'bounded-event-three');

        // Re-arm after the fixture writes: EventPage's own invalidation hooks call
        // clearEventCache(), which resolves and memoises the real backend into
        // $cache_instance. Left in place, the loop below would write through that
        // real (working) cache and the doubles installed underneath would never be
        // reached - the test would pass or fail for reasons unrelated to the guard.
        $this->resetInstanceCacheState();

        $warnings = [];
        $logger = $this->recordingLogger($warnings);

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($brokenCache),
                CacheFactory::class
            );
            Injector::inst()->registerService($logger, LoggerInterface::class);

            foreach ([$this->event, $second, $third] as $event) {
                EventInstanceCache::setCachedInstances($event, '2026-04-01', '2026-04-30', []);
            }

            $this->assertCount(
                1,
                $warnings,
                'Three distinct failing writes in one request must collapse to one warning'
            );

            EventInstanceCache::clearAllCache();

            EventInstanceCache::setCachedInstances($this->event, '2026-05-01', '2026-05-31', []);

            $this->assertCount(
                2,
                $warnings,
                'clearAllCache() must re-arm the guard so a later failure is still reported'
            );
            $this->assertStringContainsString('2026-05-01', $warnings[1]);
        } finally {
            Injector::unnest();
        }
    }

    /**
     * Test 5: a logger whose warning() throws must not propagate out of
     * setCachedInstances(), and the error_log() fallback must carry the message.
     *
     * @return void
     */
    public function testThrowingLoggerDoesNotPropagateAndFallsBackToErrorLog()
    {
        $brokenCache = $this->createStub(CacheInterface::class);
        $brokenCache->method('get')->willReturn(null);
        $brokenCache->method('set')->willReturn(false);

        $brokenLogger = $this->createStub(LoggerInterface::class);
        $brokenLogger->method('warning')->willThrowException(new \RuntimeException('logger unavailable'));

        $errorLogFile = tempnam(sys_get_temp_dir(), 'instance-cache-error-log-');
        $previousErrorLog = ini_set('error_log', $errorLogFile);

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($brokenCache),
                CacheFactory::class
            );
            Injector::inst()->registerService($brokenLogger, LoggerInterface::class);

            EventInstanceCache::setCachedInstances(
                $this->event,
                '2026-04-01',
                '2026-04-30',
                [['title' => 'Survives A Throwing Logger']]
            );

            $logged = file_get_contents($errorLogFile);
            $this->assertStringContainsString(
                'failed to write event instances cache entry',
                $logged,
                'The error_log() fallback must fire when the logger service throws'
            );
            $this->assertStringContainsString('RuntimeException', $logged);
        } finally {
            Injector::unnest();
            ini_set('error_log', $previousErrorLog);
            unlink($errorLogFile);
        }
    }

    /**
     * Test 6: an empty instance list is a normal write, not an error.
     *
     * An event with no occurrences in range is the common case, and must produce
     * neither a warning nor an exception.
     *
     * @return void
     */
    public function testEmptyInstanceListWritesCleanly()
    {
        $workingCache = $this->createStub(CacheInterface::class);
        $workingCache->method('get')->willReturn(null);
        $workingCache->method('set')->willReturn(true);

        $warnings = [];
        $logger = $this->recordingLogger($warnings);

        Injector::nest();
        try {
            Injector::inst()->registerService(
                $this->makeCacheFactoryReturning($workingCache),
                CacheFactory::class
            );
            Injector::inst()->registerService($logger, LoggerInterface::class);

            EventInstanceCache::setCachedInstances($this->event, '2026-04-01', '2026-04-30', []);

            $this->assertSame([], $warnings, 'An empty instance list is not a failure');
            $this->assertSame(
                [],
                EventInstanceCache::getCachedInstances($this->event, '2026-04-01', '2026-04-30'),
                'An empty cached list must read back empty, not null'
            );
        } finally {
            Injector::unnest();
        }
    }
}
