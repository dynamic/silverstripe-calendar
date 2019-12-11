<?php

namespace Dynamic\Calendar\Tests\Page;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Versioned\Versioned;

/**
 * Class EventPageTest
 * @package Dynamic\Calendar\Tests\Page
 */
class EventPageTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = '../Calendar.yml';

    /**
     *
     */
    public function testRecursiveEventCreation()
    {
        Config::modify()->set(EventPage::class, 'recursion', true);

        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');
        $tomorrow = strtotime('tomorrow');

        $event->StartDate = date('Y-m-d', $tomorrow);
        $event->Recursion = 'DAILY';
        $event->Interval = 2;
        $event->RecursionEndDate = date('Y-m-d', strtotime("+7 day", $tomorrow));
        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $event = EventPage::get()->byID($event->ID);

        $this->assertEquals(3, $event->AllChildren()->count());

        $event->Interval = 1;
        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $this->assertEquals(7, $event->AllChildren()->count());

        $event->Interval = 2;
        $event->writeToStage(Versioned::DRAFT);
        $event->publishRecursive();

        $this->assertEquals(3, $event->AllChildren()->count());


        Config::modify()->set(EventPage::class, 'recursion', false);
    }

    /**
     *
     */
    public function testLumberjackPagesForGridfield()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->assertFalse($event->getLumberjackPagesForGridfield()->exists());
    }

    /**
     *
     */
    public function testCMSFields()
    {
        /** @var EventPage $event */
        $event = $this->objFromFixture(EventPage::class, 'one');

        $this->assertInstanceOf(FieldList::class, $event->getCMSFields());
    }
}
