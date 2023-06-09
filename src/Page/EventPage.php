<?php

namespace Dynamic\Calendar\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Controller\EventPageController;
use Dynamic\Calendar\Factory\RecursiveEventFactory;
use Dynamic\Calendar\Form\CalendarTimeField;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\RRule\CustomSchemaHelper;
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
 * @property string $RecursionString
 * @method ManyManyList Categories()
 */
class EventPage extends \Page
{
    /**
     * array
     */
    const RRULE = [
        'DAILY' => 'Day(s)',
        'WEEKLY' => 'Week(s)',
        'MONTHLY' => 'Month(s)',
        'YEARLY' => 'Year(s)',
    ];

    /**
     * @var string
     */
    private static $singular_name = 'Event';

    /**
     * @var string
     */
    private static $plural_name = 'Events';

    /**
     * @var array
     */
    private static $allowed_children = [RecursiveEvent::class];

    /**
     * @var bool
     */
    private static $show_in_sitetree = false;

    /**
     * @var string
     */
    private static $icon_class = 'font-icon-p-event';

    /**
     *
     */
    private static $can_be_root = false;

    /**
     * Recursion is currently experimental.
     *
     * @var bool
     */
    private static $recursion = false;

    /**
     * @var array
     */
    private static $db = [
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
        'RecursionString' => 'Varchar(255)',
        'EndType' => 'Enum(array("UNTIL","COUNT"))',
        'Interval' => 'Int',
        'RecursionEndDate' => 'Date',
        'RecursionInstances' => 'Int',
        'EventType' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'Recursion' => 'NONE',
        'ShowInMenus' => false,
    ];

    /**
     * @var array
     */
    private static $extensions = [
        Lumberjack::class,
    ];

    /**
     * @var array
     */
    private static $many_many = [
        'Categories' => Category::class,
    ];

    /**
     * @var array
     */
    private static $many_many_extraFields = [
        'Categories' => [
            'SortOrder' => 'Int',
        ],
    ];

    /**
     * @var string
     */
    private static $table_name = 'EventPage';

    /**
     * @var string
     */
    private static $default_sort = 'StartDate';

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'Categories',
    ];

    /**
     * @var array
     */
    private static $cascade_deletes = [
        'Children',
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'Title' => 'Title',
        'GridFieldDate' => 'Date',
        'GridFieldTime' => 'Time',
        'HasRecurringEvents' => 'Recurring Events',
    ];

    /**
     * @var array
     */
    private static $recursion_days = [
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
    private static $recursion_changed = [
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
    public function getGridFieldDate()
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

            if ($this->config()->get('recursion') && !$this->isCopy() && $this->exists()) {
                $fields->addFieldsToTab(
                    'Root.Recursion',
                    [
                        DropdownField::create('RecursionString')
                            ->setSource($this->getRecursionStringSource())
                            ->setTitle('Repeat'),
                        FieldGroup::create(
                            $interval = NumericField::create('Interval')
                                ->setTitle('Repeat every'),
                            $recursion = DropdownField::create('Recursion')
                                ->setSource(self::RRULE)
                                ->setTitle('')
                        ),
                        FieldGroup::create(
                            $endType = DropdownField::create('EndType')
                                ->setTitle('Ends')
                                ->setSource(['UNTIL' => 'On', 'COUNT' => 'After']),
                            $recursionEndDate = DateField::create('RecursionEndDate')
                                ->setTitle(''),
                            $recursionInstances = NumericField::create('RecursionInstances')
                                ->setTitle('')
                                ->setRightTitle('Occurrences')
                        )
                    ]
                );

                $interval->hideUnless('RecursionString')->isEqualTo('CUSTOM')->end();
                $recursion->hideUnless('RecursionString')->isEqualTo('CUSTOM')->end();
                $endType->hideUnless('RecursionString')->isEqualTo('CUSTOM')->end();
                $recursionEndDate->hideUnless('RecursionString')->isEqualTo('CUSTOM')->end();
                $recursionEndDate->hideUnless('RecursionString')->isEqualTo('CUSTOM')
                    ->andIf('EndType')->isEqualTo('UNTIL')->end();
                $recursionInstances->hideUnless('RecursionString')->isEqualTo('CUSTOM')
                    ->andIf('EndType')->isEqualTo('COUNT')->end();
            }
        });

        $fields = parent::getCMSFields();

        if (($children = $fields->dataFieldByName('ChildPages')) && $children instanceof GridField) {
            if (($component = $children->getConfig()->getComponentByType(GridFieldPaginator::class))
                && $component instanceof GridFieldPaginator
            ) {
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
     * @return array
     */
    protected function getRecursionStringSource()
    {
        return ['CUSTOM' => 'Custom'];
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

        if ($this->eventRecurs()) {
            $this->generateAdditionalEvents();
        }

        $this->cleanRecursions();
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
    protected function eventRecurs()
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
     */
    protected function getRecursionSet()
    {
        $pattern = [
            'FREQ' => $this->Recursion,
            'INTERVAL' => $this->Interval,
            'DTSTART' => $this->StartDate,
            'UNTIL' => $this->RecursionEndDate,
        ];

        if (!$this->eventRecurs()) {
            return [];
        }

        $validator = CustomSchemaHelper::create($pattern);

        if (!$validator->isValidPattern()) {
            return [];
        }

        return new RRule($pattern);
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
     * @return array
     */
    protected function getValidDates()
    {
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
     * @param $list
     * @return \Generator
     */
    private function yieldSingle($list)
    {
        foreach ($list as $item) {
            yield $item;
        }
    }
}
