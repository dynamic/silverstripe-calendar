<?php

namespace Dynamic\Calendar\Traits;

use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\EventPage;
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
        $schema = EventPage::getSchema();

        // Get dynamic table names to avoid hardcoded references
        $eventPageTable = $schema->tableName(EventPage::class);
        $categoryTable = $schema->tableName(Category::class);
        $joinTable = $eventPageTable . '_Categories';

        return $list
            ->leftJoin($joinTable, "\"{$joinTable}\".\"EventPageID\" = \"{$eventPageTable}\".\"ID\"")
            ->leftJoin($categoryTable, "\"{$categoryTable}\".\"ID\" = \"{$joinTable}\".\"CategoryID\"");
    }
}
