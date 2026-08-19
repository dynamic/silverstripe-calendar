<?php

namespace Dynamic\Calendar\Traits;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Model\EventInstance;
use Generator;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

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
     * Get cached event occurrences for better performance
     *
     * @param Carbon|string|null $startDate
     * @param Carbon|string|null $endDate
     * @param int|null $limit
     * @return Generator<EventInstance>
     */
    public function getCachedOccurrences(
        $startDate = null,
        $endDate = null,
        ?int $limit = null,
        ?array $exceptions = null
    ): Generator {
        // The persistent occurrence cache was retired in 2.3.0: once
        // generation was bounded by the requested window and exception
        // lookups were batched (2.2.0), measurement showed a cache hit saved
        // 3-18% while every miss cost roughly DOUBLE raw generation
        // (serialise + write), and keys embedding the exact window plus
        // LastEdited made misses the common case. The JSON response cache
        // already covers exact-repeat requests one layer up.
        yield from $this->getOccurrences($startDate, $endDate, $limit, $exceptions);
    }

    /**
     * Generate event occurrences using Carbon periods
     *
     * @param Carbon|string|null $startDate
     * @param Carbon|string|null $endDate
     * @param int|null $limit
     * @return Generator<EventInstance>
     */
    public function getOccurrences(
        $startDate = null,
        $endDate = null,
        ?int $limit = null,
        ?array $exceptions = null
    ): Generator {
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

        // The try must wrap ITERATION, not just construction: CarbonPeriod's
        // filter() is lazy, so UnreachableException surfaces here, not in
        // createCarbonPeriod().
        try {
            foreach ($period as $date) {
                // Check for exceptions (deleted instances)
                $exception = $this->getExceptionForDate($date, $exceptions);
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
        } catch (\Throwable $e) {
            Injector::inst()->get(LoggerInterface::class)->error(
                "Error iterating occurrences for event {$this->ID}: " . $e->getMessage(),
                ['exception' => $e]
            );
            return;
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

        // ALWAYS respect RecursionEndDate as a hard limit
        // Use the EARLIER of the query end date or the recursion end date
        if ($this->RecursionEndDate) {
            $recursionEnd = Carbon::parse($this->RecursionEndDate);
            if (!$rangeEnd || $recursionEnd->lt($rangeEnd)) {
                $rangeEnd = $recursionEnd;
            }
        }

        // Default to 2 years if no end date
        if (!$rangeEnd) {
            $rangeEnd = $rangeStart->copy()->addYears(2);
        }

        try {
            $interval = max(1, (int) $this->Interval);

            // Start DAILY/WEEKLY periods at the first on-lattice occurrence at or
            // after $rangeStart instead of walking from the event's start date.
            // Without this the cost is O(event age), and CarbonPeriod's lazy
            // filter() throws UnreachableException after 1000 consecutive
            // rejections - a daily event ~3 years old 500s the feed.
            //
            // MONTHLY/YEARLY intentionally still iterate from $eventStart:
            // CarbonPeriod adds the interval to the CURRENT date, so month-end
            // dates drift (Jan 31 -> Mar 3 -> Apr 3...). Snapping would change
            // which days those events fall on. They step at most ~12x per year
            // of event age and cannot hit the 1000-rejection limit.
            $period = match ($this->Recursion) {
                'DAILY' => $this->createDailyPeriod(
                    $this->snapToLattice($eventStart, $rangeStart, $interval),
                    $rangeEnd
                ),
                'WEEKLY' => $this->createWeeklyPeriod(
                    $this->snapToLattice($eventStart, $rangeStart, 7 * $interval),
                    $rangeEnd
                ),
                'MONTHLY' => $this->createMonthlyPeriod($eventStart, $rangeEnd),
                'YEARLY' => $this->createYearlyPeriod($eventStart, $rangeEnd),
                default => null
            };

            if (!$period) {
                return null;
            }

            // Filter period to only include dates within our range. After the
            // snap this rejects at most one candidate for DAILY/WEEKLY; it
            // remains the range guard for the un-snapped MONTHLY/YEARLY.
            return $period->filter(function (Carbon $date) use ($rangeStart, $rangeEnd) {
                return $date->between($rangeStart, $rangeEnd, true);
            });
        } catch (\Exception $e) {
            // Log error and return null to prevent crashes
            $logger = \SilverStripe\Core\Injector\Injector::inst()->get(\Psr\Log\LoggerInterface::class);
            $logger->error("Error creating Carbon period for event {$this->ID}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * First date on the event's recurrence lattice at or after $rangeStart.
     *
     * Only valid for fixed-day-step patterns (DAILY, WEEKLY): occurrence n is
     * exactly eventStart + n * stepDays, so the snap is pure arithmetic.
     *
     * @param Carbon $eventStart
     * @param Carbon $rangeStart
     * @param int $stepDays
     * @return Carbon
     */
    protected function snapToLattice(Carbon $eventStart, Carbon $rangeStart, int $stepDays): Carbon
    {
        $from = $eventStart->copy()->startOfDay();
        $to = $rangeStart->copy()->startOfDay();

        // Carbon 3 diffInDays() is signed; positive when $to is after $from.
        $dayDelta = (int) round($from->diffInDays($to));

        if ($dayDelta <= 0) {
            return $eventStart->copy();
        }

        $steps = (int) ceil($dayDelta / $stepDays);

        return $eventStart->copy()->addDays($steps * $stepDays);
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
        return EventInstance::create($this, $date, $exception);
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
     * @var array<string,EventException>|null Per-request memo of this event's exceptions
     */
    protected ?array $exceptionMapCache = null;

    /**
     * All exceptions for this event, keyed by InstanceDate. One query, memoised.
     *
     * @return array<string,EventException>
     */
    protected function exceptionMap(): array
    {
        if ($this->exceptionMapCache === null) {
            $this->exceptionMapCache = [];
            foreach ($this->getExceptions() as $exception) {
                $this->exceptionMapCache[(string) $exception->InstanceDate] = $exception;
            }
        }

        return $this->exceptionMapCache;
    }

    /**
     * Drop the exception memo (call after any exception write/delete).
     */
    public function clearExceptionMap(): void
    {
        $this->exceptionMapCache = null;
    }

    /**
     * Get an exception for a specific date
     *
     * Previously this ran one SQL query per generated occurrence DATE - the
     * dominant query cost of expanding a recurring event. It now consults a
     * caller-supplied map, or the per-event memo.
     *
     * @param Carbon|string $date
     * @param array<string,EventException>|null $exceptions Prefetched map keyed by Y-m-d
     * @return EventException|null
     */
    protected function getExceptionForDate($date, ?array $exceptions = null): ?EventException
    {
        $dateString = is_string($date) ? $date : $date->format('Y-m-d');

        if ($exceptions === null) {
            $exceptions = $this->exceptionMap();
        }

        return $exceptions[$dateString] ?? null;
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

        $this->clearExceptionMap();

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
            $this->clearExceptionMap();
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
     * Check if event uses Carbon-based recursion
     * Always returns true since we've removed the legacy RRule system
     *
     * @return bool
     */
    public function usesCarbonRecursion(): bool
    {
        return true;
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
