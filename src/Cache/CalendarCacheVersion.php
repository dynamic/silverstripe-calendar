<?php

namespace Dynamic\Calendar\Cache;

use Dynamic\Calendar\Page\EventPage;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

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

        // Seed durable stamps immediately: get() deliberately never writes
        // (see below), so without seeding here a quiet site would not cache
        // again until its next content edit.
        self::bump(self::SCOPE_ANY);
        self::bump(self::SCOPE_TAXONOMY);
    }

    /**
     * A fingerprint of the EventPage<->Category join table, folded into every
     * feed cache key. ManyManyList::add()/remove() fire no extension hooks
     * (onAfterLink/onAfterUnlink never existed in the framework), so a pure
     * relation mutation with no accompanying write() cannot bump a stamp -
     * instead the key itself observes the relation state.
     *
     * (COUNT, MAX(ID)) is change-sensitive: IDs auto-increment, so any add
     * raises MAX; any removal changes COUNT or removes the MAX row. One
     * PK-indexed query per feed request, memoized.
     */
    public static function relationFingerprint(): string
    {
        // Deliberately not memoized: the whole point is observing relation
        // state the hooks cannot see, and the cost is one PK-indexed
        // aggregate per key computation.
        $table = DataObject::getSchema()->tableName(EventPage::class) . '_Categories';
        $row = DB::query(
            "SELECT COUNT(*) AS c, COALESCE(MAX(\"ID\"), 0) AS m FROM \"{$table}\""
        )->record();

        return "r{$row['c']}x{$row['m']}";
    }

    private static function get(string $scope): string
    {
        if (isset(self::$memo[$scope])) {
            return self::$memo[$scope];
        }

        $stamp = self::stampCache()->get("calendar_version_{$scope}");

        if (!$stamp) {
            // Do NOT persist on the read path. A reader that misses right
            // after a flush could otherwise overwrite a concurrent writer's
            // fresh bump and then cache a stale response under that stamp for
            // the full TTL (TOCTOU). Instead: memoize a throwaway stamp for
            // this request (consistent keys within the request, entries land
            // under a never-reused key = harmless orphans), and let flush()
            // seeding or the next bump() establish the durable stamp.
            $stamp = self::freshStamp();
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
