<?php

namespace Dynamic\Calendar\Page;

use SilverStripe\Versioned\Versioned;

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
    }

    /**
     *
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        //$this->syncRelationsFromParentEvent();
    }

    /**
     *
     */
    protected function syncRelationsFromParentEvent()
    {
        if ($this->config()->get('sync_relations')) {
            $this->duplicateRelations($this->Parent(), $this, $this->config()->get('sync_relations'));
        }

        $this->writeToStage(Versioned::DRAFT);
        if ($this->Parent()->isPublished()) {
            $this->publishRecursive();
        }
    }
}
