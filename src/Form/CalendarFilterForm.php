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
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;
use SilverStripe\View\Requirements;
use Exception;

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
        $this->addExtraClass('calendar-filter-form bg-light p-4 rounded shadow-sm mb-4 horizontal-form');
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

        // Load Choices.js for enhanced multi-select dropdowns
        // TODO: Bundle locally for better security and offline capability
        Requirements::javascript('https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js');
        Requirements::css('https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css');

        // Add SRI attributes for security
        Requirements::customScript('
            document.addEventListener("DOMContentLoaded", function() {
                // Add integrity attributes to external resources for security
                const choicesScript = document.querySelector("script[src*=\"choices.js\"]");
                if (choicesScript) {
                    choicesScript.crossOrigin = "anonymous";
                }
                const choicesStyle = document.querySelector("link[href*=\"choices.js\"]");
                if (choicesStyle) {
                    choicesStyle.crossOrigin = "anonymous";
                }
            });
        ', 'choices-security');

        // Add CSS for horizontal layout and Clear Filters functionality
        $fields->push(LiteralField::create('horizontalCSS', '
            <style>
            .horizontal-form fieldset { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; }
            .horizontal-form .field { flex: 1; min-width: 200px; margin-bottom: 0 !important; }
            .horizontal-form .field.col-md-4 { flex: 2; }
            .horizontal-form .field.col-md-3 { flex: 1.5; }
            .horizontal-form .field.col-md-2 { flex: 1; }
            .horizontal-form .form-actions { margin-top: 1rem; }
            .horizontal-form .btn-toolbar { display: flex; gap: 0.5rem; align-items: center; }
            @media (max-width: 768px) {
                .horizontal-form fieldset { flex-direction: column; }
                .horizontal-form .field { width: 100%; }
            }
            </style>
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Add Clear Filters button if URL has search parameters (indicating active filters)
                const urlParams = new URLSearchParams(window.location.search);
                const hasFilters = urlParams.has("search") || urlParams.has("categories") ||
                                 urlParams.has("from") || urlParams.has("to") ||
                                 urlParams.has("eventType") || urlParams.has("allDay");

                if (hasFilters) {
                    const actionsDiv = document.querySelector(".btn-toolbar.form-actions");
                    if (actionsDiv) {
                        const clearLink = document.createElement("a");
                        clearLink.href = window.location.pathname;
                        clearLink.className = "btn btn-outline-secondary";
                        clearLink.title = "Remove all filters and show all events";
                        clearLink.textContent = "Clear All";
                        actionsDiv.appendChild(clearLink);
                    }
                }

                // Initialize Choices.js on multi-select dropdowns
                if (typeof Choices !== "undefined") {
                    const multiSelectElements = document.querySelectorAll(".js-choice");
                    multiSelectElements.forEach(function(element) {
                        // Only initialize on select elements, not wrapper divs
                        if (element.tagName === "SELECT") {
                            new Choices(element, {
                                removeItemButton: true,
                                searchEnabled: true,
                                searchChoices: true,
                                placeholderValue: "Choose categories",
                                noChoicesText: "No categories available",
                                itemSelectText: "Press to select",
                                shouldSort: false,
                                searchPlaceholderValue: "Search categories..."
                            });
                        }
                    });
                } else {
                    console.log("Choices.js library not loaded, falling back to native multi-select");
                }
            });
            </script>
        '));

        // Row 1: Horizontal layout with main filters
        // Search field - takes up more space
        $fields->push(TextField::create('search', 'Search Events')
            ->setValue($request->getVar('search'))
            ->setAttribute('placeholder', 'Search event titles and descriptions...')
            ->setAttribute('class', 'form-control')
            ->addExtraClass('col-md-4 mb-3'));

        // Category filter (if enabled) - medium width
        if ($this->calendar->ShowCategoryFilter) {
            $availableCategories = $this->getAvailableCategories();
            if ($availableCategories->count()) {
                $fields->push(DropdownField::create('categories', 'Filter by Category')
                    ->setSource($availableCategories->map('ID', 'Title')->toArray())
                    ->setValue($request->getVar('categories'))
                    ->setAttribute('multiple', 'multiple')
                    ->addExtraClass('js-choice col-md-3 mb-3'));
            }
        }

        // Date range - compact side by side
        $fields->push(DateField::create('from', 'From Date')
            ->setValue($request->getVar('from'))
            ->setAttribute('class', 'form-control')
            ->addExtraClass('col-md-2 mb-3'));

        $fields->push(DateField::create('to', 'To Date')
            ->setValue($request->getVar('to'))
            ->setAttribute('class', 'form-control')
            ->addExtraClass('col-md-2 mb-3'));

        // Advanced filters - with simple toggle
        if ($this->calendar->ShowEventTypeFilter || $this->calendar->ShowAllDayFilter) {
            $showAdvanced = $request->getVar('advanced') ||
                           $request->getVar('eventType') ||
                           $request->getVar('allDay');

            // Always show the toggle button
            $fields->push(HiddenField::create('advanced', 'advanced')
                ->setValue($showAdvanced ? '1' : '0'));

            if ($showAdvanced) {
                // Event type filter
                if ($this->calendar->ShowEventTypeFilter) {
                    $fields->push(DropdownField::create('eventType', 'Event Type')
                        ->setSource([
                            '' => 'All Events',
                            'one-time' => 'One-Time Events',
                            'recurring' => 'Recurring Events'
                        ])
                        ->setValue($request->getVar('eventType'))
                        ->setAttribute('class', 'form-control')
                        ->addExtraClass('col-md-6 d-inline-block mt-2'));
                }

                // All-day filter
                if ($this->calendar->ShowAllDayFilter) {
                    $fields->push(DropdownField::create('allDay', 'Duration')
                        ->setSource([
                            '' => 'All Events',
                            '1' => 'All-Day Events',
                            '0' => 'Timed Events'
                        ])
                        ->setValue($request->getVar('allDay'))
                        ->setAttribute('class', 'form-control')
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

        // Apply Filters button
        $actions->push(FormAction::create('doFilter', 'Apply Filters')
            ->addExtraClass('btn btn-primary btn-lg px-4 me-2')
            ->setUseButtonTag(true)
            ->setAttribute('title', 'Apply the selected filters to view matching events'));

        // Clear Filters button will be added via JavaScript to avoid timing issues

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
        $filterVars = ['categories', 'eventType', 'allDay', 'search', 'from', 'to'];

        foreach ($filterVars as $var) {
            $value = $request->getVar($var);
            if ($value && $value !== '') {
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

    /**
     * Check if any filters are currently active (for template use)
     * This method provides a safe way to check for active filters from templates
     * without requiring direct access to the request object.
     *
     * @return bool
     */
    public function getHasActiveFilters(): bool
    {
        try {
            // Try to get the request from the controller
            $controller = $this->getController();
            if ($controller && method_exists($controller, 'getRequest')) {
                $request = $controller->getRequest();
                if ($request && $request instanceof HTTPRequest) {
                    return self::hasActiveFilters($request);
                }
            }

            // Fallback: try to get request from current controller
            if (class_exists('SilverStripe\Control\Controller')) {
                $currentController = \SilverStripe\Control\Controller::curr();
                if ($currentController && method_exists($currentController, 'getRequest')) {
                    $request = $currentController->getRequest();
                    if ($request && $request instanceof HTTPRequest) {
                        return self::hasActiveFilters($request);
                    }
                }
            }
        } catch (\LogicException $e) {
            // Log the error for debugging but don't break the page
            if (class_exists('SilverStripe\Core\Injector\Injector')) {
                $logger = \SilverStripe\Core\Injector\Injector::inst()->get('Psr\Log\LoggerInterface');
                $logger->warning('CalendarFilterForm: Logic error while checking active filters - ' . $e->getMessage());
            }
        } catch (\RuntimeException $e) {
            // Log the error for debugging but don't break the page
            if (class_exists('SilverStripe\Core\Injector\Injector')) {
                $logger = \SilverStripe\Core\Injector\Injector::inst()->get('Psr\Log\LoggerInterface');
                $logger->warning(
                    'CalendarFilterForm: Runtime error while checking active filters - ' . $e->getMessage()
                );
            }
        }

        // Safe fallback - no filters are active if we can't determine
        return false;
    }

    /**
     * Get the clear filters link (for template use)
     *
     * @return string
     */
    public function getClearFiltersLink(): string
    {
        return $this->calendar->Link();
    }
}
