<?php

namespace Dynamic\Calendar\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Form\CalendarFilterForm;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Cache\CacheFactory;
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
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

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
        // Check cache for JSON responses first
        if ($this->isAjaxRequest($request)) {
            $cacheKey = $this->generateEventsCacheKey($request);
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

        $events = $this->calendar->getEventsFeed(null, $categories, $fromDate, $toDate);

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
        if ($json === false) {
            throw new \RuntimeException('Failed to encode events data: ' . json_last_error_msg());
        }

        // Cache the JSON response
        $cacheKey = $this->generateEventsCacheKey($request);
        $cache = $this->getEventsCache();
        $cache->set($cacheKey, $json, self::CACHE_TTL);            $response = $this->getResponse();
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
        $events = $this->calendar->getEventsFeed(null, $categories, $fromDate, $toDate);

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
     * Get from date from request or null if no filter applied
     *
     * @param HTTPRequest $request
     * @return Carbon|null
     */
    protected function getFromDate(HTTPRequest $request): ?Carbon
    {
        $from = $request->getVar('from');

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
        $to = $request->getVar('to');

        if ($to && Carbon::hasFormat($to, 'Y-m-d')) {
            return Carbon::createFromFormat('Y-m-d', $to);
        }

        // Return null when no date filter is applied - this will show all events
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
        $events = $this->calendar->getEventsFeed(null, $categories, $fromDate, $toDate);

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
        // Symfony cache keys cannot contain: {}()/\@:
        // Hash timestamps to avoid special characters
        $start = $request->getVar('start') ? md5($request->getVar('start')) : 'no-start';
        $end = $request->getVar('end') ? md5($request->getVar('end')) : 'no-end';
        $cats = $request->getVar('categories') ? md5(serialize($request->getVar('categories'))) : 'no-cats';

        $parts = [
            'calendar_json',
            $this->calendar->ID,
            $start,
            $end,
            $cats
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
            ['defaultLifetime' => self::CACHE_TTL]
        );
    }
}
