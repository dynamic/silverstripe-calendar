<?php

namespace Dynamic\Calendar\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Lumberjack\Model\Lumberjack;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;

/**
 * Class Calendar
 * @package Dynamic\Calendar\Page
 */
class Calendar extends \Page
{
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
     * @var int
     *
     * @todo move to CMS
     */
    private static int $events_per_page = 12;

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
        $fields = parent::getCMSFields();

        // Add filtering configuration fields
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
     * @return DataList
     */
    public function getLumberjackPagesForGridfield(): DataList
    {
        return EventPage::get()->filter([
            'ParentID' => $this->ID,
            //'StartDatetime:GreaterThanOrEqual' => Carbon::now()->subDay()->format('Y-m-d 23:59:59'),
        ])->sort('StartDate DESC');
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
     * @param int|null $limit Maximum number of events to return
     * @param ManyManyList|null $categories Categories to filter by
     * @param Carbon|string|null $fromDate Start date for events (default: now)
     * @param Carbon|string|null $toDate End date for events (default: 6 months from now)
     * @return ArrayList
     */
    public function getEventsFeed(?int $limit = null, $categories = null, $fromDate = null, $toDate = null): ArrayList
    {
        // Parse dates
        $fromDate = $fromDate ? Carbon::parse($fromDate) : Carbon::now();
        $toDate = $toDate ? Carbon::parse($toDate) : Carbon::now()->addMonths(6);

        $allEvents = ArrayList::create();

        // Get regular (non-recurring) events
        $regularEvents = EventPage::get()
            ->filter([
                'ParentID' => $this->ID,
                'Recursion' => 'NONE',
            ])
            ->where([
                'StartDate >= ?' => $fromDate->format('Y-m-d'),
                'StartDate <= ?' => $toDate->format('Y-m-d'),
            ]);

        foreach ($regularEvents as $event) {
            $allEvents->push($event);
        }

        // Get recurring events and their virtual instances
        $recurringEvents = EventPage::get()
            ->filter([
                'ParentID' => $this->ID,
            ])
            ->exclude('Recursion', 'NONE');

        foreach ($recurringEvents as $event) {
            // Get occurrences within the date range using Carbon recursion
            $occurrences = $event->getOccurrences($fromDate, $toDate);

            // Get event exceptions for this event
            $exceptions = $event->EventExceptions();
            $exceptionsByDate = [];

            foreach ($exceptions as $exception) {
                $exceptionsByDate[$exception->InstanceDate] = $exception;
            }

            foreach ($occurrences as $occurrence) {
                $instanceDate = $occurrence->getInstanceDate()->format('Y-m-d');

                // Check if this instance has an exception
                if (isset($exceptionsByDate[$instanceDate])) {
                    $exception = $exceptionsByDate[$instanceDate];

                    // Skip deleted instances
                    if ($exception->isDeleted()) {
                        continue;
                    }

                    // Apply modifications for modified instances
                    if ($exception->isModified()) {
                        // Apply any modifications from the exception
                        $modifications = $exception->getOverrides();
                        foreach ($modifications as $property => $value) {
                            if ($value !== null && $value !== '') {
                                $occurrence->$property = $value;
                            }
                        }
                        // Set a flag so we know this instance is modified
                        $occurrence->IsModified = true;
                        $occurrence->Exception = $exception;
                    }
                }

                $allEvents->push($occurrence);
            }
        }

        // Filter by categories if specified
        if ($categories && $categories->exists()) {
            $categoryIDs = $categories->column('ID');
            $filteredEvents = ArrayList::create();

            foreach ($allEvents as $event) {
                // For virtual instances, check original event categories
                if ($event->hasMethod('getOriginalEvent')) {
                    $eventCategories = $event->getOriginalEvent()->Categories();
                } else {
                    $eventCategories = $event->Categories();
                }

                // Check if event has any of the selected categories
                foreach ($eventCategories as $category) {
                    if (in_array($category->ID, $categoryIDs)) {
                        $filteredEvents->push($event);
                        break; // Only add once even if multiple categories match
                    }
                }
            }
            $allEvents = $filteredEvents;
        }

        // Sort events by start date
        $allEvents = $allEvents->sort('StartDate', 'ASC');

        // Apply limit if specified
        if ($limit && $limit > 0) {
            $allEvents = $allEvents->limit($limit);
        }

        $this->extend('updateEventsFeed', $allEvents);

        return $allEvents;
    }
}
