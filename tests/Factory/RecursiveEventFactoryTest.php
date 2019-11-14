<?php

namespace Dynamic\Calendar\Tests\Factory;

use Carbon\Carbon;
use Dynamic\Calendar\Factory\RecursiveEventFactory;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use SilverStripe\Core\Config\Config;
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
    protected static $fixture_file = '../Calendar.yml';

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
     * @var
     */
    private $annual_event;

    /**
     *
     */
    protected function setUp()
    {
        parent::setUp();

        Config::modify()->set(EventPage::class, 'recursion', true);

        $this->setCalendar();
        $this->setDailyEvent();
        /*$this->setWeeklyEvent();
        $this->setMonthlyEvent();
        $this->setAnnualEvent();

        $this->reading_mode = Versioned::get_reading_mode();
        Versioned::set_reading_mode('stage');//*/
    }

    /**
     *
     */
    protected function tearDown()
    {
        //Versioned::set_reading_mode($this->reading_mode);

        parent::tearDown();

        Config::modify()->set(EventPage::class, 'recursion', true);
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
        if (!$this->daily_event instanceof EventPage) {
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
        $startDate = Carbon::now()->addDay();

        $event = EventPage::create();
        $event->ParentID = $this->getCalendar()->ID;
        $event->Title = 'My Daily Event';
        $event->URLSegment = 'my-daily-event';
        $event->Recursion = 'DAILY';
        $event->StartDate = $startDate->format('Y-m-d');
        $event->Interval = 2;
        $event->RecursionEndDate = $startDate->addDay(7)->format('Y-m-d');
        $event->write();

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
            $event->Recursion = 'WEEKLY';
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
            $event->Recursion = 'MONTHLY';
            $event->StartDatetime = Carbon::now()->addDay()->format('Y-m-d');
            $event->write();
        }

        $this->monthly_event = $event;

        return $this;
    }

    /**
     * @return mixed
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function getMonthEvent()
    {
        if (!$this->annual_event) {
            $this->setAnnualEvent();
        }

        return $this->annual_event;
    }

    /**
     * @return $this
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function setAnnualEvent()
    {
        if (!$event = EventPage::get()->filter('Recursion', 'Annual')->first()) {
            $event = EventPage::create();
            $event->ParentID = $this->getCalendar()->ID;
            $event->Title = 'My Annual Event';
            $event->URLSegment = 'my-annual-event';
            $event->Recursion = 'Annual';
            $event->StartDatetime = Carbon::now()->addDay()->format('Y-m-d');
            $event->write();
        }

        $this->annual_event = $event;

        return $this;
    }

    /**
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function testYieldRecursionData()
    {
        $event = $this->getDailyEvent();

        $factory = RecursiveEventFactory::create($event);

        $this->assertInstanceOf(EventPage::class, $event);
        $this->assertEquals(4, $factory->getFullRecursionCount());
    }

    /**
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function testDailyEventRecursion()
    {
        $factory = RecursiveEventFactory::create($this->getDailyEvent());
        $factory->createRecursiveEvents();

        $this->assertEquals(3, $this->getDailyEvent()->Children()->filter('ClassName', RecursiveEvent::class)->count());

        $event = $this->getDailyEvent();
        $event->StartDate = Carbon::parse($event->StartDate)->addDay()->format('Y-m-d');
        $event->publishRecursive();

        $this->assertEquals(3, EventPage::get()->byID($event->ID)->Children()->filter('ClassName', RecursiveEvent::class)->count());

        $event->publishRecursive();
        $this->assertEquals(3, EventPage::get()->byID($event->ID)->Children()->filter('ClassName', RecursiveEvent::class)->count());
        //*/
    }

    /**
     *
     */
    public function testGenerateEvents()
    {
        $this->markTestSkipped('Complete with recursion');
        /*$newEvent = $this->getDailyEvent();
        $changeSet = $newEvent->getCurrentRecursionChangeSet();

        $factory = RecursiveEventFactory::create($changeSet);
        $this->deleteChildren($newEvent);

        $newEvent = EventPage::get()->byID($newEvent->ID);
        $children = $newEvent->Children()->exists();

        $this->assertFalse($children);

        $factory->generateEvents();
        $newEvent = EventPage::get()->byID($newEvent->ID);
        //$this->assertEquals(RecursiveEvent::config()->get('create_new_max'), $newEvent->Children()->count());//*/
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
