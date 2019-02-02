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
     * @var Calendar
     */
    private $calendar;

    /**
     * @var EventPage
     */
    private $daily_event;

    /**
     *
     */
    protected function setUp()
    {
        parent::setUp();

        $this->setCalendar();
        $this->setDailyEvent();
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
     * @return EventPage
     */
    protected function getDailyEvent()
    {
        if (!$this->daily_event) {
            $this->setDailyEvent();
        }

        return $this->daily_event;
    }

    /**
     *
     */
    protected function setDailyEvent()
    {
        if (!$event = EventPage::get()->filter('Recursion', 'Daily')->first()) {
            $event = EventPage::create();
            $event->ParentID = $this->getCalendar()->ID;
            $event->Title = 'My Daily Event';
            $event->URLSegment = 'my-daily-event';
            $event->Recursion = 'Daily';
            $event->StartDatetime = Carbon::parse('2019-02-01 21:00:00')->format('Y-m-d H:i:s');
            $event->writeToStage(Versioned::DRAFT);
            $event->publishRecursive();
        }

        $this->daily_event = EventPage::get()->byID($event->ID);

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

        foreach ($this->yieldSingle($newEvent->Children()) as $child) {
            $child->doUnpublish();
            $child->doArchive();
        }

        $newEvent = EventPage::get()->byID($newEvent->ID);
        $children = $newEvent->Children()->exists();

        $this->assertFalse($children);

        $factory->generateEvents();
        $newEvent = EventPage::get()->byID($newEvent->ID);

        $this->assertEquals(RecursiveEvent::config()->get('create_new_max'), $newEvent->Children()->count());
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
