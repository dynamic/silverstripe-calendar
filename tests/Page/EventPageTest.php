<?php

namespace Dynamic\Calendar\Tests\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use PHP_CodeSniffer\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;

/**
 * Class EventPageTest
 * @package Dynamic\Calendar\Tests\Page
 */
class EventPageTest extends SapphireTest
{
    /**
     * @var Calendar
     */
    private $calendar;

    /**
     * @var
     */
    private $know_daily_event;

    /**
     * @var
     */
    private $known_weekly_event;

    /**
     *
     */
    protected function setUp()
    {
        parent::setUp();

        if (!$this->getCalendar()) {
            $this->setCalendar();
        }
    }

    /**
     * @return Calendar
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
     */
    protected function setCalendar()
    {
        if (!$calendar = Calendar::get()->first()) {
            $calendar = Calendar::create(['Title' => 'My Calendar']);
            $calendar->writeToStage(Versioned::DRAFT);
            $calendar->publishRecursive();
        }

        $this->calendar = $calendar;

        return $this;
    }

    /**
     *
     */
    public function testEventEdit()
    {
        $event = $this->getKnownWeeklyEvent();

        $currentChangeSet = $event->RecursionChangeSetID;

        $this->assertTrue($event->exists());

        $recursionOptions = $event->getPatternSource();

        $weeklyString = $recursionOptions['Daily'];

        $event->Recursion = 'Daily';

        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $event = EventPage::get()->byID($event->ID);

        $this->assertNotEquals($currentChangeSet, $event->RecursionChangeSetID);

        $childenCount = EventPage::get()->byID($event->ID)->Children()->count();

        //$this->assertEquals(RecursiveEvent::config()->get('create_new_max'), $event->Children()->count());
    }

    public function testNewDate()
    {
        $event = $this->getKnownDailyEvent();
        $this->assertEquals(14, $event->Children()->count());
    }

    protected function getKnownWeeklyEvent()
    {
        if (!$this->known_weekly_event instanceof EventPage) {
            $this->setKnownWeeklyEvent();
        }

        return $this->known_weekly_event;
    }

    /**
     * @return EventPage
     */
    protected function getKnownDailyEvent()
    {
        if (!$this->know_daily_event instanceof EventPage) {
            $this->setKnownDailyEvent();
        }

        return $this->know_daily_event;
    }

    /**
     * @return $this
     */
    protected function setKnownWeeklyEvent()
    {
        if (!$event = EventPage::get()->filter('Recursion', 'Weekly')->first()) {
            $event = EventPage::create();

            $event->Title = 'My Daily Event';
            $event->StartDatetime = Carbon::now(\DateTimeZone::AMERICA)->format('Y-m-d H:i:s');
            $event->Recursion = 'Weekly';
            $event->ParentID = $this->getCalendar()->ID;

            $event->writeToStage(Versioned::DRAFT);
            $event->publishRecursive();
        }

        $this->known_weekly_event = EventPage::get()->byID($event->ID);

        return $this;
    }

    /**
     * @return $this
     */
    protected function setKnownDailyEvent()
    {
        if (!$event = EventPage::get()->filter('Recursion', 'Daily')->first()) {
            $event = EventPage::create();

            $event->Title = 'My Daily Event';
            $event->StartDatetime = Carbon::now(\DateTimeZone::AMERICA)->format('Y-m-d H:i:s');
            $event->Recursion = 'Daily';
            $event->ParentID = $this->getCalendar()->ID;

            $event->writeToStage(Versioned::DRAFT);
            $event->publishRecursive();
        }

        $this->know_daily_event = EventPage::get()->byID($event->ID);

        return $this;
    }

}
