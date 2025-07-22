<?php

namespace Dynamic\Calendar\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;

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
    ];

    /**
     * @var array
     */
    private static array $url_handlers = [
        '' => 'index',
        'events' => 'events',
    ];

    /**
     * @var int
     */
    private static int $events_per_page = 12;

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
     * Events action for AJAX requests
     *
     * @param HTTPRequest $request
     * @return array
     */
    public function events(HTTPRequest $request): array
    {
        $fromDate = $this->getFromDate($request);
        $toDate = $this->getToDate($request);

        $events = $this->calendar->getEventsFeed(null, null, $fromDate, $toDate);

        return [
            'Events' => $events,
            'TotalEvents' => $events->count(),
        ];
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
            'CurrentFromDate' => $fromDate->format('Y-m-d'),
            'CurrentToDate' => $toDate->format('Y-m-d'),
            'RecurringEventsCount' => $this->getRecurringEventsCount(),
            'OneTimeEventsCount' => $this->getOneTimeEventsCount(),
            'AvailableCategories' => $this->getAvailableCategoriesForTemplate($request),
            'ShowCategoryFilter' => $this->calendar->ShowCategoryFilter,
            'DebugCategories' => $this->getDebugCategoryInfo($request),
        ];
    }

    /**
     * Get from date from request or default
     *
     * @param HTTPRequest $request
     * @return Carbon
     */
    protected function getFromDate(HTTPRequest $request): Carbon
    {
        $from = $request->getVar('from');

        if ($from && Carbon::hasFormat($from, 'Y-m-d')) {
            return Carbon::createFromFormat('Y-m-d', $from);
        }

        // Default to start of current month
        return Carbon::now()->startOfMonth();
    }

    /**
     * Get to date from request or default
     *
     * @param HTTPRequest $request
     * @return Carbon
     */
    protected function getToDate(HTTPRequest $request): Carbon
    {
        $to = $request->getVar('to');

        if ($to && Carbon::hasFormat($to, 'Y-m-d')) {
            return Carbon::createFromFormat('Y-m-d', $to);
        }

        // Default to end of next month
        return Carbon::now()->addMonth()->endOfMonth();
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
        // Start from EventPages and get their categories
        $eventsInCalendar = EventPage::get()->filter(['ParentID' => $this->calendar->ID]);
        $categoryIDs = [];
        
        foreach ($eventsInCalendar as $event) {
            foreach ($event->Categories() as $category) {
                $categoryIDs[] = $category->ID;
            }
        }
        
        // Remove duplicates
        $categoryIDs = array_unique($categoryIDs);
        
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
     * Debug method to check category configuration
     *
     * @param HTTPRequest $request
     * @return ArrayData
     */
    protected function getDebugCategoryInfo(HTTPRequest $request): ArrayData
    {
        $allCategories = Category::get();
        $allEventsInCalendar = EventPage::get()->filter(['ParentID' => $this->calendar->ID]);
        $eventsWithCategories = EventPage::get()
            ->filter(['ParentID' => $this->calendar->ID])
            ->innerJoin('EventPage_Categories', '"EventPage"."ID" = "EventPage_Categories"."EventPageID"');

        return ArrayData::create([
            'CalendarID' => $this->calendar->ID,
            'CalendarTitle' => $this->calendar->Title,
            'ShowCategoryFilter' => (bool)$this->calendar->ShowCategoryFilter,
            'TotalCategories' => $allCategories->count(),
            'TotalEventsInCalendar' => $allEventsInCalendar->count(),
            'EventsWithCategories' => $eventsWithCategories->count(),
            'AllCategoryTitles' => $allCategories->column('Title'),
        ]);
    }

    /**
     * Clean and sanitize request variables    /**
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
}
