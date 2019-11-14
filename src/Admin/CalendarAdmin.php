<?php

namespace Dynamic\Calendar\Admin;

use Dynamic\Calendar\Model\Category;
use SilverStripe\Admin\ModelAdmin;

/**
 * Class CalendarAdmin
 * @package Dynamic\Calendar\Admin
 */
class CalendarAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $menu_title = 'Calendar';

    /**
     * @var string
     */
    private static $url_segment = 'calendar-admin';

    /**
     * @var array
     */
    private static $managed_models = [
        Category::class,
    ];
}
