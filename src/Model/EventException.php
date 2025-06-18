<?php

namespace Dynamic\Calendar\Model;

use Dynamic\Calendar\Page\EventPage;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Event Exception
 *
 * Represents modifications or deletions to specific instances of a recurring event.
 * This allows individual occurrences to be customized without affecting the entire series.
 *
 * @package Dynamic\Calendar\Model
 *
 * @property string $InstanceDate
 * @property string $Action
 * @property string $ModifiedTitle
 * @property string $ModifiedContent
 * @property string $ModifiedStartTime
 * @property string $ModifiedEndTime
 * @property string $ModifiedStartDate
 * @property string $ModifiedEndDate
 * @property bool $ModifiedAllDay
 * @property string $Reason
 *
 * @method EventPage OriginalEvent()
 */
class EventException extends DataObject implements PermissionProvider
{
    /**
     * @var string
     */
    private static string $table_name = 'EventException';

    /**
     * @var string
     */
    private static string $singular_name = 'Event Exception';

    /**
     * @var string
     */
    private static string $plural_name = 'Event Exceptions';

    /**
     * @var array
     */
    private static array $db = [
        'InstanceDate' => 'Date',
        'Action' => 'Enum("MODIFIED,DELETED","MODIFIED")',
        'ModifiedTitle' => 'Varchar(255)',
        'ModifiedContent' => 'HTMLText',
        'ModifiedStartTime' => 'Time',
        'ModifiedEndTime' => 'Time',
        'ModifiedStartDate' => 'Date',
        'ModifiedEndDate' => 'Date',
        'ModifiedAllDay' => 'Boolean',
        'Reason' => 'Text',
    ];

    /**
     * @var array
     */
    private static array $has_one = [
        'OriginalEvent' => EventPage::class,
    ];

    /**
     * @var array
     */
    private static array $indexes = [
        'EventDate' => [
            'type' => 'index',
            'columns' => ['OriginalEventID', 'InstanceDate'],
        ],
    ];

    /**
     * @var array
     */
    private static array $summary_fields = [
        'OriginalEvent.Title' => 'Event',
        'InstanceDate' => 'Instance Date',
        'Action' => 'Action',
        'ModifiedTitle' => 'Modified Title',
    ];

    /**
     * @var array
     */
    private static array $searchable_fields = [
        'OriginalEvent.Title',
        'InstanceDate',
        'Action',
        'ModifiedTitle',
    ];

    /**
     * @var string
     */
    private static string $default_sort = 'InstanceDate ASC';

    /**
     * Mapping of fields that can be overridden
     *
     * @var array
     */
    private static array $overridable_fields = [
        'Title' => 'ModifiedTitle',
        'Content' => 'ModifiedContent',
        'StartTime' => 'ModifiedStartTime',
        'EndTime' => 'ModifiedEndTime',
        'StartDate' => 'ModifiedStartDate',
        'EndDate' => 'ModifiedEndDate',
        'AllDay' => 'ModifiedAllDay',
    ];

    /**
     * Check if this exception has an override for a specific property
     *
     * @param string $property
     * @return bool
     */
    public function hasOverride(string $property): bool
    {
        $overridableFields = $this->config()->get('overridable_fields');

        if (!isset($overridableFields[$property])) {
            return false;
        }

        $overrideField = $overridableFields[$property];

        // Check if the override field has a value
        return !empty($this->$overrideField);
    }

    /**
     * Get the override value for a specific property
     *
     * @param string $property
     * @return mixed|null
     */
    public function getOverride(string $property)
    {
        if (!$this->hasOverride($property)) {
            return null;
        }

        $overridableFields = $this->config()->get('overridable_fields');
        $overrideField = $overridableFields[$property];

        return $this->$overrideField;
    }

    /**
     * Set an override value for a specific property
     *
     * @param string $property
     * @param mixed $value
     * @return $this
     */
    public function setOverride(string $property, $value): self
    {
        $overridableFields = $this->config()->get('overridable_fields');

        if (!isset($overridableFields[$property])) {
            throw new \InvalidArgumentException("Property '{$property}' cannot be overridden");
        }

        $overrideField = $overridableFields[$property];
        $this->$overrideField = $value;

        return $this;
    }

    /**
     * Get all override values as an array
     *
     * @return array
     */
    public function getOverrides(): array
    {
        $overrides = [];
        $overridableFields = $this->config()->get('overridable_fields');

        foreach ($overridableFields as $property => $overrideField) {
            if ($this->hasOverride($property)) {
                $overrides[$property] = $this->getOverride($property);
            }
        }

        return $overrides;
    }

    /**
     * Check if this exception represents a deleted instance
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->Action === 'DELETED';
    }

    /**
     * Check if this exception represents a modified instance
     *
     * @return bool
     */
    public function isModified(): bool
    {
        return $this->Action === 'MODIFIED';
    }

    /**
     * Get a human-readable description of this exception
     *
     * @return string
     */
    public function getDescription(): string
    {
        if ($this->isDeleted()) {
            return "Deleted occurrence on {$this->InstanceDate}";
        }

        if ($this->isModified()) {
            $changes = [];

            if ($this->hasOverride('Title')) {
                $changes[] = 'title';
            }
            if ($this->hasOverride('StartTime') || $this->hasOverride('EndTime')) {
                $changes[] = 'time';
            }
            if ($this->hasOverride('StartDate') || $this->hasOverride('EndDate')) {
                $changes[] = 'date';
            }
            if ($this->hasOverride('Content')) {
                $changes[] = 'content';
            }

            $changesText = !empty($changes) ? ' (' . implode(', ', $changes) . ')' : '';

            return "Modified occurrence on {$this->InstanceDate}{$changesText}";
        }

        return "Exception on {$this->InstanceDate}";
    }

    /**
     * Validation
     *
     * @return \SilverStripe\ORM\ValidationResult
     */
    public function validate()
    {
        $result = parent::validate();

        if (!$this->InstanceDate) {
            $result->addError('Instance Date is required');
        }

        if (!$this->OriginalEventID) {
            $result->addError('Original Event is required');
        }

        if ($this->Action === 'MODIFIED' && !$this->hasAnyOverrides()) {
            $result->addError('Modified exceptions must have at least one override value');
        }

        return $result;
    }

    /**
     * Check if this exception has any override values
     *
     * @return bool
     */
    protected function hasAnyOverrides(): bool
    {
        $overridableFields = $this->config()->get('overridable_fields');

        foreach ($overridableFields as $property => $overrideField) {
            if (!empty($this->$overrideField)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Permission provider implementation
     *
     * @return array
     */
    public function providePermissions(): array
    {
        return [
            'EDIT_EVENT_EXCEPTIONS' => 'Edit event exceptions',
            'DELETE_EVENT_EXCEPTIONS' => 'Delete event exceptions',
            'CREATE_EVENT_EXCEPTIONS' => 'Create event exceptions',
        ];
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        $originalEvent = $this->OriginalEvent();
        return $originalEvent && $originalEvent->exists() && $originalEvent->canView($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canEdit($member = null): bool
    {
        if (Permission::check('EDIT_EVENT_EXCEPTIONS', 'any', $member)) {
            return true;
        }
        
        $originalEvent = $this->OriginalEvent();
        return $originalEvent && $originalEvent->exists() && $originalEvent->canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canDelete($member = null): bool
    {
        if (Permission::check('DELETE_EVENT_EXCEPTIONS', 'any', $member)) {
            return true;
        }
        
        $originalEvent = $this->OriginalEvent();
        return $originalEvent && $originalEvent->exists() && $originalEvent->canDelete($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canCreate($member = null, $context = []): bool
    {
        return Permission::check('CREATE_EVENT_EXCEPTIONS', 'any', $member);
    }

    /**
     * Find an exception for a specific event and date
     *
     * @param EventPage $event
     * @param string $instanceDate
     * @return EventException|null
     */
    public static function findForEventAndDate(EventPage $event, string $instanceDate): ?EventException
    {
        return static::get()->filter([
            'OriginalEventID' => $event->ID,
            'InstanceDate' => $instanceDate,
        ])->first();
    }

    /**
     * Create a deletion exception for a specific instance
     *
     * @param EventPage $event
     * @param string $instanceDate
     * @param string $reason
     * @return EventException
     */
    public static function createDeletion(EventPage $event, string $instanceDate, string $reason = ''): EventException
    {
        $exception = static::create([
            'OriginalEventID' => $event->ID,
            'InstanceDate' => $instanceDate,
            'Action' => 'DELETED',
            'Reason' => $reason,
        ]);

        $exception->write();

        return $exception;
    }

    /**
     * Create a modification exception for a specific instance
     *
     * @param EventPage $event
     * @param string $instanceDate
     * @param array $overrides
     * @param string $reason
     * @return EventException
     */
    public static function createModification(
        EventPage $event,
        string $instanceDate,
        array $overrides,
        string $reason = ''
    ): EventException {
        $exception = static::create([
            'OriginalEventID' => $event->ID,
            'InstanceDate' => $instanceDate,
            'Action' => 'MODIFIED',
            'Reason' => $reason,
        ]);

        foreach ($overrides as $property => $value) {
            $exception->setOverride($property, $value);
        }

        $exception->write();

        return $exception;
    }
}
