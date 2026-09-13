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
use SilverStripe\Versioned\Versioned;
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
        $isAjax = $this->isAjaxRequest($request);
        // Resolved once, so the entry this action reads from and the entry it
        // writes to are the same one, and computed once rather than once per
        // pass. Both stay null off the AJAX path, where no cache is touched.
        $cache = $isAjax ? $this->getEventsCache() : null;
        $cacheKey = $isAjax ? $this->generateEventsCacheKey($request) : null;

        // Check cache for JSON responses first
        if ($cache !== null && $cacheKey !== null) {
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

        // Get category filter
        $categoryIDs = $request->getVar('categories');
        $categories = null;

        if ($categoryIDs) {
            if (!is_array($categoryIDs)) {
                $categoryIDs = [$categoryIDs];
            }
            $categories = Category::get()->byIDs($categoryIDs);
        } else {
            // If no categories provided in request, check if calendar has default categories
            $defaultCategories = $this->calendar->DefaultCategories();
            if ($defaultCategories && $defaultCategories->exists()) {
                $categories = $defaultCategories;
            }
        }

        $events = $this->calendar->getEventsFeed(
            null,
            $categories,
            $fromDate,
            $toDate,
            $this->getFilterParams($request)
        );

        // Same snapshot as the cache read above, so the branch taken here
        // cannot disagree with the one taken there.
        if ($isAjax) {
            // Transform events for FullCalendar format
            $eventsData = [];
            foreach ($events as $event) {
                // AllDay is the source of truth (issue #150). It is the field an editor
                // sets in the CMS, and it is the column the feed filters on in
                // Calendar::getEventsFeed(). Deriving allDay from StartTime presence instead
                // let the two disagree, so one record could match ?allDay=1 and still be
                // drawn as timed. For an EventInstance this resolves through __get() to the
                // exception's ModifiedAllDay when one is set, so an occurrence can be promoted
                // to all-day. It cannot be demoted: ModifiedAllDay is a NOT NULL Boolean, so
                // ModifiedAllDay = 0 is indistinguishable from "no override" and the parent's
                // all-day value wins. A ModifiedStartTime on such an occurrence is dropped
                // below. Making the column nullable is tracked in issue #143.
                $allDay = (bool) $event->AllDay;

                $eventData = [
                    'id' => $event->ID,
                    'title' => $event->Title,
                    // Cast: an EventInstance resolves StartDate/EndDate through __get() to a
                    // DBField object, which json_encode() would emit as {} rather than a date.
                    'start' => (string) $event->StartDate,
                    'allDay' => $allDay,
                    'url' => $event->AbsoluteLink(),
                    'extendedProps' => [
                        'summary' => $event->Summary ? $event->dbObject('Summary')->Summary(100) : '',
                        'categories' => [],
                        'isRecurring' => $event->Recursion !== 'NONE'
                    ]
                ];

                // Only a timed event carries a clock time. An all-day row keeps a date-only
                // start even when a StartTime is still stored against it, which is the state
                // of every row saved as all-day before the onBeforeWrite() guard existed: the
                // CMS hides the time field without emptying it. Emitting that leftover here
                // would draw the row as timed and contradict the filter that matched it.
                if (!$allDay && $event->StartTime) {
                    $eventData['start'] = $event->StartDate . 'T' . $event->StartTime;
                }

                if ($event->EndDate) {
                    // Same rule on the far edge: an all-day range stays date-only.
                    $eventData['end'] = (!$allDay && $event->EndTime)
                        ? $event->EndDate . 'T' . $event->EndTime
                        : (string) $event->EndDate;
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

            // $cache and $cacheKey were resolved at the top of this action for
            // exactly this branch, so neither is null here.
            if (!$cache->set($cacheKey, $json, $this->config()->get('json_cache_ttl'))) {
                $this->logCacheWriteFailure($cacheKey);
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
     * Longest search string accepted. Bounds the SQL LIKE operand and the
     * pre-hash cache-key input, keeping both to a fixed size regardless of
     * how long a visitor-supplied value is. It does not bound cache-key
     * cardinality - distinct short values (?search=a1, ?search=a2, ...)
     * still mint unbounded distinct cache entries, since the key is hashed
     * per value in generateEventsCacheKey() regardless of length.
     */
    public const SEARCH_MAX_LENGTH = 64;

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
        // Legacy 'from' wins over 'start' (FullCalendar), but only when it is a
        // non-empty string: ?? alone would let an array-typed `?from[]=x` win the
        // coalesce, fail the string check below, and so discard a valid
        // `&start=` that was also present - widening the feed instead of failing.
        // A non-empty but UNPARSEABLE `from` (`?from=garbage&start=2025-10-01`)
        // still wins here and is dropped by the hasFormat() check below, also
        // widening the feed - pre-existing behaviour, unchanged by this guard.
        $from = $request->getVar('from');
        if (!is_string($from) || $from === '') {
            $from = $request->getVar('start');
        }

        // is_string() before the string-typed hasFormat(): array-typed params
        // (`?from[]=x`) must not reach it, same convention as
        // normaliseFilterParams(). Without it such a request is an uncaught
        // TypeError, and since the cache key now resolves dates through this
        // accessor it fires on the cache path too, ahead of the cache read.
        if (is_string($from) && Carbon::hasFormat($from, 'Y-m-d')) {
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
        // Legacy 'to' wins over 'end', falling through when empty or not a
        // string - see
        // getFromDate() for why ?? alone is not enough, and for the same
        // non-empty-but-unparseable caveat.
        $to = $request->getVar('to');
        if (!is_string($to) || $to === '') {
            $to = $request->getVar('end');
        }

        // See getFromDate() - array-typed params must not reach hasFormat().
        if (is_string($to) && Carbon::hasFormat($to, 'Y-m-d')) {
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
     * Generate a cache key for the events JSON response
     *
     * Covers every request-parameter and reading-mode component the response
     * body varies on, so no two such requests share an entry.
     *
     * NOT covered, two dimensions, both pre-existing and both filed:
     *  - the absolute-URL dimension. The body embeds `AbsoluteLink()`, which
     *    resolves through Director::host() and so varies with the request Host
     *    header, and with the scheme, unless `alternate_base_url` is pinned.
     *    See #206.
     *  - time of day. getFromDate()/getToDate() parse with 'Y-m-d', which
     *    fills the unspecified time fields from now rather than midnight, so
     *    the resolved window's boundaries move with the wall clock while this
     *    key does not. See #207.
     *
     * The Versioned reading mode IS covered here rather than left to the
     * framework's versioned cache adapter, which also segments by reading mode
     * but only while the mode is non-empty and only while that adapter is in
     * place. The full mode is used, not Versioned::get_stage(), because
     * get_stage() is null for every `Archive.<date>.<stage>` mode and for an
     * empty mode.
     *
     * @param HTTPRequest $request
     * @return string
     */
    private function generateEventsCacheKey(HTTPRequest $request): string
    {
        // Cast, not merely for symmetry: Versioned::$reading_mode is null until
        // something sets it, and md5(null) is deprecated in PHP 8.1+. The empty
        // string is what Versioned::reset() leaves behind. It is NOT equivalent
        // to Stage.Live - ReadingMode::toDataQueryParams('') returns null, so
        // Versioned::augmentSQL() early-returns and the query hits the draft
        // base table. It therefore needs its own bucket, and
        // VersionedCacheAdapter::getKeyID() will not provide one because it
        // appends nothing for a falsy mode.
        $mode = (string) Versioned::get_reading_mode();

        // Key on the RESOLVED dates, not the raw parameters, so that the key
        // and the body are derived from the same values. getFromDate() and
        // getToDate() prefer the legacy 'from'/'to' when that value is a
        // usable non-empty string, and fall back to 'start'/'end' otherwise.
        // Reading the raw parameters here would prefer 'start' instead, which
        // would hand '?start=A&end=B&from=C' (window C..B) and
        // '?start=A&end=B' (window A..B) one key despite different windows.
        // Values failing the strict Y-m-d check - garbage, ISO8601 datetimes,
        // unpadded days - resolve to null, i.e. no filter, so they key as
        // 'no-start'/'no-end': one entry for every unfiltered request rather
        // than one per distinct invalid string.
        // Formatted as Ymd: Symfony cache keys cannot contain {}()/\@:.
        $fromDate = $this->getFromDate($request);
        $start = $fromDate ? $fromDate->format('Ymd') : 'no-start';
        $toDate = $this->getToDate($request);
        $end = $toDate ? $toDate->format('Ymd') : 'no-end';

        // Key on the resolved, sorted category IDs rather than the raw
        // parameter, so ?categories[]=1&categories[]=2 and its reverse
        // ordering share a cache entry.
        $categoryIDs = $request->getVar('categories');
        if ($categoryIDs) {
            if (!is_array($categoryIDs)) {
                $categoryIDs = [$categoryIDs];
            }
            sort($categoryIDs);
            $cats = md5(serialize($categoryIDs));
        } else {
            $cats = 'no-cats';
        }

        // The filters change the response body, so they must be part of the
        // key - a shared entry would serve filtered results to unfiltered
        // requests and vice versa. Hashed: search is free text and Symfony
        // cache keys forbid several characters.
        $filters = $this->getFilterParams($request);
        $filterPart = ($filters['search'] === '' && $filters['eventType'] === '' && $filters['allDay'] === null)
            ? 'no-filters'
            : md5(mb_strtolower($filters['search']) . '|' . $filters['eventType'] . '|' . ($filters['allDay'] ?? ''));

        $parts = [
            'calendar_json',
            $this->calendar->ID,
            $start,
            $end,
            $cats,
            $filterPart,
            md5($mode)
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
