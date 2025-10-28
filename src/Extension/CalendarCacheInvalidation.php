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
     * We create the cache with the exact same parameters as CalendarController
     * to ensure we're clearing the correct cache instance.
     *
     * @return void
     */
    protected function clearCalendarJSONCache(): void
    {
        // Get the cache factory and create cache with same parameters as CalendarController
        $cacheFactory = Injector::inst()->get(CacheFactory::class);
        $cache = $cacheFactory->create(
            'CalendarJSON',
            ['defaultLifetime' => 1800]
        );

        // Clear all items in the cache namespace
        $cache->clear();
    }
}
