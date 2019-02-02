<?php

namespace Dynamic\Calendar\Factory;

use Carbon\Carbon;
use Dynamic\Calendar\Model\RecursionChangeSet;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\Versioned;

/**
 * Class RecursiveEventFactory
 * @package Dynamic\Calendar\Factory
 */
class RecursiveEventFactory
{
    use Injectable;
    use Configurable;

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
     * @var array
     */
    private $daily_dates;

    /**
     * @var string
     */
    private static $date_string = 'Y-m-d H:i:s';

    /**
     * RecursiveEventFactory constructor.
     * @param RecursionChangeSet $changeSet
     * @param array $existingDates
     */
    public function __construct(RecursionChangeSet $changeSet, $existingDates = null)
    {
        $this->setChangeSet($changeSet);
        $this->setEvent($changeSet);

        if ($existingDates) {
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
     * @return SS_List
     */
    public function getExistingDates()
    {
        return $this->existing_dates;
    }

    /**
     *
     */
    public function generateEvents()
    {
        $dates = $this->getDailyDates();

        foreach ($dates as $date) {
            $this->createRecursiveEvent($date);
        }
    }

    /**
     * @return $this
     */
    private function setDailyDates()
    {
        $baseDateTime = Carbon::parse($this->getEvent()->StartDatetime);
        $now = Carbon::now()->setTimeFromTimeString($baseDateTime->format('H:i:s'));
        $start = ($now->timestamp > $baseDateTime->timestamp) ? $now : $baseDateTime;

        $count = 0;
        $max = RecursiveEvent::config()->get('create_new_max');

        $newDates = [];

        while ($count < $max) {
            if (!$existing = $this->dateExists($start)) {
                $newDates[] = $start->format($this->config()->get('date_string'));
            }

            $start->addDay();
            $count++;
        }

        $this->daily_dates = $newDates;

        return $this;
    }

    /**
     * @return array
     */
    private function getDailyDates()
    {
        if (!$this->daily_dates) {
            $this->setDailyDates();
        }

        return $this->daily_dates;
    }

    /**
     * @param $date
     * @return \SilverStripe\ORM\DataObject
     */
    protected function dateExists(Carbon $date)
    {
        return RecursiveEvent::get()->filter('StartDatetime',
            $date->format($this->config()->get('date_string')))->first();
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
    protected function createRecursiveEvent(Carbon $date)
    {
        $event = RecursiveEvent::create();
        $event->ParentID = $this->getEvent()->ID;
        $event->Title = $this->getEvent()->Title;
        $event->Content = $this->getEvent()->Content;
        $event->StartDatetime = $date->format($this->config()->get('date_string'));
        $event->GeneratingChangeSetID = $this->getChaneSet()->ID;
        $event->writeToStage(Versioned::DRAFT);

        if ($this->getEvent()->isPublished()) {
            $event->publishRecursive();
        }
    }
}
