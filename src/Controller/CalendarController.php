<?php

namespace Dynamic\Calendar\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Form\CalendarFilterForm;
use Dynamic\Calendar\Traits\LoggerFallback;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Cache\CacheFactory;
use Psr\SimpleCache\CacheInterface;
use Psr\Log\LogLevel;

/**
 * Calendar Controller
 *
 * Handles the display and filtering of calendar events, including virtual instances
 * created by the Carbon recursion system.
 *
 * @package Dynamic\Calendar\Controller
 */
class CalendarController extends \PageController
{
    use LoggerFallback;

    /**
     * @var Calendar
     */
    protected Calendar $calendar;

    /**
     * @var array
     */
    private static array $allowed_actions = [
        'index',
        'events',
        'ical',
    ];

    /**
     * @var array
     */
    private static array $url_handlers = [
        '' => 'index',
        'events' => 'events',
        'ical' => 'ical',
    ];

    /**
     * JSON cache TTL in seconds (default 30 minutes)
     * Can be configured per project via YAML config
     *
     * @var int
     */
    private static int $json_cache_ttl = 1800;

    /**
     * @var int
     */
    private static int $events_per_page = 12;

    /**
     * Timezone for events (should match where events are created)
     * Events are stored in this timezone and converted to UTC for ICS feeds
     *
     * @var string
     */
    private static string $timezone = 'UTC';

    /**
     * @var bool
     */
    protected bool $useDefaultFilter = false;

    /**
     * @var ArrayList
     */
    protected $events;

    /**
     * Constructor
     *
     * @param Calendar $calendar
     */
    public function __construct(Calendar $calendar)
    {
        $this->calendar = $calendar;
        parent::__construct($calendar);
    }

    /**
     * Default action - display calendar with events
     *
     * @param HTTPRequest $request
     * @return array
     */
    public function index(HTTPRequest $request): array
    {
        return $this->renderCalendar($request);
    }

    /**
     * Check if the request is an AJAX request
     *
     * @param HTTPRequest $request
     * @return bool
     */
    private function isAjaxRequest(HTTPRequest $request): bool
    {
        return $request->isAjax()
            || $request->getHeader('Accept') === 'application/json'
            || $request->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Events action for AJAX requests
     *
     * @param HTTPRequest $request
     * @return HTTPResponse|array
     */
    public function events(HTTPRequest $request)
    {
        // Resolved once and passed down, so the cache gate, the cache key and
        // the feed query all read the same values instead of each re-deriving
        // them (issue #162).
        $filters = $this->getFilterParams($request);
        $shouldCache = $this->shouldCacheEvents($filters);
        $cacheKey = null;

        // Check cache for JSON responses first.
        // Search-filtered requests are deliberately excluded. The hazard is not
        // that one visitor's search results reach another visitor - the write
        // gate below means the pool never holds a search-filtered body. It is
        // the reverse direction: `search` is absent from the key, so an
        // ungated read here would hand a searching visitor the unfiltered feed,
        // showing every event under UI that claims to show matches. The gate
        // stays load-bearing even though nothing search-filtered is ever
        // written. Such requests still report X-Calendar-Cache: MISS, which is
        // accurate - nothing was served from the cache.
        if ($shouldCache && $this->isAjaxRequest($request)) {
            // Generated once and reused by the write below, so the two cannot
            // drift and the category lookup behind it runs at most once.
            $cacheKey = $this->generateEventsCacheKey($request, $filters);
            $cache = $this->getEventsCache();
            $cachedJson = $cache->get($cacheKey);

            if ($cachedJson !== null) {
                $response = $this->getResponse();
                $response->addHeader('Content-Type', 'application/json');
                $response->addHeader('X-Calendar-Cache', 'HIT');
                return $response->setBody($cachedJson);
            }
        }

        $fromDate = $this->getFromDate($request);
        $toDate = $this->getToDate($request);

        // Reuse the same resolution generateEventsCacheKey() already read the
        // cache key from. A second, independent derivation here (as this used
        // to do) can drift from it: two implementations of "what categories
        // does this request mean" agreeing today does not guarantee they keep
        // agreeing, and a cache key that describes a different body than the
        // one it's stored under is a poisoning hole, not just a wasted entry.
        $resolvedCategoryIDs = $this->resolveCategoryIDs($request);
        if ($resolvedCategoryIDs === null) {
            // If no categories provided in request, check if calendar has default categories
            $defaultCategories = $this->calendar->DefaultCategories();
            $categories = ($defaultCategories && $defaultCategories->exists()) ? $defaultCategories : null;
        } elseif ($resolvedCategoryIDs === []) {
            // Present, matched nothing: byIDs([]) throws InvalidArgumentException
            // in this framework version rather than returning an empty list
            // (confirmed - not assumed). getEventsFeed() treats a null $categories
            // and a non-existent DataList identically (`$categories && $categories
            // ->exists()`), so null reproduces the same unfiltered-feed behaviour
            // without the empty-set call. Skips the default-category fallback
            // above deliberately - the request DID specify categories, just ones
            // that don't exist, so it must not fall back to filtering.
            $categories = null;
        } else {
            $categories = Category::get()->byIDs($resolvedCategoryIDs);
        }

        $events = $this->calendar->getEventsFeed(
            null,
            $categories,
            $fromDate,
            $toDate,
            $filters
        );

        // Check if this is an AJAX request for JSON data
        if ($this->isAjaxRequest($request)) {
            // Transform events for FullCalendar format
            $eventsData = [];
            foreach ($events as $event) {
                $eventData = [
                    'id' => $event->ID,
                    'title' => $event->Title,
                    'start' => $event->StartDate,
                    'allDay' => true, // Default to all day
                    'url' => $event->AbsoluteLink(),
                    'extendedProps' => [
                        'summary' => $event->Summary ? $event->dbObject('Summary')->Summary(100) : '',
                        'categories' => [],
                        'isRecurring' => $event->Recursion !== 'NONE'
                    ]
                ];

                // Add time information if available
                if ($event->StartTime) {
                    $eventData['start'] = $event->StartDate . 'T' . $event->StartTime;
                    $eventData['allDay'] = false;
                }

                if ($event->EndDate && $event->EndTime) {
                    $eventData['end'] = $event->EndDate . 'T' . $event->EndTime;
                } elseif ($event->EndDate) {
                    $eventData['end'] = $event->EndDate;
                }

                // Add category information with colors
                $categoryColor = null;

                // Handle both EventPage and EventInstance objects
                $categories = null;
                if ($event instanceof EventInstance) {
                    // For EventInstance, get categories from the original event
                    if ($event->originalEvent && $event->originalEvent->exists()) {
                        $categories = $event->originalEvent->Categories();
                    }
                } else {
                    // For regular EventPage objects
                    $categories = $event->Categories();
                }

                if ($categories && $categories->exists()) {
                    foreach ($categories as $category) {
                        $eventData['extendedProps']['categories'][] = [
                            'ID' => $category->ID,
                            'Title' => $category->Title,
                            'Color' => $category->ColorPreview
                        ];
                        // Use first category's color for event styling
                        if ($categoryColor === null) {
                            $categoryColor = '#' . $category->getColorHex();
                        }
                    }
                }

                // Apply the first category's color to the event
                if ($categoryColor !== null) {
                    $eventData['backgroundColor'] = $categoryColor;
                    $eventData['borderColor'] = $categoryColor;
                    $eventData['textColor'] = $this->getContrastColor($categoryColor);
                }

                $eventsData[] = $eventData;
            }

            $json = json_encode($eventsData);

            // Cache the JSON response under the same key the read above used.
            // A null key means this request was gated off the cache — a live
            // search filter (issue #162): its payload is not described by any
            // key that omits `search`, so writing it would leak those results
            // to other filter combinations, and mint one entry per term.
            if ($cacheKey !== null) {
                $cache = $this->getEventsCache();
                if (!$cache->set($cacheKey, $json, $this->config()->get('json_cache_ttl'))) {
                    $this->logCacheWriteFailure($cacheKey);
                }
            }

            $response = $this->getResponse();
            $response->addHeader('Content-Type', 'application/json');
            $response->addHeader('X-Calendar-Cache', 'MISS');
            return $response->setBody($json);
        }

        // For non-AJAX requests, return template data
        return [
            'Events' => $events,
            'TotalEvents' => $events->count(),
        ];
    }

    /**
     * Get the calendar filter form
     *
     * @return CalendarFilterForm
     */
    public function FilterForm(): CalendarFilterForm
    {
        return CalendarFilterForm::create($this, 'FilterForm', $this->calendar, $this->getRequest());
    }

    /**
     * Render the calendar with events
     *
     * @param HTTPRequest $request
     * @return array
     */
    protected function renderCalendar(HTTPRequest $request): array
    {
        $fromDate = $this->getFromDate($request);
        $toDate = $this->getToDate($request);

        // Get category filter
        $categoryIDs = $request->getVar('categories');
        $categories = null;

        if ($categoryIDs) {
            if (!is_array($categoryIDs)) {
                $categoryIDs = [$categoryIDs];
            }
            $categories = Category::get()->byIDs($categoryIDs);
        }

        // Use the Calendar page's getEventsFeed method with category filtering
        $events = $this->calendar->getEventsFeed(
            null,
            $categories,
            $fromDate,
            $toDate,
            $this->getFilterParams($request)
        );

        // Create paginated list
        $paginatedEvents = PaginatedList::create($events, $request);
        $paginatedEvents->setPageLength($this->config()->get('events_per_page'));

        return [
            'Calendar' => $this->calendar,
            'Events' => $paginatedEvents,
            'CurrentFromDate' => $fromDate ? $fromDate->format('Y-m-d') : null,
            'CurrentToDate' => $toDate ? $toDate->format('Y-m-d') : null,
            'RecurringEventsCount' => $this->getRecurringEventsCount(),
            'OneTimeEventsCount' => $this->getOneTimeEventsCount(),
            'AvailableCategories' => $this->getAvailableCategoriesForTemplate($request),
            'ShowCategoryFilter' => $this->calendar->ShowCategoryFilter,
        ];
    }

    /**
     * Longest search string accepted. Bounds the SQL LIKE operand, keeping the
     * matching work a fixed size regardless of how long a visitor-supplied
     * value is.
     *
     * It bounds nothing on the cache path: `search` is excluded from
     * generateEventsCacheKey() and events() neither reads nor writes the cache
     * while a search filter is live (issue #162), so a search value never
     * reaches the CalendarJSON pool at any length.
     */
    public const SEARCH_MAX_LENGTH = 64;

    /**
     * Longest submitted `categories` list resolveCategoryIDs() will query on.
     * Bounds the SQL IN() list to a fixed size regardless of how many values a
     * visitor supplies; a request over the cap is truncated before the query,
     * identically for the feed body and the cache key that describes it.
     */
    public const MAX_SUBMITTED_CATEGORIES = 100;

    /**
     * Normalise the optional feed filters from a request.
     *
     * These params were sent by CalendarFilterForm and appended to the
     * events XHR by CalendarView.js all along - the controller just never
     * read them (issue #133). allDay uses a strict allowlist: '0' (Timed
     * Events) is a real filter value that if($var) would drop, and anything
     * outside '0'/'1' means "no filter".
     *
     * Public and static because it is the SINGLE definition of "what counts
     * as an active filter". CalendarFilterForm's hasActiveFiltersStatic() and
     * getFilterSummary() consume it too, so the chrome (the active-filter
     * banner, the Clear Filters link, the summary) can never claim a filter
     * is live that this method discarded. An earlier version duplicated the
     * allowlists in the form and they drifted: ?eventType=banana rendered
     * "Clear Filters" over a completely unfiltered feed - the same silent
     * UI-lies-about-the-server defect as #133 itself, relocated to the chrome.
     * Add a new filter param here and both consumers pick it up for free.
     *
     * @return array{search: string, eventType: string, allDay: string|null}
     */
    public static function normaliseFilterParams(HTTPRequest $request): array
    {
        // Array-typed params (?search[]=x) must not reach the string casts.
        $rawSearch = $request->getVar('search');
        $search = is_string($rawSearch)
            ? mb_substr(trim($rawSearch), 0, self::SEARCH_MAX_LENGTH)
            : '';

        $rawType = $request->getVar('eventType');
        $eventType = is_string($rawType) && in_array($rawType, ['one-time', 'recurring'], true)
            ? $rawType
            : '';

        // Allowlisted like eventType: the form submits exactly '0' or '1',
        // and anything else ('banana', 'false', '0.0') must mean "no filter"
        // rather than silently coercing to an all-day-only filter.
        $allDay = $request->getVar('allDay');
        $allDay = (is_string($allDay) && in_array($allDay, ['0', '1'], true))
            ? $allDay
            : null;

        return [
            'search' => $search,
            'eventType' => $eventType,
            'allDay' => $allDay,
        ];
    }

    /**
     * Instance-side alias kept so existing call sites read naturally.
     *
     * @return array{search: string, eventType: string, allDay: string|null}
     */
    protected function getFilterParams(HTTPRequest $request): array
    {
        return self::normaliseFilterParams($request);
    }

    /**
     * Get from date from request or null if no filter applied
     *
     * @param HTTPRequest $request
     * @return Carbon|null
     */
    protected function getFromDate(HTTPRequest $request): ?Carbon
    {
        // Support both 'from' (legacy) and 'start' (FullCalendar) parameter names
        $from = $request->getVar('from') ?? $request->getVar('start');

        if ($from && Carbon::hasFormat($from, 'Y-m-d')) {
            return Carbon::createFromFormat('Y-m-d', $from);
        }

        // Return null when no date filter is applied - this will show all events
        return null;
    }

    /**
     * Get to date from request or null if no filter applied
     *
     * @param HTTPRequest $request
     * @return Carbon|null
     */
    protected function getToDate(HTTPRequest $request): ?Carbon
    {
        // Support both 'to' (legacy) and 'end' (FullCalendar) parameter names
        $to = $request->getVar('to') ?? $request->getVar('end');

        if ($to && Carbon::hasFormat($to, 'Y-m-d')) {
            return Carbon::createFromFormat('Y-m-d', $to);
        }

        // Return null when no date filter is applied
        return null;
    }

    /**
     * Get count of recurring events
     *
     * @return int
     */
    protected function getRecurringEventsCount(): int
    {
        return EventPage::get()
            ->filter([
                'ParentID' => $this->calendar->ID,
            ])
            ->exclude('Recursion', 'NONE')
            ->count();
    }

    /**
     * Get count of one-time events
     *
     * @return int
     */
    protected function getOneTimeEventsCount(): int
    {
        return EventPage::get()
            ->filter([
                'ParentID' => $this->calendar->ID,
                'Recursion' => 'NONE',
            ])
            ->count();
    }

    /**
     * Get link to this calendar
     *
     * @param string $action
     * @return string
     */
    public function Link($action = null): string
    {
        return $this->calendar->Link($action);
    }

    /**
     * Get the calendar page
     *
     * @return Calendar
     */
    public function getCalendar(): Calendar
    {
        return $this->calendar;
    }

    /**
     * Get available categories for template with selection state
     *
     * @param HTTPRequest $request
     * @return ArrayList
     */
    protected function getAvailableCategoriesForTemplate(HTTPRequest $request): ArrayList
    {
        $selectedCategoryIDs = $request->getVar('categories') ?: [];
        if (!is_array($selectedCategoryIDs)) {
            $selectedCategoryIDs = [$selectedCategoryIDs];
        }

        // Get categories that are actually used by events in this calendar
        // Use efficient join query to avoid N+1 problem
        $categoryIDs = EventPage::get()
            ->filter(['ParentID' => $this->calendar->ID])
            ->leftJoin('EventPage_Categories', '"EventPage"."ID" = "EventPage_Categories"."EventPageID"')
            ->leftJoin('Category', '"EventPage_Categories"."CategoryID" = "Category"."ID"')
            ->column('Category.ID');

        // Remove duplicates and null values
        $categoryIDs = array_unique(array_filter($categoryIDs));

        // Get the category objects
        $availableCategories = ArrayList::create();
        if (!empty($categoryIDs)) {
            $categories = Category::get()->byIDs($categoryIDs)->sort('Title ASC');

            foreach ($categories as $category) {
                $categoryData = ArrayData::create([
                    'ID' => $category->ID,
                    'Title' => $category->Title,
                    'IsSelected' => in_array($category->ID, $selectedCategoryIDs),
                ]);
                $availableCategories->push($categoryData);
            }
        }

        return $availableCategories;
    }

    /**
     * Clean and sanitize request variables
     *
     * @param array $vars
     * @return array
     */
    public static function clean_request_vars(array $vars): array
    {
        // Remove any potentially dangerous variables
        $cleanVars = [];
        foreach ($vars as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $cleanVars[$key] = $value;
            } elseif (is_string($key) && is_array($value)) {
                $cleanVars[$key] = self::clean_request_vars($value);
            }
        }
        return $cleanVars;
    }

    /**
     * Get appropriate text color (white/black) based on background color for accessibility
     *
     * @param string $backgroundColor Hex color code
     * @return string
     */
    private function getContrastColor(string $backgroundColor): string
    {
        // Remove # if present
        $color = ltrim($backgroundColor, '#');

        // Convert to RGB
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));

        // Calculate luminance using relative luminance formula
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Return white for dark colors, black for light colors
        return $luminance > 0.5 ? '#000000' : '#FFFFFF';
    }

    /**
     * ICS action for generating iCalendar feeds
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function ical(HTTPRequest $request)
    {
        $fromDate = $this->getFromDate($request);
        $toDate = $this->getToDate($request);

        // Get category filter - reuse existing logic
        $categoryIDs = $request->getVar('categories');
        $categories = null;

        if ($categoryIDs) {
            if (!is_array($categoryIDs)) {
                $categoryIDs = [$categoryIDs];
            }
            $categories = Category::get()->byIDs($categoryIDs);
        }

        // Use the existing Calendar page's getEventsFeed method
        $events = $this->calendar->getEventsFeed(
            null,
            $categories,
            $fromDate,
            $toDate,
            $this->getFilterParams($request)
        );

        // Generate ICS content manually for now
        $icsContent = $this->generateICSContent($events);

        // Set appropriate headers for ICS response
        $response = $this->getResponse();
        $response->addHeader('Content-Type', 'text/calendar; charset=utf-8');
        $response->addHeader('Content-Disposition', 'attachment; filename="calendar.ics"');
        $response->addHeader('Cache-Control', 'no-cache, must-revalidate');

        return $response->setBody($icsContent);
    }

    /**
     * Generate ICS content from events
     *
     * @param ArrayList $events
     * @return string
     */
    private function generateICSContent(ArrayList $events): string
    {
        $ics = [];

        // ICS Header
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = 'PRODID:-//Dynamic SilverStripe Calendar//EN';
        $ics[] = 'CALSCALE:GREGORIAN';
        $ics[] = 'METHOD:PUBLISH';

        // Add events
        foreach ($events as $event) {
            $eventICS = $this->transformEventToICS($event);
            if ($eventICS) {
                $ics = array_merge($ics, $eventICS);
            }
        }

        // ICS Footer
        $ics[] = 'END:VCALENDAR';

        return implode("\r\n", $ics);
    }

    /**
     * Transform an event to ICS format
     *
     * @param EventPage|EventInstance $event
     * @return array|null
     */
    private function transformEventToICS($event): ?array
    {
        try {
            $ics = [];

            $ics[] = 'BEGIN:VEVENT';

            // Set unique ID
            $uniqueId = $event->ID;
            if ($event->hasMethod('getInstanceDate')) {
                // For recurring event instances, include the instance date in the ID
                $uniqueId .= '-' . $event->getInstanceDate()->format('Ymd');
            }
            $ics[] = 'UID:' . $uniqueId . '@' . $_SERVER['HTTP_HOST'] ?? 'calendar.local';

            // Add timestamp
            $ics[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');

            // Set basic event properties
            $ics[] = 'SUMMARY:' . $this->escapeICSValue($event->Title);

            // Add description if available
            if ($event->Content) {
                $ics[] = 'DESCRIPTION:' . $this->escapeICSValue(strip_tags($event->Content));
            }

            // Add location if available
            if ($event->Location) {
                $ics[] = 'LOCATION:' . $this->escapeICSValue($event->Location);
            }

                        // Handle dates and times
            if ($event->AllDay) {
                // All-day event
                $ics[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $event->StartDate);
                if ($event->EndDate) {
                    // For all-day events, end date should be the day after
                    $endDate = Carbon::parse($event->EndDate)->addDay();
                    $ics[] = 'DTEND;VALUE=DATE:' . $endDate->format('Ymd');
                }
            } else {
                // Timed event - parse in the configured timezone, then convert to UTC
                $timezone = $this->config()->get('timezone');

                $startDateTime = Carbon::parse($event->StartDate . ' ' . $event->StartTime, $timezone);
                $ics[] = 'DTSTART:' . $startDateTime->utc()->format('Ymd\THis\Z');

                if ($event->EndDate && $event->EndTime) {
                    $endDateTime = Carbon::parse($event->EndDate . ' ' . $event->EndTime, $timezone);
                    $ics[] = 'DTEND:' . $endDateTime->utc()->format('Ymd\THis\Z');
                } else {
                    // Default 1 hour duration
                    $endDateTime = $startDateTime->copy()->addHour();
                    $ics[] = 'DTEND:' . $endDateTime->utc()->format('Ymd\THis\Z');
                }
            }

            // Add categories
            $eventCategories = $event->hasMethod('getOriginalEvent')
                ? $event->getOriginalEvent()->Categories()
                : $event->Categories();

            if ($eventCategories && $eventCategories->exists()) {
                $categoryNames = $eventCategories->map('Title')->toArray();
                $ics[] = 'CATEGORIES:' . implode(',', array_map([$this, 'escapeICSValue'], $categoryNames));
            }

            // Add URL if available
            $url = $event->AbsoluteLink();
            if ($url) {
                $ics[] = 'URL:' . $url;
            }

            $ics[] = 'END:VEVENT';

            return $ics;
        } catch (\Exception $e) {
            // Log error and continue with other events
            $this->logWithFallback(
                "Error transforming event {$event->ID} to ICS: " . $e->getMessage(),
                LogLevel::ERROR
            );
            return null;
        }
    }

    /**
     * Escape ICS values according to RFC 5545
     *
     * @param string $value
     * @return string
     */
    private function escapeICSValue(string $value): string
    {
        // Escape special characters
        $value = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $value);

        return $value;
    }

    /**
     * Whether this request's events JSON may touch the CalendarJSON cache.
     *
     * A live search filter makes the response uncacheable (issue #162):
     * visitor-supplied search text has no cardinality bound, so it is excluded
     * from generateEventsCacheKey() - and an entry keyed without it must never
     * be read for, or written by, a search-filtered response. Gating both the
     * read and the write on this one accessor is what keeps the two call sites
     * in events() from drifting apart.
     *
     * Reads getFilterParams(), the single definition of "what counts as an
     * active filter", rather than the raw request var: ?search[]=x is coerced
     * to '' there, so this agrees with the feed query about which responses are
     * genuinely search-filtered (an array-typed search filters nothing, and its
     * response is the ordinary cacheable feed).
     *
     * @param array{search: string, eventType: string, allDay: string|null} $filters
     * @return bool
     */
    private function shouldCacheEvents(array $filters): bool
    {
        return $filters['search'] === '';
    }

    /**
     * Resolve the submitted `categories` param to the integer IDs the database
     * actually matched, or null when the param is absent.
     *
     * null and [] are deliberately different answers: null takes events()'
     * default-category fallback, [] means "present, matched nothing" and leaves
     * the feed unfiltered. Collapsing them would serve a default-filtered feed
     * to a request that turned filtering off.
     *
     * The point of resolving rather than sanitising is that any local
     * canonicalisation has to re-implement byIDs()/ExactMatchFilter's own
     * admission test, and getting it wrong is a poisoning hole rather than a
     * lost cache hit: ExactMatchFilter::usePlaceholders() binds anything that
     * is not ctype_digit and not numerically equal to its int form, so
     * ?categories[]=1.9 matches no row and yields the UNFILTERED body, while a
     * local (int) cast would have called that category 1 and keyed it as such.
     *
     * Costs one indexed primary-key SELECT, and only for requests that supply
     * the param, so an ordinary no-param cache hit stays query-free. This is
     * now the single place the feed and the cache key both resolve categories
     * from (issue #162 round 3) - a second, independent derivation previously
     * lived in events(), and nothing kept the two in agreement.
     *
     * The submitted list is capped at self::MAX_SUBMITTED_CATEGORIES before it
     * reaches the query: an unbounded IN() list is a real cost on every
     * categorised request, cache hit or miss, not just an uncached one. The
     * cap applies here - the one place both the key and the body read from -
     * so a request over the cap is truncated identically for both; it cannot
     * key one set and filter another.
     *
     * @return int[]|null
     */
    private function resolveCategoryIDs(HTTPRequest $request): ?array
    {
        $rawCategories = $request->getVar('categories');
        if (!$rawCategories) {
            return null;
        }

        $submitted = array_filter(
            is_array($rawCategories) ? $rawCategories : [$rawCategories],
            function ($value): bool {
                return is_scalar($value) && $value !== '';
            }
        );
        if ($submitted === []) {
            return [];
        }

        $submitted = array_slice($submitted, 0, self::MAX_SUBMITTED_CATEGORIES);
        $matched = Category::get()->byIDs($submitted)->column('ID');

        return array_map('intval', $matched);
    }

    /**
     * Generate a cache key for the events JSON response
     *
     * Every variable part is derived from the value the feed query actually
     * used, never from the raw request parameter. Keying on raw text and
     * querying on a parsed value lets two requests that produce the same body
     * mint different entries (unbounded pool growth), and worse, lets two
     * requests that produce different bodies share one entry (poisoning).
     *
     * @param HTTPRequest $request
     * @param array{search: string, eventType: string, allDay: string|null} $filters
     * @return string
     */
    private function generateEventsCacheKey(HTTPRequest $request, array $filters): string
    {
        // Read the parsed dates, not the raw params. getFromDate()/getToDate()
        // prefer the legacy from/to over start/end and reject anything that is
        // not Y-m-d; the key must follow that same resolution or a request
        // carrying BOTH names (FullCalendar always sends start/end, a scraper
        // can add from/to) keys on one window and queries another. That is a
        // poisoning hole, not just wasted space: an attacker could write an
        // empty window's payload under the key every browser reads.
        // A formatted date is also a legal cache-key literal, so no hashing.
        $fromDate = $this->getFromDate($request);
        $toDate = $this->getToDate($request);
        $start = $fromDate ? $fromDate->format('Y-m-d') : 'no-start';
        $end = $toDate ? $toDate->format('Y-m-d') : 'no-end';

        // The IDs the database matched (see resolveCategoryIDs()). null = param
        // absent, which takes events()' default-category fallback; an empty list
        // = present but matched nothing, which skips that fallback and leaves the
        // feed unfiltered. Different bodies, so distinct tokens. Hashed because
        // the matched list is variable-length.
        //
        // Distinct matched subsets, and distinct valid date windows, still key
        // distinctly - that is genuinely distinct output. Bounding how many such
        // entries may accumulate is a design decision, tracked in #193. Reading
        // mode (draft vs live) is not keyed here either: the live CacheFactory
        // wraps every cache in VersionedCacheAdapter, which appends the reading
        // mode to the key itself (see #149).
        $resolvedCategories = $this->resolveCategoryIDs($request);
        if ($resolvedCategories === null) {
            $cats = 'no-cats';
        } else {
            sort($resolvedCategories);
            $cats = $resolvedCategories
                ? 'cats-' . md5(implode('-', $resolvedCategories))
                : 'cats-none';
        }

        // Filters that change the response body must be part of the key - a
        // shared entry would serve filtered results to unfiltered requests and
        // vice versa. Built by iterating $filters rather than naming
        // eventType/allDay individually (issue #162 round 3): a filter added
        // to getFilterParams() in the future is keyed by default, so leaving
        // one out of the key has to be a deliberate edit to this exclusion
        // list, not a silent omission. `search` is the one deliberate
        // exclusion - it is unbounded free text, and keying on it per value
        // minted one permanent entry per term, which is the pool growth being
        // fixed. events() neither reads nor writes the cache while a search
        // filter is live (see shouldCacheEvents()), so no cached entry ever
        // holds search-filtered results; the tradeoff this accepts is that a
        // search-filtered request always re-runs the full uncached query
        // (documented on issue #193 alongside the cardinality tradeoff it
        // already tracks, rather than building bounded search caching here).
        $filterParts = [];
        foreach ($filters as $name => $value) {
            // `''`/null is the "not set" convention every normaliseFilterParams()
            // entry uses (see its own allowlist comments) - a filter at that
            // value contributes nothing to the key, matching every existing
            // filter's behaviour and keeping the common "no filters" request
            // on its own short, readable key rather than every request paying
            // for every filter name.
            if ($name === 'search' || $value === '' || $value === null) {
                continue;
            }
            $filterParts[] = $name . '-' . $value;
        }
        $filterPart = $filterParts === [] ? 'no-filters' : 'filters-' . implode('-', $filterParts);

        $parts = [
            'calendar_json',
            $this->calendar->ID,
            $start,
            $end,
            $cats,
            $filterPart
        ];

        return implode('_', $parts);
    }

    /**
     * Get cache instance for events JSON
     *
     * @return CacheInterface
     */
    private function getEventsCache(): CacheInterface
    {
        return Injector::inst()->get(CacheFactory::class)->create(
            'CalendarJSON',
            ['defaultLifetime' => $this->config()->get('json_cache_ttl')]
        );
    }

    /**
     * Log a failed events cache write. A false-returning set() otherwise
     * leaves every request a permanent, indistinguishable MISS. Symfony's
     * own cache adapter already logs a "Failed to save key" warning in this
     * case when it has a logger attached (which SilverStripe's default
     * CacheFactory wiring provides) - this adds calendar-specific context
     * (the calendar cache key) rather than being the only signal, and is
     * the sole signal for a CacheFactory implementation that doesn't wire
     * one. The logger lookup itself is guarded by LoggerFallback::logWithFallback(),
     * which keeps a missing or misconfigured logger service from turning a cache-write
     * failure into a fatal error on the response path.
     *
     * @param string $cacheKey
     * @return void
     */
    private function logCacheWriteFailure(string $cacheKey): void
    {
        $message = 'CalendarController: failed to write events cache entry - ' . $cacheKey;
        $this->logWithFallback($message);
    }
}
