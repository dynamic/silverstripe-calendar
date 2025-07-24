<?php

namespace Dynamic\Calendar\Page;

use SilverStripe\Versioned\Versioned;

/**
 * Class RecursiveEvent
 * @package Dynamic\Calendar\Page
 * @method EventPage Parent()
 */
class RecursiveEvent extends EventPage
{
    /**
     * @var string
     */
    private static string $hide_ancestor = RecursiveEvent::class;

    /**
     * @var string
     */
    private static string $singular_name = 'Recursive Event Record';

    /**
     * @var string
     */
    private static string $plural_name = 'Recursive Event Records';

    /**
     * @var string
     */
    private static string $default_parent = EventPage::class;

    /**
     * @var string
     */
    private static string $table_name = 'RecursiveEvent';

    /**
     * @var bool
     */
    private static bool $show_in_sitetree = false;

    /**
     * @return array
     */
    public function summaryFields(): array
    {
        $fields = parent::summaryFields();

        unset($fields['HasRecurringEvents']);

        return $fields;
    }

    /**
     * @return void
     */
    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        if (!$this->exists()) {
            $this->URLSegment = $this->URLSegment . "-{$this->StartDate}";
        }
    }

    /**
     * @return void
     */
    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        //$this->syncRelationsFromParentEvent();
    }

    /**
     * @return void
     */
    protected function syncRelationsFromParentEvent(): void
    {
        if ($this->config()->get('sync_relations')) {
            $this->duplicateRelations($this->Parent(), $this, $this->config()->get('sync_relations'));
        }

        $this->writeToStage(Versioned::DRAFT);
        $parent = $this->Parent();
        if ($parent && $parent->exists() && $parent->isPublished()) {
            $this->publishRecursive();
        }
    }
}
