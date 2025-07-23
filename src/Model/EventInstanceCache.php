<?php

namespace Dynamic\Calendar\Model;

use Dynamic\Calendar\Page\EventPage;
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
        self::getCache()->set($cacheKey, $instances, $ttl);
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
