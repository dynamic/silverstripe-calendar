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
    public function generateEvents()
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
        $existing = $this->getExistingDates();
        $count = 0;
        $start = Carbon::parse($this->getChaneSet()->EventPage()->StartDatetime)->addDay();
        $max = RecursiveEvent::config()->get('create_new_max');

        if ($existing !== false) {
            $existing->filter('StartDatetime:GreaterThanOrEqual', Carbon::now()->format('Y-m-d H:i:s'));
            if (($last = $existing->last())) {
                $start = Carbon::parse($last->StartDatetime)->addDay();
                $count = $existing->count();
            }
        }

        $newDates = [];

        if ($existing !== false && $count > 0) {
            $newDates = array_merge($existing->column('StartDatetime', $newDates));
            $existingDates = $existing->column('StartDatetime');
        }

        while ($count < $max) {
            if (isset($existingDates)) {
                if (!in_array($start->format('Y-m-d H:i:s'), $existingDates)) {
                    $newDates[] = $start->format('Y-m-d H:i:s');
                    $start->addDay();
                    $count++;
                }
            } else {
                $newDates[] = $start->format('Y-m-d H:i:s');
                $start->addDay();
                $count++;
            }
        }

        return $newDates;
    }

    /**
     * @return mixed
     */
    protected function getLastKnownDate()
    {
        return RecursiveEvent::get()->filter('ParentID', $this->getEvent()->ID)->max('StartDatetime')->StartDatetime;
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
        $event->GeneratingChangeSetID = $this->getChaneSet()->ID;
        $event->writeToStage(Versioned::DRAFT);
    }
}
