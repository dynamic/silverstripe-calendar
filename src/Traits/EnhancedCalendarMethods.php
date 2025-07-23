<?php

namespace Dynamic\Calendar\Traits;

use Carbon\Carbon;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;

/**
 * Enhanced Calendar Methods
 *
 * Provides methods for handling both regular events and virtual recurring instances
 * in the CalendarController using the new Carbon-based recursion system.
 *
 * @package Dynamic\Calendar\Traits
 */
trait EnhancedCalendarMethods
{
    /**
     * @var ArrayList|null Cached combined events (regular + virtual instances)
     */
    protected $combinedEvents = null;

    /**
     * Get events including virtual instances from recurring events
     *
     * @return ArrayList
     */
    public function getCombinedEvents(): ArrayList
    {
        if ($this->combinedEvents === null) {
            $this->setCombinedEvents();
        }

        return $this->combinedEvents;
    }

    /**
     * Set combined events (regular + virtual instances)
     *
     * @return $this
     */
    protected function setCombinedEvents(): self
    {
        $allEvents = ArrayList::create();

        $startDate = $this->getStartDate();
        $endDate = $this->getEndDate();

        // Get regular (non-recurring) events
        $regularEvents = $this->getRegularEvents($startDate, $endDate);

        // Get recurring events and their virtual instances
        $recurringEvents = $this->getRecurringEvents();

        // Add regular events to the list
        foreach ($regularEvents as $event) {
            $allEvents->push($event);
        }

        // Add virtual instances from recurring events
        foreach ($recurringEvents as $event) {
            if ($event->usesCarbonRecursion()) {
                foreach ($event->getOccurrences($startDate, $endDate) as $instance) {
                    if (!$instance->isDeleted()) {
                        $allEvents->push($instance);
                    }
                }
            } else {
                // Legacy RRule-based events - handle as before
                if ($this->eventInDateRange($event, $startDate, $endDate)) {
                    $allEvents->push($event);
                }
            }
        }

        // Apply request-based filtering
        $allEvents = $this->filterCombinedEventsByRequest($allEvents);

        // Sort by start date
        $allEvents = $allEvents->sort('StartDate', 'ASC');

        $this->extend('updateCombinedEvents', $allEvents);

        $this->combinedEvents = $allEvents;

        return $this;
    }

    /**
     * Get regular (non-recurring) events
     *
     * @param string $startDate
     * @param string $endDate
     * @return DataList
     */
    protected function getRegularEvents(string $startDate, string $endDate): DataList
    {
        $events = EventPage::get()
            ->filter($this->getDefaultFilter())
            ->filter('Recursion', 'NONE')
            ->filterAny([
                'StartDate:GreaterThanOrEqual' => $startDate,
                'EndDate:GreaterThanOrEqual' => Carbon::now()->format('Y-m-d'),
            ]);

        if ($endDate) {
            $events = $events->filter('StartDate:LessThanOrEqual', $endDate);
        }

        return $events;
    }

    /**
     * Get recurring events
     *
     * @return DataList
     */
    protected function getRecurringEvents(): DataList
    {
        return EventPage::get()
            ->filter($this->getDefaultFilter())
            ->filter('Recursion:not', 'NONE');
    }

    /**
     * Check if an event falls within the date range
     *
     * @param EventPage $event
     * @param string $startDate
     * @param string $endDate
     * @return bool
     */
    protected function eventInDateRange(EventPage $event, string $startDate, string $endDate): bool
    {
        $eventStart = Carbon::parse($event->StartDate);
        $eventEnd = $event->EndDate ? Carbon::parse($event->EndDate) : $eventStart;
        $rangeStart = Carbon::parse($startDate);
        $rangeEnd = $endDate ? Carbon::parse($endDate) : Carbon::now()->addYear();

        // Check if event overlaps with the date range
        return $eventStart->lte($rangeEnd) && $eventEnd->gte($rangeStart);
    }

    /**
     * Apply request-based filtering to combined events
     *
     * @param ArrayList $events
     * @return ArrayList
     */
    protected function filterCombinedEventsByRequest(ArrayList $events): ArrayList
    {
        $request = $this->getRequest();
        $filtered = ArrayList::create();

        foreach ($events as $event) {
            $include = true;

            // Filter by end date
            if ($endDate = $request->getVar('EndDate')) {
                $endDateTime = Carbon::parse($endDate)->endOfDay();
                $eventEndDate = $event->EndDate ? Carbon::parse($event->EndDate) : Carbon::parse($event->StartDate);

                if ($eventEndDate->gt($endDateTime)) {
                    $include = false;
                }
            }

            // Filter by title
            if ($title = $request->getVar('Title')) {
                if (stripos($event->Title, $title) === false) {
                    $include = false;
                }
            }

            // Filter by categories
            if ($categories = $request->getVar('Categories')) {
                if (!is_array($categories)) {
                    $categories = [$categories];
                }

                $eventCategories = $event instanceof EventInstance
                    ? $event->getOriginalEvent()->Categories()->column('ID')
                    : $event->Categories()->column('ID');

                if (!array_intersect($categories, $eventCategories)) {
                    $include = false;
                }
            }

            if ($include) {
                $filtered->push($event);
            }
        }

        return $filtered;
    }

    /**
     * Get end date for filtering
     *
     * @return string|null
     */
    protected function getEndDate(): ?string
    {
        $request = $this->getRequest();
        $endDate = $request->getVar('EndDate');

        if (!$endDate) {
            // Default to 6 months from start date if not specified
            $startDate = Carbon::parse($this->getStartDate());
            $endDate = $startDate->copy()->addMonths(6)->format('Y-m-d');
        }

        return $endDate;
    }

    /**
     * Get paginated combined events
     *
     * @return \SilverStripe\ORM\PaginatedList
     */
    public function getPaginatedCombinedEvents()
    {
        return \SilverStripe\ORM\PaginatedList::create($this->getCombinedEvents(), $this->getRequest())
            ->setPageLength($this->data()->config()->get('events_per_page'));
    }

    /**
     * Get events for a specific month (useful for calendar views)
     *
     * @param Carbon|string|null $month
     * @return ArrayList
     */
    public function getEventsForMonth($month = null): ArrayList
    {
        $monthDate = $month ? Carbon::parse($month) : Carbon::now();
        $startDate = $monthDate->copy()->startOfMonth()->format('Y-m-d');
        $endDate = $monthDate->copy()->endOfMonth()->format('Y-m-d');

        $events = ArrayList::create();

        // Get regular events for the month
        $regularEvents = $this->getRegularEvents($startDate, $endDate);
        foreach ($regularEvents as $event) {
            $events->push($event);
        }

        // Get recurring event instances for the month
        $recurringEvents = $this->getRecurringEvents();
        foreach ($recurringEvents as $event) {
            if ($event->usesCarbonRecursion()) {
                foreach ($event->getOccurrencesInMonth($monthDate) as $instance) {
                    if (!$instance->isDeleted()) {
                        $events->push($instance);
                    }
                }
            }
        }

        return $events->sort('StartDate', 'ASC');
    }

    /**
     * Get events for a specific week (useful for week views)
     *
     * @param Carbon|string|null $week
     * @return ArrayList
     */
    public function getEventsForWeek($week = null): ArrayList
    {
        $weekDate = $week ? Carbon::parse($week) : Carbon::now();
        $startDate = $weekDate->copy()->startOfWeek()->format('Y-m-d');
        $endDate = $weekDate->copy()->endOfWeek()->format('Y-m-d');

        $events = ArrayList::create();

        // Get regular events for the week
        $regularEvents = $this->getRegularEvents($startDate, $endDate);
        foreach ($regularEvents as $event) {
            $events->push($event);
        }

        // Get recurring event instances for the week
        $recurringEvents = $this->getRecurringEvents();
        foreach ($recurringEvents as $event) {
            if ($event->usesCarbonRecursion()) {
                foreach ($event->getOccurrencesInWeek($weekDate) as $instance) {
                    if (!$instance->isDeleted()) {
                        $events->push($instance);
                    }
                }
            }
        }

        return $events->sort('StartDate', 'ASC');
    }

    /**
     * Check if Carbon recursion is enabled
     *
     * @return bool
     */
    public function usesCarbonRecursion(): bool
    {
        return EventPage::config()->get('recursion_system') === 'carbon';
    }

    /**
     * Get upcoming events (next N events from now)
     *
     * @param int $limit
     * @return ArrayList
     */
    public function getUpcomingEvents(int $limit = 10): ArrayList
    {
        $now = Carbon::now();
        $futureDate = $now->copy()->addMonths(12); // Look ahead 1 year

        $events = ArrayList::create();

        // Get regular events
        $regularEvents = EventPage::get()
            ->filter($this->getDefaultFilter())
            ->filter('Recursion', 'NONE')
            ->filter('StartDate:GreaterThanOrEqual', $now->format('Y-m-d'))
            ->sort('StartDate', 'ASC')
            ->limit($limit);

        foreach ($regularEvents as $event) {
            $events->push($event);
        }

        // Get instances from recurring events
        $recurringEvents = $this->getRecurringEvents();
        foreach ($recurringEvents as $event) {
            if ($event->usesCarbonRecursion()) {
                $count = 0;
                foreach ($event->getOccurrences($now, $futureDate) as $instance) {
                    if (!$instance->isDeleted() && $count < $limit) {
                        $events->push($instance);
                        $count++;
                    }
                }
            }
        }

        // Sort all events by date and limit
        return $events->sort('StartDate', 'ASC')->limit($limit);
    }
}
