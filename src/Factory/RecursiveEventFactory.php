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
     * @var
     */
    private $weekly_dates;

    /**
     * @var
     */
    private $monthly_dates;

    /**
     * @var
     */
    private $yearly_dates;

    /**
     * @var string
     */
    private static $date_format = 'Y-m-d H:i:s';

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
        switch ($this->getEvent()->Recursion) {
            case 'Daily':
                $dates = $this->getDailyDates();
                break;
            case 'Weekly':
                $dates = $this->getWeeklyDates();
                break;
            case 'Monthly':
                $dates = $this->getMonthlyDates();
                break;
        }

        if (isset($dates)) {
            foreach ($dates as $date) {
                $this->createRecursiveEvent($date);
            }
        }
    }

    /**
     * @return $this
     */
    private function setDailyDates()
    {
        $freshDate = function ($string) {
            return Carbon::parse($string);
        };

        $format = $this->config()->get('date_format');
        $date = Carbon::parse($this->getEvent()->StartDatetime)->addDay();
        $max = RecursiveEvent::config()->get('create_new_max');

        $dates = [];

        while (count($dates) < $max) {
            $dates[] = $freshDate($date->format($format));
            $date->addDay();
        }

        $this->daily_dates = $dates;

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
     * @return mixed
     */
    private function getWeeklyDates()
    {
        if (!$this->weekly_dates) {
            $this->setWeeklyDates();
        }

        return $this->weekly_dates;
    }

    /**
     * @return $this
     */
    private function setWeeklyDates()
    {
        $freshDate = function ($string) {
            return Carbon::parse($string);
        };

        $format = $this->config()->get('date_format');
        $max = RecursiveEvent::config()->get('create_new_max');
        $date = Carbon::parse($this->getEvent()->StartDatetime)->addDay(7);

        $dates = [];

        while (count($dates) < $max) {
            $dates[] = $freshDate($date->format($format));
            $date->addDay(7);
        }

        $this->weekly_dates = $dates;

        return $this;
    }

    /**
     * @return mixed
     */
    private function getMonthlyDates()
    {
        if (!$this->monthly_dates) {
            $this->setMonthlyDates();
        }

        return $this->monthly_dates;
    }

    /**
     * @return $this
     */
    private function setMonthlyDates()
    {
        $freshDate = function ($string) {
            return Carbon::parse($string);
        };

        $max = RecursiveEvent::config()->get('create_new_max');
        $date = Carbon::parse($this->getEvent()->StartDatetime);
        $dates = [];

        while (count($dates) < $max) {
            $month = $date->month;

            while ($month == $date->month) {
                $date->addWeek();
            }

            $dates[] = $freshDate($date->format($this->config()->get('date_format')));
        }

        $this->monthly_dates = $dates;

        return $this;
    }

    /**
     * @return mixed
     */
    private function getYearlyEvents()
    {
        if (!$this->yearly_dates) {
            $this->setYearlyDates();
        }

        return $this->yearly_dates;
    }

    /**
     * @return $this
     */
    private function setYearlyDates()
    {
        $freshDate = function ($string) {
            return Carbon::parse($string);
        };

        $max = RecursiveEvent::config()->get('create_new_max');
        $date = Carbon::parse($this->getEvent()->StartDatetime)->addYear();
        $dates = [];

        while (count($dates) < $max) {
            $dates[] = $freshDate($date->format($this->config()->get('date_format')));
            $date->addYear();
        }

        $this->yearly_dates = $dates;

        return $this;
    }

    /**
     * @param $date
     * @return \SilverStripe\ORM\DataObject
     */
    protected function dateExists(Carbon $date)
    {
        return RecursiveEvent::get()->filter('StartDatetime',
            $date->format($this->config()->get('date_format')))->first();
    }

    /**
     * @return mixed
     */
    protected function getLastKnownDate()
    {
        return RecursiveEvent::get()->filter('ParentID', $this->getEvent()->ID)->max('StartDatetime')->StartDatetime;
    }

    /**
     * @param Carbon $date
     * @return RecursiveEvent|\SilverStripe\ORM\DataObject
     */
    public function createRecursiveEvent(Carbon $date)
    {
        $time = Carbon::parse($this->getEvent()->StartDatetime)->format('H:i:s');
        $dateTime = $date->setTimeFromTimeString($time)->format($this->config()->get('date_format'));

        $filter = [
            'ParentID' => $this->getEvent()->ID,
            'StartDatetime' => $dateTime,
        ];

        if (!$event = RecursiveEvent::get()->filter($filter)->first()) {
            $event = RecursiveEvent::create();
            $event->ParentID = $this->getEvent()->ID;
            $event->GeneratingChangeSetID = $this->getChaneSet()->ID;
        }

        $event->StartDatetime = $dateTime;
        $event->Title = $this->getEvent()->Title;
        $event->Content = $this->getEvent()->Content;
        $event->writeToStage(Versioned::DRAFT);

        if ($this->getEvent()->isPublished()) {
            $event->publishRecursive();
        }

        return $event;
    }
}
