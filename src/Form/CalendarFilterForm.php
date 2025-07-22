<?php

namespace Dynamic\Calendar\Form;

use Dynamic\Calendar\Model\Category;
use Dynamic\Calendar\Page\Calendar;
use Dynamic\Calendar\Page\EventPage;
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
use DFT\SilverStripe\FrontendMultiSelectField\FrontendMultiSelectField;

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
        $this->setFormAction($this->calendar->Link());
        $this->addExtraClass('calendar-filter-form bg-light p-4 rounded shadow-sm mb-4');
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

        // Primary filters row - most commonly used
        $fields->push(TextField::create('search', 'Search Events')
            ->setValue($request->getVar('search'))
            ->setAttribute('placeholder', 'Search event titles and descriptions...')
            ->setAttribute('class', 'form-control form-control-lg')
            ->addExtraClass('mb-3'));

        // Category filter (if enabled) - prominent placement
        if ($this->calendar->ShowCategoryFilter) {
            $availableCategories = $this->getAvailableCategories();
            if ($availableCategories->count()) {
                $fields->push(FrontendMultiSelectField::create('categories', 'Filter by Category')
                    ->setSource($availableCategories->map('ID', 'Title')->toArray())
                    ->setValue($request->getVar('categories'))
                    ->setSearch(true)
                    ->setSelectAll(true)
                    ->setDescription('Choose one or more categories')
                    ->addExtraClass('mb-3'));
            }
        }

        // Date range in a compact row
        $fields->push(DateField::create('from', 'From Date')
            ->setValue($request->getVar('from'))
            ->setAttribute('class', 'form-control')
            ->addExtraClass('col-md-6 d-inline-block'));

        $fields->push(DateField::create('to', 'To Date')
            ->setValue($request->getVar('to'))
            ->setAttribute('class', 'form-control')
            ->addExtraClass('col-md-6 d-inline-block'));

        // Advanced filters - collapsed by default (optional)
        if ($this->calendar->ShowEventTypeFilter || $this->calendar->ShowAllDayFilter) {
            // Only show these if specifically enabled and user requests advanced options
            $showAdvanced = $request->getVar('advanced') || 
                           $request->getVar('eventType') || 
                           $request->getVar('allDay');
            
            if ($showAdvanced) {
                // Event type filter - more compact as dropdown
                if ($this->calendar->ShowEventTypeFilter) {
                    $fields->push(DropdownField::create('eventType', 'Event Type')
                        ->setSource([
                            '' => 'All Events',
                            'one-time' => 'One-Time Events', 
                            'recurring' => 'Recurring Events'
                        ])
                        ->setValue($request->getVar('eventType'))
                        ->setAttribute('class', 'form-control form-control-sm')
                        ->addExtraClass('col-md-6 d-inline-block mt-2'));
                }

                // All-day filter - more compact as dropdown  
                if ($this->calendar->ShowAllDayFilter) {
                    $fields->push(DropdownField::create('allDay', 'Duration')
                        ->setSource([
                            '' => 'All Events',
                            '1' => 'All-Day Events',
                            '0' => 'Timed Events'
                        ])
                        ->setValue($request->getVar('allDay'))
                        ->setAttribute('class', 'form-control form-control-sm')
                        ->addExtraClass('col-md-6 d-inline-block mt-2'));
                }
            }
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

        $actions->push(FormAction::create('doFilter', 'Apply Filters')
            ->addExtraClass('btn btn-primary btn-lg px-4')
            ->setUseButtonTag(true)
            ->setAttribute('title', 'Apply the selected filters to view matching events'));

        // Clear filters link (if any filters are active)
        // Only add this if we can safely get the controller and request
        $controller = $this->getController();
        if ($controller && $controller->getRequest()) {
            $request = $controller->getRequest();
            if (self::hasActiveFilters($request)) {
                $actions->push(FormAction::create('clearFilters', 'Clear All')
                    ->addExtraClass('btn btn-outline-secondary ms-2')
                    ->setUseButtonTag(true)
                    ->setAttribute('title', 'Remove all filters and show all events'));
            }
        }

        return $actions;
    }

    /**
     * Get available categories for this calendar
     *
     * @return DataList
     */
    protected function getAvailableCategories()
    {
        // Get categories that are actually used by events in this calendar
        // Use the same approach as CalendarController to avoid relation errors
        $categoryIDs = EventPage::get()
            ->filter(['ParentID' => $this->calendar->ID])
            ->leftJoin('EventPage_Categories', '"EventPage"."ID" = "EventPage_Categories"."EventPageID"')
            ->leftJoin('Category', '"EventPage_Categories"."CategoryID" = "Category"."ID"')
            ->column('Category.ID');

        // Remove duplicates and null values
        $categoryIDs = array_unique(array_filter($categoryIDs));

        if (empty($categoryIDs)) {
            return Category::get()->filter('ID', 0); // Return empty DataList
        }

        return Category::get()->byIDs($categoryIDs)->sort('Title ASC');
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
     * Handle clearing all filters
     *
     * @param array $data
     * @param Form $form
     * @return mixed
     */
    public function clearFilters($data, $form)
    {
        $controller = $this->getController();
        
        // Redirect back to calendar without any filter parameters
        return $controller->redirect($controller->Link());
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
