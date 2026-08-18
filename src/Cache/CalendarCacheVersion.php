<?php

namespace Dynamic\Calendar\Cache;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;

/**
 * Generational invalidation for the calendar JSON feed cache.
 *
 * Instead of wiping the pool on every write (which previously happened up to
 * several times per event save, and - because the pool had no namespace -
 * unlinked every file in TEMP_PATH/@/, including other modules' caches), the
 * feed cache key embeds version stamps. Invalidation is then one or two
 * set() calls: entries written under an old stamp become unreachable and
 * expire on TTL.
 *
 * Three stamp scopes, so invalidation is as narrow as correctness allows:
 *
 *  - calendar_{ID}  bumped when that calendar's content changes (its events,
 *                   their exceptions, the calendar row itself). Scoped feeds
 *                   embed this, so calendar B's cache survives calendar A's
 *                   edits.
 *  - any            bumped alongside every calendar_{ID} bump. Cross-calendar
 *                   feeds (allow_cross_calendar_feed) embed this instead,
 *                   since they can contain any calendar's events.
 *  - taxonomy       bumped by Category writes/deletes. Embedded in EVERY feed
 *                   key: category titles and colours are baked into the cached
 *                   JSON, so a rename or recolour must invalidate everything.
 *                   (Previously a Category edit invalidated nothing - stale
 *                   colours were served until an unrelated event save.)
 *
 * A missing stamp is seeded with the current microtime - never a constant -
 * so an evicted stamp produces a harmless fresh miss rather than resurrecting
 * stale entries.
 */
class CalendarCacheVersion implements Flushable
{
    private const SCOPE_ANY = 'any';

    private const SCOPE_TAXONOMY = 'taxonomy';

    /**
     * Stamps must outlive the entries that embed them; an expired stamp only
     * causes a full miss, so one year is plenty.
     */
    private const STAMP_TTL = 31536000;

    /**
     * @var array<string,string> per-request memo so a feed request reads each
     * stamp at most once
     */
    private static array $memo = [];

    /**
     * The version fragment to embed in a feed cache key.
     *
     * @param int|null $calendarID the calendar being served, or null for a
     *                             cross-calendar feed
     */
    public static function keyFragment(?int $calendarID): string
    {
        $content = $calendarID
            ? self::get("calendar_{$calendarID}")
            : self::get(self::SCOPE_ANY);

        return $content . '_' . self::get(self::SCOPE_TAXONOMY);
    }

    /**
     * A calendar's content changed: its events, their exceptions, or the
     * calendar row itself.
     */
    public static function bumpCalendar(int $calendarID): void
    {
        self::bump("calendar_{$calendarID}");
        self::bump(self::SCOPE_ANY);
    }

    /**
     * Content changed but the owning calendar could not be determined -
     * invalidate every calendar's feeds.
     */
    public static function bumpAllCalendars(): void
    {
        // 'any' covers cross-calendar feeds; taxonomy is embedded in every
        // scoped key, so bumping it reaches those too.
        self::bump(self::SCOPE_ANY);
        self::bump(self::SCOPE_TAXONOMY);
    }

    /**
     * Category data changed - titles/colours are baked into every cached
     * response, so everything invalidates.
     */
    public static function bumpTaxonomy(): void
    {
        self::bump(self::SCOPE_TAXONOMY);
    }

    /**
     * ?flush=1 / dev/build: clear both pools (responses and stamps).
     */
    public static function flush(): void
    {
        self::$memo = [];
        self::stampCache()->clear();
        Injector::inst()->get(CacheInterface::class . '.calendarJSON')->clear();
    }

    private static function get(string $scope): string
    {
        if (isset(self::$memo[$scope])) {
            return self::$memo[$scope];
        }

        $cache = self::stampCache();
        $key = "calendar_version_{$scope}";
        $stamp = $cache->get($key);

        if (!$stamp) {
            $stamp = self::freshStamp();
            $cache->set($key, $stamp, self::STAMP_TTL);
        }

        return self::$memo[$scope] = (string) $stamp;
    }

    private static function bump(string $scope): void
    {
        $stamp = self::freshStamp();

        self::stampCache()->set("calendar_version_{$scope}", $stamp, self::STAMP_TTL);
        self::$memo[$scope] = $stamp;
    }

    private static function freshStamp(): string
    {
        // microtime + pid: two bumps within the same microsecond (or across
        // web workers with skewed clocks) still produce distinct stamps.
        return str_replace('.', '', sprintf('%.6F', microtime(true))) . getmypid();
    }

    /**
     * Stamps use a filesystem-only pool: an APCu layer is per process tier, so
     * a bump from CLI or a queued job would be invisible to web workers.
     */
    private static function stampCache(): CacheInterface
    {
        return Injector::inst()->get(CacheInterface::class . '.calendarJSONVersions');
    }
}
