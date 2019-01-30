<?php

namespace Dynamic\Calendar\Factory;

use Carbon\Carbon;
use Dynamic\Calendar\Model\RecursionChangeSet;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\Versioned;

/**
 * Class RecursiveEventFactory
 * @package Dynamic\Calendar\Factory
 */
class RecursiveEventFactory
{
    use Injectable;

    /**
     * @var RecursionChangeSet
     */
    private $change_set;

    /**
     * @var EventPage
     */
    private $event;

    /**
     * @var array
     */
    private $recursion_dates = [];

    /**
     * @var SS_List
     */
    private $existing_dates;

    /**
     * RecursiveEventFactory constructor.
     * @param RecursionChangeSet $changeSet
     * @param array $existingDates
     */
    public function __construct(RecursionChangeSet $changeSet, $existingDates = [])
    {
        $this->setChangeSet($changeSet);
        $this->setEvent($changeSet);

        if (!empty($existingDates)) {
            $this->setExistingDates($existingDates);
        }
    }

    /**
     * @param RecursionChangeSet $changeSet
     * @return $this
     */
    public function setChangeSet(RecursionChangeSet $changeSet)
    {
        $this->change_set = $changeSet;

        return $this;
    }

    /**
     * @return RecursionChangeSet|null
     */
    public function getChaneSet()
    {
        return $this->change_set;
    }

    /**
     * @param RecursionChangeSet $changeSet
     * @return $this
     */
    protected function setEvent(RecursionChangeSet $changeSet)
    {
        $this->event = $changeSet->EventPage();

        return $this;
    }

    /**
     * @return EventPage
     */
    protected function getEvent()
    {
        if (!$this->event) {
            $this->setEvent($this->getChaneSet());
        }

        return $this->event;
    }

    /**
     * @param $existingDates
     * @return $this
     */
    public function setExistingDates($existingDates)
    {
        $this->existing_dates = $existingDates;

        return $this;
    }

    /**
     * @return array|bool
     */
    public function getExistingDates()
    {
        return ($this->existing_dates === (array)$this->existing_dates) ? $this->existing_dates : false;
    }

    /**
     *
     */
    public function generateNewEvents()
    {
        $dates = $this->getNextDateSet();

        foreach ($dates as $date) {
            $this->createRecursiveEvent($date);
        }
    }

    /**
     * @return array
     */
    protected function getNextDateSet()
    {
        $dates = [];

        if ($this->getChaneSet()->EventPage()->Recursion == 'Daily') {
            $dates = $this->getDailyDates();
        }

        return $dates;
    }

    /**
     * @return array
     */
    private function getDailyDates()
    {
        if (($existing = $this->getExistingDates()) && ($last = $existing->last())) {
            $start = Carbon::parse($last->StartDatetime)->addDay();
        } else {
            $start = Carbon::parse($this->getChaneSet()->EventPage()->StartDatetime);
        }

        $newDates = [];

        for ($count = 0; $count < RecursiveEvent::config()->get('create_new_max'); $count++) {
            $newDates[] = $start->format('Y-m-d H:i:s');
            $start->addDay();
        }

        return $newDates;
    }

    /**
     * @param $date
     */
    protected function createRecursiveEvent($date)
    {
        $event = RecursiveEvent::create();
        $event->ParentID = $this->getEvent()->ID;
        $event->Title = $this->getEvent()->Title;
        $event->Content = $this->getEvent()->Content;
        $event->StartDatetime = $date;
        $event->writeToStage(Versioned::DRAFT);
    }
}
