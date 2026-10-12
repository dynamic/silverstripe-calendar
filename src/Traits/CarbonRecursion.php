<?php

namespace Dynamic\Calendar\Traits;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Carbon\Exceptions\UnreachableException;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Model\EventInstance;
use Dynamic\Calendar\Model\EventInstanceCache;
use Generator;

/**
 * Carbon Recursion Trait
 *
 * Provides Carbon-based recursion functionality for EventPage.
 * This replaces the RRule-based system with a more efficient and flexible approach.
 *
 * Composes LoggerFallback rather than just requiring callers to add it separately -
 * PHP allows a trait to use another trait, so any class using CarbonRecursion gets
 * logWithFallback() for free instead of needing to know it must add LoggerFallback too.
 *
 * @package Dynamic\Calendar\Traits
 */
trait CarbonRecursion
{
    use LoggerFallback;

    /**
     * @var array Cache for occurrence calculations
     */
    protected array $occurrenceCache = [];

    /**
     * Whether the last expansion was cut short by an UnreachableException, or by a period
     * that could not be built at all.
     *
     * getCachedOccurrences() must not write a truncated set: an interrupted expansion
     * yields fewer instances than the window contains, and a cache write would then be
     * served as a valid HIT for the whole TTL - the same class of bug as a failed encode
     * being cached and read back as an empty HIT (#226). Reset at the top of every
     * expansion so a later complete run clears it.
     *
     * @var bool
     */
    protected bool $expansionInterrupted = false;

    /**
     * Get cached event occurrences for better performance
     *
     * Unlike the other occurrence readers in this trait, the lower bound here is an
     * OVERLAP bound: an occurrence that started before $startDate and has not ended by it
     * is included, because it is still running inside the window
     * (dynamic/silverstripe-calendar#312). This is the feed path - the method's only
     * caller is Calendar::getEventsFeed(), whose #267 pass keeps an occurrence whose END
     * overlaps the window start and must actually be handed one. Callers that want
     * start-date semantics use getOccurrences() instead. The cache key stays the caller's
     * $start/$end; only generation widens.
     *
     * @param Carbon|string|null $startDate
     * @param Carbon|string|null $endDate
     * @param int|null $limit
     * @return Generator<EventInstance>
     */
    public function getCachedOccurrences($startDate = null, $endDate = null, ?int $limit = null): Generator
    {
        $start = $startDate ? Carbon::parse($startDate)->format('Y-m-d') : Carbon::now()->subMonth()->format('Y-m-d');
        $end = $endDate ? Carbon::parse($endDate)->format('Y-m-d') : Carbon::now()->addYear()->format('Y-m-d');

        // Try to get from cache first
        $cached = EventInstanceCache::getCachedInstances($this, $start, $end);

        if ($cached !== null) {
            $count = 0;
            foreach ($cached as $instanceData) {
                yield EventInstance::fromArray($instanceData);

                if ($limit && ++$count >= $limit) {
                    break;
                }
            }
            return;
        }

        // Generate fresh occurrences and cache them
        $instances = [];
        $count = 0;

        // $includeRunning is opted in HERE and only here: this method's single caller is
        // Calendar::getEventsFeed(), whose #267 pass already keeps an occurrence whose END
        // overlaps the window start - it was simply never handed one by the generator
        // (dynamic/silverstripe-calendar#312). Every other public API below keeps the
        // start-date semantics its callers rely on.
        foreach ($this->getOccurrences($startDate, $endDate, $limit, true) as $instance) {
            $instances[] = $instance->toArray();
            yield $instance;

            if ($limit && ++$count >= $limit) {
                break;
            }
        }

        // Cache the results for future requests - but only when the expansion ran to
        // completion. An interrupted one is incomplete data, not a result set.
        if (!$this->expansionInterrupted) {
            EventInstanceCache::setCachedInstances($this, $start, $end, $instances);
        }
    }

    /**
     * Generate event occurrences using Carbon periods
     *
     * The lower bound is a start-date bound by default: an occurrence that began before
     * $startDate is not returned even when it is still running inside the window.
     * $includeRunning opts a single caller into overlap semantics for the lower bound -
     * see getCachedOccurrences(), which is the only place that passes true.
     *
     * @param Carbon|string|null $startDate
     * @param Carbon|string|null $endDate
     * @param int|null $limit
     * @param bool $includeRunning Widen generation to occurrences already running at $startDate
     * @return Generator<EventInstance>
     */
    public function getOccurrences(
        $startDate = null,
        $endDate = null,
        ?int $limit = null,
        bool $includeRunning = false
    ): Generator {
        // Reset before anything can set it, so a false value once this generator is done
        // means the expansion covered the whole window. It sits at the very top because
        // createCarbonPeriod() can also fail, and that path must not be judged by an older
        // run's flag.
        $this->expansionInterrupted = false;

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

        $period = $this->createCarbonPeriod($startDate, $endDate, $includeRunning);

        if (!$period) {
            return;
        }

        // Refresh the exception memo once per expansion (one query) rather than once per
        // generated date, so an exception written between two expansions is still picked
        // up without paying a query per occurrence.
        $exceptions = $this->exceptionMap(true);

        // The try must wrap ITERATION, not just construction: CarbonPeriod's filter() is
        // lazy, so UnreachableException surfaces here, not in createCarbonPeriod().
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
        } catch (UnreachableException $e) {
            $this->expansionInterrupted = true;
            // Route through LoggerFallback rather than resolving the logger directly: an
            // unguarded lookup/write here would let a broken logger service turn this
            // "don't crash the feed" catch into a new, uncaught throwable on the render
            // path - exactly the failure this catch exists to stop (#182).
            $this->logWithFallback(
                "Error iterating occurrences for event {$this->ID}: " . $e->getMessage(),
                \Psr\Log\LogLevel::ERROR
            );
            return;
        }
    }

    /**
     * Create a Carbon period based on the event's recursion settings
     *
     * @param Carbon|string|null $startDate
     * @param Carbon|string|null $endDate
     * @param bool $includeRunning Also generate occurrences that started before $rangeStart
     * @return CarbonPeriod|null
     */
    protected function createCarbonPeriod(
        $startDate = null,
        $endDate = null,
        bool $includeRunning = false
    ): ?CarbonPeriod {
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

        // dynamic/silverstripe-calendar#312: a multi-day occurrence that started before
        // $rangeStart but has not ended by it is still running inside the window. When -
        // and only when - the caller asked for overlap semantics, open generation
        // durationDays earlier so the lattice reaches that occurrence; the filter below
        // then keeps it. Occurrences are at most occurrenceDurationDays() long, so the
        // earliest one that can still be running at $rangeStart starts exactly there, and
        // no occurrence that has already ended is included (the earliest generated start,
        // $generationStart, ends on $rangeStart). Callers that leave $includeRunning false
        // - every other public method in this trait - get the un-widened window and the
        // un-widened filter, so their start-date semantics are unchanged and
        // getNextOccurrence() needs no compensating skip.
        $generationStart = $rangeStart->copy();
        if ($includeRunning === true) {
            $generationStart->subDays($this->occurrenceDurationDays());
        }

        try {
            $interval = max(1, (int) $this->Interval);

            // Start DAILY/WEEKLY periods at the first on-lattice occurrence at or after
            // $rangeStart instead of walking from the event's start date. Without this the
            // cost is O(event age), and CarbonPeriod's lazy filter() throws
            // UnreachableException after 1000 consecutive rejections - a daily event ~3
            // years old 500ed the events feed for a correctly parsed Y-m-d window.
            //
            // MONTHLY/YEARLY intentionally still iterate from $eventStart: CarbonPeriod
            // adds the interval to the CURRENT date, so month-end dates drift
            // (Jan 31 -> Mar 3 -> Apr 3...). Snapping would change which days those events
            // fall on. They step at most ~12x per year of event age and cannot reach the
            // 1000-rejection limit.
            $period = match ($this->Recursion) {
                'DAILY' => $this->createDailyPeriod(
                    $this->snapToLattice($eventStart, $generationStart, $interval),
                    $rangeEnd
                ),
                'WEEKLY' => $this->createWeeklyPeriod(
                    $this->snapToLattice($eventStart, $generationStart, 7 * $interval),
                    $rangeEnd
                ),
                'MONTHLY' => $this->createMonthlyPeriod($eventStart, $rangeEnd),
                'YEARLY' => $this->createYearlyPeriod($eventStart, $rangeEnd),
                default => null
            };

            if (!$period) {
                return null;
            }

            // Filter period to only include dates within our range. After the snap this
            // rejects at most one candidate for DAILY/WEEKLY; it remains the range guard
            // for the un-snapped MONTHLY/YEARLY.
            // The lower bound is $generationStart rather than $rangeStart so the occurrence
            // the window was widened to reach actually survives the filter; when
            // $includeRunning is false the two hold the same date (one is a copy of the
            // other) and this is the original start-date filter.
            return $period->filter(function (Carbon $date) use ($generationStart, $rangeEnd) {
                return $date->between($generationStart, $rangeEnd, true);
            });
        } catch (\Throwable $e) {
            // A failed period build yields no instances at all. Mark the expansion as
            // interrupted so getCachedOccurrences() does not cache that emptiness as a
            // result set; the 'does not recur' and unknown-pattern nulls above are genuine
            // answers and stay cacheable.
            $this->expansionInterrupted = true;
            // Route through LoggerFallback rather than resolving the logger directly: an
            // unguarded lookup/write here would let a broken logger service turn this
            // "prevent crashes" catch into a new, uncaught throwable on the render path -
            // exactly the failure this catch exists to stop (dynamic/silverstripe-calendar#182).
            $this->logWithFallback(
                "Error creating Carbon period for event {$this->ID}: " . $e->getMessage(),
                \Psr\Log\LogLevel::ERROR
            );
            return null;
        }
    }

    /**
     * How many whole days after its StartDate one occurrence of this event ends.
     *
     * Used by createCarbonPeriod() to widen the generation window for the events feed
     * only (dynamic/silverstripe-calendar#312). Deliberately not a copy of
     * EventInstance::calculateVirtualProperties()'s raw diffInDays: any unusable value -
     * empty or unparseable EndDate, or one that precedes StartDate - yields 0 here, which
     * leaves the generation window as the plain query window instead of silently shifting
     * it by a garbage span.
     *
     * @return int Whole days between StartDate and EndDate, 0 when unusable or negative.
     */
    protected function occurrenceDurationDays(): int
    {
        if (!$this->EndDate) {
            return 0;
        }

        try {
            $eventStart = Carbon::parse($this->StartDate);
            $eventEnd = Carbon::parse($this->EndDate);
        } catch (\Throwable $e) {
            return 0;
        }

        if (!$eventStart || !$eventEnd) {
            return 0;
        }

        // Carbon 3 diffInDays() is signed: positive when the argument is later, negative
        // when it is earlier. Taking max(0, ...) on the SIGNED value is what keeps an
        // inverted EndDate from widening the window - abs() would widen it by the same
        // amount in the wrong direction.
        $durationDays = (int) round($eventStart->diffInDays($eventEnd));

        return max(0, $durationDays);
    }

    /**
     * First date on the event's recurrence lattice at or after $rangeStart.
     *
     * Only valid for fixed-day-step patterns (DAILY, WEEKLY): occurrence n is exactly
     * eventStart + n * stepDays, so the snap is pure arithmetic.
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

        if ($dayDelta <= 0 || $stepDays < 1) {
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
     * @var array<string,EventException>|null Per-object memo of this event's exceptions, keyed by InstanceDate
     */
    protected ?array $exceptionMapCache = null;

    /**
     * All exceptions for this event, keyed by InstanceDate.
     *
     * One query for the whole set, memoised on the object. Previously every generated
     * occurrence date cost its own SELECT, which made expanding an old recurring event
     * the dominant query cost of the events feed.
     *
     * @param bool $refresh Discard the memo and query again. getOccurrences() uses this so
     *                      each expansion sees exceptions written since the last one.
     * @return array<string,EventException>
     */
    protected function exceptionMap(bool $refresh = false): array
    {
        if ($refresh || $this->exceptionMapCache === null) {
            $this->exceptionMapCache = [];
            // Order by ID and keep the first hit so duplicate rows for the same date
            // resolve to the lowest ID, matching the findForEventAndDate()->first() this
            // replaced. There is no unique index on (OriginalEventID, InstanceDate).
            foreach ($this->getExceptions()->sort('ID', 'ASC') as $exception) {
                $instanceDate = (string) $exception->InstanceDate;
                if (!isset($this->exceptionMapCache[$instanceDate])) {
                    $this->exceptionMapCache[$instanceDate] = $exception;
                }
            }
        }

        return $this->exceptionMapCache;
    }

    /**
     * Drop the exception memo (call after any exception write/delete).
     *
     * @return void
     */
    public function clearExceptionMap(): void
    {
        $this->exceptionMapCache = null;
    }

    /**
     * Get an exception for a specific date
     *
     * Consults the caller-supplied map when given one, otherwise the per-event memo -
     * replacing the one SQL query per generated occurrence DATE that used to happen here.
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
