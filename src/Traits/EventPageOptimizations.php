<?php

namespace Dynamic\Calendar\Traits;

use SilverStripe\ORM\DataList;

/**
 * Trait EventPageOptimizations
 * Provides shared optimization logic for EventPage queries
 *
 * @package Dynamic\Calendar\Traits
 */
trait EventPageOptimizations
{
    /**
     * Add optimized joins and eager loading for EventPage queries
     * Reduces N+1 query problems when working with EventPage categories
     *
     * @param DataList $list
     * @return DataList
     */
    protected function addEventPageOptimizations(DataList $list): DataList
    {
        // The previous implementation added two leftJoins that selected no
        // columns and eager-loaded nothing: pure row fan-out plus a DISTINCT
        // for the database to de-duplicate, with the category N+1 untouched.
        // eagerLoad() (framework 5.1+) actually populates Categories() so
        // per-row summary fields stop issuing their own queries. Guarded so
        // the module keeps installing on framework 5.0.
        if (method_exists($list, 'eagerLoad')) {
            return $list->eagerLoad('Categories');
        }

        return $list;
    }
}
