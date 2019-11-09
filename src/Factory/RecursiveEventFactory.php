<?php

namespace Dynamic\Calendar\Factory;

use Carbon\Carbon;
use Dynamic\Calendar\Model\RecursionChangeSet;
use Dynamic\Calendar\Page\EventPage;
use Dynamic\Calendar\Page\RecursiveEvent;
use RRule\RRule;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
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
     * @var array
     */
    private $recursion_dates = [];

    /**
     * @var SS_List
     */
    private $existing_dates;

    /**
     * RecursiveEventFactory constructor.
     * @param EventPage $event
     */
    public function __construct(EventPage $event)
    {
        $this->setEvent($event);
    }

    /**
     * @param EventPage $event
     * @return $this
     */
    protected function setEvent(EventPage $event)
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
     * @return RRule
     */
    protected function getRecursionSet()
    {
        return new RRule([
            'FREQ' => EventPage::RRULE[$this->getEvent()->Recursion],
            'INTERVAL' => $this->getEvent()->Interval,
            'DTSTART' => $this->getEvent()->StartDate,
            'UNTIL' => $this->getEvent()->RecursionEndDate,
        ]);
    }

    /**
     * The total count will include the originating date.
     *
     * @return int
     */
    public function getFullRecursionCount()
    {
        return $this->getRecursionSet()->count();
    }

    /**
     *
     */
    public function yieldRecursionData()
    {
        foreach ($this->getRecursionSet() as $recurrence) {
            yield $recurrence;
        }
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
     * @param RecursiveEvent $recursion
     * @return RecursiveEvent
     */
    protected function duplicateData(RecursiveEvent $recursion)
    {
        foreach ($this->getEventCloneData() as $type => $mapping) {
            switch (true) {
                case 'db':
                    $recursion = $this->duplicateDB($recursion, $mapping);
                    break;
                case 'has_one':
                    $recursion = $this->duplicateHasOne($recursion, $mapping);
                    break;
                case 'has_many':
                    $recursion = $this->duplicateHasMany($recursion, $mapping);
                    break;
                case 'many_many':
                    $recursion = $this->duplicateManyMany($recursion, $mapping);
                    break;
            }
        }

        return $recursion;
    }

    /**
     * @param RecursiveEvent $recursion
     * @param array $mapping
     * @return RecursiveEvent
     */
    protected function duplicateDB(RecursiveEvent $recursion, array $mapping)
    {
        $event = $this->getEvent();

        foreach ($mapping as $field) {
            $recursion->{$field} = $event->{$field};
        }

        return $recursion;
    }

    /**
     * @param RecursiveEvent $recursion
     * @param array $mapping
     * @return RecursiveEvent
     */
    protected function duplicateHasOne(RecursiveEvent $recursion, array $mapping)
    {
        $event = $this->getEvent();
        $hasOne = EventPage::singleton()->hasOne();

        foreach ($mapping as $relation => $createNew) {
            // if the "create new" option is not specified, we will assume we are just using the same record for this has_one
            if ((is_int($relation) && array_key_exists($createNew, $hasOne))
                || (array_key_exists($relation, $hasOne) && !$createNew)) {
                $field = "{$createNew}ID";
                $recursion->{$field} = $event->{$field};
            } else {
                // create a new version of the related object
                $field = "{$relation}ID";
                $object = $event->$relation();
                $duplicateObject = $object->duplicate();
                $duplicateObject->write();
                $recursion->{$field} = $duplicateObject->ID;
            }
        }

        return $recursion;
    }

    /**
     * @param RecursiveEvent $recursion
     * @param array $mapping
     * @return RecursiveEvent
     */
    protected function duplicateHasMany(RecursiveEvent $recursion, array $mapping)
    {
        $event = $this->getEvent();
        $hasMany = EventPage::singleton()->hasMany();

        foreach ($mapping as $relation) {
            if (array_key_exists($recursion, $hasMany)) {
                $newRelationSet = $recursion->$relation();
                $existingRelationSet = $event->$relation();

                foreach ($existingRelationSet as $object) {
                    $newObject = $object->duplicate();
                    $newObject->write();
                    $newRelationSet->add($newObject);
                }
            }
        }

        return $recursion;
    }

    /**
     * @param RecursiveEvent $recursion
     * @param array $mapping
     * @return RecursiveEvent
     */
    protected function duplicateManyMany(RecursiveEvent $recursion, array $mapping)
    {
        $event = $this->getEvent();
        $manyMany = EventPage::singleton()->manyMany();
        $extraFields = EventPage::singleton()->manyManyExtraFields();

        foreach ($mapping as $relation) {
            if (array_key_exists($relation, $manyMany)) {
                $newRelationSet = $recursion->$relation();
                $existingRelationSet = $event->$relation();

                foreach ($existingRelationSet as $object) {
                    $fields = [];

                    foreach (array_keys($extraFields) as $field) {
                        $fields[$field] = $object->{$field};
                    }

                    $newRelationSet->add($object, $fields);
                }
            }
        }

        return $recursion;
    }

    /**
     *
     */
    public function createRecursiveEvent()
    {
        $event = $this->getEvent();

        $validDates = [];

        foreach ($this->yieldRecursionData() as $date) {
            if ($date->format('Y-m-d') != $event->StartDate) {
                $validDates[$date->format('Y-m-d')] = $date->format('Y-m-d');
            }
        }

        $existing = RecursiveEvent::get()
            ->filter('ParentID', $this->getEvent()->ID)
            ->exclude($validDates)
            ->column('StartDate');

        $remaining = array_diff($validDates, $existing);

        foreach ($remaining as $startDate) {
            $recursion = RecursiveEvent::create();
            $recursion->ParentID = $this->getEvent()->ID;
            $recursion->StartDate = $startDate;

            $recursion = $this->duplicateData($recursion);
            $recursion->writeToStage(Versioned::DRAFT);

            if ($event->isPublished()) {
                $recursion->publishRecursive();
            }
        }
    }
}
