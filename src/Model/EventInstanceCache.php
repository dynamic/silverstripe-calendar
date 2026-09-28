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
     * Whether a cache-write failure has already been reported this PHP process.
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
     * What it does: emits one warning naming this class and the cache key, so that a
     * false-returning set() is not mistaken for ordinary cache misses (issue #160;
     * the wording mirrors CalendarController::logCacheWriteFailure(), which fixed the
     * same invisibility for the events JSON cache).
     *
     * The bound: $write_failure_logged suppresses every later emission in the same
     * PHP process, so only the first failure names its key - the defect is the shared
     * backend, not any one key, and an unbounded call would cost one synchronous
     * logger call (or, when the logger itself throws, one inline error_log() write)
     * per recurring event for a single dead backend. The scope is per process, not
     * per request; PHP-FPM statics die at request end, which is why the distinction
     * is invisible on this module's own caller set and why a long-lived caller of the
     * public getEventsFeed() - a queued job, a dev/task - gets one warning for its
     * whole run.
     *
     * How it resets: clearAllCache() is the only in-band reset, and it is not a
     * targeted re-arm - it also wipes every cached instance. The production
     * invalidation path, clearEventCache(), does not re-arm at all. Whether a
     * targeted re-arm hook belongs there is a module-wide suppression policy
     * decision, not this call site's.
     *
     * The guard: the logger lookup and the write to it both go through
     * LoggerFallback, so a missing or throwing logger cannot escalate a cache-write
     * failure into a fatal error. That guarantee covers the logger path only - a
     * set() that throws rather than returning false is still unguarded here, and is
     * tracked separately. The throwaway instance exists only because
     * logWithFallback() is a protected non-static method.
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
        // failure in the same process must not repeat it.
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
