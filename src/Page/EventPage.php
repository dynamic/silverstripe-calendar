<?php

namespace Dynamic\Calendar\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\EventPageController;
use Dynamic\Calendar\Extension\CalendarCacheInvalidation;
use Dynamic\Calendar\Form\CalendarTimeField;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Traits\CarbonRecursion;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Lumberjack\Model\Lumberjack;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBTime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Versioned\Versioned;

/**
 * Class EventPage
 * @package Dynamic\Calendar\Page
 *
 * @property DBDate $StartDate
 * @property DBTime $StartTime
 * @property DBDate $EndDate
 * @property DBTime $EndTime
 * @property bool $AllDay
 * @property string $Recursion
 * @property int $Interval
 * @property string $EventType
 * @property DBDate $RecursionEndDate
 * @method ManyManyList Categories()
 */
class EventPage extends \Page
{
    use CarbonRecursion;
    use CalendarCacheInvalidation;

    /**
     * Recurring pattern options for event frequency
     * @var array
     */
    private const CARBON_PATTERNS = [
        'DAILY' => 'Day(s)',
        'WEEKLY' => 'Week(s)',
        'MONTHLY' => 'Month(s)',
        'YEARLY' => 'Year(s)',
    ];

    /**
     * @var string
     */
    private static string $singular_name = 'Event';

    /**
     * @var string
     */
    private static string $plural_name = 'Events';

    /**
     * @var array
     */
    private static array $allowed_children = [];

    /**
     * @var bool
     */
    private static bool $show_in_sitetree = false;

    /**
     * @var string
     */
    private static string $icon_class = 'font-icon-p-event';

    /**
     *
     */
    private static bool $can_be_root = false;

    /**
     * Recursion is currently experimental.
     *
     * @var bool
     */
    private static bool $recursion = false;

    /**
     * @var array
     */
    private static array $db = [
        'StartDatetime' => 'DBDatetime',
        /** @deprecated */
        'EndDatetime' => 'DBDatetime',
        /** @deprecated */
        'StartDate' => 'Date',
        'EndDate' => 'Date',
        'StartTime' => 'Time',
        'EndTime' => 'Time',
        'AllDay' => 'Boolean',
        'Recursion' => 'Enum(array("NONE","DAILY","WEEKLY","MONTHLY","YEARLY"), "NONE")',
        'Interval' => 'Int',
        'RecursionEndDate' => 'Date',
        'EventType' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static array $defaults = [
        'Recursion' => 'NONE',
        'ShowInMenus' => false,
    ];

    /**
     * @var array
     */
    private static array $extensions = [
        Lumberjack::class,
    ];

    /**
     * @var array
     */
    private static array $many_many = [
        'Categories' => Category::class,
    ];

    /**
     * @var array
     */
    private static array $many_many_extraFields = [
        'Categories' => [
            'SortOrder' => 'Int',
        ],
    ];

    /**
     * @var array
     */
    private static array $has_many = [
        'EventExceptions' => EventException::class . '.OriginalEvent',
    ];

    /**
     * @var array
     */
    private static array $has_one = [
        'FeaturedImage' => Image::class,
    ];

    /**
     * @var array
     */
    private static array $owns = [
        'FeaturedImage',
    ];

    /**
     * @var string
     */
    private static string $table_name = 'EventPage';

    /**
     * @var string
     */
    private static string $default_sort = 'StartDate';

    /**
     * @var array
     */
    private static array $cascade_duplicates = [
        'Categories',
    ];

    /**
     * @var array
     */
    private static array $cascade_deletes = [
        'Children',
    ];

    /**
     * @var array
     */
    private static array $summary_fields = [
        'Title' => 'Title',
        'CategoriesList' => 'Categories',
        'GridFieldDate' => 'Date',
        'GridFieldTime' => 'Time',
        'HasRecurringEvents' => 'Recurring Events',
    ];

    /**
     * @var array
     */
    private static array $searchable_fields = [
        'Title' => 'PartialMatchFilter',
        'Content' => 'PartialMatchFilter',
        'StartDate' => 'GreaterThanOrEqualFilter',
        'AllDay' => 'ExactMatchFilter',
        'Recursion' => 'ExactMatchFilter',
    ];

    /**
     * @var array
     */
    private static array $recursion_days = [
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
        '7' => 'Sunday',
    ];

    /**
     * @var array
     */
    private static array $recursion_changed = [
        'StartDate',
        'StartTime',
        'EndDate',
        'EndTime',
        'Recursion',
        'Interval',
        'RecursionEndDate',
    ];

    /**
     * @var string|null
     */
    private $categoriesListCache = null;

    /**
     * @return string
     */
    public function getGridFieldDate(): string
    {
        /** @var DBDate $date */
        $date = DBField::create_field(DBDate::class, $this->StartDate);

        return "{$date->ShortMonth()} {$date->DayOfMonth(true)}, {$date->Year()}";
    }

    /**
     * @return false|string
     */
    public function getGridFieldTime()
    {
        // Return "All Day" for all-day events
        if ($this->AllDay) {
            return _t('EventPage.ALL_DAY', 'All Day');
        }

        /** @var DBTime $date */
        $time = DBField::create_field(DBTime::class, $this->StartTime);

        return $time->Nice();
    }

    /**
     * @return mixed|string
     */
    public function getHasRecurringEvents()
    {
        // With Carbon system, check if this event has recurring patterns
        $hasRecurrence = $this->eventRecurs();

        $summary = DBField::create_field(DBBoolean::class, $hasRecurrence)->Nice();

        if ($hasRecurrence) {
            // Count virtual instances in a reasonable timeframe
            $count = min($this->getFullRecursionCount(), 100); // Cap display count
            $summary .= " ({$count})";
        }

        return $summary;
    }

    /**
     * Get the state of this event for GridField display
     *
     * @return string
     */
    public function getEventState(): string
    {
        $today = Carbon::now()->format('Y-m-d');
        $startDate = $this->StartDate;
        $isRecurring = $this->eventRecurs();

        if ($startDate === $today) {
            return $isRecurring ? 'Recurring (Today)' : 'Today';
        } elseif ($startDate > $today) {
            return $isRecurring ? 'Recurring (Future)' : 'Future';
        } else {
            return $isRecurring ? 'Recurring (Past)' : 'Past';
        }
    }

    /**
     * Get comma-separated list of category names for summary fields
     * Uses instance variable caching to prevent repeated database queries
     *
     * @return string
     */
    public function getCategoriesList(): string
    {
        // Use an instance variable to cache the result per object
        if ($this->categoriesListCache !== null) {
            return $this->categoriesListCache;
        }

        $categories = $this->Categories();

        if ($categories->count() === 0) {
            $this->categoriesListCache = '';
            return $this->categoriesListCache;
        }

        // Get titles efficiently
        $titles = $categories->column('Title');

        $this->categoriesListCache = implode(', ', $titles);
        return $this->categoriesListCache;
    }

    /**
     * @return string
     */
    public function getLumberjackTitle()
    {
        return 'Recurring Events';
    }

    /**
     * @return \SilverStripe\ORM\DataList
     */
    public function getLumberjackPagesForGridfield()
    {
        // With Carbon system, we don't have physical RecursiveEvent records
        // Return empty DataList since we use virtual instances
        return EventPage::get()->filter('ID', 0); // Returns empty DataList
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            // SS6 scaffolds custom $db / $has_one / $many_many fields into the form.
            // Remove the scaffolded versions: this closure re-adds the curated fields
            // below, and duplicates make FieldList::dataFields() throw in SS6.
            $fields->removeByName([
                'StartDatetime',
                'EndDatetime',
                'StartDate',
                'EndDate',
                'StartTime',
                'EndTime',
                'AllDay',
                'Recursion',
                'Interval',
                'RecursionEndDate',
                'EventType',
                'Categories',
                'FeaturedImage',
            ]);

            // Add ParentID dropdown for Calendar selection
            $calendars = Calendar::get();
            if ($calendars->exists()) {
                $parentField = DropdownField::create(
                    'ParentID',
                    'Calendar',
                    $calendars->map('ID', 'Title')
                )->setEmptyString('Select a Calendar...')
                 ->setDescription('Choose which Calendar this event belongs to');

                // Make the field required
                $parentField->setAttribute('required', true);

                $fields->addFieldToTab(
                    'Root.Main',
                    $parentField,
                    'Content'
                );
            } else {
                // If no calendars exist, show a helpful message
                $fields->addFieldToTab(
                    'Root.Main',
                    LiteralField::create(
                        'NoCalendarWarning',
                        '<p class="alert alert-warning">' .
                        'No Calendar pages exist. Please create a Calendar page first before adding events.' .
                        '</p>'
                    ),
                    'Content'
                );
            }

            // Add Featured Image field
            $fields->addFieldToTab(
                'Root.Main',
                UploadField::create('FeaturedImage')
                    ->setTitle('Featured Image')
                    ->setDescription('Main image for this event (recommended: 1200x630px)')
                    ->setFolderName('Uploads/Events')
                    ->setAllowedMaxFileNumber(1)
                    ->setAllowedFileCategories('image'),
                'Content'
            );

            $fields->addFieldsToTab(
                'Root.EventSettings',
                [
                    FieldGroup::create(
                        $start = DateField::create('StartDate')
                            ->setTitle('Start Date'),
                        $startTime = CalendarTimeField::create('StartTime')
                            ->setTitle('Start Time')
                    )->setTitle('From'),
                    FieldGroup::create(
                        $end = DateField::create('EndDate')
                            ->setTitle('End Date'),
                        $endTime = CalendarTimeField::create('EndTime')
                            ->setTitle('End Time')
                    )->setTitle('To'),
                    $allDay = DropdownField::create('AllDay')
                        ->setTitle('All Day')
                        ->setSource([false => 'No', true => 'Yes']),
                    $categories = TreeMultiselectField::create('Categories')
                        ->setTitle('Categories')
                        ->setSourceObject(Category::class),
                ]
            );

            $startTime->hideIf('AllDay')->isEqualTo(true)->end();
            $endTime->hideIf('AllDay')->isEqualTo(true)->end();

            if ($this->config()->get('recursion') && !$this->isCopy()) {
                $fields->addFieldsToTab(
                    'Root.Recursion',
                    [
                        FieldGroup::create(
                            $interval = NumericField::create('Interval')
                                ->setTitle(''),
                            $recursion = DropdownField::create('Recursion')
                                ->setSource($this->getPatternSource()),
                            $recursionEndDate = DateField::create('RecursionEndDate')
                                ->setTitle('Ending On')
                        )->setTitle('Repeat every'),
                    ]
                );
            }
        });

        $fields = parent::getCMSFields();

        if (($children = $fields->dataFieldByName('ChildPages')) && $children instanceof GridField) {
            if (
                ($component = $children->getConfig()->getComponentByType(GridFieldPaginator::class))
                && $component instanceof GridFieldPaginator
            ) {
                // Set items per page for paginator
                $component->setItemsPerPage(7);
            }
        }

        if ($this->isCopy()) {
            $fields->removeByName('ChildPages');
            $fields = $fields->makeReadonly();
        }

        if (!$this->config()->get('recursion')) {
            $fields->removeByName('ChildPages');
        }

        // Add EventExceptions GridField for recurring event exceptions
        if ($this->ID && $this->eventRecurs()) {
            $exceptionsConfig = GridFieldConfig_RelationEditor::create();

            $exceptionsGrid = \SilverStripe\Forms\GridField\GridField::create(
                'EventExceptions',
                'Event Exceptions',
                $this->EventExceptions(),
                $exceptionsConfig
            );

            $fields->addFieldToTab('Root.Exceptions', $exceptionsGrid);
        }

        return $fields;
    }

    /**
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->EventType = static::class;

        // Add default 1-hour duration if start time is set but no end time
        if ($this->StartTime && !$this->EndTime) {
            try {
                $startTimeObj = \SilverStripe\ORM\FieldType\DBTime::create_field(
                    'SilverStripe\ORM\FieldType\DBTime',
                    $this->StartTime
                );
                $startTimeDT = $startTimeObj->getValue() ? new \DateTime($startTimeObj->getValue()) : null;
                if ($startTimeDT) {
                    $startTimeDT->add(new \DateInterval('PT1H')); // Add 1 hour
                    $this->EndTime = $startTimeDT->format('H:i:s');
                } else {
                    error_log(
                        "EventPage: Failed to parse StartTime '{$this->StartTime}' as DBTime in onBeforeWrite."
                    );
                }
            } catch (\Exception $e) {
                error_log(
                    "EventPage: Exception parsing StartTime '{$this->StartTime}' in onBeforeWrite: " .
                    $e->getMessage()
                );
            }
        }

        // If no end date is specified, use start date
        if ($this->StartDate && !$this->EndDate) {
            $this->EndDate = $this->StartDate;
        }
    }

    /**
     *
     */
    public function onAfterPublish()
    {
        parent::onAfterPublish();

        // Carbon system uses virtual instances - no need to generate physical events
    }

    /**
     * @return string
     */
    public function getControllerName()
    {
        return EventPageController::class;
    }

    /**
     * @return bool
     */
    public function eventRecurs()
    {
        return $this->config()->get('recursion')
            && $this->ClassName == EventPage::class
            && $this->Recursion != 'NONE';
    }





    /**
     * The total count will include the originating date.
     *
     * @return int
     */
    public function getFullRecursionCount()
    {
        // Use Carbon system to count virtual instances in a reasonable range
        $count = 0;
        $limit = 1000; // Reasonable limit to prevent infinite loops
        foreach ($this->getOccurrences(null, null, $limit) as $instance) {
            $count++;
        }
        return $count;
    }



    /**
     * @return bool
     */
    protected function recursionChanged()
    {
        foreach ($this->yieldSingle($this->config()->get('recursion_changed')) as $field) {
            if ($this->isChanged($field, self::CHANGE_VALUE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canEdit($member = null)
    {
        if ($this->isCopy()) {
            return false;
        }

        return parent::canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canPublish($member = null)
    {
        if ($this->isCopy()) {
            return false;
        }

        return parent::canPublish($member);
    }

    /**
     * @param null $member
     * @return bool|mixed
     */
    public function canUnpublish($member = null)
    {
        if ($this->isCopy()) {
            return false;
        }

        return parent::canUnpublish($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canArchive($member = null)
    {
        if ($this->isCopy()) {
            return false;
        }

        return parent::canArchive($member);
    }

    /**
     * @return bool
     */
    public function isCopy()
    {
        // With Carbon system, we don't have physical RecursiveEvent records
        return false;
    }

    /**
     * Get the pattern source for recurring events dropdown
     * @return array
     */
    public function getPatternSource()
    {
        return array_merge(['NONE' => 'Does not repeat'], self::CARBON_PATTERNS);
    }

    /**
     * @param $list
     * @return \Generator
     */
    private function yieldSingle($list)
    {
        foreach ($list as $item) {
            yield $item;
        }
    }

    /**
     * Create an exception for a specific instance of this recurring event
     *
     * @param string $instanceDate
     * @param string $action Either 'MODIFIED' or 'DELETED'
     * @param array $overrides Override values for modified instances
     * @param string $reason Optional reason for the exception
     * @return EventException
     */
    public function createException(
        string $instanceDate,
        string $action,
        array $overrides = [],
        string $reason = ''
    ): EventException {
        if ($action === 'DELETED') {
            return EventException::createDeletion($this, $instanceDate, $reason);
        } elseif ($action === 'MODIFIED') {
            return EventException::createModification($this, $instanceDate, $overrides, $reason);
        } else {
            throw new \InvalidArgumentException("Invalid action: $action. Must be 'MODIFIED' or 'DELETED'");
        }
    }

    /**
     * Get all child events/instances for this recurring event
     *
     * For the Carbon system, this returns an ArrayList of virtual instances
     *
     * @return \SilverStripe\Model\List\ArrayList
     */
    public function allChildren()
    {
        // Use virtual instances with Carbon system
        if (!$this->eventRecurs()) {
            return \SilverStripe\Model\List\ArrayList::create();
        }

        // Get occurrences within a reasonable timeframe for testing
        $endDate = $this->RecursionEndDate ? Carbon::parse($this->RecursionEndDate) :
            Carbon::parse($this->StartDate)->addMonth();
        $occurrences = $this->getOccurrences($this->StartDate, $endDate);

        $children = \SilverStripe\Model\List\ArrayList::create();
        $originalStartDate = $this->StartDate;

        foreach ($occurrences as $occurrence) {
            // Exclude the original event instance (only return the recurring ones)
            // Convert both to string to ensure proper comparison
            $occurrenceStartDate = (string) $occurrence->StartDate;
            if ($occurrenceStartDate !== $originalStartDate) {
                $children->push($occurrence);
            }
        }

        return $children;
    }

    /**
     * Clear caches when event is modified
     */
    /**
     * Validate that a parent Calendar is selected
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        // Only validate ParentID if we don't have a valid parent already
        if (!$this->ParentID || $this->ParentID == 0) {
            $result->addError('Please select a Calendar for this event.');
        } else {
            // Ensure the selected parent is actually a Calendar
            $parent = Calendar::get()->byID($this->ParentID);
            if (!$parent) {
                $result->addError('Selected parent must be a Calendar page.');
            }
        }

        return $result;
    }

    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        // Clear caches if recursion-related fields changed
        if ($this->recursionChanged()) {
            \Dynamic\Calendar\Model\EventInstanceCache::clearEventCache($this);
        }

        // Clear JSON cache when event is modified
        $this->clearCalendarJSONCache();
    }

    /**
     * Clear caches when event is deleted
     */
    public function onAfterDelete(): void
    {
        parent::onAfterDelete();

        \Dynamic\Calendar\Model\EventInstanceCache::clearEventCache($this);

        // Clear JSON cache when event is deleted
        $this->clearCalendarJSONCache();
    }

    /**
     * Clear caches when a category is added to this event
     * This is triggered when the many-to-many relationship changes
     *
     * @param DataObject $category
     * @param string $relationName
     */
    public function onAfterLink(DataObject $category, string $relationName): void
    {
        // Clear JSON cache when categories are modified
        if ($relationName === 'Categories') {
            $this->clearCalendarJSONCache();
        }
    }

    /**
     * Clear caches when a category is removed from this event
     * This is triggered when the many-to-many relationship changes
     *
     * @param DataObject $category
     * @param string $relationName
     */
    public function onAfterUnlink(DataObject $category, string $relationName): void
    {
        // Clear JSON cache when categories are modified
        if ($relationName === 'Categories') {
            $this->clearCalendarJSONCache();
        }
    }
}
