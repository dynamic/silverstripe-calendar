<?php

namespace Dynamic\Calendar\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\EventPageController;
use Dynamic\Calendar\Factory\RecursiveEventFactory;
use Dynamic\Calendar\Form\CalendarTimeField;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\EventException;
use Dynamic\Calendar\Traits\CarbonRecursion;
use RRule\RRule;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Lumberjack\Model\Lumberjack;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBTime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
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

    /**
     * array
     */
    private const RRULE = [
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
    private static array $allowed_children = [RecursiveEvent::class];

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
     * Recursion system to use: 'rrule' (legacy) or 'carbon' (new)
     *
     * @var string
     */
    private static string $recursion_system = 'carbon';

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
        'GridFieldDate' => 'Date',
        'GridFieldTime' => 'Time',
        'HasRecurringEvents' => 'Recurring Events',
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
        /** @var DBTime $date */
        $time = DBField::create_field(DBTime::class, $this->StartTime);

        return $time->Nice();
    }

    /**
     * @return mixed|string
     */
    public function getHasRecurringEvents()
    {
        $filter = [
            'ParentID' => $this->ID,
        ];

        $instances = RecursiveEvent::get()->filter($filter);
        $existing = $instances->count() > 0;

        $summary = DBField::create_field(DBBoolean::class, $existing)->Nice();

        if ($existing) {
            $summary .= " ({$instances->count()})";
        }

        return $summary;
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
        return RecursiveEvent::get()->filter([
            'ParentID' => $this->ID,
            'StartDate:GreaterThanOrEqual' => Carbon::now()->subDay()->format('Y-m-d 23:59:59'),
        ])->sort('StartDate ASC');
    }

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
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
                        $endTime = CalendarTimeField::create('EndTime')
                            ->setTitle('End Time'),
                        $end = DateField::create('EndDate')
                            ->setTitle('End Date')
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

        return $fields;
    }

    /**
     *
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->EventType = static::class;
    }

    /**
     *
     */
    public function onAfterPublish()
    {
        parent::onAfterPublish();

        // Only generate physical recurring events if using the legacy RRule system
        if ($this->config()->get('recursion_system') === 'rrule') {
            if ($this->eventRecurs()) {
                $this->generateAdditionalEvents();
            }

            $this->cleanRecursions();
        }
        // For Carbon system, we use virtual instances - no need to generate physical events
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
            && !$this instanceof RecursiveEvent
            && $this->Recursion != 'NONE';
    }

    /**
     *
     */
    protected function generateAdditionalEvents()
    {
        $factory = RecursiveEventFactory::create();
        $factory->setEvent($this);
        $skip = $this->getSkipList();

        foreach ($this->yieldSingle($this->getValidDates()) as $date) {
            if ($date != $this->StartDate && !in_array($date, $skip)) {
                $factory->setDate($date);
                $factory->createEvent();
            }
        }
    }

    /**
     * @return array
     */
    protected function getSkipList()
    {
        $skip = RecursiveEvent::get()->filter([
            'ParentID' => $this->ID,
        ]);

        if (count($this->getValidDates())) {
            $skip = $skip->exclude([
                'StartDate' => $this->getValidDates(),
            ]);
        };

        return $skip->column('StartDate');
    }

    /**
     *
     */
    protected function cleanRecursions()
    {
        $clean = RecursiveEvent::get()
            ->filter('ParentID', $this->ID);

        if (count($this->getValidDates())) {
            $clean = $clean->exclude('StartDate', $this->getValidDates());
        }


        /** @var RecursiveEvent $event */
        foreach ($this->yieldSingle($clean) as $event) {
            $event->doArchive();
        }
    }

    /**
     * @return RRule|array
     * @deprecated Use Carbon-based getOccurrences() method instead
     */
    protected function getRecursionSet()
    {
        if (!$this->eventRecurs()) {
            return [];
        }

        // For Carbon system, don't use RRule
        if ($this->config()->get('recursion_system') === 'carbon') {
            return [];
        }

        return new RRule([
            'FREQ' => $this->Recursion,
            'INTERVAL' => $this->Interval,
            'DTSTART' => $this->StartDate,
            'UNTIL' => $this->RecursionEndDate,
        ]);
    }

    /**
     * The total count will include the originating date.
     *
     * @return int
     */
    public function getFullRecursionCount()
    {
        if ($this->config()->get('recursion_system') === 'carbon') {
            // For Carbon system, count virtual instances in a reasonable range
            $count = 0;
            $limit = 1000; // Reasonable limit to prevent infinite loops
            foreach ($this->getOccurrences(null, null, $limit) as $instance) {
                $count++;
            }
            return $count;
        }

        // Legacy RRule system
        return $this->getRecursionSet()->count();
    }

    /**
     * @return array
     * @deprecated Use Carbon-based getOccurrences() method instead
     */
    protected function getValidDates()
    {
        if ($this->config()->get('recursion_system') === 'carbon') {
            // For Carbon system, return empty array since we use virtual instances
            return [];
        }

        // Legacy RRule system
        $dates = [];

        foreach ($this->yieldSingle($this->getRecursionSet()) as $date) {
            if ($date->format('Y-m-d') != $this->StartDate) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
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
        return $this->ParentID > 0 && $this->ClassName == RecursiveEvent::class;
    }

    /**
     * @return mixed
     */
    public function getPatternSource()
    {
        return array_merge(['NONE' => 'Does not repeat'], self::RRULE);
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
     * For the legacy RRule system, this would return actual RecursiveEvent records
     *
     * @return \SilverStripe\ORM\ArrayList|\SilverStripe\ORM\DataList
     */
    public function AllChildren()
    {
        if ($this->config()->get('recursion_system') === 'carbon') {
            // For Carbon system, use virtual instances
            if (!$this->eventRecurs()) {
                return \SilverStripe\ORM\ArrayList::create();
            }

            // Get occurrences within a reasonable timeframe for testing
            $endDate = $this->RecursionEndDate ? Carbon::parse($this->RecursionEndDate) :
                Carbon::parse($this->StartDate)->addMonth();
            $occurrences = $this->getOccurrences($this->StartDate, $endDate);

            $children = \SilverStripe\ORM\ArrayList::create();
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
        } else {
            // Legacy RRule system - return actual RecursiveEvent records
            return RecursiveEvent::get()->filter('ParentID', $this->ID);
        }
    }
}
