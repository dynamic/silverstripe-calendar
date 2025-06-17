<?php

namespace Dynamic\Calendar\Traits;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Model\EventInstance;
use Generator;

/**
 * Carbon Recursion Trait
 *
 * Provides Carbon-based recursion functionality for EventPage.
 * This replaces the RRule-based system with a more efficient and flexible approach.
 *
 * @package Dynamic\Calendar\Traits
 */
trait CarbonRecursion
{
    /**
     * @var array Cache for occurrence calculations
     */
    protected array $occurrenceCache = [];

    /**
     * Generate event occurrences using Carbon periods
     *
     * @param Carbon|string|null $startDate
     * @param Carbon|string|null $endDate
     * @param int|null $limit
     * @return Generator<EventInstance>
     */
    public function getOccurrences($startDate = null, $endDate = null, ?int $limit = null): Generator
    {
        if (!$this->eventRecurs()) {
            // For non-recurring events, just return the original if it falls within range
            $eventStart = Carbon::parse($this->StartDate);
            $rangeStart = $startDate ? Carbon::parse($startDate) : Carbon::now()->subMonth();
            $rangeEnd = $endDate ? Carbon::parse($endDate) : Carbon::now()->addYear();

            if ($eventStart->between($rangeStart, $rangeEnd)) {
                yield $this->createVirtualInstance($eventStart);
            }
            return;
        }

        $count = 0;
        $period = $this->createCarbonPeriod($startDate, $endDate);

        if (!$period) {
            return;
        }

        foreach ($period as $date) {
            // Check for exceptions (deleted instances)
            $exception = $this->getExceptionForDate($date);
            if ($exception && $exception->isDeleted()) {
                continue;
            }

            // Create virtual instance
            $instance = $this->createVirtualInstance($date, $exception);

            yield $instance;

            // Apply limit if specified
            if ($limit && ++$count >= $limit) {
                break;
            }
        }
    }

    /**
     * Create a Carbon period based on the event's recursion settings
     *
     * @param Carbon|string|null $startDate
     * @param Carbon|string|null $endDate
     * @return CarbonPeriod|null
     */
    protected function createCarbonPeriod($startDate = null, $endDate = null): ?CarbonPeriod
    {
        if (!$this->eventRecurs()) {
            return null;
        }

        $eventStart = Carbon::parse($this->StartDate);
        $rangeStart = $startDate ? Carbon::parse($startDate) : $eventStart;
        $rangeEnd = $endDate ? Carbon::parse($endDate) : null;

        // Use recursion end date if no range end specified
        if (!$rangeEnd && $this->RecursionEndDate) {
            $rangeEnd = Carbon::parse($this->RecursionEndDate);
        }

        // Default to 2 years if no end date
        if (!$rangeEnd) {
            $rangeEnd = $rangeStart->copy()->addYears(2);
        }

        try {
            $period = match ($this->Recursion) {
                'DAILY' => $this->createDailyPeriod($eventStart, $rangeEnd),
                'WEEKLY' => $this->createWeeklyPeriod($eventStart, $rangeEnd),
                'MONTHLY' => $this->createMonthlyPeriod($eventStart, $rangeEnd),
                'YEARLY' => $this->createYearlyPeriod($eventStart, $rangeEnd),
                default => null
            };

            if (!$period) {
                return null;
            }

            // Filter period to only include dates within our range
            return $period->filter(function (Carbon $date) use ($rangeStart, $rangeEnd) {
                return $date->between($rangeStart, $rangeEnd, true);
            });
        } catch (\Exception $e) {
            // Log error and return null to prevent crashes
            error_log("Error creating Carbon period for event {$this->ID}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create daily recurrence period
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return CarbonPeriod
     */
    protected function createDailyPeriod(Carbon $start, Carbon $end): CarbonPeriod
    {
        $interval = max(1, (int) $this->Interval);
        return CarbonPeriod::create($start, "{$interval} days", $end);
    }

    /**
     * Create weekly recurrence period
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return CarbonPeriod
     */
    protected function createWeeklyPeriod(Carbon $start, Carbon $end): CarbonPeriod
    {
        $interval = max(1, (int) $this->Interval);
        return CarbonPeriod::create($start, "{$interval} weeks", $end);
    }

    /**
     * Create monthly recurrence period
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return CarbonPeriod
     */
    protected function createMonthlyPeriod(Carbon $start, Carbon $end): CarbonPeriod
    {
        $interval = max(1, (int) $this->Interval);
        return CarbonPeriod::create($start, "{$interval} months", $end);
    }

    /**
     * Create yearly recurrence period
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return CarbonPeriod
     */
    protected function createYearlyPeriod(Carbon $start, Carbon $end): CarbonPeriod
    {
        $interval = max(1, (int) $this->Interval);
        return CarbonPeriod::create($start, "{$interval} years", $end);
    }

    /**
     * Create a virtual instance for a specific date
     *
     * @param Carbon $date
     * @param EventException|null $exception
     * @return EventInstance
     */
    protected function createVirtualInstance(Carbon $date, ?EventException $exception = null): EventInstance
    {
        return new EventInstance($this, $date, $exception);
    }

    /**
     * Get the next occurrence after a given date
     *
     * @param Carbon|string|null $afterDate
     * @return EventInstance|null
     */
    public function getNextOccurrence($afterDate = null): ?EventInstance
    {
        $after = $afterDate ? Carbon::parse($afterDate) : Carbon::now();

        // For non-recurring events
        if (!$this->eventRecurs()) {
            $eventStart = Carbon::parse($this->StartDate);
            return $eventStart->gt($after) ? $this->createVirtualInstance($eventStart) : null;
        }

        // For recurring events, get occurrences for the next year and return the first one
        $occurrences = $this->getOccurrences($after, $after->copy()->addYear(), 1);

        foreach ($occurrences as $occurrence) {
            return $occurrence;
        }

        return null;
    }

    /**
     * Get occurrences within a specific month
     *
     * @param Carbon|string $month
     * @return array<EventInstance>
     */
    public function getOccurrencesInMonth($month): array
    {
        $monthStart = Carbon::parse($month)->startOfMonth();
        $monthEnd = Carbon::parse($month)->endOfMonth();

        $occurrences = [];
        foreach ($this->getOccurrences($monthStart, $monthEnd) as $occurrence) {
            $occurrences[] = $occurrence;
        }

        return $occurrences;
    }

    /**
     * Get occurrences within a specific week
     *
     * @param Carbon|string $week
     * @return array<EventInstance>
     */
    public function getOccurrencesInWeek($week): array
    {
        $weekStart = Carbon::parse($week)->startOfWeek();
        $weekEnd = Carbon::parse($week)->endOfWeek();

        $occurrences = [];
        foreach ($this->getOccurrences($weekStart, $weekEnd) as $occurrence) {
            $occurrences[] = $occurrence;
        }

        return $occurrences;
    }

    /**
     * Count total occurrences for this event
     *
     * @param Carbon|string|null $until
     * @return int
     */
    public function countOccurrences($until = null): int
    {
        if (!$this->eventRecurs()) {
            return 1;
        }

        $endDate = $until ? Carbon::parse($until) :
                  ($this->RecursionEndDate ? Carbon::parse($this->RecursionEndDate) : Carbon::now()->addYears(2));

        $count = 0;
        foreach ($this->getOccurrences(null, $endDate) as $occurrence) {
            $count++;
        }

        return $count;
    }

    /**
     * Get an exception for a specific date
     *
     * @param Carbon|string $date
     * @return EventException|null
     */
    protected function getExceptionForDate($date): ?EventException
    {
        $dateString = is_string($date) ? $date : $date->format('Y-m-d');

        return EventException::findForEventAndDate($this, $dateString);
    }

    /**
     * Check if an instance exists on a specific date
     *
     * @param Carbon|string $date
     * @return bool
     */
    public function hasOccurrenceOn($date): bool
    {
        $checkDate = is_string($date) ? Carbon::parse($date) : $date;

        // For non-recurring events
        if (!$this->eventRecurs()) {
            $eventStart = Carbon::parse($this->StartDate);
            $eventEnd = $this->EndDate ? Carbon::parse($this->EndDate) : $eventStart;
            return $checkDate->between($eventStart, $eventEnd, true);
        }

        // For recurring events, check if there's an occurrence on this date
        $dayStart = $checkDate->copy()->startOfDay();
        $dayEnd = $checkDate->copy()->endOfDay();

        foreach ($this->getOccurrences($dayStart, $dayEnd, 1) as $occurrence) {
            return true;
        }

        return false;
    }

    /**
     * Create an exception for a specific instance
     *
     * @param string $instanceDate
     * @param string $action 'MODIFIED' or 'DELETED'
     * @param array $overrides
     * @param string $reason
     * @return EventException
     */
    public function createException(
        string $instanceDate,
        string $action,
        array $overrides = [],
        string $reason = ''
    ): EventException {
        // Remove any existing exception for this date
        $existing = EventException::findForEventAndDate($this, $instanceDate);
        if ($existing) {
            $existing->delete();
        }

        if ($action === 'DELETED') {
            return EventException::createDeletion($this, $instanceDate, $reason);
        } else {
            return EventException::createModification($this, $instanceDate, $overrides, $reason);
        }
    }

    /**
     * Remove an exception for a specific instance
     *
     * @param string $instanceDate
     * @return bool
     */
    public function removeException(string $instanceDate): bool
    {
        $exception = EventException::findForEventAndDate($this, $instanceDate);
        if ($exception) {
            $exception->delete();
            return true;
        }

        return false;
    }

    /**
     * Get all exceptions for this event
     *
     * @return \SilverStripe\ORM\DataList
     */
    public function getExceptions()
    {
        return EventException::get()->filter('OriginalEventID', $this->ID);
    }

    /**
     * Check if event uses Carbon-based recursion (vs legacy RRule)
     *
     * @return bool
     */
    public function usesCarbonRecursion(): bool
    {
        return $this->config()->get('recursion_system') === 'carbon';
    }

    /**
     * Get human-readable recurrence description
     *
     * @return string
     */
    public function getRecurrenceDescription(): string
    {
        if (!$this->eventRecurs()) {
            return 'Does not repeat';
        }

        $interval = max(1, (int) $this->Interval);
        $intervalText = $interval === 1 ? '' : " {$interval}";

        $pattern = match ($this->Recursion) {
            'DAILY' => $interval === 1 ? 'Daily' : "Every {$interval} days",
            'WEEKLY' => $interval === 1 ? 'Weekly' : "Every {$interval} weeks",
            'MONTHLY' => $interval === 1 ? 'Monthly' : "Every {$interval} months",
            'YEARLY' => $interval === 1 ? 'Yearly' : "Every {$interval} years",
            default => 'Unknown pattern'
        };

        if ($this->RecursionEndDate) {
            $endDate = Carbon::parse($this->RecursionEndDate)->format('M j, Y');
            $pattern .= " until {$endDate}";
        }

        return $pattern;
    }
}
