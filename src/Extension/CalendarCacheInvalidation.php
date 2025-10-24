<?php

namespace Dynamic\Calendar\Extension;

use SilverStripe\Core\Cache\CacheFactory;
use SilverStripe\Core\Injector\Injector;

/**
 * Calendar Cache Invalidation Trait
 *
 * Provides cache invalidation functionality for calendar-related models.
 * When applied to models, it clears the CalendarController JSON cache
 * to ensure fresh data is served after changes.
 *
 * @package Dynamic\Calendar\Extension
 */
trait CalendarCacheInvalidation
{
    /**
     * Clear CalendarController JSON cache
     *
     * This method clears the entire CalendarJSON cache namespace.
     * Since this cache is isolated by namespace, clearing it won't
     * affect other caches in the system.
     *
     * Note: The defaultLifetime parameter is not needed when clearing,
     * but we still pass it to ensure the same cache instance is used.
     * The value doesn't affect the clear operation.
     *
     * @return void
     */
    protected function clearCalendarJSONCache(): void
    {
        // Get the cache instance - TTL doesn't matter for clearing
        // but we need to use the same cache name
        $cache = Injector::inst()->get(CacheFactory::class)->create('CalendarJSON');
        $cache->clear();
    }
}
