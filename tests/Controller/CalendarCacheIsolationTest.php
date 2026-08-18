<?php

namespace Dynamic\Calendar\Tests\Controller;

use Dynamic\Calendar\Cache\CalendarCacheVersion;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;

/**
 * Guards the 2.3.0 generational invalidation (issue #132).
 *
 * Before: every event save called $cache->clear() on a namespace-less pool -
 * one calendar's edit cold-started every calendar's cached feed (and every
 * other namespace-less cache on the site). Now writes bump per-calendar
 * version stamps folded into the cache key, so invalidation is scoped.
 */
class CalendarCacheIsolationTest extends SapphireTest
{
    protected static $fixture_file = '../MultiCalendar.yml';

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        // The DB rolls back between tests but the cache pool and the static
        // stamp memo do not - flush so every test starts cold.
        CalendarCacheVersion::flush();
    }

    private function fetchFeed(Calendar $calendar): string
    {
        $controller = CalendarController::create($calendar);

        $request = new HTTPRequest('GET', 'events', [
            'start' => '2025-06-01',
            'end' => '2025-07-31',
        ]);
        $request->addHeader('X-Requested-With', 'XMLHttpRequest');

        $response = $controller->events($request);

        return $response->getHeader('X-Calendar-Cache');
    }

    public function testUnrelatedCalendarSaveDoesNotEvictCachedFeed(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $other = $this->objFromFixture(Calendar::class, 'other');

        // Prime both caches
        $this->assertEquals('MISS', $this->fetchFeed($primary));
        $this->assertEquals('HIT', $this->fetchFeed($primary));
        $this->assertEquals('MISS', $this->fetchFeed($other));

        // Save an event belonging to the OTHER calendar
        $theirs = $this->objFromFixture(EventPage::class, 'theirs');
        $theirs->Title = 'Theirs (edited)';
        $theirs->write();

        // The other calendar's feed must re-generate...
        $this->assertEquals('MISS', $this->fetchFeed($other));
        // ...but the primary calendar's cached feed must survive.
        $this->assertEquals(
            'HIT',
            $this->fetchFeed($primary),
            'An event save on one calendar evicted an unrelated calendar\'s cached feed'
        );
    }

    public function testOwnCalendarSaveEvictsCachedFeed(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $this->assertEquals('MISS', $this->fetchFeed($primary));
        $this->assertEquals('HIT', $this->fetchFeed($primary));

        $mine = $this->objFromFixture(EventPage::class, 'mine');
        $mine->Title = 'Mine (edited)';
        $mine->write();

        $this->assertEquals('MISS', $this->fetchFeed($primary));
    }

    public function testCategoryChangeEvictsAllCachedFeeds(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $other = $this->objFromFixture(Calendar::class, 'other');

        $this->assertEquals('MISS', $this->fetchFeed($primary));
        $this->assertEquals('MISS', $this->fetchFeed($other));
        $this->assertEquals('HIT', $this->fetchFeed($primary));
        $this->assertEquals('HIT', $this->fetchFeed($other));

        // Category titles/colours are baked into the cached JSON. Before
        // 2.3.0 a category edit invalidated nothing.
        $category = $this->objFromFixture(Category::class, 'shared');
        $category->Title = 'Shared (renamed)';
        $category->write();

        $this->assertEquals('MISS', $this->fetchFeed($primary));
        $this->assertEquals('MISS', $this->fetchFeed($other));
    }

    public function testEventMovedBetweenCalendarsEvictsBoth(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $other = $this->objFromFixture(Calendar::class, 'other');

        $this->assertEquals('MISS', $this->fetchFeed($primary));
        $this->assertEquals('MISS', $this->fetchFeed($other));

        // Move an event from primary to other
        $mine = $this->objFromFixture(EventPage::class, 'mine');
        $mine->ParentID = $other->ID;
        $mine->write();

        $this->assertEquals(
            'MISS',
            $this->fetchFeed($primary),
            'The calendar an event was moved FROM kept serving its stale cached feed'
        );
        $this->assertEquals('MISS', $this->fetchFeed($other));
    }
}
