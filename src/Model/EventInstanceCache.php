<?php

namespace Dynamic\Calendar\Model;

use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Traits\LoggerFallback;
use SilverStripe\Core\Cache\CacheFactory;
use SilverStripe\Core\Injector\Injector;
use Psr\SimpleCache\CacheInterface;

/**
 * Event Instance Cache
 *
 * Provides multi-layer caching for virtual event instances using
 * SilverStripe's built-in caching system instead of Redis.
 */
class EventInstanceCache
{
    use LoggerFallback;

    /**
     * Memory cache for the current request
     * @var array
     */
    private static array $memory_cache = [];

    /**
     * Cache interface for persistent storage
     * @var CacheInterface|null
     */
    private static ?CacheInterface $cache_instance = null;

    /**
     * Default cache TTL (1 hour)
     * @var int
     */
    private static int $default_ttl = 3600;

    /**
     * Whether a cache-write failure has already been reported this request.
     *
     * Volume bound, not a suppression policy: see logCacheWriteFailure().
     *
     * @var bool
     */
    private static bool $write_failure_logged = false;

    /**
     * Get cached event instances
     */
    public static function getCachedInstances(
        EventPage $event,
        string $start,
        string $end
    ): ?array {
        $cacheKey = self::generateCacheKey($event, $start, $end);

        // Memory cache first (fastest)
        if (isset(self::$memory_cache[$cacheKey])) {
            return self::$memory_cache[$cacheKey];
        }

        // Persistent cache second
        $cached = self::getCache()->get($cacheKey);
        if ($cached !== null) {
            // Store in memory cache for subsequent requests
            self::$memory_cache[$cacheKey] = $cached;
            return $cached;
        }

        return null;
    }

    /**
     * Set cached event instances
     */
    public static function setCachedInstances(
        EventPage $event,
        string $start,
        string $end,
        array $instances,
        ?int $ttl = null
    ): void {
        $cacheKey = self::generateCacheKey($event, $start, $end);
        $ttl = $ttl ?: self::$default_ttl;

        // Store in both memory and persistent cache
        self::$memory_cache[$cacheKey] = $instances;
        $written = self::getCache()->set($cacheKey, $instances, $ttl);
        if (!$written) {
            self::logCacheWriteFailure($cacheKey);
        }
    }

    /**
     * Clear cached instances for a specific event
     */
    public static function clearEventCache(EventPage $event): void
    {
        // Clear memory cache entries for this event
        foreach (array_keys(self::$memory_cache) as $key) {
            if (strpos($key, "event_{$event->ID}_") === 0) {
                unset(self::$memory_cache[$key]);
            }
        }

        // For persistent cache, we'd need to iterate or use pattern matching
        // SilverStripe's cache doesn't support pattern deletion, so we'll
        // use cache tags when the event is modified
        self::getCache()->delete("event_instances_{$event->ID}");
    }

    /**
     * Clear all cached instances
     */
    public static function clearAllCache(): void
    {
        self::$memory_cache = [];
        self::$write_failure_logged = false;
        self::getCache()->clear();
    }

    /**
     * Generate a consistent cache key
     */
    private static function generateCacheKey(EventPage $event, string $start, string $end): string
    {
        // Include the event's last edited date to auto-invalidate when event changes
        $lastEdited = $event->LastEdited ?: date('Y-m-d H:i:s');
        $hash = md5($lastEdited . $event->Recursion . $event->Interval . $event->RecursionEndDate);

        return sprintf(
            'event_instances_%d_%s_%s_%s',
            $event->ID,
            $start,
            $end,
            substr($hash, 0, 8) // Short hash for cache invalidation
        );
    }

    /**
     * Get the cache instance
     */
    private static function getCache(): CacheInterface
    {
        if (self::$cache_instance === null) {
            self::$cache_instance = Injector::inst()->get(
                CacheFactory::class
            )->create('CalendarEventInstances');
        }

        return self::$cache_instance;
    }

    /**
     * Log a failed event instances cache write. A false-returning set() otherwise
     * leaves every later request for this event's instances a permanent,
     * indistinguishable cache miss (issue #160, the same invisibility #152 fixed
     * for the events JSON cache in CalendarController::logCacheWriteFailure(),
     * whose message wording this mirrors).
     *
     * Emission is bounded to once per request by $write_failure_logged, reset only
     * by clearAllCache(). The bound is load-bearing rather than cosmetic:
     * setCachedInstances() is reached once per recurring event from
     * Calendar::getEventsFeed() via CarbonRecursion::getCachedOccurrences(), so an
     * unbounded call would cost N synchronous logger calls - and, when the logger
     * service itself throws, N inline error_log() writes - on the response path for
     * a single dead backend. The first failure names its own key; later distinct
     * keys in the same request are not individually named, because the defect is
     * the shared backend, not any one key. A wider suppression policy across the
     * module is #179's to decide, not this method's.
     *
     * The logger lookup and the write to it are both guarded, via LoggerFallback,
     * so a missing or throwing logger cannot turn a cache-write failure into a fatal
     * error on the response path. The throwaway instance exists only because
     * logWithFallback() is a protected non-static method: promoting it to static is
     * #200's decision, so this call site adapts rather than changing the trait.
     *
     * @param string $cacheKey
     * @return void
     */
    private static function logCacheWriteFailure(string $cacheKey): void
    {
        if (self::$write_failure_logged) {
            return;
        }
        // Flag before emitting, not after: if the emit path ever throws, the next
        // failure in the same request must not repeat it.
        self::$write_failure_logged = true;

        (new self())->logWithFallback(
            'EventInstanceCache: failed to write event instances cache entry - ' . $cacheKey
        );
    }

    /**
     * Get cache statistics for debugging
     */
    public static function getCacheStats(): array
    {
        return [
            'memory_cache_entries' => count(self::$memory_cache),
            'memory_cache_size' => strlen(serialize(self::$memory_cache)),
            'cache_backend' => get_class(self::getCache()),
        ];
    }
}
