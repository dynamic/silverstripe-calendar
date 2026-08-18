<?php

namespace Dynamic\Calendar\Extension;

use Dynamic\Calendar\Cache\CalendarCacheVersion;

/**
 * Calendar Cache Invalidation Trait
 *
 * Invalidates cached JSON feeds by bumping the version stamps that
 * CalendarController folds into its cache keys. One or two set() calls per
 * bump - the previous implementation called $cache->clear() on a
 * namespace-less pool, which unlinked every file in TEMP_PATH/@/ (including
 * caches belonging to the framework and other modules) up to several times
 * per event save.
 *
 * @package Dynamic\Calendar\Extension
 */
trait CalendarCacheInvalidation
{
    /**
     * Invalidate the cached feeds containing this record's calendar. Pass the
     * calendar (ParentID) when known; without it, every calendar's feeds are
     * invalidated - correct but broader than necessary.
     */
    protected function bumpCalendarCacheVersion(?int $calendarID = null): void
    {
        if ($calendarID) {
            CalendarCacheVersion::bumpCalendar($calendarID);
        } else {
            CalendarCacheVersion::bumpAllCalendars();
        }
    }

    /**
     * @deprecated 2.3.0 Use bumpCalendarCacheVersion() - kept so subclasses
     * calling the old name keep working.
     */
    protected function clearCalendarJSONCache(): void
    {
        CalendarCacheVersion::bumpAllCalendars();
    }
}
