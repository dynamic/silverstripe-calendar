<?php

namespace Dynamic\Calendar\Factory;

use Dynamic\Calendar\Model\RecursionChangeSet;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Debug;

/**
 * Class RecursionChangeSetFactory
 * @package Dynamic\Calendar\Factory
 */
class RecursionChangeSetFactory
{
    use Configurable;
    use Injectable;

    /**
     * @var EventPage
     */
    private $event_page;

    /**
     * @var
     */
    private $change_set;

    /**
     * @var string
     */
    private $last_change_set;

    /**
     * @var array
     */
    private static $event_recursion_fields = [
        'StartDatetime',
        'Recursion',
        'RecursionEndDate',
    ];

    /**
     * RecursionChangeSetFactory constructor.
     * @param EventPage $eventPage
     */
    public function __construct(EventPage $eventPage)
    {
        $this->setEventPage($eventPage);
        $this->setLastChangeset($eventPage);
    }

    /**
     * @param EventPage $eventPage
     * @return $this
     */
    public function setEventPage(EventPage $eventPage)
    {
        $this->event_page = $eventPage;

        return $this;
    }

    /**
     * @return EventPage
     */
    public function getEventPage()
    {
        return $this->event_page;
    }

    /**
     * @param EventPage $eventPage
     * @return $this
     */
    protected function setLastChangeSet(EventPage $eventPage)
    {
        if (($changeSet = $eventPage->RecursionChangeSets()->last()) && $changeSet->exists()) {
            $this->last_change_set = $changeSet;
        }

        return $this;
    }

    protected function getLastChangeSet()
    {
        return $this->last_change_set;
    }

    public function getChangeSet()
    {
        if (!$this->change_set instanceof RecursionChangeSet) {
            $this->setChangeSet();
        }

        return $this->change_set;
    }

    /**
     * @return $this
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function setChangeSet()
    {
        $changeSet = RecursionChangeSet::create($this->getDataArray());

        $changeSet->write();

        $this->change_set = $changeSet;

        return $this;
    }

    /**
     * @return array
     */
    private function getDataArray()
    {
        $data = [
            'RecursionPattern' => $this->getRecursionPattern(),
            'EventPageID' => $this->getEventPage()->ID,
            //'EventPageVersion' => $this->getEventPageVersion(),
        ];

        return $data;
    }

    /**
     * @return string|null
     */
    protected function getRecursionPattern()
    {
        $patternSource = $this->getEventPage()->getPatternSource();
        $recursion = $this->getEventPage()->Recursion;

        return (isset($patternSource[$recursion])) ? $patternSource[$recursion] : null;
    }

    /**
     * @return int
     */
    protected function getEventPageVersion()
    {
        return $this->getEventPage()->Version;
    }

    /**
     * @return array
     */
    protected function getEventData()
    {
        $event = $this->getEventPage()->toMap();

        $fields = $this->config()->get('event_recursion_fields');

        foreach ($event as $fieldKey => $fieldValue) {
            if (!in_array($fieldKey, $fields)) {
                unset($event[$fieldKey]);
            }
        }

        return $event;
    }

    /**
     * @param RecursionChangeSet $changeSet
     * @return EventPage
     */
    public function newEventFromChangeSet(RecursionChangeSet $changeSet)
    {
        return EventPage::create($changeSet->getChangeSetData());
    }
}
