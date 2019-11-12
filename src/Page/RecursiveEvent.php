<?php

namespace Dynamic\Calendar\Page;

use Dynamic\Calendar\Model\Category;

/**
 * Class RecursiveEvent
 * @package Dynamic\Calendar\Page
 * @property int $GeneratingChangeSetID
 * @method EventPage Parent()
 */
class RecursiveEvent extends EventPage
{
    /**
     * @var string
     */
    private static $hide_ancestor = RecursiveEvent::class;

    /**
     * @var string
     */
    private static $singular_name = 'Recursive Event Record';

    /**
     * @var string
     */
    private static $plural_name = 'Recursive Event Records';

    /**
     * @var string
     */
    private static $default_parent = EventPage::class;

    /**
     * @var string
     */
    private static $table_name = 'RecursiveEvent';

    /**
     * @var bool
     */
    private static $show_in_sitetree = false;

    /**
     * @return array
     */
    public function summaryFields()
    {
        $fields = parent::summaryFields();

        unset($fields['HasRecurringEvents']);

        return $fields;
    }

    /**
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->exists()) {
            $this->URLSegment = $this->URLSegment . "-{$this->StartDate}";
        }

        $parent = $this->Parent();

        if ($parent instanceof EventPage && $this->exists()) {
            $this->unsetCategories();
            $this->setCategoriesFromParent();
        }
    }

    /**
     *
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        $parentCategories = $this->Parent()->Categories()->sort('ID')->column();
        $categories = $this->Categories()->sort('ID')->column();

        if ($parentCategories != $categories) {
            $this->unsetCategories();
            $this->setCategoriesFromParent();
        }
    }

    /**
     *
     */
    private function unsetCategories()
    {
        $this->Categories()->removeAll();
    }

    /**
     *
     */
    private function setCategoriesFromParent()
    {
        $categories = $this->Categories();
        if (($parent = $this->Parent()) && $parent instanceof EventPage) {
            $parent->Categories()->each(function (Category $category) use (&$categories) {
                $categories->add($category);
            });
        }
    }
}
