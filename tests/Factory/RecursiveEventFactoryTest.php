<?php

namespace Dynamic\Calendar\Tests\Factory;

use Carbon\Carbon;
use Dynamic\Calendar\Factory\RecursiveEventFactory;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

/**
 * Class RecursiveEventFactoryTest
 * @package Dynamic\Calendar\Tests\Factory
 */
class RecursiveEventFactoryTest extends SapphireTest
{
    /**
     * @var string
     */
    //protected static $fixture_file = 'calendartest.yml';

    /**
     * @var
     */
    private $reading_mode;

    /**
     * @var Calendar
     */
    private $calendar;

    /**
     * @var EventPage
     */
    private $daily_event;

    /**
     * @var EventPage
     */
    private $weekly_event;

    /**
     * @var EventPage
     */
    private $monthly_event;

    /**
     *
     */
    protected function setUp()
    {
        parent::setUp();

        $this->setCalendar();
        $this->setDailyEvent();
        $this->setWeeklyEvent();

        $this->reading_mode = Versioned::get_reading_mode();
        Versioned::set_reading_mode('stage');
    }

    /**
     *
     */
    protected function tearDown()
    {
        Versioned::set_reading_mode($this->reading_mode);

        parent::tearDown();
    }

    /**
     * @return Calendar
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function getCalendar()
    {
        if (!$this->calendar) {
            $this->setCalendar();
        }

        return $this->calendar;
    }

    /**
     * @return $this
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function setCalendar()
    {
        if (!$calendar = Calendar::get()->first()) {
            $calendar = Calendar::create(['Title' => 'My Calendar']);
            $calendar->write();
        }

        $this->calendar = $calendar;

        return $this;
    }

    /**
     * @return EventPage
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function getDailyEvent()
    {
        if (!$this->daily_event) {
            $this->setDailyEvent();
        }

        return $this->daily_event;
    }

    /**
     * @return $this
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function setDailyEvent()
    {
        if (!$event = EventPage::get()->filter('Recursion', 'Daily')->first()) {
            $event = EventPage::create();
            $event->ParentID = $this->getCalendar()->ID;
            $event->Title = 'My Daily Event';
            $event->URLSegment = 'my-daily-event';
            $event->Recursion = 'Daily';
            $event->StartDatetime = Carbon::now()->addDay()->format('Y-m-d H:i:s');
            $event->write();
        }

        $this->daily_event = EventPage::get()->byID($event->ID);

        return $this;
    }

    /**
     * @return mixed
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function getWeeklyEvent()
    {
        if (!$this->weekly_event) {
            $this->setWeeklyEvent();
        }

        return $this->weekly_event;
    }

    /**
     * @return $this
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function setWeeklyEvent()
    {
        if (!$event = EventPage::get()->filter('Recursion', 'Weekly')->first()) {
            $event = EventPage::create();
            $event->ParentID = $this->getCalendar()->ID;
            $event->Title = 'My Weekly Event';
            $event->URLSegment = 'my-weekly-event';
            $event->Recursion = 'Weekly';
            $event->StartDatetime = Carbon::now()->addDay()->format('Y-m-d H:i:s');
            $event->write();
        }

        $this->weekly_event = EventPage::get()->byID($event->ID);

        return $this;
    }

    /**
     * @return EventPage
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function getMonthlyEvent()
    {
        if (!$this->monthly_event) {
            $this->setMonthlyEvent();
        }

        return $this->monthly_event;
    }

    /**
     * @return $this
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function setMonthlyEvent()
    {
        if (!$event = EventPage::get()->filter('Recursion', 'Monthly')->first()) {
            $event = EventPage::create();
            $event->ParentID = $this->getCalendar()->ID;
            $event->Title = 'My Monthly Event';
            $event->URLSegment = 'my-monthly-event';
            $event->Recursion = 'Monthly';
            $event->StartDatetime = Carbon::now()->addDay()->format('Y-m-d H:i:s');
            $event->write();
        }

        $this->monthly_event = $event;

        return $this;
    }

    /**
     *
     */
    public function testSetChangeSet()
    {
        $changeSet = $this->getDailyEvent()->getCurrentRecursionChangeSet();

        $factory = RecursiveEventFactory::create($changeSet);

        $this->assertEquals($changeSet, $factory->getChaneSet());
    }

    /**
     *
     */
    public function testSetExistingDates()
    {
        $changeSet = $this->getDailyEvent()->getCurrentRecursionChangeSet();

        $factory = RecursiveEventFactory::create($changeSet);

        $existingDates = $this->getDailyEvent()->Children();

        $this->assertNull($factory->getExistingDates());

        $factory->setExistingDates($existingDates);

        $this->assertEquals($existingDates, $factory->getExistingDates());
    }

    /**
     *
     */
    public function testGenerateEvents()
    {
        $newEvent = $this->getDailyEvent();
        $changeSet = $newEvent->getCurrentRecursionChangeSet();

        $factory = RecursiveEventFactory::create($changeSet);
        $this->deleteChildren($newEvent);

        $newEvent = EventPage::get()->byID($newEvent->ID);
        $children = $newEvent->Children()->exists();

        $this->assertFalse($children);

        $factory->generateEvents();
        $newEvent = EventPage::get()->byID($newEvent->ID);

        //$this->assertEquals(RecursiveEvent::config()->get('create_new_max'), $newEvent->Children()->count());
    }

    /**
     *
     */
    public function testShiftBaseDay()
    {
        //$this->markTestSkipped('This is broke.');
        /*$event = $this->getDailyEvent();

        /**
         * @param EventPage $event
         * @return EventPage|DataObject
         *
        $refreshEvent = function (EventPage $event) {
            return EventPage::get()->byID($event->ID);
        };

        $this->deleteChildren($event);

        /** @var EventPage $event *
        $event = $refreshEvent($event);

        $count = $event->Children()->count();

        $this->assertFalse($event->Children()->exists());

        $dayTime = $baseDayTime = Carbon::now()->addDay();
        $dayTimeFormat = RecursiveEventFactory::config()->get('date_format');

        $event->StartDatetime = $dayTime->format($dayTimeFormat);
        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $event = $refreshEvent($event);

        $count = $event->Children()->count();

        $this->assertEquals(RecursiveEvent::config()->get('create_new_max'), $event->Children()->count());

        $event->StartDatetime = $dayTime->subDay()->format($dayTimeFormat);
        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $event = $refreshEvent($event);

        $this->assertEquals(RecursiveEvent::config()->get('create_new_max') + 1, $event->Children()->count());
        //*/
    }

    /**
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function testWeeklyEvents()
    {
        $event = $this->getWeeklyEvent();

        //$this->assertEquals(RecursiveEvent::config()->get('create_new_max'), $event->Children()->count());
    }

    public function testMonthlyEvents()
    {
        $event = $this->getMonthlyEvent();

        $this->assertEquals(RecursiveEvent::config()->get('create_new_max'), $event->Children()->count());
    }

    /**
     * @param EventPage $event
     */
    private function deleteChildren(EventPage $event)
    {
        foreach ($this->yieldSingle($event->Children()) as $child) {
            $child->doUnpublish();
            $child->doArchive();
        }
    }

    /**
     * @param $items
     * @return \Generator
     */
    private function yieldSingle($items)
    {
        foreach ($items as $item) {
            yield $item;
        }
    }
}
