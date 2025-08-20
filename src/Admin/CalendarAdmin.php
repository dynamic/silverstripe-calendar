<?php

namespace Dynamic\Calendar\Admin;

use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Traits\EventPageOptimizations;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\ORM\DataList;

/**
 * Class CalendarAdmin
 * @package Dynamic\Calendar\Admin
 */
class CalendarAdmin extends ModelAdmin
{
    use EventPageOptimizations;

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
            $list = $this->addEventPageOptimizations($list)
                ->sort(['StartDate' => 'DESC', 'Created' => 'DESC']);
        }

        return $list;
    }
}
