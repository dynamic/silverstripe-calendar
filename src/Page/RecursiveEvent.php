<?php

namespace Dynamic\Calendar\Page;

use Dynamic\Calendar\Model\Category;

/**
 * Class RecursiveEvent
 * @package Dynamic\Calendar\Page
 *
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
     * @var int
     */
    private static $create_new_max = 14;

    /**
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if($this->exists()){
            $parent = $this->Parent();

            $this->Title = $parent->Title;
            $this->Content = $parent->Content;


            if ($parent instanceof EventPage) {
                $this->AllDay = $parent->AllDay;

                if ($this->exists()) {
                    $this->unsetCategories();
                    $this->setCategoriesFromParent();
                }
            }
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
