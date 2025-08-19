<?php

namespace Dynamic\Calendar\Admin;

use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\ORM\DataList;

/**
 * Class CalendarAdmin
 * @package Dynamic\Calendar\Admin
 */
class CalendarAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static string $menu_title = 'Calendar';

    /**
     * @var string
     */
    private static string $url_segment = 'calendar-admin';

    /**
     * @var array
     */
    private static array $managed_models = [
        Category::class,
        EventPage::class,  // NEW: Add EventPage management
    ];

    /**
     * Optimized list method for EventPage with eager loading and proper sorting
     * 
     * @return DataList
     */
    public function getList()
    {
        $list = parent::getList();
        
        if ($this->modelClass === EventPage::class) {
            // Add default sorting and eager loading for EventPage
            $list = $list
                ->sort(['StartDate' => 'DESC', 'Created' => 'DESC'])
                ->leftJoin('Dynamic_Calendar_Category_EventPages', 'Dynamic_Calendar_Category_EventPages.EventPageID = "EventPage"."ID"')
                ->leftJoin('Dynamic_Calendar_Category', 'Dynamic_Calendar_Category.ID = Dynamic_Calendar_Category_EventPages.CategoryID');
        }
        
        return $list;
    }
}
