<?php

namespace Dynamic\Calendar\Tests\Page;

use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;

/**
 * Regression tests for the getEventsFeed() calendar-scoping fix.
 *
 * Before 2.2.0, supplying ANY category filter silently removed the ParentID
 * scope from both the regular and recurring queries, so every calendar on the
 * site leaked into the feed. These tests use two calendars sharing a category,
 * which is exactly the shape that exposed the bug in production.
 */
class CalendarFeedScopingTest extends SapphireTest
{
    protected static $fixture_file = '../MultiCalendar.yml';

    public function testCategoryFilterDoesNotLeakAcrossCalendars(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $shared = $this->objFromFixture(Category::class, 'shared');

        $events = $primary->getEventsFeed(
            null,
            ArrayList::create([$shared]),
            '2025-06-01',
            '2025-07-31'
        );

        $titles = [];
        foreach ($events as $event) {
            $titles[] = (string) $event->Title;
        }

        $this->assertContains('Mine', $titles);
        $this->assertContains('Mine Recurring', $titles);
        $this->assertNotContains('Theirs', $titles, 'Regular events leaked across ParentID');
        $this->assertNotContains('Theirs Recurring', $titles, 'Recurring events leaked across ParentID');
    }

    public function testUnfilteredFeedIsScopedToOwnCalendar(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $events = $primary->getEventsFeed(null, null, '2025-06-01', '2025-07-31');

        foreach ($events as $event) {
            $owner = $event->hasMethod('getOriginalEvent') ? $event->getOriginalEvent() : $event;
            $this->assertEquals($primary->ID, $owner->ParentID);
        }
    }

    public function testCrossCalendarFeedRequiresExplicitOptIn(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $shared = $this->objFromFixture(Category::class, 'shared');

        $events = $primary->getEventsFeed(
            null,
            ArrayList::create([$shared]),
            '2025-06-01',
            '2025-07-31',
            true
        );

        $titles = [];
        foreach ($events as $event) {
            $titles[] = (string) $event->Title;
        }

        $this->assertContains('Theirs', $titles);
        $this->assertContains('Theirs Recurring', $titles);
    }

    public function testCrossCalendarFeedViaConfig(): void
    {
        Config::modify()->set(Calendar::class, 'allow_cross_calendar_feed', true);

        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $events = $primary->getEventsFeed(null, null, '2025-06-01', '2025-07-31');

        $titles = [];
        foreach ($events as $event) {
            $titles[] = (string) $event->Title;
        }

        $this->assertContains('Theirs', $titles);
    }

    public function testLimitMatchesUnlimitedPrefixWhenTypesInterleave(): void
    {
        // 'mine' (2025-06-18) interleaves with weekly occurrences of
        // 'mineRecurring' (18 Jun, 25 Jun, ...). A naive implementation that
        // limits the regular side and appends occurrences would get this wrong.
        $primary = $this->objFromFixture(Calendar::class, 'primary');

        $all = $primary->getEventsFeed(null, null, '2025-06-01', '2025-07-31');
        $limited = $primary->getEventsFeed(3, null, '2025-06-01', '2025-07-31');

        $key = static function ($event): string {
            $date = $event->hasMethod('getInstanceDate')
                ? $event->getInstanceDate()->format('Y-m-d')
                : (string) $event->StartDate;

            return $event->Title . '|' . $date;
        };

        $this->assertCount(3, $limited);
        $this->assertSame(
            array_map($key, array_slice($all->toArray(), 0, 3)),
            array_map($key, $limited->toArray())
        );
    }

    public function testFeedIsSortedByStartDate(): void
    {
        $primary = $this->objFromFixture(Calendar::class, 'primary');
        $events = $primary->getEventsFeed(null, null, '2025-06-01', '2025-07-31');

        $previous = null;
        foreach ($events as $event) {
            $date = $event->hasMethod('getInstanceDate')
                ? $event->getInstanceDate()->format('Y-m-d')
                : (string) $event->StartDate;

            if ($previous !== null) {
                $this->assertGreaterThanOrEqual($previous, $date);
            }

            $previous = $date;
        }
    }
}
