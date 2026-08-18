<?php

namespace Dynamic\Calendar\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Extension\CalendarCacheInvalidation;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Traits\EventPageOptimizations;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\NumericField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Lumberjack\Model\Lumberjack;
use Dynamic\Calendar\Model\EventInstance;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\Versioned;

/**
 * Class Calendar
 * @package Dynamic\Calendar\Page
 */
class Calendar extends \Page
{
    use EventPageOptimizations;
    use CalendarCacheInvalidation;

    /**
     * @var string
     */
    private static string $table_name = 'Calendar';

    /**
     * @var string
     */
    private static string $singular_name = 'Calendar';

    /**
     * @var string
     */
    private static string $plural_name = 'Calendars';

    /**
     * @var string
     */
    private static string $icon_class = 'font-icon-p-event-alt';

    /**
     * Default window in years for recurring events when no date filter is applied.
     *
     * Rationale:
     * The default value of 2 years was chosen to provide a reasonable balance between
     * displaying a comprehensive set of upcoming recurring events and maintaining
     * acceptable performance. Loading all possible recurrences for events with no
     * date filter can be expensive, especially for events with complex recurrence rules.
     *
     * Performance Impact:
     * Increasing this window will result in more recurring event instances being
     * generated and loaded, which can significantly impact page load times and
     * server resource usage. Conversely, reducing the window may cause some future
     * recurring events to be omitted from the display.
     *
     * Adjust this value based on the expected number of recurring events and the
     * performance characteristics of your hosting environment.
     *
     * @var int
     */
    private static int $default_recurring_window_years = 2;

    /**
     * When true, getEventsFeed() may return events from calendars other than
     * this one. Default false: the feed is always scoped to this calendar
     * unless a caller explicitly opts out via the $allCalendars argument.
     *
     * Before 2.2.0 any category filter silently widened the feed to every
     * calendar on the site - both a content leak and a full-table scan.
     *
     * @config
     * @var bool
     */
    private static bool $allow_cross_calendar_feed = false;

    /**
     * @var array
     */
    private static array $casting = [
        'NextDate' => 'Date',
    ];

    /**
     * Database fields for Calendar filtering configuration
     *
     * - EventsPerPage: The number of events to display per page
     * - ShowCategoryFilter: Whether to show the category filter in the UI
     * - ShowEventTypeFilter: Whether to show the event type filter in the UI
     * - ShowAllDayFilter: Whether to show the all-day event filter in the UI
     * - DefaultFromDateMonths: The default number of months from current date for "from" filter
     * - DefaultToDateMonths: The default number of months from current date for "to" filter
     *
     * @var array
     */
    private static $db = [
        'EventsPerPage' => 'Int',
        'ShowCategoryFilter' => 'Boolean',
        'ShowEventTypeFilter' => 'Boolean',
        'ShowAllDayFilter' => 'Boolean',
        'DefaultFromDateMonths' => 'Int',
        'DefaultToDateMonths' => 'Int',
    ];

    /**
     * Default values for Calendar filtering configuration
     *
     * @var array
     */
    private static $defaults = [
        'EventsPerPage' => 12,
        'ShowCategoryFilter' => true,
        'ShowEventTypeFilter' => true,
        'ShowAllDayFilter' => false,
        'DefaultFromDateMonths' => 0,
        'DefaultToDateMonths' => 6,
    ];

    /**
     * Many-to-many relationships for Calendar
     *
     * @var array
     */
    private static $many_many = [
        'DefaultCategories' => Category::class,
    ];

    /**
     * @var array
     */
    private static array $allowed_children = [
        EventPage::class,
    ];

    /**
     * @var array
     */
    private static array $extensions = [
        Lumberjack::class,
    ];

    /**
     * @var bool
     */
    private static bool $include_child_categories = false;

    /**
     * @return string
     */
    public function getLumberjackTitle(): string
    {
        return 'Events';
    }



    /**
     * @return FieldList
     */
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();        // Add filtering configuration fields
        $fields->addFieldsToTab('Root.FilterSettings', [
            HeaderField::create('FilterOptionsHeader', 'Event Filtering Options'),

            CheckboxField::create('ShowCategoryFilter')
                ->setTitle('Show Category Filter')
                ->setDescription('Allow visitors to filter events by category'),

            CheckboxField::create('ShowEventTypeFilter')
                ->setTitle('Show Event Type Filter')
                ->setDescription('Allow visitors to filter between one-time and recurring events'),

            CheckboxField::create('ShowAllDayFilter')
                ->setTitle('Show All-Day Filter')
                ->setDescription('Allow visitors to filter between all-day and timed events'),

            HeaderField::create('DefaultSettingsHeader', 'Default Settings'),

            NumericField::create('EventsPerPage')
                ->setTitle('Events Per Page')
                ->setDescription('Number of events to display per page (default: 12)'),

            NumericField::create('DefaultFromDateMonths')
                ->setTitle('Default Start Date (Months from Now)')
                ->setDescription('How many months from current date to start showing events (0 = current month)'),

            NumericField::create('DefaultToDateMonths')
                ->setTitle('Default End Date (Months from Now)')
                ->setDescription('How many months from current date to show events until (6 = 6 months from now)'),

            HeaderField::create('CategoryDefaultsHeader', 'Default Category Selection'),

            CheckboxSetField::create('DefaultCategories')
                ->setTitle('Default Selected Categories')
                ->setDescription('Categories that will be pre-selected when visitors first view the calendar')
                ->setSource(Category::get()->map('ID', 'Title'))
                ->setDescription('Leave empty to show all categories by default'),
        ]);

        return $fields;
    }

    /**
     * Optimized method for getting EventPages for Lumberjack GridField
     * This method is called by the Lumberjack extension with excluded classes
     *
     * @param array $excluded List of class names excluded from the SiteTree
     * @return DataList
     */
    public function getLumberjackPagesForGridfield($excluded = []): DataList
    {
        $list = EventPage::get()
            ->filter(['ParentID' => $this->ID])
            ->sort(['StartDate' => 'DESC', 'StartTime' => 'DESC']);

        $list = $this->addEventPageOptimizations($list);

        // Allow extensions to modify the list before returning
        $this->extend('updateLumberjackPagesForGridfield', $list, $excluded);

        // Let Lumberjack handle pagination through GridField components
        // Don't apply limit() here as it interferes with Lumberjack's pagination
        return $list;
    }

    /**
     * Determine if an associative array key exists based off an given pattern
     *
     * @param $pattern
     * @param $array
     * @return int
     */
    public static function pregArrayKeyExists($pattern, $array): int
    {
        $keys = array_keys($array);

        return (int)preg_grep($pattern, $keys);
    }

    /**
     * method that augments the filterAny to include
     * child categories of a parent. this does this collectively.
     *
     * @param $filterAny
     * @return mixed
     */
    private static function augmentCategoryFiltering($filterAny)
    {
        if (isset($filterAny['Categories.ID'])) {
            $categories = Category::get()->byIDs($filterAny['Categories.ID']);
            $subs = $categories->relation('Children');
            $filterAny['Categories.ID'] = $filterAny['Categories.ID'] + (array)$subs->map('ID', 'ID')->toArray();

            return $filterAny;
        }
    }

    /**
     * @return string
     */
    public function getControllerName(): string
    {
        return CalendarController::class;
    }

    /**
     * Get events feed for consumption by ElementCalendar and other components
     * This method handles all the Carbon recursion logic and provides a clean API
     *
     * Filtering, sorting and the LIMIT are pushed into SQL for non-recurring
     * events; recurring events are bounded in SQL to the candidate set that can
     * possibly occur inside the window before being expanded in PHP.
     *
     * @param int|null $limit Maximum number of events to return
     * @param SS_List|null $categories Categories to filter by
     * @param Carbon|string|null $fromDate Start date for events
     * @param Carbon|string|null $toDate End date for events
     * @param bool|null $allCalendars Include events from every calendar, not just
     *                                this one. Defaults to the
     *                                allow_cross_calendar_feed config value
     *                                (false). Previously ANY category filter
     *                                silently widened the feed to all calendars.
     * @return ArrayList
     */
    public function getEventsFeed(
        ?int $limit = null,
        $categories = null,
        $fromDate = null,
        $toDate = null,
        ?bool $allCalendars = null
    ): ArrayList {
        // Parse dates - date filtering is only applied if date parameters are provided
        $fromDate = $fromDate ? Carbon::parse($fromDate)->startOfDay() : null;
        $toDate = $toDate ? Carbon::parse($toDate)->endOfDay() : null;

        $categoryIDs = ($categories && $categories->exists())
            ? array_values(array_unique(array_map('intval', $categories->column('ID'))))
            : [];

        if ($allCalendars === null) {
            $allCalendars = (bool) $this->config()->get('allow_cross_calendar_feed');
        }

        // ------------------------------------------------------------------
        // Non-recurring events: filter, sort AND limit entirely in SQL.
        // ------------------------------------------------------------------
        $regularEvents = EventPage::get()
            ->filter('Recursion', 'NONE')
            ->exclude('StartDate', null);

        if (!$allCalendars) {
            $regularEvents = $regularEvents->filter('ParentID', $this->ID);
        }

        if ($categoryIDs) {
            // DataQuery is distinct-by-default, so the many_many join cannot
            // duplicate rows.
            $regularEvents = $regularEvents->filter('Categories.ID', $categoryIDs);
        }

        if ($fromDate) {
            $regularEvents = $regularEvents->filter(
                'StartDate:GreaterThanOrEqual',
                $fromDate->format('Y-m-d')
            );
        }

        if ($toDate) {
            $regularEvents = $regularEvents->filter(
                'StartDate:LessThanOrEqual',
                $toDate->format('Y-m-d')
            );
        }

        // This ORDER BY must stay order-isomorphic to eventFeedSortKey()
        // restricted to EventPage rows - the LIMIT over-fetch below depends
        // on it.
        $regularEvents = $regularEvents->sort(['StartDate' => 'ASC', 'ID' => 'ASC']);

        if ($limit && $limit > 0) {
            // Over-fetch bound: any regular event among the first $limit of the
            // merged feed has at most $limit - 1 predecessors overall, so at
            // most $limit - 1 predecessors among regular events - i.e. it is
            // within SQL's first $limit rows. Fetching more is waste; fewer is
            // unsound.
            $regularEvents = $regularEvents->limit($limit);
        }

        // ------------------------------------------------------------------
        // Recurring events: bound the candidate set in SQL so only events that
        // can possibly occur inside the window are loaded and expanded.
        // ------------------------------------------------------------------
        $windowYears = $this->config()->get('default_recurring_window_years')
            ?? self::config()->get('default_recurring_window_years');
        $windowStart = $fromDate ? $fromDate->copy() : Carbon::now()->startOfYear();
        $windowEnd = $toDate ? $toDate->copy() : Carbon::now()->addYears($windowYears)->endOfYear();

        $recurringEvents = EventPage::get()
            ->filter('Recursion', ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'])
            ->exclude('StartDate', null)
            ->filter('StartDate:LessThanOrEqual', $windowEnd->format('Y-m-d'))
            ->filterAny([
                'RecursionEndDate' => null,
                'RecursionEndDate:GreaterThanOrEqual' => $windowStart->format('Y-m-d'),
            ]);

        if (!$allCalendars) {
            $recurringEvents = $recurringEvents->filter('ParentID', $this->ID);
        }

        if ($categoryIDs) {
            $recurringEvents = $recurringEvents->filter('Categories.ID', $categoryIDs);
        }

        $recurringEvents = $recurringEvents->toArray();
        $regularEvents = $regularEvents->toArray();

        // ------------------------------------------------------------------
        // Prime each event's Parent component: link generation walks Parent(),
        // and DataObject::getComponent() memoises per instance only - without
        // this, every distinct event in the response re-fetches its calendar
        // (one SiteTree query per event).
        // ------------------------------------------------------------------
        $this->primeParentComponents(array_merge($regularEvents, $recurringEvents), $allCalendars);

        // ------------------------------------------------------------------
        // ONE query for every exception of every selected recurring event.
        // ------------------------------------------------------------------
        $exceptionsByEvent = [];
        $recurringIDs = array_map(static fn ($event) => (int) $event->ID, $recurringEvents);

        if ($recurringIDs) {
            $exceptionRows = EventException::get()->filter('OriginalEventID', $recurringIDs);

            foreach ($exceptionRows as $exception) {
                $exceptionsByEvent[(int) $exception->OriginalEventID][(string) $exception->InstanceDate]
                    = $exception;
            }
        }

        // ------------------------------------------------------------------
        // Expand occurrences, feeding the prefetched exception map down so the
        // generator issues no queries of its own.
        // ------------------------------------------------------------------
        $occurrences = [];

        foreach ($recurringEvents as $event) {
            $exceptionsByDate = $exceptionsByEvent[(int) $event->ID] ?? [];

            foreach ($event->getCachedOccurrences($windowStart, $windowEnd, null, $exceptionsByDate) as $occurrence) {
                $instanceDate = $occurrence->getInstanceDate()->format('Y-m-d');
                $exception = $exceptionsByDate[$instanceDate] ?? null;

                // A stale instance-cache entry can still contain an occurrence
                // whose exception was created after the entry was written.
                if ($exception && $exception->isDeleted()) {
                    continue;
                }

                if ($exception && $exception->isModified()) {
                    // EventInstance::__get() resolves field overrides from the
                    // exception itself; only the flags need setting here.
                    $occurrence->IsModified = true;
                    $occurrence->Exception = $exception;

                    // A modification can move an occurrence outside the window.
                    if ($exception->hasOverride('StartDate')) {
                        $moved = Carbon::parse((string) $occurrence->StartDate);
                        if ($moved->lt($windowStart) || $moved->gt($windowEnd)) {
                            continue;
                        }
                    }
                }

                $occurrences[] = $occurrence;
            }
        }

        // ------------------------------------------------------------------
        // Merge, sort with a total order, then apply the final limit.
        // ------------------------------------------------------------------
        $merged = array_merge($regularEvents, $occurrences);

        usort($merged, fn ($a, $b) => $this->eventFeedSortKey($a) <=> $this->eventFeedSortKey($b));

        if ($limit && $limit > 0) {
            $merged = array_slice($merged, 0, $limit);
        }

        $allEvents = ArrayList::create($merged);

        $this->extend('updateEventsFeed', $allEvents);

        return $allEvents;
    }

    /**
     * Categories actually used by this calendar's events, as one DISTINCT query.
     *
     * Replaces two independent copies of a triple-join that fetched one row per
     * event x category and de-duplicated ~25 IDs out of thousands in PHP
     * (CalendarController::getAvailableCategoriesForTemplate() and
     * CalendarFilterForm::getAvailableCategories()).
     *
     * @return DataList<Category>
     */
    public function getUsedCategories(): DataList
    {
        $schema = EventPage::getSchema();
        $categoryTable = $schema->tableName(Category::class);
        $joinTable = $schema->tableName(EventPage::class) . '_Categories';

        // ParentID lives on the SiteTree table, which is stage-suffixed.
        $siteTreeTable = $schema->tableName(SiteTree::class);
        if (Versioned::get_stage() === Versioned::LIVE) {
            $siteTreeTable .= '_Live';
        }

        // DataList is distinct-by-default, so this is SELECT DISTINCT Category.*
        return Category::get()
            ->innerJoin(
                $joinTable,
                "\"{$categoryTable}\".\"ID\" = \"{$joinTable}\".\"CategoryID\""
            )
            ->innerJoin(
                $siteTreeTable,
                "\"{$siteTreeTable}\".\"ID\" = \"{$joinTable}\".\"EventPageID\""
                . " AND \"{$siteTreeTable}\".\"ParentID\" = " . (int) $this->ID
            )
            ->sort('Title', 'ASC');
    }

    /**
     * Prime the Parent component on a set of events so later Link() calls do
     * not each re-query the same calendar page.
     *
     * @param EventPage[] $events
     * @param bool $allCalendars
     */
    protected function primeParentComponents(array $events, bool $allCalendars): void
    {
        if (!$events) {
            return;
        }

        if (!$allCalendars) {
            // Single-calendar scope: every selected event's parent is $this.
            foreach ($events as $event) {
                if ((int) $event->ParentID === (int) $this->ID) {
                    $event->setComponent('Parent', $this);
                }
            }

            return;
        }

        $parentIDs = array_values(array_unique(array_map(
            static fn ($event) => (int) $event->ParentID,
            $events
        )));

        $parents = [];
        foreach (SiteTree::get()->byIDs($parentIDs) as $parent) {
            $parents[(int) $parent->ID] = $parent;
        }

        foreach ($events as $event) {
            if (isset($parents[(int) $event->ParentID])) {
                $event->setComponent('Parent', $parents[(int) $event->ParentID]);
            }
        }
    }

    /**
     * Total order over the merged feed.
     *
     * Must agree with the SQL ORDER BY used for the non-recurring branch when
     * restricted to EventPage rows, otherwise the SQL LIMIT over-fetch in
     * getEventsFeed() is unsound. Arrays compare element-wise, which avoids
     * string-key padding pitfalls.
     *
     * @param EventPage|EventInstance $event
     * @return array
     */
    protected function eventFeedSortKey($event): array
    {
        if ($event instanceof EventInstance) {
            return [
                (string) $event->getInstanceDate()->format('Y-m-d'),
                1,
                (int) $event->getOriginalEvent()->ID,
            ];
        }

        return [(string) $event->StartDate, 0, (int) $event->ID];
    }

    /**
     * Invalidate cached feeds when calendar settings are modified
     */
    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        $this->bumpCalendarCacheVersion((int) $this->ID);
    }
}
