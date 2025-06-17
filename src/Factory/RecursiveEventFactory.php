<?php

namespace Dynamic\Calendar\Factory;

use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
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
    public function setEvent(EventPage $event): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @return EventPage
     */
    protected function getEvent(): EventPage
    {
        return $this->event;
    }

    /**
     * @param string $date
     * @return $this
     */
    public function setDate($date): self
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getDate(): mixed
    {
        return $this->date;
    }

    /**
     * @return array
     */
    protected function getEventCloneData(): array
    {
        $eventCloneData = $this->config()->get('event_clone_data');

        $this->extend('updateEventCloneData', $eventCloneData);

        return $eventCloneData;
    }

    /**
     * @return array
     */
    protected function getCleanMap(): array
    {
        $map = $this->getEvent()->toMap();

        unset($map['ID']);
        unset($map['ClassName']);
        unset($map['Created']);
        unset($map['LastEdited']);
        unset($map['Version']);
        unset($map['URLSegment']);
        unset($map['Sort']);

        return $map;
    }

    /**
     * @return RecursiveEvent
     */
    public function createEvent(): RecursiveEvent
    {
        $event = $this->getEvent();
        $findFilter = [
            'ParentID' => $event->ID,
            'StartDate' => $this->getDate(),
        ];

        if (!$recursion = RecursiveEvent::get()->filter($findFilter)->first()) {
            $recursion = RecursiveEvent::create($this->getCleanMap());
            $recursion->ID = null;
            $recursion->ParentID = $event->ID;
            $recursion->StartDate = $this->getDate();
            $recursion->writeToStage(Versioned::DRAFT);
        }

        return $recursion;
    }

    /**
     * @param $list
     * @return \Generator
     */
    protected function yieldSingle($list): \Generator
    {
        foreach ($list as $item) {
            yield $item;
        }
    }
}
