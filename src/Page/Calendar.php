<?php

namespace Dynamic\Calendar\Page;

use Dynamic\Calendar\Controller\CalendarController;
use Dynamic\Calendar\Model\Category;
use SilverStripe\Lumberjack\Model\Lumberjack;
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
    public static function preg_array_key_exists($pattern, $array): int
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
    public function getControllerName(): string
    {
        return CalendarController::class;
    }
}
