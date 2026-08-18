<?php

namespace Dynamic\Calendar\Model;

use Dynamic\Calendar\Page\EventPage;

/**
 * Event Instance Cache
 *
 * @deprecated 2.3.0 The persistent occurrence cache is retired and every
 * method is a no-op. Once occurrence generation was bounded by the requested
 * window and exception lookups were batched (2.2.0), measurement on a
 * 5,000-event dataset showed a cache hit saved 3-18% of generation cost while
 * every miss cost roughly double (generate + serialise + write), with keys
 * that made misses the common case. The class is kept only so external
 * callers do not fatal; it will be removed in the next major.
 *
 * Historical note: this cache never worked as designed anyway - it was
 * created without a namespace argument, so it shared the namespace-less
 * TEMP_PATH/@/ pool with the JSON feed cache (issue #132), and both branches
 * of clearEventCache() targeted key shapes that were never written.
 *
 * @package Dynamic\Calendar\Model
 */
class EventInstanceCache
{
    /**
     * @deprecated 2.3.0 Always returns null (cache retired)
     * @return array|null
     */
    public static function getCachedInstances(
        EventPage $event,
        string $start,
        string $end
    ): ?array {
        return null;
    }

    /**
     * @deprecated 2.3.0 No-op (cache retired)
     */
    public static function setCachedInstances(
        EventPage $event,
        string $start,
        string $end,
        array $instances,
        ?int $ttl = null
    ): void {
        // no-op
    }

    /**
     * @deprecated 2.3.0 No-op (cache retired)
     */
    public static function clearEventCache(EventPage $event): void
    {
        // no-op
    }

    /**
     * @deprecated 2.3.0 No-op (cache retired)
     */
    public static function clearAllCache(): void
    {
        // no-op
    }

    /**
     * @deprecated 2.3.0 Reports an empty, disabled cache
     */
    public static function getCacheStats(): array
    {
        return [
            'memory_cache_entries' => 0,
            'memory_cache_size' => 0,
            'cache_backend' => 'disabled',
        ];
    }
}
