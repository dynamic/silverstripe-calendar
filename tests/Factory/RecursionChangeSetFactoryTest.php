<?php

namespace Dynamic\Calendar\Tests\Factory;

use Dynamic\Calendar\Model\Event;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Versioned\Versioned;

/**
 * Class RecursionChangeSetFactoryTest
 * @package Dynamic\Calendar\Tests\Factory
 */
class RecursionChangeSetFactoryTest extends FunctionalTest
{
    /**
     * @var string
     */
    protected static $fixture_file = '../Calendar.yml';

    /**
     * @var array
     */
    private $event_data;

    /**
     *
     */
    protected function setUp()
    {
        parent::setUp();

        $this->setEventData([
            'Title' => 'Event One',
            'StartDatetime' => date('Y-m-d H:i:s', strtotime('Next Wednesday')),
            'URLSegment' => 'event-one-recursive',
            'RecursiveRecords' => true,
            'RecursionPattern' => $this->getPattern([1]),
        ]);
    }

    /**
     * @param array $data
     * @return $this
     */
    private function setEventData($data)
    {
        $this->event_data = $data;

        return $this;
    }

    /**
     * @return array
     */
    private function getEventData()
    {
        return $this->event_data;
    }

    /**
     * @param array $days an array of day numbers (1-7), with 1 being Monday
     * @return string|null
     */
    protected function getPattern($days = [])
    {
        return ListboxField::create('RecursionPattern')->stringEncode($days);
    }
}
