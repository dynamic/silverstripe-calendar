<?php

namespace Dynamic\Calendar\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Factory\RecursionChangeSetFactory;
use Dynamic\Calendar\Factory\RecursiveEventFactory;
use Dynamic\Calendar\Form\CalendarTimeField;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\RecursionChangeSet;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\Lumberjack\Model\Lumberjack;
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
 * @property DBDate $RecursionEndDate
 * @property int $RecursionChangeSetID
 * @method HasManyList RecursionChangeSets()
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
        'Recursion' => 'Enum(array("DAILY","WEEKLY","MONTHLY","YEARLY"))',
        'Interval' => 'Int',
        'RecursionEndDate' => 'Date',
        'RecursionChangeSetID' => 'Int',
        'EventType' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'Recursion' => null,
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
    private static $has_many = [
        'RecursionChangeSets' => RecursionChangeSet::class,
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
    private static $summary_fields = [
        'Title' => 'Title',
        'GridFieldDate' => 'Date',
        'GridFieldTime' => 'Time',
        'HasRecurringEvents' => 'Recurring Event',
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
        if ($this->Recursion && ($changeSet = $this->getCurrentRecursionChangeSet())) {
            return $changeSet->RecursionPattern;
        }

        return 'No';
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
                    $start = DateField::create('StartDate')
                        ->setTitle('Start Date'),
                    $startTime = CalendarTimeField::create('StartTime')
                        ->setTitle('Start Time'),
                    $end = DateField::create('EndDate')
                        ->setTitle('End Date'),
                    $endTime = CalendarTimeField::create('EndTime')
                        ->setTitle('EndTime'),
                    $allDay = DropdownField::create('AllDay')
                        ->setTitle('All Day')
                        ->setSource([false => 'No', true => 'Yes']),
                    $categories = TreeMultiselectField::create('Categories')
                        ->setTitle('Categories')
                        ->setSourceObject(Category::class),
                ]
            );

            $startTime->hideIf('AllDay')->isEqualTo(true)->end();
            $end->hideIf('AllDay')->isEqualTo(true)->end();
            $endTime->hideIf('AllDay')->isEqualTo(true)->end();

            if ($this->config()->get('recursion')) {
                $fields->addFieldsToTab(
                    'Root.Recursion',
                    [
                        FieldGroup::create(
                            $interval = NumericField::create('Interval')
                                ->setTitle(''),
                            $recursion = DropdownField::create('Recursion')
                                ->setSource($this->getPatternSource())
                                ->setEmptyString('Does not repeat'),
                            $recursionEndDate = DateField::create('RecursionEndDate')
                                ->setTitle('Ending On')
                        )->setTitle('Repeat every'),
                    ]
                );
            }

            if ($this->Recursion && $this->config()->get('recursion')) {
                if ($this->isCopy()) {
                    //$allDayGroup->performReadonlyTransformation();
                }
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
            $fields = $fields->makeReadonly();
        }

        if (!$this->config()->get('recursion')) {
            $fields->removeByName('ChildPages');
        }

        return $fields;
    }

    /**
     * Set the $RecursionHash as a checksum for the event recursion is enabled
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $this->EventType = static::class;

        if (!$this->isCopy() && $this->Recursion != null && $this->recursionChanged()) {
            $factory = RecursiveEventFactory::create($this);
            $factory->createRecursiveEvent();
        }
        /*if (!$this->isCopy() && !$this->Recursion && $this->Children()->count()) {
            $this->deleteChildren();
        } elseif (!$this->isCopy() && $this->Recursion && !$this->recursionChanged()) {
            $this->writeChildrenToStage();
        }//*/
    }

    /**
     *
     */
    private function writeChildrenToStage()
    {
        $this->Children()
            ->filter('StartDate:GreaterThanOrEqual', Carbon::now()->format('Y-m-d'))
            ->each(function (RecursiveEvent $event) {
                $event->writeToStage(Versioned::DRAFT);
            });
    }

    /**
     *
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        /** @var RecursionChangeSet $changeSet */
        if ($changeSet = RecursionChangeSet::get()->byID($this->RecursionChangeSetID)) {
            $changeSet->EventPageID = $changeSet->EventPageID == 0 ? $this->ID : $changeSet->EventPageID;
            //$changeSet->write();
        }

        $changeType = self::CHANGE_VALUE;

        if ($this->isChanged('Recursion', $changeType)) {
            $this->deleteChildren();
        }

        if (!$this->isChanged('Recursion', $changeType) && $this->isChanged('StartDate', $changeType)) {
            /** @var RecursiveEvent $event */
            if ($event = RecursiveEvent::get()->filter([
                'StartDate' => $this->StartDate,
                'ParentID' => $this->ID,
            ])->first()) {
                $event->doUnpublish();
                $event->doArchive();
            }
        }

        if (!$this->isCopy() && $this->Recursion) {
            //$this->createOrUpdateChildren();
        }
    }

    /**
     *
     */
    public function onAfterPublish()
    {
        parent::onAfterPublish();

        $this->Children()->each(function (RecursiveEvent $event) {
            $event->publishRecursive();
        });
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
     * @return RecursionChangeSet
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function generateRecursionChangeSet()
    {
        $factory = RecursionChangeSetFactory::create($this);

        return $factory->getChangeSet();
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

        return parent::canEdit($member); // TODO: Change the autogenerated stub
    }

    /**
     * @param null $member
     * @return bool
     */
    /*public function canAddChildren($member = null)
    {
        return false;
    }*/

    /**
     * @param null $member
     * @return bool
     */
    public function canPublish($member = null)
    {
        if ($this->isCopy()) {
            return false;
        }

        return parent::canPublish($member); // TODO: Change the autogenerated stub
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

        return parent::canUnpublish($member); // TODO: Change the autogenerated stub
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
    public function getIsDaily()
    {
        return $this->Recursion == 'Daily';
    }

    /**
     * @return bool
     */
    private function isCopy()
    {
        return $this->ParentID > 0 && $this instanceof RecursiveEvent;
    }

    /**
     * @return bool|\SilverStripe\ORM\DataObject
     */
    public function getCurrentRecursionChangeSet()
    {
        return $this->RecursionChangeSetID ? RecursionChangeSet::get()->byID($this->RecursionChangeSetID) : false;
    }

    /**
     * @return string|null
     */
    public function generateRecursionPattern()
    {
        $source = $this->getPatternSource();

        if (isset($source[$this->Recursion])) {
            return $source[$this->Recursion];
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getPatternSource()
    {
        return self::RRULE;
    }

    /**
     * @param $date
     * @return false|string
     */
    protected function getDayFromDate($date)
    {
        if (strpos($date, ' ')) {
            $date = strtotime($date);
        }

        return date('l', $date);
    }

    /**
     * @return string
     */
    public function getMonthDay()
    {
        $startDate = Carbon::parse($this->StartDate);

        $day = $startDate->format('l');
        $month = $startDate->format('F');
        $year = $startDate->format('Y');

        $date = Carbon::parse("First day of {$month} {$year}");
        $end = Carbon::parse("Last day of {$month} {$year}");

        $days = [];

        while ($date->timestamp <= $end->timestamp) {
            if ($date->format('l') == $day) {
                $days[] = $date->format('Y-m-d');
            }
            $date = $date->addDay();
        }

        $dayCount = array_search($startDate->format('Y-m-d'), $days);

        if ($dayCount === false) {
            return '';
        } else {
            $dayCount = $dayCount + 1;
        }

        $monthDay = (isset($firstLast))
            ? "{$firstLast} {$day}"
            : "{$this->ordinal($dayCount)} {$day}";

        return $monthDay;
    }

    /**
     * @param Carbon $date
     * @return string
     */
    protected function getDayName(Carbon $date)
    {
        if ($date->isMonday()) {
            return 'Monday';
        } elseif ($date->isTuesday()) {
            return 'Tuesday';
        } elseif ($date->isWednesday()) {
            return 'Wednesday';
        } elseif ($date->isThursday()) {
            return 'Thursday';
        } elseif ($date->isFriday()) {
            return 'Friday';
        } elseif ($date->isSaturday()) {
            return 'Saturday';
        } elseif ($date->isSunday()) {
            return 'Sunday';
        }

        return '';
    }

    /**
     * @param $number
     * @return string
     */
    public function ordinal($number)
    {
        $first_word = [
            'eth',
            'First',
            'Second',
            'Third',
            'Fouth',
            'Fifth',
            'Sixth',
            'Seventh',
            'Eighth',
            'Ninth',
            'Tenth',
            'Elevents',
            'Twelfth',
            'Thirteenth',
            'Fourteenth',
            'Fifteenth',
            'Sixteenth',
            'Seventeenth',
            'Eighteenth',
            'Nineteenth',
            'Twentieth',
        ];
        $second_word = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty'];

        if ($number <= 20) {
            return $first_word[$number];
        }

        $first_num = substr($number, -1, 1);
        $second_num = substr($number, -2, 1);

        return $string = str_replace('y-eth', 'ieth', $second_word[$second_num] . '-' . $first_word[$first_num]);
    }

    public function numToOrdinalWord($num)
    {
        $first_word = [
            'eth',
            'First',
            'Second',
            'Third',
            'Fouth',
            'Fifth',
            'Sixth',
            'Seventh',
            'Eighth',
            'Ninth',
            'Tenth',
            'Elevents',
            'Twelfth',
            'Thirteenth',
            'Fourteenth',
            'Fifteenth',
            'Sixteenth',
            'Seventeenth',
            'Eighteenth',
            'Nineteenth',
            'Twentieth',
        ];
        $second_word = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty'];

        if ($num <= 20) {
            return $first_word[$num];
        }

        $first_num = substr($num, -1, 1);
        $second_num = substr($num, -2, 1);

        return $string = str_replace('y-eth', 'ieth', $second_word[$second_num] . '-' . $first_word[$first_num]);
    }

    /**
     * @return false|string
     */
    protected function getMonthDate()
    {
        return date('F j', strtotime($this->StartDate));
    }

    /**
     * @return \SilverStripe\ORM\DataList
     */
    protected function getAllEvents()
    {
        $idSet = [$this->ID];

        $idSet = array_merge($idSet, $this->Children()->column());

        return EventPage::get()->filter(['ID' => $idSet]);
    }

    /**
     *
     */
    private function createOrUpdateChildren()
    {
        $recursionSet = RecursionChangeSet::get()->byID($this->RecursionChangeSetID);
        $existing = RecursiveEvent::get()->filter([
            'ParentID' => $this->ID,
            'StartDate:GreaterThanOrEqual' => Carbon::now()
                ->format(RecursiveEventFactory::config()->get('date_format')),
        ]);

        if (!$existing->exists()) {
            $existing = null;
        }

        $eventFactory = RecursiveEventFactory::create($recursionSet, $existing);

        $eventFactory->generateEvents();
    }

    /**
     * Delete children generated from the recursion pattern
     */
    private function deleteChildren()
    {
        foreach ($this->yieldSingle(RecursiveEvent::get()->filter('ParentID', $this->ID)) as $child) {
            $child->doUnpublish();
            $child->doArchive();
        }
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
