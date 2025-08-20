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
        EventPage::class,
        Category::class,
    ];

    /**
     * Optimized list method for EventPage with proper sorting
     *
     * @return DataList
     */
    public function getList()
    {
        $list = parent::getList();

        if ($this->modelClass === EventPage::class) {
            // Add default sorting for EventPage
            $list = $list->sort(['StartDate' => 'DESC', 'Created' => 'DESC']);
        }

        return $list;
    }
}
