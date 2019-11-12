<?php

namespace Dynamic\Calendar\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Factory\RecursiveEventFactory;
use Dynamic\Calendar\Form\CalendarTimeField;
use Dynamic\Calendar\Model\Category;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
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
        'Recursion' => 'Enum(array("NONE","DAILY","WEEKLY","MONTHLY","YEARLY"), "NONE")',
        'Interval' => 'Int',
        'RecursionEndDate' => 'Date',
        'RecursionChangeSetID' => 'Int',
        'EventType' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $defaults = [
        'Recursion' => 'NONE',
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
     * Set the $RecursionHash as a checksum for the event recursion is enabled
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

        if ($this->ClassName == EventPage::class) {
            if ($this->Recursion != 'NONE') {
                $factory = RecursiveEventFactory::create($this);
                $factory->createRecursiveEvents();
            } else {
                $this->deleteRecursions();
            }
        }
    }

    /**
     *
     */
    protected function deleteRecursions()
    {
        RecursiveEvent::get()->filter('ParentID', $this->ID)->each(function (RecursiveEvent $event) {
            $event->doUnpublish();
            $event->doArchive();
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
    public function isCopy()
    {
        return $this->ParentID > 0 && $this instanceof RecursiveEvent;
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
}
