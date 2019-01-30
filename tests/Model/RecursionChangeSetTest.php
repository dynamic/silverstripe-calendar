<?php

namespace Dynamic\Calendar\Tests;

use Dynamic\Calendar\Model\RecursionChangeSet;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\ListboxField;

/**
 * Class RecursionChangeSetTest
 * @package Dynamic\Calendar\Tests
 */
class RecursionChangeSetTest extends SapphireTest
{
    /**
     * @var
     */
    private $data;

    /**
     *
     */
    protected function setUp()
    {
        parent::setUp();

        $this->setDefaultData();
    }

    /**
     * @param array $data
     * @return $this
     */
    protected function setData($data = [])
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getData()
    {
        return $this->data;
    }

    /**
     *
     */
    protected function setDefaultData()
    {
        $this->setData([
            'Title' => 'Event One',
            'StartDatetime' => date('Y-m-d H:i:s', strtotime('Next Wednesday')),
            'URLSegment' => 'event-one-recursive',
            'RecursiveRecords' => true,
            'RecursionPattern' => $this->getPattern([1]),
        ]);
    }
}
