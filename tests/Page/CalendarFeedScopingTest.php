<?php

namespace Dynamic\Calendar\Tests\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\List\ArrayList;

/**
 * Scoping tests for Calendar::getEventsFeed() and dynamic/silverstripe-calendar#256.
 *
 * Categories are a shared taxonomy: the same Category record can be attached to
 * events of several calendars. Before the fix, the mere presence of a category in
 * the feed's category list dropped the ParentID scope from both the one-time and
 * the recurring query, so a category filter on calendar A returned calendar B's
 * events - including through CalendarController's DefaultCategories substitution,
 * which reaches getEventsFeed() with no visitor input at all.
 *
 * Every case below uses a fixed date window so recurrence expansion is
 * deterministic and does not depend on the day of the year the suite runs.
 */
class CalendarFeedScopingTest extends SapphireTest
{
    /**
     * Declared explicitly: this class has no $fixture_file, so without it the
     * tests would error with "Table ... doesn't exist".
     *
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var string
     */
    protected const WINDOW_FROM = '2025-06-01';

    /**
     * @var string
     */
    protected const WINDOW_TO = '2025-06-30';

    /**
     * @var Calendar
     */
    protected $calendarA;

    /**
     * @var Calendar
     */
    protected $calendarB;

    /**
     * Category attached to events on both calendars.
     *
     * @var Category
     */
    protected $sharedCategory;

    /**
     * Category attached to events on calendar B only.
     *
     * @var Category
     */
    protected $bOnlyCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sharedCategory = Category::create(['Title' => 'Shared Category']);
        $this->sharedCategory->write();

        $this->bOnlyCategory = Category::create(['Title' => 'Only On Calendar B']);
        $this->bOnlyCategory->write();

        $this->calendarA = $this->makeCalendar('Scoping Calendar A', 'scoping-calendar-a');
        $this->calendarB = $this->makeCalendar('Scoping Calendar B', 'scoping-calendar-b');

        // Both calendars hold a published one-time event and a recurring event in
        // the same category, which is the shape the bug needs.
        $this->makeEvent($this->calendarA, 'A One Time', 'NONE', $this->sharedCategory);
        $this->makeEvent($this->calendarA, 'A Recurring', 'WEEKLY', $this->sharedCategory);
        $this->makeEvent($this->calendarB, 'B One Time', 'NONE', $this->sharedCategory);
        $this->makeEvent($this->calendarB, 'B Recurring', 'WEEKLY', $this->sharedCategory);

        // A category that calendar A has no events for, but calendar B does.
        $this->makeEvent($this->calendarB, 'B Exclusive', 'NONE', $this->bOnlyCategory);

        // Calendar A's default category selection, as the CMS would set it.
        $this->calendarA->DefaultCategories()->add($this->sharedCategory);
        $this->calendarA->write();
        $this->calendarA->publishRecursive();
    }

    /**
     * A visitor-selected category filter keeps the ParentID scope.
     */
    public function testCategoryFilterIsScopedToThisCalendar(): void
    {
        $events = $this->calendarA->getEventsFeed(
            null,
            ArrayList::create([$this->sharedCategory]),
            Carbon::parse(self::WINDOW_FROM),
            Carbon::parse(self::WINDOW_TO)
        );

        $this->assertSame(
            ['A One Time', 'A Recurring'],
            $this->titles($events),
            'A category filter on calendar A must not return calendar B events'
        );
    }

    /**
     * The same filter, run on calendar B, returns B's events and not A's.
     */
    public function testCategoryFilterIsScopedFromTheOtherCalendar(): void
    {
        $events = $this->calendarB->getEventsFeed(
            null,
            ArrayList::create([$this->sharedCategory]),
            Carbon::parse(self::WINDOW_FROM),
            Carbon::parse(self::WINDOW_TO)
        );

        $this->assertSame(
            ['B One Time', 'B Recurring'],
            $this->titles($events),
            'The same category must return only the calendar it is filtered from'
        );
    }

    /**
     * Both branches are scoped: the recurring query dropped ParentID too, and its
     * virtual instances carry the parent event's category.
     */
    public function testCategoryFilterScopesBothOneTimeAndRecurringBranches(): void
    {
        $events = $this->calendarA->getEventsFeed(
            null,
            ArrayList::create([$this->sharedCategory]),
            Carbon::parse(self::WINDOW_FROM),
            Carbon::parse(self::WINDOW_TO)
        );

        // Recurring occurrences expand to several instances, so this proves the
        // recurring branch really ran rather than returning nothing.
        $this->assertGreaterThan(
            2,
            $events->count(),
            'The scoped feed should still expand calendar A recurring events'
        );
        $this->assertSame(
            ['A One Time', 'A Recurring'],
            $this->titles($events),
            'Neither the one-time nor the recurring branch may cross calendars'
        );
    }

    /**
     * DefaultCategories passed to getEventsFeed() - what CalendarController
     * substitutes when the request carries no category - keeps the same scope.
     */
    public function testDefaultCategoriesAreScopedToThisCalendar(): void
    {
        $defaultCategories = $this->calendarA->DefaultCategories();
        $this->assertTrue($defaultCategories->exists(), 'Fixture should give calendar A a default category');

        $events = $this->calendarA->getEventsFeed(
            null,
            $defaultCategories,
            Carbon::parse(self::WINDOW_FROM),
            Carbon::parse(self::WINDOW_TO)
        );

        $this->assertSame(
            ['A One Time', 'A Recurring'],
            $this->titles($events),
            'DefaultCategories substitution must not leak another calendar events'
        );
    }

    /**
     * The opt-out restores the pre-3.1.0 cross-calendar behaviour.
     */
    public function testAllowCrossCalendarFeedRestoresOtherCalendarEvents(): void
    {
        Config::modify()->set(Calendar::class, 'allow_cross_calendar_feed', true);

        $events = $this->calendarA->getEventsFeed(
            null,
            ArrayList::create([$this->sharedCategory]),
            Carbon::parse(self::WINDOW_FROM),
            Carbon::parse(self::WINDOW_TO)
        );

        $this->assertSame(
            ['A One Time', 'A Recurring', 'B One Time', 'B Recurring'],
            $this->titles($events),
            'allow_cross_calendar_feed: true must bring back the old cross-calendar result set'
        );
    }

    /**
     * A category calendar A has no events for yields an empty feed, rather than
     * another calendar's events.
     */
    public function testCategoryWithoutEventsOnThisCalendarReturnsEmptyFeed(): void
    {
        $events = $this->calendarA->getEventsFeed(
            null,
            ArrayList::create([$this->bOnlyCategory]),
            Carbon::parse(self::WINDOW_FROM),
            Carbon::parse(self::WINDOW_TO)
        );

        $this->assertSame(
            [],
            $this->titles($events),
            'An unfiltered-on-this-calendar category must produce an empty feed, not calendar B events'
        );
    }

    /**
     * Regression guard: with no categories at all the feed was already scoped, and
     * must stay that way.
     */
    public function testFeedWithoutCategoriesStaysScopedToThisCalendar(): void
    {
        $events = $this->calendarA->getEventsFeed(
            null,
            null,
            Carbon::parse(self::WINDOW_FROM),
            Carbon::parse(self::WINDOW_TO)
        );

        $this->assertSame(
            ['A One Time', 'A Recurring'],
            $this->titles($events),
            'A category-less feed must remain ParentID scoped'
        );
    }

    /**
     * @param string $title
     * @param string $urlSegment
     * @return Calendar
     */
    private function makeCalendar(string $title, string $urlSegment): Calendar
    {
        $calendar = Calendar::create([
            'Title' => $title,
            'URLSegment' => $urlSegment,
        ]);
        $calendar->write();
        $calendar->publishRecursive();

        return $calendar;
    }

    /**
     * @param Calendar $parent
     * @param string $title
     * @param string $recursion 'NONE' for a one-time event, else a Carbon pattern
     * @param Category $category
     * @return EventPage
     */
    private function makeEvent(Calendar $parent, string $title, string $recursion, Category $category): EventPage
    {
        $event = EventPage::create([
            'Title' => $title,
            'StartDate' => '2025-06-05',
            'StartTime' => '10:00:00',
            'AllDay' => 0,
            'ParentID' => $parent->ID,
            'Recursion' => $recursion,
            'RecursionEndDate' => ($recursion === 'NONE') ? null : self::WINDOW_TO,
        ]);
        $event->write();
        $event->Categories()->add($category);
        $event->publishRecursive();

        return $event;
    }

    /**
     * Distinct event titles in the feed, sorted, so an assertion reports the
     * leaked/missing event rather than an opaque count difference.
     *
     * Recurring events contribute several instances per parent event, so counting
     * rows here would say nothing about which events were returned.
     *
     * @param ArrayList $events
     * @return array<int,string>
     */
    private function titles($events): array
    {
        $titles = [];
        foreach ($events as $event) {
            $titles[(string)$event->Title] = true;
        }
        $result = array_keys($titles);
        sort($result);

        return $result;
    }
}
