<?php

namespace Dynamic\Calendar\Page;

use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\CalendarConfig;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\Event;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\ArrayList;

/**
 * Class Calendar
 *
 * @package calendar
 *
 * @method \SilverStripe\ORM\DataList Categories()
 */
class Calendar extends \Page
{
    /**
     * @var string
     */
    private static $table_name = 'Calendar';

    /**
     * @var string
     */
    private static $singular_name = 'Calendar';

    /**
     * @var string
     */
    private static $plural_name = 'Calendars';

    /**
     * @var array
     */
    private static $casting = [
        'NextDate' => 'Date',
    ];

    /**
     * @var array
     */
    private static $allowed_children = [
        EventPage::class,
    ];

    /**
     * @var int
     */
    private static $events_per_page = 12;

    /**
     * @var bool
     */
    private static $include_child_categories = false;

    /**
     * @param array $filter
     * @param array $filterAny
     * @param array $exclude
     * @param null|mixed $filterByCallback
     * @return ArrayList|\SilverStripe\ORM\DataList
     */
    public static function upcoming_events(
        $filter = [],
        $filterAny = [],
        $exclude = [],
        $filterByCallback = null
    ) {
        $instanceLimit = (
            CalendarConfig::current_calendar_config()->EventInstancesToShow
            && CalendarConfig::current_calendar_config()->EventInstancesToShow > 0)
            ? CalendarConfig::current_calendar_config()->EventInstancesToShow
            : Config::inst()->get('Event', 'instances_to_show');

        $eventsList = ArrayList::create();

        $dateFilter = [];
        if (static::preg_array_key_exists('/StartDate/', $filter)) {
            $key = preg_grep('/StartDate/', array_keys($filter))[0];
            $dateFilter[$key] = $filter[$key];
            unset($filter[$key]);
        }

        if (static::preg_array_key_exists('/EndDate/', $filter)) {
            $key = preg_grep('/EndDate/', array_keys($filter))[0];
            $dateFilter[$key] = $filter[$key];
            unset($filter[$key]);
        }

        $events = Event::get()->filter($filter);

        if (!empty($filterAny)) {
            if (Config::inst()->get(Calendar::class, 'include_child_categories')) {
                $filterAny = static::augment_category_filtering($filterAny);
            }

            $events = $events->filterAny($filterAny);
        }

        if (!empty($exclude)) {
            $events = $events->exclude($exclude);
        }

        if ($filterByCallback !== null && is_callable($filterByCallback)) {
            $events = $events->filterByCallback($filterByCallback);
        }

        if ($events->exists()) {
            $pushEvent = function (Event $event) use (&$eventsList, &$instanceLimit, &$filter) {
                if ($event->getNextEventInstance()) {
                    foreach ($event->getAllUpcomingEventInstances($filter, $instanceLimit) as $instance) {
                        $eventsList->push($instance);
                    }
                }
            };
            $events->each($pushEvent);
        }

        if (!empty($dateFilter)) {
            $filterByDates = function (Event $event) use (&$dateFilter) {
                $passed = true;
                foreach ($dateFilter as $key => $val) {
                    if (!$passed) {
                        return false;
                    }

                    $filterParts = explode(':', $key);
                    if (isset($filterParts[1])) {
                        $field = $filterParts[0];

                        switch ($filterParts[1]) {
                            case "GreaterThanOrEqual":
                                $passed = $event->$field >= $val;
                                break;
                            case "GreaterThan":
                                $passed = $event->$field > $val;
                                break;
                            case "LessThanOrEqual":
                                $passed = $event->$field <= $val;
                                break;
                            case "LessThan":
                                $passed = $event->$field < $val;
                                break;
                            default:
                                $passed = $event->$field == $val;
                                break;
                        }
                    }
                }

                return $passed;
            };

            $eventsList = $eventsList->filterByCallback($filterByDates);
        }

        $eventList = $eventsList->sort([
            'StartDate' => 'ASC',
            'StartTime' => 'ASC',
        ]);

        return $eventList;
    }

    /**
     * Determine if an associative array key exists based off an given pattern
     *
     * @param $pattern
     * @param $array
     * @return int
     */
    public static function preg_array_key_exists($pattern, $array)
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
    private static function augment_category_filtering($filterAny)
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
    public function getControllerName()
    {
        return CalendarController::class;
    }
}
