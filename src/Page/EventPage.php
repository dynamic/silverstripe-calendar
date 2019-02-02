<?php

namespace Dynamic\Calendar\Page;

use Carbon\Carbon;
use Dynamic\Calendar\Factory\RecursionChangeSetFactory;
use Dynamic\Calendar\Factory\RecursiveEventFactory;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Model\RecursionChangeSet;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\TreeMultiselectField;
use SilverStripe\ORM\FieldType\DBDate;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Versioned\Versioned;

/**
 * Class EventPage
 * @package Dynamic\Calendar\Page
 *
 * @property DBDatetime $StartDatetime
 * @property DBDatetime $EndDatetime
 * @property bool $AllDay
 * @property string $Recursion
 * @property DBDate $RecursionEndDate
 * @property int $RecursionChangeSetID
 * @method HasManyList RecursionChangeSets()
 * @method ManyManyList Categories()
 */
class EventPage extends \Page
{
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
     *
     */
    private static $can_be_root = false;

    /**
     * @var array
     */
    private static $db = [
        'StartDatetime' => 'DBDatetime',
        'EndDatetime' => 'DBDatetime',
        'AllDay' => 'Boolean',
        'Recursion' => 'Enum(array("Daily","Weekly","Monthly","Weekdays","Annual"))',
        'RecursionEndDate' => 'Date',
        'RecursionChangeSetID' => 'Int',
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
    private static $default_sort = 'StartDatetime DESC';

    /**
     * @var array
     */
    private static $cascade_duplicates = [
        'Categories',
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
        'StartDatetime',
        'EndDatetime',
        'Recursion',
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->addFieldsToTab(
                'Root.EventSettings',
                [
                    $start = DatetimeField::create('StartDatetime')
                        ->setTitle('Start Date & Time'),
                    $end = DatetimeField::create('EndDatetime')
                        ->setTitle('End Date & Time'),
                    $allDayGroup = FieldGroup::create(
                        'Pattern',
                        $allDay = DropdownField::create('AllDay')
                            ->setTitle('All Day')
                            ->setSource([false => 'No', true => 'Yes'])
                    )->setTitle(''),
                    $categories = TreeMultiselectField::create('Categories')
                        ->setTitle('Categories')
                        ->setSourceObject(Category::class),
                ]
            );

            $end->hideIf('AllDay')->isEqualTo(true)->end();

            if ($this->StartDatetime) {
                $allDayGroup->push(
                    $recursion = DropdownField::create('Recursion')
                        ->setSource($this->getPatternSource())
                        ->setEmptyString('Does not repeat')
                );
            }

            if ($this->Recursion) {
                if ($this->isCopy()) {
                    $allDayGroup->performReadonlyTransformation();
                } else {
                    $fields->addFieldToTab(
                        'Root.EventSettings',
                        GridField::create(
                            'RecursionChangeSets',
                            'Recursion Change Sets',
                            $this->RecursionChangeSets(),
                            $recursionsConfig = GridFieldConfig_RecordViewer::create()
                        )
                    );
                }
            }
        });

        $fields = parent::getCMSFields();

        if ($this->isCopy()) {
            $fields = $fields->makeReadonly();
        }

        return $fields;
    }

    /**
     * Set the $RecursionHash as a checksum for the event recursion is enabled
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->isCopy() && $this->Recursion != null && $this->recursionChanged()) {
            $this->RecursionChangeSetID = $this->generateRecursionChangeSet()->ID;
        }

        if (!$this->isCopy() && $this->Recursion && !$this->recursionChanged()) {
            $this->Children()
                ->filter('StartDatetime:GreaterThanOrEqual', Carbon::now()->format('Y-m-d'))
                ->each(function (RecursiveEvent $event) {
                    $event->writeToStage(Versioned::DRAFT);
                });
        }
    }

    /**
     *
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        $changeType = self::CHANGE_VALUE;

        if ($this->isChanged('Recursion', $changeType)) {
            $this->deleteChildren();
        }

        if (!$this->isChanged('Recursion', $changeType) && $this->isChanged('StartDatetime', $changeType)) {
            if ($event = RecursiveEvent::get()->filter('StartDatetime', $this->StartDatetime)->first()) {
                $event->doUnpublish();
                $event->doArchive();
            }
        }

        if (!$this->isCopy() && $this->Recursion) {
            $this->createOrUpdateChildren();
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
    private function isCopy()
    {
        return $this->ParentID > 0 && $this->Parent() instanceof EventPage;
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
        $pattern = EventPage::singleton()->dbObject('Recursion')->enumValues();
        $day = $this->getDayFromDate($this->StartDatetime);

        foreach ($pattern as $key => $val) {
            switch ($key) {
                case 'Weekly':
                    $pattern[$key] = "{$val} on {$day}";
                    break;
                case 'Monthly':
                    $pattern[$key] = "{$val} on the {$this->getMonthDay()}";
                    break;
                case 'Annual':
                    $pattern[$key] = "{$val} on {$this->getMonthDate()}";
                    break;
            }
        }

        return $pattern;
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
    protected function getMonthDay()
    {
        $startDate = new Carbon($this->StartDatetime);

        $day = $this->getDayFromDate($startDate);
        $month = $startDate->format('F');
        $year = $startDate->format('Y');

        $date = strtotime("First day of {$month} {$year}");
        $end = strtotime("Last day of {$month} {$year}");

        $days = [];

        while ($date <= $end) {
            if ($this->getDayFromDate($date) == $day) {
                $days[] = date('Y-m-d', $date);
            }
            $date = strtotime("tomorrow", $date);
        }

        $dayCount = array_search(date('Y-m-d', strtotime($this->StartDatetime)), $days);

        if ($dayCount === false) {
            return '';
        }

        if ($dayCount == 0) {
            $firstLast = 'first';
        }

        if ($dayCount == count($days) - 1) {
            $firstLast = 'last';
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
        $ends = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number . 'th';
        } else {
            return $number . $ends[$number % 10];
        }
    }

    /**
     * @return false|string
     */
    protected function getMonthDate()
    {
        return date('F j', strtotime($this->StartDatetime));
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
     * @return bool|string
     */
    private function recursionCheckSum()
    {
        if ($this->Recursion) {
            $data = $this->toMap();

            foreach ($this->config()->get('ignore_changed') as $field) {
                unset($data[$field]);
            }

            return md5(serialize($data));
        }

        return false;
    }

    /**
     *
     */
    private function createOrUpdateChildren()
    {
        $recursionSet = RecursionChangeSet::get()->byID($this->RecursionChangeSetID);
        $existing = RecursiveEvent::get()->filter('ParentID', $this->ID);
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
