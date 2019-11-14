<?php

namespace Dynamic\Calendar\Factory;

use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use RRule\RRule;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
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
    use Extensible;

    /**
     * @var EventPage
     */
    private $event;

    /**
     * @var
     */
    private $date;

    /**
     * RecursiveEventFactory constructor.
     * @param EventPage|null $event
     */
    public function __construct(EventPage $event = null)
    {
        if ($event instanceof EventPage) {
            $this->setEvent($event);
        }
    }

    /**
     * @param EventPage $event
     * @return $this
     */
    public function setEvent(EventPage $event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @return EventPage
     */
    protected function getEvent()
    {
        return $this->event;
    }

    /**
     * @param string $date
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getDate()
    {
        return $this->date;
    }

    /**
     * @return array
     */
    protected function getEventCloneData()
    {
        $eventCloneData = $this->config()->get('event_clone_data');

        $this->extend('updateEventCloneData', $eventCloneData);

        return $eventCloneData;
    }

    /**
     * @return RecursiveEvent
     */
    public function createEvent()
    {
        $event = $this->getEvent();
        $findFilter = [
            'ParentID' => $event->ID,
            'StartDate' => $this->getDate(),
        ];

        if (!$recursion = RecursiveEvent::get()->filter($findFilter)->first()) {
            /** @var RecursiveEvent $recursion */
            $recursion = Injector::inst()->create(RecursiveEvent::class, $event->toMap());
            $recursion->StartDate = $this->getDate();
            $recursion->writeToStage(Versioned::DRAFT);
        }

        $recursion->syncRelationsFromParentEvent();

        return $recursion;
    }

    /**
     * @param $list
     * @return \Generator
     */
    protected function yieldSingle($list)
    {
        foreach ($list as $item) {
            yield $item;
        }
    }
}
