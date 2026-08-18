<?php

namespace Dynamic\Calendar\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Cache\CalendarCacheVersion;
use Dynamic\Calendar\Form\CalendarFilterForm;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use SilverStripe\Core\Injector\Injector;
use Psr\SimpleCache\CacheInterface;

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
     * Whether index() should build the full event list, pagination and counts.
     *
     * The module's own Calendar.ss uses none of them (FullCalendar fetches
     * events from the /events endpoint), so this defaults to false. Enable it
     * if a project template renders $Events, $RecurringEventsCount,
     * $OneTimeEventsCount or $AvailableCategories.
     *
     * @config
     * @var bool
     */
    private static bool $index_provides_event_list = false;

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
        // The cache stores Live-stage responses only. A draft-stage request
        // (?stage=Stage, or Versioned.use_session) must neither read a Live
        // entry nor poison the pool with draft content that would previously
        // have been wiped by the next save but now survives the full TTL.
        $cacheable = $this->isAjaxRequest($request)
            && Versioned::get_stage() === Versioned::LIVE;

        // Check cache for JSON responses first. The key is computed once and
        // reused by the write path below - it embeds version stamps and the
        // relation fingerprint, and nothing in between can legitimately
        // change them mid-request.
        $cacheKey = $cacheable ? $this->generateEventsCacheKey($request) : null;

        if ($cacheable) {
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
            null,
            $this->getFilterParams($request)
        );

        // Check if this is an AJAX request for JSON data
        if ($this->isAjaxRequest($request)) {
            $writeToCache = $cacheable;
            // Prefetch the category map (two queries for the whole response)
            // and resolve each parent EventPage's absolute link once - the
            // loop previously issued ~2 queries per row via Categories() and
            // AbsoluteLink()'s Parent() walk.
            $categoriesByEvent = $this->prefetchEventCategories($events);

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

                // Handle both EventPage and EventInstance objects - instances
                // resolve through their original event's ID.
                $ownerID = $event instanceof EventInstance
                    ? (int) $event->getOriginalEvent()->ID
                    : (int) $event->ID;
                $eventCategories = $categoriesByEvent[$ownerID] ?? [];

                if ($eventCategories) {
                    foreach ($eventCategories as $category) {
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

            // Cache the JSON response - Live stage only (see $cacheable above)
            if ($writeToCache && $cacheKey !== null) {
                $cache = $this->getEventsCache();
                $cache->set($cacheKey, $json, $this->config()->get('json_cache_ttl'));
            }

            $response = $this->getResponse();
            $response->addHeader('Content-Type', 'application/json');
            $response->addHeader('X-Calendar-Cache', $writeToCache ? 'MISS' : 'BYPASS');
            return $response->setBody($json);
        }

        // For non-AJAX requests, return template data
        return [
            'Events' => $events,
            'TotalEvents' => $events->count(),
        ];
    }

    /**
     * Prefetch every event's categories in two queries.
     *
     * The JSON transform previously called Categories() per row - one
     * many-many query per event, repeated for every occurrence of the same
     * recurring event. This resolves the join table once and hydrates each
     * Category once, keyed by owning EventPage ID.
     *
     * @param iterable $events Mixed EventPage|EventInstance list
     * @return array<int,array<int,Category>>
     */
    protected function prefetchEventCategories(iterable $events): array
    {
        $eventIDs = [];
        foreach ($events as $event) {
            $eventIDs[] = $event instanceof EventInstance
                ? (int) $event->getOriginalEvent()->ID
                : (int) $event->ID;
        }
        $eventIDs = array_values(array_unique(array_filter($eventIDs)));

        if (!$eventIDs) {
            return [];
        }

        $joinTable = DataObject::getSchema()->tableName(EventPage::class) . '_Categories';
        $placeholders = implode(',', array_fill(0, count($eventIDs), '?'));

        $rows = DB::prepared_query(
            "SELECT \"EventPageID\", \"CategoryID\" FROM \"{$joinTable}\""
            . " WHERE \"EventPageID\" IN ({$placeholders})"
            . ' ORDER BY "SortOrder" ASC',
            $eventIDs
        );

        $categoryIDsByEvent = [];
        $allCategoryIDs = [];

        foreach ($rows as $row) {
            $categoryIDsByEvent[(int) $row['EventPageID']][] = (int) $row['CategoryID'];
            $allCategoryIDs[(int) $row['CategoryID']] = true;
        }

        if (!$allCategoryIDs) {
            return [];
        }

        $categoriesByID = [];
        foreach (Category::get()->byIDs(array_keys($allCategoryIDs)) as $category) {
            $categoriesByID[(int) $category->ID] = $category;
        }

        $map = [];
        foreach ($categoryIDsByEvent as $eventID => $categoryIDs) {
            foreach ($categoryIDs as $categoryID) {
                if (isset($categoriesByID[$categoryID])) {
                    $map[$eventID][] = $categoriesByID[$categoryID];
                }
            }
        }

        return $map;
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

        $data = [
            'Calendar' => $this->calendar,
            'CurrentFromDate' => $fromDate ? $fromDate->format('Y-m-d') : null,
            'CurrentToDate' => $toDate ? $toDate->format('Y-m-d') : null,
            'ShowCategoryFilter' => $this->calendar->ShowCategoryFilter,
        ];

        // The module's own Calendar.ss renders none of the event data -
        // FullCalendar loads everything through the /events AJAX endpoint - so
        // by default the initial page load no longer materialises the full
        // feed just to discard it. Projects whose templates render $Events,
        // $RecurringEventsCount, $OneTimeEventsCount or $AvailableCategories
        // can restore them with one line of YAML:
        //
        //   Dynamic\Calendar\Controller\CalendarController:
        //     index_provides_event_list: true
        if ($this->config()->get('index_provides_event_list')) {
            $categoryIDs = $request->getVar('categories');
            $categories = null;

            if ($categoryIDs) {
                if (!is_array($categoryIDs)) {
                    $categoryIDs = [$categoryIDs];
                }
                $categories = Category::get()->byIDs($categoryIDs);
            }

            $events = $this->calendar->getEventsFeed(
                null,
                $categories,
                $fromDate,
                $toDate,
                null,
                $this->getFilterParams($request)
            );

            $paginatedEvents = PaginatedList::create($events, $request);
            $paginatedEvents->setPageLength($this->config()->get('events_per_page'));

            $data['Events'] = $paginatedEvents;
            $data['RecurringEventsCount'] = $this->getRecurringEventsCount();
            $data['OneTimeEventsCount'] = $this->getOneTimeEventsCount();
            $data['AvailableCategories'] = $this->getAvailableCategoriesForTemplate($request);
        }

        return $data;
    }

    /**
     * Get from date from request or null if no filter applied
     *
     * @param HTTPRequest $request
     * @return Carbon|null
     */
    /**
     * Extract the optional feed filters from the request.
     *
     * These params were sent by CalendarFilterForm and appended to the
     * events XHR by CalendarView.js all along - the controller just never
     * read them (issue #133). Note allDay uses strict comparisons: '0'
     * (Timed Events) is a real filter value that if($var) would drop.
     *
     * @return array{search: string, eventType: string, allDay: string|null}
     */
    /**
     * Longest search string accepted. The value feeds both the SQL predicate
     * and the cache key, so it must be bounded - an unbounded visitor-supplied
     * value would hand visitors control of cache-pool growth (same hazard
     * parseRequestDate() guards for dates).
     */
    private const SEARCH_MAX_LENGTH = 64;

    protected function getFilterParams(HTTPRequest $request): array
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

        $allDay = $request->getVar('allDay');
        $allDay = ($allDay === null || $allDay === '' || !is_scalar($allDay))
            ? null
            : (string) (int) (bool) $allDay;

        return [
            'search' => $search,
            'eventType' => $eventType,
            'allDay' => $allDay,
        ];
    }

    protected function getFromDate(HTTPRequest $request): ?Carbon
    {
        // Support both 'from' (legacy) and 'start' (FullCalendar) parameter names
        return $this->parseRequestDate($request->getVar('from') ?? $request->getVar('start'));
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
        return $this->parseRequestDate($request->getVar('to') ?? $request->getVar('end'));
    }

    /**
     * Parse a date request parameter into a Carbon instance.
     *
     * FullCalendar sends info.startStr / info.endStr, which are plain Y-m-d in
     * dayGridMonth but full ISO-8601 with a UTC offset in timeGrid and list
     * views (e.g. 2026-08-01T00:00:00-05:00). The old Carbon::hasFormat(...,
     * 'Y-m-d') gate rejected the latter and returned null - which disabled
     * date filtering entirely and expanded the whole event corpus on every
     * week/day/list request.
     *
     * Deliberately restricted to ISO-like shapes: bare Carbon::parse() also
     * accepts relative strings ("yesterday", "+1 week"), which would hand
     * visitors control of the cache key.
     *
     * @param mixed $value
     * @return Carbon|null
     */
    protected function parseRequestDate($value): ?Carbon
    {
        if (!$value || !is_string($value) || strlen($value) > 40) {
            return null;
        }

        // Fast path: '!' zeroes unspecified fields instead of inheriting the
        // current wall-clock time.
        if (Carbon::hasFormat($value, 'Y-m-d')) {
            return Carbon::createFromFormat('!Y-m-d', $value);
        }

        if (
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?)?$/',
                $value
            )
        ) {
            return null;
        }

        try {
            // The string carries its own offset, so format('Y-m-d') yields the
            // browser-local calendar date the grid is drawing.
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
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
        $selectedIDs = $this->getSelectedCategoryIDs($request);

        $availableCategories = ArrayList::create();
        foreach ($this->calendar->getUsedCategories() as $category) {
            $availableCategories->push(ArrayData::create([
                'ID' => $category->ID,
                'Title' => $category->Title,
                'Selected' => in_array($category->ID, $selectedIDs),
            ]));
        }

        return $availableCategories;
    }

    /**
     * Selected category IDs from the request, normalised to ints.
     *
     * @param HTTPRequest $request
     * @return array<int>
     */
    protected function getSelectedCategoryIDs(HTTPRequest $request): array
    {
        $ids = $request->getVar('categories');

        if (!$ids) {
            return [];
        }

        return array_map('intval', is_array($ids) ? $ids : [$ids]);
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
            null,
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
            // Previously read $_SERVER['HTTP_HOST'] with a dead null-coalesce
            // ('.' binds tighter than '??'), so in any CLI/queued-job/test
            // context the undefined-index error made transformEventToICS()
            // return null - silently emptying the entire ICS feed.
            $host = parse_url(Director::absoluteBaseURL(), PHP_URL_HOST) ?: 'calendar.local';
            $ics[] = 'UID:' . $uniqueId . '@' . $host;

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
            error_log("Error transforming event {$event->ID} to ICS: " . $e->getMessage());
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
     * @param HTTPRequest $request
     * @return string
     */
    private function generateEventsCacheKey(HTTPRequest $request): string
    {
        // Key on the PARSED dates, not the raw strings: 2026-08-17 and
        // 2026-08-17T00:00:00-05:00 describe the same window and must share a
        // cache entry, otherwise every calendar navigation is a fresh miss.
        // (Symfony cache keys cannot contain: {}()/\@:)
        $from = $this->getFromDate($request);
        $to = $this->getToDate($request);

        $cats = $request->getVar('categories');
        if ($cats) {
            $cats = is_array($cats) ? $cats : [$cats];
            $cats = array_map('intval', $cats);
            sort($cats);
            $cats = md5(implode('-', $cats));
        } else {
            $cats = 'no-cats';
        }

        // Whether this response can contain other calendars' events decides
        // which content stamp governs it.
        $crossCalendar = (bool) $this->calendar->config()->get('allow_cross_calendar_feed');

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
            // Generational invalidation: writes bump these stamps instead of
            // wiping the pool, so entries die by becoming unreachable.
            CalendarCacheVersion::keyFragment($crossCalendar ? null : (int) $this->calendar->ID),
            // Relation mutations fire no hooks, so the key observes the join
            // table directly (one memoized PK-indexed query per request).
            CalendarCacheVersion::relationFingerprint(),
            $from ? $from->format('Ymd') : 'no-start',
            $to ? $to->format('Ymd') : 'no-end',
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
        // The named, namespaced pool registered in _config/enhanced-caching.yml.
        // Previously this went through CacheFactory::create() with no namespace
        // argument, landing in the shared TEMP_PATH/@/ pool (issue #132). The
        // TTL is still applied explicitly at set() time from json_cache_ttl.
        return Injector::inst()->get(CacheInterface::class . '.calendarJSON');
    }
}
