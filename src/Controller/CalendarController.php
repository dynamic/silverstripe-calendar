<?php

namespace Dynamic\Calendar\Controller;

use Dynamic\Calendar\Controller\EventController;
use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;

/**
 * Class CalendarController
 *
 * @package calendar
 */
class CalendarController extends \PageController
{

    /**
     * @var array
     */
    private static $url_handlers = array(
        'event' => 'event',
    );

    /**
     * @var array
     */
    private static $allowed_actions = array(
        'index',
        'event',
        'EventFilterForm',
    );

    /**
     * @var int
     */
    private static $pagination_length = 8;

    /**
     * @var string
     */
    private static $filter_prefix = 'f';

    /**
     * @var string
     */
    private static $filter_any_prefix = 'a';

    /**
     * @var string
     */
    private static $partial_match_prefix = 'p';

    /**
     * @var string
     */
    private static $exclude_prefix = 'e';

    /**
     * @var
     */
    private $filter;

    /**
     * @var
     */
    private $filter_any;

    /**
     * @var
     */
    private $exclude;

    /**
     * @param HTTPRequest $request
     * @return \SilverStripe\View\ViewableData_Customised
     */
    public function index(HTTPRequest $request)
    {
        $filter = $this->getFilter();
        $filterAny = $this->getFilterAny();
        $exclude = $this->getExclude();

        $events = Calendar::upcoming_events($filter, $filterAny, $exclude);
        $start = ($request->getVar('start')) ? (int)$request->getVar('start') : 0;

        $list = PaginatedList::create($events, $this->request);
        $list->setPageStart($start);
        $list->setPageLength(Config::inst()->get(Calendar::class, 'events_per_page'));

        $agenda = $this->isAgenda();

        return $this->customise(new ArrayData([
            'IsAgenda' => $agenda,
            'Events' => $list,
        ]));
    }

    /**
     * @param null $request
     * @return \SilverStripe\Control\HTTPResponse
     */
    public function event($request = null)
    {
        if (!$request) {
            $request = $this->getRequest();
        }
        return EventController::create()
            ->handleRequest($request, $this->model);
    }

    /**
     * @return \SilverStripe\Forms\Form
     */
    public function EventFilterForm()
    {

        $filter = $this->config()->get('filter_prefix');
        $partial = $this->config()->get('partial_match_prefix');
        $filterAny = $this->config()->get('filter_any_prefix');

        $categoryFilter = (class_exists('Subsite')) ? ['SubsiteID' => Subsite::currentSubsiteID()] : [];

        $fields = FieldList::create(
            TextField::create($partial . '_Title')
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
                    $date = DateField::create($filter . '_StartDate')
                        ->setTitle('')->addExtraClass('startdate'),
                    LiteralField::create(
                        'daterangemiddle',
                        '<div class="daterangemiddle"><label>to</label></div>'
                    ),
                    DateField::create($filter . '_EndDate')
                        ->setTitle('')->addExtraClass('enddate')
                )
            )->setTitle('Date Range')->addExtraClass('double-bottom'),
            CheckboxSetField::create($filterAny . '_Categories')
                ->setTitle('Categories')
                ->setSource(Category::get()->filter($categoryFilter)->map())
        );

        $this->extend('updateEventFilterFormFields', $fields);

        $actions = FieldList::create(
            FormAction::create('doFilter')
                ->setTitle('Filter')
        );

        return Form::create($this, __FUNCTION__, $fields, $actions)
            ->setFormMethod('GET')
            ->setFormAction($this->Link())
            ->disableSecurityToken()
            ->loadDataFrom($this->request->getVars());
    }

    /**
     * @return array
     */
    public function getFilter()
    {
        if (!$this->filter) {
            $this->setFilter($this->filterFromRequest());
        }
        return $this->filter;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setFilter($params = array())
    {
        $this->extend('updateCalendarFilter', $params);
        $this->filter = $params;
        return $this;
    }

    /**
     * @param HTTPRequest|null $request
     * @return array
     */
    public function filterFromRequest(HTTPRequest $request = null)
    {
        $request = ($request) ? $request : $this->request;
        $params = self::clean_request_vars($request->getVars());
        $filterPrefix = $this->config()->get('filter_prefix');

        if (empty($params)) {
            return array();
        }

        $filter = [];

        foreach ($params as $key => $val) {
            if (strpos($key, $filterPrefix . '_') !== false) {
                if (!$request->getVar($key)) {
                    continue;
                }
                $parts = explode('_', $key);
                $filterKey = ((array)$val === $val) ? $parts[1] . '.ID' : $parts[1];
                if (strpos($filterKey, 'Start') !== false) {
                    $filterKey = $filterKey . ':GreaterThanOrEqual';
                    $val = date('Y-m-d', strtotime($val));
                }
                if (strpos($filterKey, 'End') !== false) {
                    $filterKey = $filterKey . ':LessThanOrEqual';
                    $val = date('Y-m-d', strtotime($val));
                }
                $filter[$filterKey] = $val;
            }
        }

        return $filter;
    }

    /**
     * @return array
     */
    public function getFilterAny()
    {
        if (!$this->filter_any) {
            $this->setFilterAny($this->filterAnyFromRequest());
        }
        return $this->filter_any;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setFilterAny($params = array())
    {
        $this->extend('updateCalendarFilterAny', $params);
        $this->filter_any = $params;
        return $this;
    }

    /**
     * @param HTTPRequest|null $request
     * @return array
     */
    public function filterAnyFromRequest(HTTPRequest $request = null)
    {
        $request = ($request) ? $request : $this->request;
        $params = self::clean_request_vars($request->getVars());
        $filterAnyPrefix = $this->config()->get('filter_any_prefix');
        $partialMatchPrefix = $this->config()->get('partial_match_prefix');

        if (empty($params)) {
            return array();
        }

        $filterAny = [];

        foreach ($params as $key => $val) {
            if (strpos($key, $partialMatchPrefix . '_') !== false || strpos($key, $filterAnyPrefix . '_') !== false) {
                if (!$request->getVar($key)) {
                    continue;
                }
                $parts = explode('_', $key);
                if ($parts[0] == $filterAnyPrefix) {
                    $filterKey = ((array)$val === $val) ? $parts[1] . '.ID' : $parts[1];
                } else {
                    $filterKey = $parts[1] . ':PartialMatch';
                }
                $filterAny[$filterKey] = $val;
            }
        }

        return $filterAny;
    }

    /**
     * @return array
     */
    public function getExclude()
    {
        if (!$this->exclude) {
            $this->setExclude($this->excludeFromRequest());
        }
        return $this->exclude;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function setExclude($params = array())
    {
        $this->extend('updateCalendarExclude', $params);
        $this->exclude = $params;
        return $this;
    }

    /**
     * @param HTTPRequest|null $request
     * @return array
     */
    public function excludeFromRequest(HTTPRequest $request = null)
    {
        $request = ($request) ? $request : $this->request;
        $params = self::clean_request_vars($request->getVars());
        $excludePrefix = $this->config()->get('exclude_prefix');

        if (empty($params)) {
            return array();
        }

        $exclude = [];

        foreach ($params as $key => $val) {
            if (strpos($key, $excludePrefix . '_') !== false) {
                if (!$request->getVar($key)) {
                    continue;
                }
                $parts = explode('_', $key);
                $filterKey = ((array)$val === $val) ? $parts[1] . '.ID' : $parts[1];
                $exclude[$filterKey] = $val;
            }
        }

        return $exclude;
    }

    /**
     * @param array $vars
     * @return array
     */
    public static function clean_request_vars($vars = array())
    {
        if (isset($vars['url'])) {
            unset($vars['url']);
        }
        if (isset($vars['action_doFilter'])) {
            unset($vars['action_doFilter']);
        }
        return $vars;
    }

    /**
     * @param $vars
     * @return mixed
     */
    protected static function clear_view($vars)
    {
        if (isset($vars['view'])) {
            unset($vars['view']);
        }
        return $vars;
    }

    /**
     * @return bool
     */
    protected function isAgenda()
    {
        return ($this->request->getVar('view') && $this->request->getVar('view') == 'agenda');
    }

    /**
     * @param array $vars
     * @return string
     */
    protected static function build_view_link_vars($vars = array())
    {
        return http_build_query($vars);
    }

    /**
     * @return String
     */
    public function AgendaLink()
    {
        $requestVars = self::clear_view(self::clean_request_vars($this->getRequest()->getVars()));
        $requestVars['view'] = 'agenda';
        return Controller::join_links($this->Link(), '?' . self::build_view_link_vars($requestVars));
    }

    /**
     * @return String
     */
    public function GridLink()
    {
        $requestVars = self::clear_view(self::clean_request_vars($this->getRequest()->getVars()));
        $requestVars['view'] = 'grid';
        return Controller::join_links($this->Link(), '?' . self::build_view_link_vars($requestVars));
    }
}
