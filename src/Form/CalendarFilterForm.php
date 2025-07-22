<?php

namespace Dynamic\Calendar\Form;

use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;

/**
 * Calendar Event Filtering Form
 *
 * Provides a structured form for filtering calendar events with various criteria
 * including categories, event types, date ranges, and search terms.
 */
class CalendarFilterForm extends Form
{
    /**
     * Calendar instance associated with this form
     *
     * @var Calendar
     */
    protected $calendar;

    /**
     * Create the calendar filter form
     *
     * @param Controller $controller
     * @param string $name
     * @param Calendar $calendar
     * @param HTTPRequest $request
     */
    public function __construct(Controller $controller, string $name, Calendar $calendar, HTTPRequest $request)
    {
        $this->calendar = $calendar;

        $fields = $this->getFilterFields($request);
        $actions = $this->getFilterActions();

        parent::__construct($controller, $name, $fields, $actions);

        // Set form method and CSS class
        $this->setFormMethod('GET');
        $this->addExtraClass('calendar-filter-form');
    }

    /**
     * Get filter form fields
     *
     * @param HTTPRequest $request
     * @return FieldList
     */
    protected function getFilterFields(HTTPRequest $request): FieldList
    {
        $fields = FieldList::create();

        // Date range fields
        $fields->push(DateField::create('from', 'From Date')
            ->setValue($request->getVar('from'))
            ->setAttribute('class', 'form-control'));

        $fields->push(DateField::create('to', 'To Date')
            ->setValue($request->getVar('to'))
            ->setAttribute('class', 'form-control'));

        // Search field
        $fields->push(TextField::create('search', 'Search')
            ->setValue($request->getVar('search'))
            ->setAttribute('placeholder', 'Search event titles...')
            ->setAttribute('class', 'form-control'));

                // Category filter (if enabled)
        if ($this->calendar->ShowCategoryFilter) {
            $availableCategories = $this->getAvailableCategories();
            if ($availableCategories->count()) {
                $fields->push(CheckboxSetField::create('categories', 'Categories')
                    ->setSource($availableCategories->map('ID', 'Title'))
                    ->setValue($request->getVar('categories'))
                    ->addExtraClass('compact-checkbox-set')
                    ->setDescription('Select one or more categories to filter events'));
            }
        }

        // Event type filter (if enabled)
        if ($this->calendar->ShowEventTypeFilter) {
            $fields->push(OptionsetField::create('eventType', 'Event Type')
                ->setSource([
                    '' => 'All Events',
                    'one-time' => 'One-Time Events',
                    'recurring' => 'Recurring Events'
                ])
                ->setValue($request->getVar('eventType')));
        }

        // All-day filter (if enabled)
        if ($this->calendar->ShowAllDayFilter) {
            $fields->push(OptionsetField::create('allDay', 'Event Duration')
                ->setSource([
                    '' => 'All Events',
                    '1' => 'All-Day Events',
                    '0' => 'Timed Events'
                ])
                ->setValue($request->getVar('allDay')));
        }

        return $fields;
    }

    /**
     * Get filter form actions
     *
     * @return FieldList
     */
    protected function getFilterActions(): FieldList
    {
        $actions = FieldList::create();

        $actions->push(FormAction::create('doFilter', 'Filter Events')
            ->addExtraClass('btn btn-primary')
            ->setUseButtonTag(true));

        return $actions;
    }

    /**
     * Get available categories for this calendar
     *
     * @return DataList
     */
    protected function getAvailableCategories()
    {
        return Category::get()
            ->innerJoin('EventPage_Categories', '"Category"."ID" = "EventPage_Categories"."CategoryID"')
            ->innerJoin('EventPage', '"EventPage_Categories"."EventPageID" = "EventPage"."ID"')
            ->filter(['EventPage.ParentID' => $this->calendar->ID])
            ->sort('Title ASC');
    }

    /**
     * Handle form submission (though form uses GET method, this provides validation)
     *
     * @param array $data
     * @param Form $form
     * @return mixed
     */
    public function doFilter($data, $form)
    {
        // The actual filtering is handled by the CalendarController
        // This method can be used for additional validation or processing

        $controller = $this->getController();

        // Redirect back to calendar with filter parameters
        return $controller->redirect($controller->Link() . '?' . http_build_query($data));
    }

    /**
     * Check if any filters are currently active
     *
     * @param HTTPRequest $request
     * @return bool
     */
    public static function hasActiveFilters(HTTPRequest $request): bool
    {
        $filterVars = ['categories', 'eventType', 'allDay', 'search'];

        foreach ($filterVars as $var) {
            if ($request->getVar($var)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a summary of active filters
     *
     * @param HTTPRequest $request
     * @param Calendar $calendar
     * @return array
     */
    public static function getFilterSummary(HTTPRequest $request, Calendar $calendar): array
    {
        $summary = [];

        // Category filters
        if ($categoryIDs = $request->getVar('categories')) {
            if (!is_array($categoryIDs)) {
                $categoryIDs = [$categoryIDs];
            }
            $categories = Category::get()->byIDs($categoryIDs);
            $summary['categories'] = $categories->column('Title');
        }

        // Event type filter
        if ($eventType = $request->getVar('eventType')) {
            $summary['eventType'] = $eventType;
        }

        // All-day filter
        if ($allDay = $request->getVar('allDay')) {
            $summary['allDay'] = $allDay === '1' ? 'All-Day Events' : 'Timed Events';
        }

        // Search filter
        if ($search = $request->getVar('search')) {
            $summary['search'] = $search;
        }

        return $summary;
    }
}
