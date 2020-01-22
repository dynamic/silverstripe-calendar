<?php

namespace Dynamic\Calendar\Controller;

use Carbon\Carbon;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\EventPage;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\PaginatedList;

/**
 * Class CalendarController
 *
 * @package calendar
 */
class CalendarController extends \PageController
{
    /**
     * @var
     */
    private $events = null;

    /**
     * @var array
     */
    private $default_filter;

    /**
     * @var string
     */
    private $view_type;

    /**
     * The Public stage.
     */
    const LISTVIEW = 'list';

    /**
     * The draft (default) stage
     */
    const GRIDVIEW = 'grid';

    /**
     * @var string
     */
    private $grid_link;

    /**
     * @var string
     */
    private $list_link;

    /**
     * @var
     */
    private $start_date;

    /**
     * @var string
     */
    private static $view_session = 'Calendar.VIEW';

    /**
     * @param bool $global
     * @return $this
     */
    public function setDefaultFilter($global = false)
    {
        $filter = ['StartDate:GreaterThanOrEqual' => $this->getStartDate()];

        if (!$global) {
            $filter['ParentID'] = $this->data()->ID;
        }

        $this->default_filter = $filter;

        return $this;
    }

    /**
     * @return array
     */
    public function getDefaultFilter()
    {
        if (!$this->default_filter) {
            $this->setDefaultFilter();
        }

        return $this->default_filter;
    }

    /**
     * @return $this
     */
    protected function setEvents()
    {
        $events = EventPage::get()
            ->filter($this->getDefaultFilter());

        $events = $this->filterByRequest($events);

        $this->extend('updateEvents', $events);

        $this->events = $events;

        return $this;
    }

    /**
     * @return |null
     */
    public function getEvents()
    {
        if ($this->events === null) {
            $this->setEvents();
        }

        return $this->events;
    }

    /**
     * @return PaginatedList
     */
    public function getPaginatedEvents()
    {
        return PaginatedList::create($this->getEvents(), $this->getRequest())
            ->setPageLength($this->data()->config()->get('events_per_page'));
    }

    /**
     * @return string
     */
    protected function getStartDate()
    {
        if (!$this->start_date) {
            $this->setStartDate();
        }

        return $this->start_date;
    }

    /**
     * @return $this
     */
    protected function setStartDate()
    {
        $this->start_date = ($startDate = $this->getRequest()->getVar('StartDate'))
            ? Carbon::parse($startDate)->setTimeFrom(Carbon::now())->format(Carbon::MOCK_DATETIME_FORMAT)
            : Carbon::now()->format(Carbon::MOCK_DATETIME_FORMAT);

        return $this;
    }

    /**
     * @param DataList $events
     * @return mixed
     */
    protected function filterByRequest($events)
    {
        if (!$events->exists()) {
            return $events;
        }

        $request = $this->getRequest();

        if ($endDate = $request->getVar('EndDate')) {
            $endDateTime = Carbon::parse($endDate)->endOfDay();

            $events = $events->filter(
                'EndDate:LessThanOrEqual',
                $endDateTime->format(Carbon::MOCK_DATETIME_FORMAT)
            );
        }

        if ($title = $request->getVar('Title')) {
            $events = $events->filter('Title:PartialMatch', $title);
        }

        if ($categories = $request->getVar('Categories')) {
            $events = $events->filter(['Categories.ID' => $categories]);
        }

        return $events;
    }

    /**
     * @return \SilverStripe\Forms\Form
     */
    public function EventFilterForm()
    {
        $fields = FieldList::create(
            TextField::create('Title')
                ->setTitle('Title')
                ->addExtraClass('largelabel')
                ->addExtraClass('double-bottom'),
            LiteralField::create(
                'filterText',
                '<div class="largelabel add-bottom labelborder"><label>Filters</label></div>'
            ),
            FieldGroup::create(
                'dates',
                FieldList::create(
                    $date = DateField::create('StartDate')
                        ->setTitle('')->addExtraClass('startdate'),
                    LiteralField::create(
                        'daterangemiddle',
                        '<div class="daterangemiddle"><label>to</label></div>'
                    ),
                    DateField::create('EndDate')
                        ->setTitle('')->addExtraClass('enddate')
                )
            )->setTitle('Date Range')->addExtraClass('double-bottom'),
            CheckboxSetField::create('Categories')
                ->setTitle('Categories')
                ->setSource(Category::get()->map())
        );

        $actions = FieldList::create(
            FormAction::create('doFilter')
                ->setTitle('Filter')
        );

        $form = Form::create($this, __FUNCTION__, $fields, $actions)
            ->setFormMethod('GET')
            ->setFormAction($this->Link())
            ->disableSecurityToken()
            ->loadDataFrom($this->request->getVars());

        $this->extend('updateEventSearchForm', $form);

        return $form;
    }

    /**
     * @return $this
     */
    protected function setGridLink()
    {
        $getVars = $this->getRequest()->getVars();
        $getVars['view'] = static::GRIDVIEW;

        $this->grid_link = Controller::join_links($this->Link(), http_build_query($getVars));

        return $this;
    }

    /**
     * @return string
     */
    public function getGridLink()
    {
        if (!$this->grid_link) {
            $this->setGridLink();
        }

        return $this->grid_link;
    }

    /**
     * @return $this
     */
    protected function setListLink()
    {
        $getVars = $this->getRequest()->getVars();
        $getVars['view'] = static::LISTVIEW;

        $this->list_link = Controller::join_links($this->Link(), http_build_query($getVars));

        return $this;
    }

    /**
     * @return string
     */
    public function getListLink()
    {
        if (!$this->list_link) {
            $this->setListLink();
        }

        return $this->list_link;
    }

    /**
     * @return string
     */
    public function getViewType()
    {
        if (!$this->view_type) {
            $this->setViewType();
        }

        return $this->view_type;
    }

    /**
     * @return $this
     */
    protected function setViewType()
    {
        if (($view = $this->getRequest()->getVar('view')) && $this->validView($view)) {
            $this->getRequest()->getSession()->set($this->config()->get('calendar_session'), $view);
            $this->view_type = $view;
        } elseif (($view = $this->getRequest()->getSession()->get($this->config()->get('calendar_session')))
            && $this->validView($view)
        ) {
            $this->view_type = $view;
        } else {
            $this->view_type = static::GRIDVIEW;
        }

        return $this;
    }

    /**
     * @param $view
     * @return bool
     */
    protected function validView($view)
    {
        return $view == static::LISTVIEW || $view == static::GRIDVIEW;
    }
}
