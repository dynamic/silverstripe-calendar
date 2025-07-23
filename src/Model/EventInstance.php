<?php

namespace Dynamic\Calendar\Model;

use Carbon\Carbon;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ViewableData;

/**
 * Virtual Event Instance
 *
 * Represents a single occurrence of a recurring event without creating a database record.
 * This provides better performance and flexibility compared to storing each occurrence.
 *
 * @package Dynamic\Calendar\Model
 */
class EventInstance extends ViewableData
{
    use Injectable;

    /**
     * @var EventPage The original recurring event
     */
    protected EventPage $originalEvent;

    /**
     * @var Carbon The date/time of this specific instance
     */
    protected Carbon $instanceDate;

    /**
     * @var EventException|null Any exceptions/modifications for this instance
     */
    protected ?EventException $exception = null;

    /**
     * @var array Cached virtual properties
     */
    protected array $virtualProperties = [];

    /**
     * EventInstance constructor.
     *
     * @param EventPage $originalEvent
     * @param Carbon $instanceDate
     * @param EventException|null $exception
     */
    public function __construct(EventPage $originalEvent, Carbon $instanceDate, ?EventException $exception = null)
    {
        parent::__construct();

        $this->originalEvent = $originalEvent;
        $this->instanceDate = $instanceDate;
        $this->exception = $exception;

        $this->calculateVirtualProperties();
    }

    /**
     * Calculate virtual properties based on the instance date and original event
     */
    protected function calculateVirtualProperties(): void
    {
        $originalStart = Carbon::parse($this->originalEvent->StartDate);
        $daysDifference = $originalStart->diffInDays($this->instanceDate);

        // Calculate start date/time for this instance
        $this->virtualProperties['StartDate'] = $this->instanceDate->format('Y-m-d');
        $this->virtualProperties['StartTime'] = $this->originalEvent->StartTime;

        // Calculate end date/time
        if ($this->originalEvent->EndDate) {
            $originalEnd = Carbon::parse($this->originalEvent->EndDate);
            $durationDays = $originalStart->diffInDays($originalEnd);
            $this->virtualProperties['EndDate'] = $this->instanceDate->copy()->addDays($durationDays)->format('Y-m-d');
        } else {
            $this->virtualProperties['EndDate'] = $this->virtualProperties['StartDate'];
        }

        $this->virtualProperties['EndTime'] = $this->originalEvent->EndTime;

        // Generate a virtual ID for consistency
        $this->virtualProperties['ID'] = 'virtual_' . $this->originalEvent->ID . '_' .
            $this->instanceDate->format('Y-m-d');

        // Generate virtual URL segment
        $this->virtualProperties['URLSegment'] = $this->originalEvent->URLSegment . '-' .
            $this->instanceDate->format('Y-m-d');
    }

    /**
     * Magic getter to provide seamless access to original event properties or virtual overrides
     *
     * @param mixed $property
     * @return mixed
     */
    public function __get($property)
    {
        // Check if there's an exception override for this property
        if ($this->exception && $this->exception->hasOverride($property)) {
            return $this->exception->getOverride($property);
        }

        // Check for virtual properties
        if (isset($this->virtualProperties[$property])) {
            $value = $this->virtualProperties[$property];

            // Convert date/time strings to proper DBField objects for template formatting
            if ($property === 'StartDate' || $property === 'EndDate') {
                return DBField::create_field('Date', $value);
            }
            if ($property === 'StartTime' || $property === 'EndTime') {
                return DBField::create_field('Time', $value);
            }

            return $value;
        }

        // Fall back to original event property
        if ($this->originalEvent->hasField($property)) {
            return $this->originalEvent->$property;
        }

        return parent::__get($property);
    }

    /**
     * Check if property exists (virtual, exception, or original)
     *
     * @param string $property
     * @return bool
     */
    public function hasField($property)
    {
        return isset($this->virtualProperties[$property]) ||
               ($this->exception && $this->exception->hasOverride($property)) ||
               $this->originalEvent->hasField($property);
    }

    /**
     * Get the original recurring event
     *
     * @return EventPage
     */
    public function getOriginalEvent(): EventPage
    {
        return $this->originalEvent;
    }

    /**
     * Get the instance date
     *
     * @return Carbon
     */
    public function getInstanceDate(): Carbon
    {
        return $this->instanceDate;
    }

    /**
     * Get any exception for this instance
     *
     * @return EventException|null
     */
    public function getException(): ?EventException
    {
        return $this->exception;
    }

    /**
     * Check if this instance has been modified
     *
     * @return bool
     */
    public function isModified(): bool
    {
        return $this->exception && $this->exception->Action === 'MODIFIED';
    }

    /**
     * Check if this instance has been deleted
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->exception && $this->exception->Action === 'DELETED';
    }

    /**
     * Check if this is a virtual instance (not stored in database)
     *
     * @return bool
     */
    public function isVirtual(): bool
    {
        return true;
    }

    /**
     * Get the start datetime as Carbon instance
     *
     * @return Carbon
     */
    public function getStartDateTime(): Carbon
    {
        $date = Carbon::parse($this->StartDate);

        if ($this->StartTime) {
            $time = Carbon::parse($this->StartTime);
            $date->hour($time->hour)->minute($time->minute)->second($time->second);
        }

        return $date;
    }

    /**
     * Get the end datetime as Carbon instance
     *
     * @return Carbon
     */
    public function getEndDateTime(): Carbon
    {
        $date = Carbon::parse($this->EndDate);

        if ($this->EndTime) {
            $time = Carbon::parse($this->EndTime);
            $date->hour($time->hour)->minute($time->minute)->second($time->second);
        }

        return $date;
    }

    /**
     * Get formatted date for display
     *
     * @return string
     */
    public function getGridFieldDate(): string
    {
        $date = DBField::create_field('Date', $this->StartDate);
        return "{$date->ShortMonth()} {$date->DayOfMonth(true)}, {$date->Year()}";
    }

    /**
     * Get formatted time for display
     *
     * @return string
     */
    public function getGridFieldTime(): string
    {
        if (!$this->StartTime) {
            return 'All Day';
        }

        $time = DBField::create_field('Time', $this->StartTime);
        return $time->Nice();
    }

    /**
     * Check if this instance occurs on a specific date
     *
     * @param Carbon|string $date
     * @return bool
     */
    public function occursOn($date): bool
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $startDate = Carbon::parse($this->StartDate);
        $endDate = Carbon::parse($this->EndDate);

        return $date->between($startDate, $endDate, true);
    }

    /**
     * Get the link for this instance
     *
     * @param string|null $action
     * @return string
     */
    public function Link($action = null): string
    {
        $link = $this->originalEvent->Link($action);

        // Add instance date parameter to distinguish this occurrence
        $separator = strpos($link, '?') !== false ? '&' : '?';
        $link .= $separator . 'instance=' . $this->instanceDate->format('Y-m-d');

        return $link;
    }

    /**
     * Get the absolute link
     *
     * @param string|null $action
     * @return string
     */
    public function AbsoluteLink($action = null): string
    {
        return $this->originalEvent->AbsoluteLink($this->Link($action));
    }

    /**
     * Convert to array for JSON serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'ID' => $this->ID,
            'Title' => $this->Title,
            'StartDate' => $this->StartDate,
            'StartTime' => $this->StartTime,
            'EndDate' => $this->EndDate,
            'EndTime' => $this->EndTime,
            'AllDay' => $this->AllDay,
            'URLSegment' => $this->URLSegment,
            'Link' => $this->Link(),
            'IsVirtual' => true,
            'IsModified' => $this->isModified(),
            'IsDeleted' => $this->isDeleted(),
            'OriginalEventID' => $this->originalEvent->ID,
        ];
    }

    /**
     * Create EventInstance from array data (for cache deserialization)
     *
     * @param array $data
     * @return EventInstance|null
     */
    public static function fromArray(array $data): ?EventInstance
    {
        if (!isset($data['OriginalEventID']) || !isset($data['StartDate'])) {
            return null;
        }

        // Get the original event
        $originalEvent = EventPage::get()->byID($data['OriginalEventID']);
        if (!$originalEvent) {
            return null;
        }

        // Parse the instance date
        $instanceDate = Carbon::parse($data['StartDate']);

        // Get any exception for this date (if modified/deleted)
        $exception = null;
        if ($data['IsModified'] || $data['IsDeleted']) {
            $exception = EventException::get()->filter([
                'OriginalEventID' => $originalEvent->ID,
                'ExceptionDate' => $data['StartDate']
            ])->first();
        }

        return new static($originalEvent, $instanceDate, $exception);
    }
}
