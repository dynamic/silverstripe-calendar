<?php

namespace Dynamic\Calendar\Traits;

use Carbon\Carbon;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;

/**
 * Carbon Calendar Controller Trait
 *
 * Provides Carbon-based event handling for calendar controllers.
 * This trait handles both regular events and virtual recurring instances.
 *
 * @package Dynamic\Calendar\Traits
 */
trait CarbonCalendarController
{
    /**
     * @var array Cache for processed events
     */
    protected array $processedEvents = [];

    /**
     * Set events using Carbon-based system (handles both regular and recurring events)
     *
     * @return $this
     */
    protected function setEventsWithCarbon(): self
    {
        $events = new ArrayList();

        // Get date range for filtering
        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

        // Get all events from database
        $allEvents = $this->getBaseEventQuery();

        foreach ($allEvents as $event) {
            if ($event->eventRecurs() && $event->usesCarbonRecursion()) {
                // For recurring events, get virtual instances
                $instances = $event->getOccurrences($startDate, $endDate);
                foreach ($instances as $instance) {
                    if (!$instance->isDeleted()) {
                        $events->push($instance);
                    }
                }
            } else {
                // For regular events, check if they fall within our date range
                if ($this->eventInDateRange($event, $startDate, $endDate)) {
                    $events->push($event);
                }
            }
        }

        // Sort events by start date
        $events = $events->sort('StartDate');

        // Apply additional filters
        $events = $this->applyRequestFilters($events);

        $this->extend('updateEvents', $events);

        $this->events = $events;

        return $this;
    }

    /**
     * Get base event query with default filters
     *
     * @return DataList
     */
    protected function getBaseEventQuery(): DataList
    {
        $events = EventPage::get();

        // Apply default filters (like ParentID for calendar pages)
        if ($this->getDefaultFilter() !== null) {
            $events = $events->filter($this->getDefaultFilter());
        }

        return $events;
    }

    /**
     * Check if an event falls within the specified date range
     *
     * @param EventPage $event
     * @param Carbon|string $startDate
     * @param Carbon|string $endDate
     * @return bool
     */
    protected function eventInDateRange(EventPage $event, $startDate, $endDate): bool
    {
        $eventStart = Carbon::parse($event->StartDate);
        $eventEnd = $event->EndDate ? Carbon::parse($event->EndDate) : $eventStart;
        $rangeStart = Carbon::parse($startDate);
        $rangeEnd = Carbon::parse($endDate);

        // Check if event overlaps with date range
        return $eventStart->lte($rangeEnd) && $eventEnd->gte($rangeStart);
    }

    /**
     * Get end date for filtering (default to 1 year from start)
     *
     * @return string
     */
    protected function getEndDate(): string
    {
        $request = $this->getRequest();

        if ($endDate = $request->getVar('EndDate')) {
            return Carbon::parse($endDate)->format('Y-m-d');
        }

        // Default to 1 year from start date
        return Carbon::parse($this->getStartDate())->addYear()->format('Y-m-d');
    }

    /**
     * Apply request-based filters to events collection
     *
     * @param ArrayList $events
     * @return ArrayList
     */
    protected function applyRequestFilters(ArrayList $events): ArrayList
    {
        $request = $this->getRequest();

        // Filter by title
        if ($title = $request->getVar('Title')) {
            $events = $events->filter('Title:PartialMatch', $title);
        }

        // Filter by categories
        if ($categoryIDs = $request->getVar('Categories')) {
            if (!is_array($categoryIDs)) {
                $categoryIDs = [$categoryIDs];
            }

            $filteredEvents = new ArrayList();
            foreach ($events as $event) {
                if ($this->eventHasCategories($event, $categoryIDs)) {
                    $filteredEvents->push($event);
                }
            }
            $events = $filteredEvents;
        }

        // Filter by date range (additional to the main range)
        if ($specificDate = $request->getVar('Date')) {
            $targetDate = Carbon::parse($specificDate);
            $filteredEvents = new ArrayList();

            foreach ($events as $event) {
                if ($this->eventOccursOnDate($event, $targetDate)) {
                    $filteredEvents->push($event);
                }
            }
            $events = $filteredEvents;
        }

        return $events;
    }

    /**
     * Check if an event has any of the specified categories
     *
     * @param EventPage|EventInstance $event
     * @param array $categoryIDs
     * @return bool
     */
    protected function eventHasCategories($event, array $categoryIDs): bool
    {
        // For virtual instances, get categories from original event
        if ($event instanceof EventInstance) {
            $event = $event->getOriginalEvent();
        }

        $eventCategoryIDs = $event->Categories()->column('ID');

        return !empty(array_intersect($categoryIDs, $eventCategoryIDs));
    }

    /**
     * Check if an event occurs on a specific date
     *
     * @param EventPage|EventInstance $event
     * @param Carbon $date
     * @return bool
     */
    protected function eventOccursOnDate($event, Carbon $date): bool
    {
        if ($event instanceof EventInstance) {
            return $event->occursOn($date);
        }

        $startDate = Carbon::parse($event->StartDate);
        $endDate = $event->EndDate ? Carbon::parse($event->EndDate) : $startDate;

        return $date->between($startDate, $endDate, true);
    }

    /**
     * Get events for a specific month
     *
     * @param Carbon|string $month
     * @return ArrayList
     */
    public function getEventsForMonth($month): ArrayList
    {
        $monthStart = Carbon::parse($month)->startOfMonth();
        $monthEnd = Carbon::parse($month)->endOfMonth();

        $events = new ArrayList();
        $allEvents = $this->getBaseEventQuery();

        foreach ($allEvents as $event) {
            if ($event->eventRecurs() && $event->usesCarbonRecursion()) {
                $instances = $event->getOccurrencesInMonth($monthStart);
                foreach ($instances as $instance) {
                    if (!$instance->isDeleted()) {
                        $events->push($instance);
                    }
                }
            } else {
                if ($this->eventInDateRange($event, $monthStart, $monthEnd)) {
                    $events->push($event);
                }
            }
        }

        return $events->sort('StartDate');
    }

    /**
     * Get events for a specific week
     *
     * @param Carbon|string $week
     * @return ArrayList
     */
    public function getEventsForWeek($week): ArrayList
    {
        $weekStart = Carbon::parse($week)->startOfWeek();
        $weekEnd = Carbon::parse($week)->endOfWeek();

        $events = new ArrayList();
        $allEvents = $this->getBaseEventQuery();

        foreach ($allEvents as $event) {
            if ($event->eventRecurs() && $event->usesCarbonRecursion()) {
                $instances = $event->getOccurrencesInWeek($weekStart);
                foreach ($instances as $instance) {
                    if (!$instance->isDeleted()) {
                        $events->push($instance);
                    }
                }
            } else {
                if ($this->eventInDateRange($event, $weekStart, $weekEnd)) {
                    $events->push($event);
                }
            }
        }

        return $events->sort('StartDate');
    }

    /**
     * Get events for today
     *
     * @return ArrayList
     */
    public function getTodaysEvents(): ArrayList
    {
        $today = Carbon::today();
        $events = new ArrayList();
        $allEvents = $this->getBaseEventQuery();

        foreach ($allEvents as $event) {
            if ($event->eventRecurs() && $event->usesCarbonRecursion()) {
                if ($event->hasOccurrenceOn($today)) {
                    $instances = $event->getOccurrences($today, $today);
                    foreach ($instances as $instance) {
                        if (!$instance->isDeleted()) {
                            $events->push($instance);
                        }
                    }
                }
            } else {
                if ($this->eventOccursOnDate($event, $today)) {
                    $events->push($event);
                }
            }
        }

        return $events->sort('StartDate');
    }

    /**
     * Get upcoming events (next 30 days)
     *
     * @param int $days
     * @return ArrayList
     */
    public function getUpcomingEvents(int $days = 30): ArrayList
    {
        $start = Carbon::now();
        $end = Carbon::now()->addDays($days);

        $events = new ArrayList();
        $allEvents = $this->getBaseEventQuery();

        foreach ($allEvents as $event) {
            if ($event->eventRecurs() && $event->usesCarbonRecursion()) {
                $instances = $event->getOccurrences($start, $end);
                foreach ($instances as $instance) {
                    if (!$instance->isDeleted()) {
                        $events->push($instance);
                    }
                }
            } else {
                if ($this->eventInDateRange($event, $start, $end)) {
                    $events->push($event);
                }
            }
        }

        return $events->sort('StartDate');
    }

    /**
     * Check if Carbon recursion system should be used
     *
     * @return bool
     */
    protected function shouldUseCarbonRecursion(): bool
    {
        return EventPage::config()->get('recursion_system') === 'carbon';
    }

    /**
     * Override the main setEvents method to use Carbon system when enabled
     *
     * @return $this
     */
    protected function setEvents(): self
    {
        if ($this->shouldUseCarbonRecursion()) {
            return $this->setEventsWithCarbon();
        }

        // Fall back to original implementation for backward compatibility
        return $this->setEventsLegacy();
    }

    /**
     * Legacy setEvents method (original RRule-based implementation)
     * Kept for backward compatibility
     *
     * @return $this
     */
    protected function setEventsLegacy(): self
    {
        $events = EventPage::get()
            ->filterAny([
                'StartDate:GreaterThanOrEqual' => $this->getStartDate(),
                'EndDate:GreaterThanOrEqual' => date('Y-m-d', strtotime('now')),
            ]);

        if ($this->getDefaultFilter() != null) {
            $events = $events->filter($this->getDefaultFilter());
        }

        $events = $this->filterByRequest($events);

        $this->extend('updateEvents', $events);

        $this->events = $events;

        return $this;
    }
}
