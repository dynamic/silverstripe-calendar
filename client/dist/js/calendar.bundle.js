/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./client/src/js/calendar.js":
/*!***********************************!*\
  !*** ./client/src/js/calendar.js ***!
  \***********************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _scss_calendar_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../scss/calendar.scss */ "./client/src/scss/calendar.scss");
/* harmony import */ var choices_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! choices.js */ "./node_modules/choices.js/public/assets/scripts/choices.js");
/* harmony import */ var choices_js__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(choices_js__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _components_CalendarView__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/CalendarView */ "./client/src/js/components/CalendarView.js");
/* harmony import */ var _components_FullCalendarView__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/FullCalendarView */ "./client/src/js/components/FullCalendarView.js");
/* harmony import */ var _components_SmartFiltering__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./components/SmartFiltering */ "./client/src/js/components/SmartFiltering.js");
/* harmony import */ var _components_TouchInteractions__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./components/TouchInteractions */ "./client/src/js/components/TouchInteractions.js");
/* harmony import */ var _components_KeyboardNavigation__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./components/KeyboardNavigation */ "./client/src/js/components/KeyboardNavigation.js");
/* harmony import */ var _components_FilterEnhancements__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./components/FilterEnhancements */ "./client/src/js/components/FilterEnhancements.js");
// Calendar Frontend Module
// Bootstrap 5.3 + FullCalendar integration for Dynamic SilverStripe Calendar




// Import components







// Namespace for calendar utilities
const CalendarUtils = {};

// Function for Choices.js initialization (called by CalendarFilterForm)
CalendarUtils.initializeChoicesJS = function () {
  const multiSelectElements = document.querySelectorAll('.js-choice');
  const config = window.CalendarChoicesConfig || {};
  multiSelectElements.forEach(function (element) {
    if (element.tagName === 'SELECT' && !element.hasAttribute('data-choices-initialized')) {
      new (choices_js__WEBPACK_IMPORTED_MODULE_1___default())(element, config);
      element.setAttribute('data-choices-initialized', 'true');
    }
  });
};

// Expose CalendarUtils globally for backwards compatibility
window.CalendarUtils = CalendarUtils;
// Legacy support
window.initializeChoicesJS = CalendarUtils.initializeChoicesJS;
class CalendarModule {
  constructor() {
    this.init();
  }
  init() {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.initializeComponents());
    } else {
      this.initializeComponents();
    }
  }
  initializeComponents() {
    console.log('Initializing Dynamic Calendar Module...');

    // Initialize FullCalendar directly
    this.initializeFullCalendar();

    // Initialize filtering system
    const filterForm = document.querySelector('.calendar-filter-form');
    if (filterForm) {
      this.smartFiltering = new _components_SmartFiltering__WEBPACK_IMPORTED_MODULE_4__.SmartFiltering(filterForm);
    }

    // Initialize accessibility features
    this.keyboardNavigation = new _components_KeyboardNavigation__WEBPACK_IMPORTED_MODULE_6__.KeyboardNavigation();

    // Initialize mobile/touch features
    if (this.isTouchDevice()) {
      this.touchInteractions = new _components_TouchInteractions__WEBPACK_IMPORTED_MODULE_5__.TouchInteractions();
    }
    console.log('Calendar module initialized successfully');
  }
  initializeFullCalendar() {
    // Initialize main calendar page
    const calendarElement = document.querySelector('#fullcalendar');
    const fullCalendarSection = document.querySelector('#fullcalendar-view');
    if (calendarElement && fullCalendarSection) {
      // Get configuration from the parent container
      const eventsUrl = fullCalendarSection.dataset.eventsUrl;
      const calendarId = fullCalendarSection.dataset.calendarId;
      console.log('Initializing FullCalendar with events URL:', eventsUrl);
      try {
        this.calendarView = new _components_CalendarView__WEBPACK_IMPORTED_MODULE_2__.CalendarView(calendarElement, {
          eventsUrl: eventsUrl,
          calendarId: calendarId
        });
        console.log('FullCalendar initialized successfully');
      } catch (error) {
        console.error('Failed to initialize FullCalendar:', error);
      }
    }

    // Initialize element calendars
    this.initializeElementCalendars();
  }
  initializeElementCalendars() {
    const elementCalendars = document.querySelectorAll('.calendar-element-view');
    if (elementCalendars.length === 0) {
      console.warn('No calendar elements found');
      return;
    }
    elementCalendars.forEach((elementContainer, index) => {
      const calendarId = elementContainer.dataset.calendarId;
      const eventsUrl = elementContainer.dataset.eventsUrl;
      const eventLimit = parseInt(elementContainer.dataset.eventLimit) || 3;
      const defaultView = elementContainer.dataset.defaultView || 'dayGridMonth';

      // Find the target calendar div within this container
      const targetCalendar = elementContainer.querySelector('[id^="fullcalendar-"]');
      if (!targetCalendar) {
        console.warn('Calendar target div not found in element', elementContainer);
        return;
      }
      console.log(`Initializing Element Calendar ${index + 1} with events URL:`, eventsUrl);
      try {
        // Initialize with FullCalendarView component with all view options
        new _components_FullCalendarView__WEBPACK_IMPORTED_MODULE_3__.FullCalendarView(targetCalendar, {
          initialView: defaultView,
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridYear,timeGridWeek,timeGridDay,listMonth'
          },
          dayMaxEvents: eventLimit,
          height: 'auto',
          aspectRatio: 1.35,
          events: (info, successCallback, failureCallback) => {
            this.fetchElementCalendarEvents(eventsUrl, info, successCallback, failureCallback);
          }
        });
        console.log(`Element Calendar ${index + 1} initialized successfully`);
      } catch (error) {
        console.error(`Failed to initialize Element Calendar ${index + 1}:`, error);
      }
    });
  }
  fetchCalendarEvents(start, end, successCallback, failureCallback) {
    // Get current filter values
    const filterForm = document.querySelector('form[name="CalendarFilterForm"]');
    const params = new URLSearchParams();
    params.append('start', start.toISOString().split('T')[0]);
    params.append('end', end.toISOString().split('T')[0]);
    params.append('format', 'json');
    if (filterForm) {
      const formData = new FormData(filterForm);
      for (const [key, value] of formData.entries()) {
        if (value && key !== 'action_doFilter') {
          params.append(key, value);
        }
      }
    }

    // Fetch events from current page with AJAX
    const currentUrl = new URL(window.location);
    const eventsUrl = `${currentUrl.pathname}?${params.toString()}`;
    fetch(eventsUrl, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    }).then(events => {
      console.log('Fetched calendar events:', events.length);
      successCallback(events);
    }).catch(error => {
      console.error('Failed to fetch calendar events:', error);
      failureCallback(error);
    });
  }
  fetchElementCalendarEvents(eventsUrl, info, successCallback, failureCallback) {
    const params = new URLSearchParams();
    params.append('start', info.start.toISOString().split('T')[0]);
    params.append('end', info.end.toISOString().split('T')[0]);
    params.append('format', 'json');
    const fullEventsUrl = `${eventsUrl}?${params.toString()}`;
    fetch(fullEventsUrl, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    }).then(events => {
      console.log('Fetched element calendar events:', events.length);

      // Events are already in FullCalendar format from the Calendar module
      successCallback(events);
    }).catch(error => {
      console.error('Failed to fetch element calendar events:', error);
      failureCallback(error);
    });
  }
  isTouchDevice() {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  }
}

// Initialize when module loads
new CalendarModule();

/***/ }),

/***/ "./client/src/js/components/CalendarView.js":
/*!**************************************************!*\
  !*** ./client/src/js/components/CalendarView.js ***!
  \**************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   CalendarView: function() { return /* binding */ CalendarView; }
/* harmony export */ });
/* harmony import */ var _fullcalendar_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @fullcalendar/core */ "./node_modules/@fullcalendar/core/index.js");
/* harmony import */ var _fullcalendar_daygrid__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @fullcalendar/daygrid */ "./node_modules/@fullcalendar/daygrid/index.js");
/* harmony import */ var _fullcalendar_timegrid__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @fullcalendar/timegrid */ "./node_modules/@fullcalendar/timegrid/index.js");
/* harmony import */ var _fullcalendar_list__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @fullcalendar/list */ "./node_modules/@fullcalendar/list/index.js");
/* harmony import */ var _fullcalendar_bootstrap5__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @fullcalendar/bootstrap5 */ "./node_modules/@fullcalendar/bootstrap5/index.js");
/* harmony import */ var _fullcalendar_interaction__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @fullcalendar/interaction */ "./node_modules/@fullcalendar/interaction/index.js");
// FullCalendar Integration for Dynamic SilverStripe Calendar






class CalendarView {
  constructor(element, options = {}) {
    this.element = element;

    // Store custom configuration from options and data attributes
    this.config = {
      ...this.getConfigFromElement(),
      ...options
    };
    this.options = {
      plugins: [_fullcalendar_daygrid__WEBPACK_IMPORTED_MODULE_1__["default"], _fullcalendar_timegrid__WEBPACK_IMPORTED_MODULE_2__["default"], _fullcalendar_list__WEBPACK_IMPORTED_MODULE_3__["default"], _fullcalendar_bootstrap5__WEBPACK_IMPORTED_MODULE_4__["default"], _fullcalendar_interaction__WEBPACK_IMPORTED_MODULE_5__["default"]],
      themeSystem: 'bootstrap5',
      headerToolbar: this.getResponsiveHeaderToolbar(),
      // Responsive initial view - list on mobile, month on desktop
      initialView: this.config.defaultView || (window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth'),
      height: 'auto',
      aspectRatio: 1.8,
      eventDisplay: 'block',
      dayMaxEvents: true,
      moreLinkClick: 'popover',
      // Window resize handling for responsive behavior
      windowResizeDelay: 150
    };
    this.init();
  }
  init() {
    // Merge FullCalendar options only (exclude custom config)
    const finalOptions = {
      ...this.options,
      events: (info, successCallback, failureCallback) => {
        this.fetchEvents(info, successCallback, failureCallback);
      },
      eventClick: info => this.handleEventClick(info),
      dateClick: info => this.handleDateClick(info),
      eventDidMount: info => this.handleEventDidMount(info)
    };

    // Initialize FullCalendar
    this.calendar = new _fullcalendar_core__WEBPACK_IMPORTED_MODULE_0__.Calendar(this.element, finalOptions);
    this.calendar.render();

    // Store reference globally for debugging in development mode
    if (true) {
      window.fullCalendarInstance = this.calendar;
    }

    // Initialize mobile optimizations
    this.initializeMobileOptimizations();
  }
  getConfigFromElement() {
    const config = {};

    // Read configuration from data attributes
    if (this.element.dataset.calendarId) {
      config.calendarId = this.element.dataset.calendarId;
    }
    if (this.element.dataset.defaultView) {
      config.initialView = this.element.dataset.defaultView;
    }
    if (this.element.dataset.eventsUrl) {
      config.eventsUrl = this.element.dataset.eventsUrl;
    }
    return config;
  }
  async fetchEvents(info, successCallback, failureCallback) {
    const eventsUrl = this.config.eventsUrl;
    if (!eventsUrl) {
      console.error('Events URL not configured');
      failureCallback(new Error('Events URL not configured'));
      return;
    }
    const params = new URLSearchParams({
      start: info.startStr,
      end: info.endStr,
      format: 'json'
    });

    // Add any active filters
    const activeFilters = this.getActiveFilters();
    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value && key !== 'action_doFilter') {
        params.append(key, value);
      }
    });
    try {
      const response = await fetch(`${eventsUrl}?${params.toString()}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      const events = await response.json();
      console.log('Fetched events:', events);

      // Events should be an array directly from the server
      if (Array.isArray(events)) {
        successCallback(events);
      } else {
        console.error('Expected array of events, got:', events);
        failureCallback(new Error('Invalid events format'));
      }
    } catch (error) {
      console.error('Error fetching events:', error);
      failureCallback(error);
    }
  }
  transformEvents(events) {
    return events.map(event => ({
      id: event.ID,
      title: event.Title,
      start: event.StartDate + (event.StartTime ? 'T' + event.StartTime : ''),
      end: event.EndDate && event.EndTime ? event.EndDate + 'T' + event.EndTime : null,
      allDay: event.AllDay || !event.StartTime && !event.EndTime,
      url: event.Link,
      extendedProps: {
        summary: event.Summary,
        categories: event.Categories,
        isRecurring: event.Recursion !== 'NONE'
      },
      backgroundColor: this.getCategoryColor(event.Categories),
      borderColor: this.getCategoryColor(event.Categories)
    }));
  }
  getCategoryColor(categories) {
    // Simple color assignment based on first category
    if (!categories || categories.length === 0) return '#6c757d'; // Bootstrap secondary

    const colors = {
      'worship': '#0d6efd',
      // Bootstrap primary
      'education': '#198754',
      // Bootstrap success
      'fellowship': '#fd7e14',
      // Bootstrap warning
      'service': '#dc3545',
      // Bootstrap danger
      'music': '#6f42c1',
      // Bootstrap purple
      'youth': '#20c997' // Bootstrap teal
    };
    const firstCategory = categories[0].Title.toLowerCase();
    return colors[firstCategory] || '#6c757d';
  }
  getActiveFilters() {
    const filters = {};

    // Get filters from form elements
    const filterForm = document.querySelector('#CalendarFilterForm_FilterForm');
    if (filterForm) {
      const formData = new FormData(filterForm);
      for (let [key, value] of formData.entries()) {
        filters[key] = value;
      }
    }
    return filters;
  }
  handleEventClick(info) {
    info.jsEvent.preventDefault();

    // Custom event click handling
    const event = info.event;
    if (event.url) {
      // Open event detail page in same window
      window.location.href = event.url;
    } else {
      // Show event popup/modal
      this.showEventPopup(event);
    }
  }
  handleDateClick(info) {
    // Handle date clicks (could open "add event" interface)
    console.log('Date clicked:', info.dateStr);

    // Example: Navigate to date-specific view
    const url = new URL(window.location);
    url.searchParams.set('date', info.dateStr);
    window.history.pushState({}, '', url);
  }
  handleEventDidMount(info) {
    // Add tooltips or other enhancements when events are rendered
    const element = info.el;
    if (info.event.extendedProps.isRecurring) {
      element.classList.add('recurring-event');
      element.title = 'Recurring event';
    }
    if (info.event.extendedProps.summary) {
      element.title = info.event.extendedProps.summary;
    }
  }
  getResponsiveHeaderToolbar() {
    // Check screen size for responsive header layout
    const isSmallScreen = window.innerWidth < 768;
    const isTablet = window.innerWidth >= 768 && window.innerWidth < 1200;
    if (isSmallScreen) {
      // Mobile: All three views available, but start with list-friendly default
      return {
        left: 'prev,next',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      };
    } else if (isTablet) {
      // Tablet: All views with today button
      return {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      };
    } else {
      // Desktop: Full single-row layout
      return {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      };
    }
  }
  initializeMobileOptimizations() {
    // Simple resize handler - FullCalendar handles most responsive behavior automatically
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        // Just update the size and let FullCalendar handle the rest
        this.calendar.updateSize();

        // Update header toolbar for optimal layout
        this.calendar.setOption('headerToolbar', this.getResponsiveHeaderToolbar());
      }, 150);
    });
  }
  showEventPopup(event) {
    // Simple event popup - could be enhanced with Bootstrap modal
    const popup = document.createElement('div');
    popup.className = 'event-popup position-fixed';
    popup.style.cssText = `
      top: 50%; left: 50%; transform: translate(-50%, -50%);
      background: white; padding: 1rem; border-radius: 0.5rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1060;
      max-width: 400px; width: 90%;
    `;
    popup.innerHTML = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h5 class="mb-0">${event.title}</h5>
        <button type="button" class="btn-close" onclick="this.closest('.event-popup').remove()"></button>
      </div>
      <p class="text-muted mb-2">
        <i class="bi bi-calendar"></i> ${event.start.toLocaleDateString()}
        ${event.start.toLocaleTimeString()}
      </p>
      ${event.extendedProps.summary ? `<p>${event.extendedProps.summary}</p>` : ''}
    `;
    document.body.appendChild(popup);

    // Add backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'position-fixed';
    backdrop.style.cssText = 'top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1059;';
    backdrop.onclick = () => {
      popup.remove();
      backdrop.remove();
    };
    document.body.appendChild(backdrop);
  }
  destroy() {
    if (this.calendar) {
      this.calendar.destroy();
    }
  }
}

/***/ }),

/***/ "./client/src/js/components/FilterEnhancements.js":
/*!********************************************************!*\
  !*** ./client/src/js/components/FilterEnhancements.js ***!
  \********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   FilterEnhancements: function() { return /* binding */ FilterEnhancements; }
/* harmony export */ });
// Enhanced Filter Experience
class FilterEnhancements {
  constructor() {
    this.initCollapsibleFilters();
    this.initActiveFilterTracking();
    this.initKeyboardSupport();
    this.initFilterMemory();
  }
  initCollapsibleFilters() {
    const filterForm = document.querySelector('.calendar-filter-form');
    if (!filterForm) return;
    const header = filterForm.querySelector('.filter-header');
    const collapseTarget = filterForm.querySelector('#calendar-filters-content');
    if (!header || !collapseTarget) return;

    // Auto-expand if there are active filters
    const hasActiveFilters = filterForm.querySelector('.badge[aria-label="Active filters"]');
    if (hasActiveFilters) {
      collapseTarget.classList.add('show');
      header.setAttribute('aria-expanded', 'true');
    }

    // Enhanced collapse behavior
    collapseTarget.addEventListener('show.bs.collapse', () => {
      header.setAttribute('aria-expanded', 'true');
      this.focusFirstInput(collapseTarget);
      this.trackFilterAction('expand');
    });
    collapseTarget.addEventListener('hide.bs.collapse', () => {
      header.setAttribute('aria-expanded', 'false');
      this.trackFilterAction('collapse');
    });
  }
  initActiveFilterTracking() {
    const form = document.querySelector('.calendar-filter-form form');
    if (!form) return;
    let activeCount = 0;
    const formData = new FormData(form);

    // Initialize active count
    for (let [key, value] of formData.entries()) {
      if (key === 'SecurityID' || key === 'action_doFilter') continue;
      if (value && value.trim() !== '') {
        activeCount++;
      }
    }
    const updateActiveFiltersBadge = (fieldName, fieldValue) => {
      if (fieldName === 'SecurityID' || fieldName === 'action_doFilter') return;
      const isActive = fieldValue && fieldValue.trim() !== '';
      const fieldPreviouslyActive = formData.get(fieldName)?.trim() !== '';
      if (isActive && !fieldPreviouslyActive) {
        activeCount++;
      } else if (!isActive && fieldPreviouslyActive) {
        activeCount--;
      }
      formData.set(fieldName, fieldValue);
      this.updateFilterBadge(activeCount);
    };

    // Listen for form changes
    form.addEventListener('change', event => {
      const {
        name,
        value
      } = event.target;
      updateActiveFiltersBadge(name, value);
    });
    form.addEventListener('input', this.debounce(event => {
      const {
        name,
        value
      } = event.target;
      updateActiveFiltersBadge(name, value);
    }, 300));
  }
  updateFilterBadge(count) {
    const header = document.querySelector('.filter-header h5');
    if (!header) return;
    let badge = header.querySelector('.badge');
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge bg-primary ms-2';
        badge.setAttribute('aria-label', 'Active filters');
        header.appendChild(badge);
      }
      badge.textContent = count;
    } else if (badge) {
      badge.remove();
    }
  }
  initKeyboardSupport() {
    const header = document.querySelector('.filter-header');
    if (!header) return;
    header.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        header.click();
      }
    });

    // Add keyboard navigation within filters
    this.setupFormKeyboardNav();
  }
  setupFormKeyboardNav() {
    const form = document.querySelector('.calendar-filter-form form');
    if (!form) return;
    const focusableElements = form.querySelectorAll('input, select, button, [tabindex]:not([tabindex="-1"])');
    focusableElements.forEach((element, index) => {
      element.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          const nextIndex = (index + 1) % focusableElements.length;
          focusableElements[nextIndex].focus();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          const prevIndex = (index - 1 + focusableElements.length) % focusableElements.length;
          focusableElements[prevIndex].focus();
        }
      });
    });
  }
  initFilterMemory() {
    const form = document.querySelector('.calendar-filter-form form');
    if (!form) return;

    // Remember filter state in localStorage
    const saveFilters = () => {
      const formData = new FormData(form);
      const filters = {};
      for (let [key, value] of formData.entries()) {
        if (key !== 'SecurityID' && key !== 'action_doFilter') {
          filters[key] = value;
        }
      }
      localStorage.setItem('calendar-filters', JSON.stringify(filters));
    };

    // Auto-save on change
    form.addEventListener('change', saveFilters);
  }
  focusFirstInput(container) {
    const firstInput = container.querySelector('input, select');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 150);
    }
  }
  trackFilterAction(action) {
    // Analytics tracking for filter usage
    if (typeof gtag !== 'undefined') {
      gtag('event', 'calendar_filter', {
        event_category: 'engagement',
        event_label: action
      });
    }
  }
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  new FilterEnhancements();
});

/***/ }),

/***/ "./client/src/js/components/FullCalendarView.js":
/*!******************************************************!*\
  !*** ./client/src/js/components/FullCalendarView.js ***!
  \******************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   FullCalendarView: function() { return /* binding */ FullCalendarView; }
/* harmony export */ });
// FullCalendar View Component
// Handles FullCalendar integration and event rendering

class FullCalendarView {
  constructor(container, options = {}) {
    this.container = container;
    this.options = {
      initialView: 'dayGridMonth',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      },
      eventDisplay: 'block',
      dayMaxEvents: 3,
      moreLinkClick: 'popover',
      eventClick: this.handleEventClick.bind(this),
      eventDidMount: this.styleEvent.bind(this),
      loading: this.handleLoading.bind(this),
      ...options
    };
    this.calendar = null;
    this.eventCache = new Map();
    this.init();
  }
  async init() {
    try {
      // Dynamic import of FullCalendar
      const {
        Calendar
      } = await Promise.resolve(/*! import() */).then(__webpack_require__.bind(__webpack_require__, /*! @fullcalendar/core */ "./node_modules/@fullcalendar/core/index.js"));
      const dayGridPlugin = await Promise.resolve(/*! import() */).then(__webpack_require__.bind(__webpack_require__, /*! @fullcalendar/daygrid */ "./node_modules/@fullcalendar/daygrid/index.js"));
      const timeGridPlugin = await Promise.resolve(/*! import() */).then(__webpack_require__.bind(__webpack_require__, /*! @fullcalendar/timegrid */ "./node_modules/@fullcalendar/timegrid/index.js"));
      const listPlugin = await Promise.resolve(/*! import() */).then(__webpack_require__.bind(__webpack_require__, /*! @fullcalendar/list */ "./node_modules/@fullcalendar/list/index.js"));
      const interactionPlugin = await Promise.resolve(/*! import() */).then(__webpack_require__.bind(__webpack_require__, /*! @fullcalendar/interaction */ "./node_modules/@fullcalendar/interaction/index.js"));

      // Configure calendar with plugins
      this.calendar = new Calendar(this.container, {
        ...this.options,
        plugins: [dayGridPlugin.default, timeGridPlugin.default, listPlugin.default, interactionPlugin.default],
        // Use provided events function if available, otherwise use loadEvents
        events: this.options.events || this.loadEvents.bind(this)
      });
      this.calendar.render();
      this.bindCustomEvents();
    } catch (error) {
      console.error('Failed to initialize FullCalendar:', error);
      this.showFallbackView();
    }
  }
  async loadEvents(info, successCallback, failureCallback) {
    try {
      const response = await fetch(this.buildEventUrl(info.start, info.end));
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      const events = await response.json();

      // Cache events
      events.forEach(event => {
        this.eventCache.set(event.id, event);
      });

      // Transform events for FullCalendar
      const transformedEvents = this.transformEvents(events);
      successCallback(transformedEvents);
    } catch (error) {
      console.error('Failed to load events:', error);
      failureCallback(error);
    }
  }
  buildEventUrl(start, end) {
    const params = new URLSearchParams({
      start: start.toISOString().split('T')[0],
      end: end.toISOString().split('T')[0],
      format: 'json'
    });

    // Use current page URL as base
    const baseUrl = window.location.pathname;
    return `${baseUrl}events?${params.toString()}`;
  }
  transformEvents(events) {
    return events.map(event => ({
      id: event.ID,
      title: event.Title,
      start: event.AllDay ? event.StartDate : `${event.StartDate}T${event.StartTime}`,
      end: event.AllDay ? null : `${event.EndDate}T${event.EndTime}`,
      allDay: event.AllDay,
      url: event.Link,
      className: this.getEventClasses(event),
      extendedProps: {
        description: event.Content,
        location: event.Location,
        category: event.Category,
        isRecurring: event.IsRecurring,
        originalEvent: event
      }
    }));
  }
  getEventClasses(event) {
    const classes = ['calendar-event'];
    if (event.AllDay) {
      classes.push('all-day-event');
    }
    if (event.IsRecurring) {
      classes.push('recurring-event');
    }
    if (event.Category) {
      classes.push(`category-${event.Category.toLowerCase().replace(/\s+/g, '-')}`);
    }
    return classes.join(' ');
  }
  styleEvent(info) {
    const event = info.event;
    const element = info.el;

    // Add Bootstrap classes
    element.classList.add('border-0', 'rounded');

    // Add category-specific styling
    const category = event.extendedProps.category;
    if (category) {
      const categoryClass = `bg-${this.getCategoryColor(category)}`;
      element.classList.add(categoryClass);
    }

    // Add accessibility attributes
    element.setAttribute('role', 'button');
    element.setAttribute('tabindex', '0');
    element.setAttribute('aria-label', `Event: ${event.title}`);

    // Add tooltip for description
    if (event.extendedProps.description) {
      element.setAttribute('title', this.stripHtml(event.extendedProps.description));
      element.setAttribute('data-bs-toggle', 'tooltip');
      element.setAttribute('data-bs-placement', 'top');
    }
  }
  getCategoryColor(category) {
    const colorMap = {
      'service': 'primary',
      'meeting': 'secondary',
      'study': 'info',
      'fellowship': 'success',
      'outreach': 'warning',
      'special': 'danger',
      'youth': 'light',
      'children': 'dark'
    };
    const key = category.toLowerCase().replace(/\s+/g, '');
    return colorMap[key] || 'secondary';
  }
  handleEventClick(info) {
    info.jsEvent.preventDefault();
    const event = info.event;

    // Emit custom event for other components
    this.container.dispatchEvent(new CustomEvent('calendar:eventClick', {
      detail: {
        event: event,
        originalEvent: event.extendedProps.originalEvent,
        jsEvent: info.jsEvent
      }
    }));

    // Show event details modal or navigate to event page
    if (event.url) {
      this.showEventModal(event);
    }
  }
  showEventModal(event) {
    // Create Bootstrap modal for event details
    const modalHtml = `
      <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="eventModalLabel">${event.title}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="event-details">
                ${this.renderEventDetails(event)}
              </div>
            </div>
            <div class="modal-footer">
              <a href="${event.url}" class="btn btn-primary">View Full Details</a>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    `;

    // Remove existing modal
    const existingModal = document.getElementById('eventModal');
    if (existingModal) {
      existingModal.remove();
    }

    // Add new modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal using Bootstrap
    const modal = new bootstrap.Modal(document.getElementById('eventModal'));
    modal.show();
  }
  renderEventDetails(event) {
    const props = event.extendedProps;
    let html = '';

    // Date and time
    html += `<p><strong>Date:</strong> ${this.formatEventDate(event)}</p>`;

    // Location
    if (props.location) {
      html += `<p><strong>Location:</strong> ${props.location}</p>`;
    }

    // Category
    if (props.category) {
      html += `<p><strong>Category:</strong> ${props.category}</p>`;
    }

    // Description
    if (props.description) {
      html += `<div><strong>Description:</strong><div class="mt-2">${props.description}</div></div>`;
    }
    return html;
  }
  formatEventDate(event) {
    const start = new Date(event.start);
    const end = event.end ? new Date(event.end) : null;
    if (event.allDay) {
      if (end && start.toDateString() !== end.toDateString()) {
        return `${start.toLocaleDateString()} - ${end.toLocaleDateString()}`;
      } else {
        return start.toLocaleDateString();
      }
    } else {
      const dateStr = start.toLocaleDateString();
      const startTime = start.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
      });
      if (end) {
        const endTime = end.toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit'
        });
        return `${dateStr} ${startTime} - ${endTime}`;
      } else {
        return `${dateStr} ${startTime}`;
      }
    }
  }
  handleLoading(isLoading) {
    const spinner = this.container.querySelector('.calendar-loading');
    if (spinner) {
      spinner.style.display = isLoading ? 'block' : 'none';
    }

    // Emit loading event
    this.container.dispatchEvent(new CustomEvent('calendar:loading', {
      detail: {
        isLoading
      }
    }));
  }
  bindCustomEvents() {
    // Filter events
    document.addEventListener('calendar:filter', e => {
      this.applyFilters(e.detail.filters);
    });

    // View change events
    document.addEventListener('calendar:changeView', e => {
      if (this.calendar) {
        this.calendar.changeView(e.detail.view);
      }
    });

    // Navigate events
    document.addEventListener('calendar:navigate', e => {
      if (this.calendar) {
        const {
          direction,
          date
        } = e.detail;
        if (date) {
          this.calendar.gotoDate(date);
        } else if (direction === 'prev') {
          this.calendar.prev();
        } else if (direction === 'next') {
          this.calendar.next();
        } else if (direction === 'today') {
          this.calendar.today();
        }
      }
    });
  }
  applyFilters(filters) {
    if (!this.calendar) return;

    // Refetch events with filters
    this.calendar.refetchEvents();
  }
  showFallbackView() {
    // Show a simple list view if FullCalendar fails to load
    this.container.innerHTML = `
      <div class="alert alert-warning">
        <h5>Calendar View Unavailable</h5>
        <p>The calendar view could not be loaded. Please refresh the page or try again later.</p>
        <a href="${window.location.pathname}?view=list" class="btn btn-primary">View Events List</a>
      </div>
    `;
  }
  stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
  }

  // Public API methods
  getCalendar() {
    return this.calendar;
  }
  refresh() {
    if (this.calendar) {
      this.calendar.refetchEvents();
    }
  }
  destroy() {
    if (this.calendar) {
      this.calendar.destroy();
    }
  }
}

// Auto-initialize for calendar containers
document.addEventListener('DOMContentLoaded', () => {
  const calendarContainers = document.querySelectorAll('.fullcalendar-view');
  calendarContainers.forEach(container => {
    if (!container.dataset.initialized) {
      new FullCalendarView(container);
      container.dataset.initialized = 'true';
    }
  });
});

// Export for manual initialization
/* harmony default export */ __webpack_exports__["default"] = (FullCalendarView);

/***/ }),

/***/ "./client/src/js/components/KeyboardNavigation.js":
/*!********************************************************!*\
  !*** ./client/src/js/components/KeyboardNavigation.js ***!
  \********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   KeyboardNavigation: function() { return /* binding */ KeyboardNavigation; }
/* harmony export */ });
// Keyboard Navigation Component
// Provides comprehensive keyboard accessibility

class KeyboardNavigation {
  constructor() {
    this.initEventCardNavigation();
    this.initCalendarNavigation();
    this.initFilterNavigation();
    this.initSkipLinks();
    this.currentFocusIndex = 0;
  }
  initEventCardNavigation() {
    const eventCards = document.querySelectorAll('.event-card[tabindex="0"]');
    eventCards.forEach((card, index) => {
      card.addEventListener('keydown', e => {
        switch (e.key) {
          case 'Enter':
          case ' ':
            e.preventDefault();
            this.activateEvent(card);
            break;
          case 'ArrowRight':
          case 'ArrowDown':
            e.preventDefault();
            this.focusNextCard(eventCards, index);
            break;
          case 'ArrowLeft':
          case 'ArrowUp':
            e.preventDefault();
            this.focusPrevCard(eventCards, index);
            break;
          case 'Home':
            e.preventDefault();
            this.focusFirstCard(eventCards);
            break;
          case 'End':
            e.preventDefault();
            this.focusLastCard(eventCards);
            break;
          case 'Escape':
            e.preventDefault();
            this.clearFocus();
            break;
        }
      });
    });
  }
  initCalendarNavigation() {
    // FullCalendar keyboard enhancements
    const calendar = document.querySelector('.fc');
    if (!calendar) return;

    // Add keyboard support for calendar navigation
    calendar.addEventListener('keydown', e => {
      if (e.target.classList.contains('fc-daygrid-day')) {
        this.handleDayNavigation(e);
      } else if (e.target.classList.contains('fc-event')) {
        this.handleEventNavigation(e);
      }
    });

    // Month navigation shortcuts
    document.addEventListener('keydown', e => {
      // Only if no input is focused
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
      switch (e.key) {
        case 'n':
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.navigateToToday();
          }
          break;
        case 'ArrowLeft':
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.navigateToPrevious();
          }
          break;
        case 'ArrowRight':
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.navigateToNext();
          }
          break;
      }
    });
  }
  handleDayNavigation(e) {
    const currentDay = e.target;
    const allDays = Array.from(document.querySelectorAll('.fc-daygrid-day'));
    const currentIndex = allDays.indexOf(currentDay);
    switch (e.key) {
      case 'ArrowLeft':
        e.preventDefault();
        this.focusDay(allDays, currentIndex - 1);
        break;
      case 'ArrowRight':
        e.preventDefault();
        this.focusDay(allDays, currentIndex + 1);
        break;
      case 'ArrowUp':
        e.preventDefault();
        this.focusDay(allDays, currentIndex - 7);
        break;
      case 'ArrowDown':
        e.preventDefault();
        this.focusDay(allDays, currentIndex + 7);
        break;
      case 'Enter':
      case ' ':
        e.preventDefault();
        this.selectDay(currentDay);
        break;
    }
  }
  handleEventNavigation(e) {
    const currentEvent = e.target;
    switch (e.key) {
      case 'Enter':
      case ' ':
        e.preventDefault();
        this.activateCalendarEvent(currentEvent);
        break;
      case 'Escape':
        e.preventDefault();
        currentEvent.blur();
        break;
    }
  }
  initFilterNavigation() {
    const filterForm = document.querySelector('.calendar-filters');
    if (!filterForm) return;

    // Quick filter navigation
    const quickFilters = document.querySelectorAll('.quick-filter');
    quickFilters.forEach((filter, index) => {
      filter.addEventListener('keydown', e => {
        switch (e.key) {
          case 'ArrowLeft':
            e.preventDefault();
            this.focusQuickFilter(quickFilters, index - 1);
            break;
          case 'ArrowRight':
            e.preventDefault();
            this.focusQuickFilter(quickFilters, index + 1);
            break;
        }
      });
    });
  }
  initSkipLinks() {
    // Add skip link if not present
    let skipLink = document.querySelector('.skip-link');
    if (!skipLink) {
      skipLink = document.createElement('a');
      skipLink.className = 'skip-link';
      skipLink.href = '#calendar-main';
      skipLink.textContent = 'Skip to calendar content';
      document.body.insertBefore(skipLink, document.body.firstChild);
    }

    // Ensure main content has proper ID
    const mainContent = document.querySelector('.calendar-container');
    if (mainContent && !mainContent.id) {
      mainContent.id = 'calendar-main';
    }
  }

  // Navigation helper methods
  focusNextCard(cards, currentIndex) {
    const nextIndex = (currentIndex + 1) % cards.length;
    this.focusCard(cards[nextIndex]);
  }
  focusPrevCard(cards, currentIndex) {
    const prevIndex = currentIndex === 0 ? cards.length - 1 : currentIndex - 1;
    this.focusCard(cards[prevIndex]);
  }
  focusFirstCard(cards) {
    if (cards.length > 0) {
      this.focusCard(cards[0]);
    }
  }
  focusLastCard(cards) {
    if (cards.length > 0) {
      this.focusCard(cards[cards.length - 1]);
    }
  }
  focusCard(card) {
    if (card) {
      card.focus();
      this.announceToScreenReader(`Focused on event: ${this.getCardTitle(card)}`);
    }
  }
  focusDay(days, index) {
    if (index >= 0 && index < days.length) {
      days[index].focus();
      this.announceToScreenReader(`Focused on ${this.getDayLabel(days[index])}`);
    }
  }
  focusQuickFilter(filters, index) {
    if (index >= 0 && index < filters.length) {
      filters[index].focus();
    }
  }
  activateEvent(card) {
    const link = card.querySelector('a');
    if (link) {
      link.click();
    } else {
      // Trigger custom event for card activation
      card.dispatchEvent(new CustomEvent('eventActivated', {
        detail: {
          card
        }
      }));
    }
  }
  activateCalendarEvent(eventElement) {
    // Trigger FullCalendar event click
    eventElement.click();
  }
  selectDay(dayElement) {
    // Add selected state
    document.querySelectorAll('.fc-daygrid-day').forEach(day => {
      day.removeAttribute('aria-selected');
    });
    dayElement.setAttribute('aria-selected', 'true');
    this.announceToScreenReader(`Selected ${this.getDayLabel(dayElement)}`);
  }
  navigateToToday() {
    const todayButton = document.querySelector('.fc-today-button');
    if (todayButton) {
      todayButton.click();
      this.announceToScreenReader('Navigated to today');
    }
  }
  navigateToPrevious() {
    const prevButton = document.querySelector('.fc-prev-button');
    if (prevButton && !prevButton.disabled) {
      prevButton.click();
      this.announceToScreenReader('Navigated to previous period');
    }
  }
  navigateToNext() {
    const nextButton = document.querySelector('.fc-next-button');
    if (nextButton && !nextButton.disabled) {
      nextButton.click();
      this.announceToScreenReader('Navigated to next period');
    }
  }
  clearFocus() {
    document.activeElement?.blur();
  }

  // Helper methods
  getCardTitle(card) {
    const titleElement = card.querySelector('.event-title, .card-title, h1, h2, h3, h4, h5, h6');
    return titleElement ? titleElement.textContent.trim() : 'Untitled event';
  }
  getDayLabel(dayElement) {
    const dateAttr = dayElement.getAttribute('data-date');
    if (dateAttr) {
      const date = new Date(dateAttr);
      return date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    }
    return 'Unknown date';
  }
  announceToScreenReader(message) {
    // Create or update live region for screen reader announcements
    let liveRegion = document.querySelector('.live-region[aria-live="polite"]');
    if (!liveRegion) {
      liveRegion = document.createElement('div');
      liveRegion.className = 'live-region';
      liveRegion.setAttribute('aria-live', 'polite');
      liveRegion.setAttribute('aria-atomic', 'true');
      document.body.appendChild(liveRegion);
    }
    liveRegion.textContent = message;
  }
}

/***/ }),

/***/ "./client/src/js/components/SmartFiltering.js":
/*!****************************************************!*\
  !*** ./client/src/js/components/SmartFiltering.js ***!
  \****************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SmartFiltering: function() { return /* binding */ SmartFiltering; }
/* harmony export */ });
// Smart Filtering Component
// Provides predictive filtering and search capabilities

class SmartFiltering {
  constructor() {
    this.filterHistory = this.loadFilterHistory();
    this.initPredictiveFilters();
    this.initSavedFilters();
    this.initAutoComplete();
  }
  initPredictiveFilters() {
    // Suggest popular filter combinations based on history
    if (this.filterHistory && this.filterHistory.length > 0) {
      this.showFilterSuggestions(this.filterHistory);
    }
  }
  initSavedFilters() {
    // Initialize saved filter functionality
    const savedFilters = this.loadSavedFilters();
    this.renderSavedFilters(savedFilters);
  }
  initAutoComplete() {
    const searchInput = document.querySelector('#event-search');
    if (!searchInput) return;
    let searchTimeout;
    searchInput.addEventListener('input', e => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        this.performSearch(e.target.value);
      }, 300);
    });
  }
  performSearch(query) {
    if (query.length < 2) return;

    // Simulate API call - in real implementation this would be an AJAX request
    const suggestions = this.generateSuggestions(query);
    this.showSearchSuggestions(suggestions);
  }
  generateSuggestions(query) {
    // Mock data - in real implementation this would come from the server
    const mockEvents = [{
      title: 'Christmas Service',
      date: '2025-12-25',
      type: 'service'
    }, {
      title: 'Youth Group Meeting',
      date: '2025-07-30',
      type: 'meeting'
    }, {
      title: 'Bible Study',
      date: '2025-07-31',
      type: 'study'
    }];
    return mockEvents.filter(event => event.title.toLowerCase().includes(query.toLowerCase()));
  }
  showSearchSuggestions(suggestions) {
    const suggestionsContainer = document.querySelector('.search-suggestions');
    if (!suggestionsContainer) return;
    if (suggestions.length === 0) {
      suggestionsContainer.style.display = 'none';
      return;
    }
    suggestionsContainer.innerHTML = suggestions.map(suggestion => `
      <div class="search-suggestion" data-id="${suggestion.id}">
        <div class="suggestion-title">${suggestion.title}</div>
        <div class="suggestion-meta">${suggestion.date} • ${suggestion.type}</div>
      </div>
    `).join('');
    suggestionsContainer.style.display = 'block';
  }
  showFilterSuggestions(history) {
    // Show suggested filter combinations
    console.log('Showing filter suggestions based on history:', history);
  }
  loadFilterHistory() {
    try {
      return JSON.parse(localStorage.getItem('calendar-filter-history') || '[]');
    } catch (e) {
      return [];
    }
  }
  loadSavedFilters() {
    try {
      return JSON.parse(localStorage.getItem('calendar-saved-filters') || '[]');
    } catch (e) {
      return [];
    }
  }
  renderSavedFilters(filters) {
    // Render saved filter dropdown
    console.log('Rendering saved filters:', filters);
  }
  saveCurrentFilter(name, filterData) {
    const savedFilters = this.loadSavedFilters();
    savedFilters.push({
      name,
      data: filterData,
      created: new Date().toISOString()
    });
    localStorage.setItem('calendar-saved-filters', JSON.stringify(savedFilters));
  }
  addToHistory(filterData) {
    const history = this.loadFilterHistory();
    history.unshift(filterData);
    // Keep only last 10 filter combinations
    const trimmedHistory = history.slice(0, 10);
    localStorage.setItem('calendar-filter-history', JSON.stringify(trimmedHistory));
  }
}

/***/ }),

/***/ "./client/src/js/components/TouchInteractions.js":
/*!*******************************************************!*\
  !*** ./client/src/js/components/TouchInteractions.js ***!
  \*******************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   TouchInteractions: function() { return /* binding */ TouchInteractions; }
/* harmony export */ });
// Touch Interactions Component
// Handles touch gestures and mobile interactions

class TouchInteractions {
  constructor() {
    this.initSwipeNavigation();
    this.initPullToRefresh();
    this.initTouchOptimizations();
    this.gestureThreshold = 50; // Minimum swipe distance
  }
  initSwipeNavigation() {
    let startX, startY, startTime;
    const calendar = document.querySelector('.calendar-container');
    if (!calendar) return;
    calendar.addEventListener('touchstart', e => {
      const touch = e.touches[0];
      startX = touch.clientX;
      startY = touch.clientY;
      startTime = Date.now();
    }, {
      passive: true
    });
    calendar.addEventListener('touchend', e => {
      if (!startX || !startY) return;
      const touch = e.changedTouches[0];
      const endX = touch.clientX;
      const endY = touch.clientY;
      const endTime = Date.now();
      const deltaX = endX - startX;
      const deltaY = endY - startY;
      const deltaTime = endTime - startTime;

      // Only process quick swipes (under 300ms)
      if (deltaTime > 300) return;

      // Horizontal swipe for month/week navigation
      if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > this.gestureThreshold) {
        e.preventDefault();
        if (deltaX > 0) {
          this.navigatePrevious();
          this.showSwipeIndicator('right');
        } else {
          this.navigateNext();
          this.showSwipeIndicator('left');
        }
      }

      // Reset coordinates
      startX = startY = null;
    }, {
      passive: false
    });
  }
  navigatePrevious() {
    // Navigate to previous month/week
    const prevButton = document.querySelector('.fc-prev-button');
    if (prevButton && !prevButton.disabled) {
      prevButton.click();
    }
  }
  navigateNext() {
    // Navigate to next month/week
    const nextButton = document.querySelector('.fc-next-button');
    if (nextButton && !nextButton.disabled) {
      nextButton.click();
    }
  }
  showSwipeIndicator(direction) {
    const indicator = document.createElement('div');
    indicator.className = `swipe-indicator swipe-${direction}`;
    indicator.innerHTML = direction === 'left' ? '→' : '←';
    document.body.appendChild(indicator);

    // Show indicator briefly
    setTimeout(() => indicator.classList.add('show'), 10);
    setTimeout(() => {
      indicator.classList.remove('show');
      setTimeout(() => document.body.removeChild(indicator), 300);
    }, 500);
  }
  initPullToRefresh() {
    let startY = 0;
    let currentY = 0;
    let isPulling = false;
    const calendar = document.querySelector('.calendar-container');
    if (!calendar) return;
    calendar.addEventListener('touchstart', e => {
      if (calendar.scrollTop === 0) {
        startY = e.touches[0].clientY;
      }
    }, {
      passive: true
    });
    calendar.addEventListener('touchmove', e => {
      if (startY === 0) return;
      currentY = e.touches[0].clientY;
      const pullDistance = currentY - startY;
      if (pullDistance > 0 && calendar.scrollTop === 0) {
        isPulling = true;

        // Show pull indicator
        if (pullDistance > 80) {
          this.showPullRefreshIndicator(true);
        } else {
          this.showPullRefreshIndicator(false);
        }
      }
    }, {
      passive: true
    });
    calendar.addEventListener('touchend', () => {
      if (isPulling && currentY - startY > 80) {
        this.performRefresh();
      }
      this.hidePullRefreshIndicator();
      startY = 0;
      currentY = 0;
      isPulling = false;
    }, {
      passive: true
    });
  }
  showPullRefreshIndicator(ready) {
    let indicator = document.querySelector('.pull-refresh-indicator');
    if (!indicator) {
      indicator = document.createElement('div');
      indicator.className = 'pull-refresh-indicator';
      indicator.innerHTML = `
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>${ready ? 'Release to refresh' : 'Pull to refresh'}</span>
      `;
      document.body.appendChild(indicator);
    }
    indicator.classList.toggle('active', true);
    indicator.querySelector('span').textContent = ready ? 'Release to refresh' : 'Pull to refresh';
  }
  hidePullRefreshIndicator() {
    const indicator = document.querySelector('.pull-refresh-indicator');
    if (indicator) {
      indicator.classList.remove('active');
    }
  }
  performRefresh() {
    // Refresh calendar data
    console.log('Refreshing calendar data...');

    // Show loading state
    const indicator = document.querySelector('.pull-refresh-indicator');
    if (indicator) {
      indicator.querySelector('span').textContent = 'Refreshing...';
    }

    // Simulate refresh (in real implementation, this would refetch data)
    setTimeout(() => {
      this.hidePullRefreshIndicator();
      // Optionally show success message
      this.showRefreshSuccess();
    }, 1000);
  }
  showRefreshSuccess() {
    // Show brief success message
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-success border-0';
    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">Calendar refreshed!</div>
      </div>
    `;
    document.body.appendChild(toast);

    // Auto-hide toast
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 2000);
  }
  initTouchOptimizations() {
    // Improve touch responsiveness
    document.addEventListener('touchstart', () => {}, {
      passive: true
    });

    // Prevent zoom on double-tap for specific elements
    const preventZoom = document.querySelectorAll('.event-card, .fc-event, .btn');
    preventZoom.forEach(el => {
      el.style.touchAction = 'manipulation';
    });

    // Improve scrolling performance
    const scrollElements = document.querySelectorAll('.calendar-container, .event-list');
    scrollElements.forEach(el => {
      el.style.webkitOverflowScrolling = 'touch';
    });
  }
}

/***/ }),

/***/ "./client/src/scss/calendar.scss":
/*!***************************************!*\
  !*** ./client/src/scss/calendar.scss ***!
  \***************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js */ "./node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/styleDomAPI.js */ "./node_modules/style-loader/dist/runtime/styleDomAPI.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/insertBySelector.js */ "./node_modules/style-loader/dist/runtime/insertBySelector.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/setAttributesWithoutAttributes.js */ "./node_modules/style-loader/dist/runtime/setAttributesWithoutAttributes.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/insertStyleElement.js */ "./node_modules/style-loader/dist/runtime/insertStyleElement.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/styleTagTransform.js */ "./node_modules/style-loader/dist/runtime/styleTagTransform.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _node_modules_css_loader_dist_cjs_js_ruleSet_1_rules_1_use_1_node_modules_sass_loader_dist_cjs_js_ruleSet_1_rules_1_use_2_calendar_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! !!../../../node_modules/css-loader/dist/cjs.js??ruleSet[1].rules[1].use[1]!../../../node_modules/sass-loader/dist/cjs.js??ruleSet[1].rules[1].use[2]!./calendar.scss */ "./node_modules/css-loader/dist/cjs.js??ruleSet[1].rules[1].use[1]!./node_modules/sass-loader/dist/cjs.js??ruleSet[1].rules[1].use[2]!./client/src/scss/calendar.scss");

      
      
      
      
      
      
      
      
      

var options = {};

options.styleTagTransform = (_node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5___default());
options.setAttributes = (_node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3___default());

      options.insert = _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2___default().bind(null, "head");
    
options.domAPI = (_node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1___default());
options.insertStyleElement = (_node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4___default());

var update = _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default()(_node_modules_css_loader_dist_cjs_js_ruleSet_1_rules_1_use_1_node_modules_sass_loader_dist_cjs_js_ruleSet_1_rules_1_use_2_calendar_scss__WEBPACK_IMPORTED_MODULE_6__["default"], options);




       /* harmony default export */ __webpack_exports__["default"] = (_node_modules_css_loader_dist_cjs_js_ruleSet_1_rules_1_use_1_node_modules_sass_loader_dist_cjs_js_ruleSet_1_rules_1_use_2_calendar_scss__WEBPACK_IMPORTED_MODULE_6__["default"] && _node_modules_css_loader_dist_cjs_js_ruleSet_1_rules_1_use_1_node_modules_sass_loader_dist_cjs_js_ruleSet_1_rules_1_use_2_calendar_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals ? _node_modules_css_loader_dist_cjs_js_ruleSet_1_rules_1_use_1_node_modules_sass_loader_dist_cjs_js_ruleSet_1_rules_1_use_2_calendar_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals : undefined);


/***/ }),

/***/ "./node_modules/css-loader/dist/cjs.js??ruleSet[1].rules[1].use[1]!./node_modules/sass-loader/dist/cjs.js??ruleSet[1].rules[1].use[2]!./client/src/scss/calendar.scss":
/*!****************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js??ruleSet[1].rules[1].use[1]!./node_modules/sass-loader/dist/cjs.js??ruleSet[1].rules[1].use[2]!./client/src/scss/calendar.scss ***!
  \****************************************************************************************************************************************************************************/
/***/ (function(module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_css_loader_dist_runtime_sourceMaps_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/css-loader/dist/runtime/sourceMaps.js */ "./node_modules/css-loader/dist/runtime/sourceMaps.js");
/* harmony import */ var _node_modules_css_loader_dist_runtime_sourceMaps_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_css_loader_dist_runtime_sourceMaps_js__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../node_modules/css-loader/dist/runtime/api.js */ "./node_modules/css-loader/dist/runtime/api.js");
/* harmony import */ var _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _node_modules_css_loader_dist_cjs_js_ruleSet_1_rules_1_use_1_node_modules_choices_js_public_assets_styles_choices_min_css__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! -!../../../node_modules/css-loader/dist/cjs.js??ruleSet[1].rules[1].use[1]!../../../node_modules/choices.js/public/assets/styles/choices.min.css */ "./node_modules/css-loader/dist/cjs.js??ruleSet[1].rules[1].use[1]!./node_modules/choices.js/public/assets/styles/choices.min.css");
// Imports



var ___CSS_LOADER_EXPORT___ = _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_1___default()((_node_modules_css_loader_dist_runtime_sourceMaps_js__WEBPACK_IMPORTED_MODULE_0___default()));
___CSS_LOADER_EXPORT___.i(_node_modules_css_loader_dist_cjs_js_ruleSet_1_rules_1_use_1_node_modules_choices_js_public_assets_styles_choices_min_css__WEBPACK_IMPORTED_MODULE_2__["default"]);
// Module
___CSS_LOADER_EXPORT___.push([module.id, "@charset \"UTF-8\";\n@media (min-width: 768px) {\n  .fc .fc-header-toolbar {\n    display: flex;\n    justify-content: space-between;\n    align-items: center;\n    flex-wrap: nowrap;\n    gap: 1rem;\n  }\n  .fc .fc-header-toolbar .fc-toolbar-chunk {\n    display: flex;\n    align-items: center;\n    gap: 0.5rem;\n  }\n  .fc .fc-header-toolbar .fc-toolbar-title {\n    font-size: 1.5rem;\n    white-space: nowrap;\n    overflow: hidden;\n    text-overflow: ellipsis;\n    min-width: 0;\n    flex: 1;\n    text-align: center;\n  }\n}\n@media (min-width: 768px) and (min-width: 992px) {\n  .fc .fc-header-toolbar .fc-toolbar-title {\n    font-size: 1.75rem;\n  }\n}\n@media (max-width: 767.98px) {\n  .fc .fc-header-toolbar .fc-toolbar-title {\n    font-size: 1.25rem;\n    margin: 0.5rem 0;\n  }\n  .fc .fc-header-toolbar .fc-button-group .fc-button {\n    font-size: 0.875rem;\n    padding: 0.25rem 0.5rem;\n  }\n  .fc .fc-daygrid-event {\n    font-size: 0.75rem;\n    padding: 1px 2px;\n  }\n  .fc .fc-list-event .fc-list-event-title {\n    font-size: 0.875rem;\n  }\n  .fc .fc-button {\n    min-height: 44px;\n    min-width: 44px;\n  }\n  .fc .fc-daygrid-day {\n    min-height: 44px;\n  }\n}\n@media (max-width: 575.98px) {\n  .fc .fc-header-toolbar .fc-toolbar-title {\n    font-size: 1.125rem;\n  }\n  .fc .fc-button-group .fc-button {\n    font-size: 0.75rem;\n    padding: 0.2rem 0.4rem;\n  }\n}\n\n.choices {\n  margin-bottom: 0;\n}\n.choices .choices__inner {\n  background-color: var(--bs-body-bg);\n  border: var(--bs-border-width) solid var(--bs-border-color);\n  border-radius: var(--bs-border-radius);\n  color: var(--bs-body-color);\n  font-size: var(--bs-body-font-size);\n  min-height: calc(1.5em + 0.75rem + 2px);\n  padding: 0.375rem 0.75rem;\n}\n.choices .choices__inner:focus-within {\n  border-color: var(--bs-primary);\n  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);\n}\n.choices .choices__list--dropdown {\n  background-color: var(--bs-body-bg);\n  border: var(--bs-border-width) solid var(--bs-border-color);\n  border-radius: var(--bs-border-radius);\n  box-shadow: var(--bs-box-shadow);\n  z-index: 1050;\n}\n.choices .choices__list--dropdown .choices__item {\n  color: var(--bs-body-color);\n}\n.choices .choices__list--dropdown .choices__item:hover, .choices .choices__list--dropdown .choices__item.is-highlighted {\n  background-color: var(--bs-primary);\n  color: var(--bs-white);\n}\n.choices.is-open .choices__inner {\n  border-radius: var(--bs-border-radius) var(--bs-border-radius) 0 0;\n}\n.choices .choices__item--choice.is-selected {\n  background-color: var(--bs-primary);\n  color: var(--bs-white);\n}\n\n.fc {\n  font-family: var(--bs-font-sans-serif);\n}\n.fc .fc-toolbar {\n  margin-bottom: 1.5rem;\n  flex-wrap: wrap;\n  gap: 0.5rem;\n}\n@media (max-width: 768px) {\n  .fc .fc-toolbar .fc-toolbar-chunk {\n    flex: 1 1 100%;\n    justify-content: center;\n  }\n  .fc .fc-toolbar .fc-toolbar-chunk:first-child {\n    order: 2;\n  }\n  .fc .fc-toolbar .fc-toolbar-chunk:last-child {\n    order: 1;\n  }\n}\n.fc .fc-button-group .fc-button {\n  border-color: var(--bs-border-color);\n  transition: all 0.2s ease-in-out;\n}\n.fc .fc-button-group .fc-button:hover {\n  transform: translateY(-1px);\n  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);\n}\n.fc .fc-daygrid-day {\n  transition: all 0.2s ease-in-out;\n}\n.fc .fc-daygrid-day:hover {\n  background-color: var(--bs-light);\n}\n.fc .fc-event {\n  border-radius: var(--bs-border-radius, 0.375rem);\n  border: none;\n  box-shadow: var(--bs-box-shadow, 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075));\n  transition: all 0.2s ease-in-out;\n  cursor: pointer;\n}\n.fc .fc-event:hover {\n  transform: translateY(-1px);\n  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);\n}\n.fc .fc-event .fc-event-title {\n  font-weight: 500;\n}\n.fc .fc-event.recurring-event {\n  border-left: 3px solid var(--bs-warning);\n}\n.fc .fc-event.recurring-event::after {\n  content: \"↻\";\n  position: absolute;\n  top: 2px;\n  right: 4px;\n  font-size: 0.75rem;\n  opacity: 0.7;\n}\n.fc .fc-list-event:hover {\n  background-color: var(--bs-light);\n}\n.fc .fc-list-event .fc-list-event-title {\n  font-weight: 500;\n}\n@media (max-width: 768px) {\n  .fc .fc-toolbar-title {\n    font-size: 1.25rem;\n  }\n  .fc .fc-button {\n    padding: 0.25rem 0.5rem;\n    font-size: 0.875rem;\n  }\n  .fc .fc-daygrid-event {\n    font-size: 0.75rem;\n  }\n}\n\n.calendar-container {\n  background: var(--bs-body-bg, white);\n  border-radius: var(--bs-border-radius, 0.375rem);\n  box-shadow: var(--bs-box-shadow, 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075));\n  padding: var(--bs-gutter-x, 1.5rem);\n  margin-bottom: 2rem;\n  border: 1px solid var(--bs-border-color, #dee2e6);\n}\n@media (max-width: 768px) {\n  .calendar-container {\n    padding: 1rem;\n    margin-bottom: 1rem;\n  }\n}\n\n.calendar-filter-form {\n  transition: all 0.3s ease;\n}\n.calendar-filter-form .filter-header {\n  cursor: pointer;\n  user-select: none;\n  transition: background-color 0.2s ease;\n}\n.calendar-filter-form .filter-header:hover {\n  background-color: rgba(0, 0, 0, 0.05);\n}\n.calendar-filter-form .filter-header:focus-visible {\n  outline: 2px solid var(--bs-primary);\n  outline-offset: -2px;\n}\n.calendar-filter-form .filter-header .filter-toggle-icon {\n  transition: transform 0.3s ease;\n  font-size: 1.1rem;\n}\n.calendar-filter-form .filter-header[aria-expanded=true] .filter-toggle-icon {\n  transform: rotate(180deg);\n}\n.calendar-filter-form .filter-header h5 {\n  color: var(--bs-gray-800);\n  font-weight: 600;\n}\n.calendar-filter-form .filter-header h5 .badge {\n  font-size: 0.75rem;\n  animation: pulse 2s infinite;\n}\n.calendar-filter-form .active-filters-summary {\n  font-size: 0.875rem;\n  color: var(--bs-gray-600);\n  margin-top: 0.5rem;\n}\n.calendar-filter-form .collapse .form-control,\n.calendar-filter-form .collapse .form-select {\n  border: 1px solid var(--bs-gray-300);\n  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;\n}\n.calendar-filter-form .collapse .form-control:focus,\n.calendar-filter-form .collapse .form-select:focus {\n  border-color: var(--bs-primary);\n  box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);\n}\n.calendar-filter-form .collapse .btn {\n  transition: all 0.15s ease-in-out;\n}\n@media (max-width: 768px) {\n  .calendar-filter-form .filter-header {\n    padding: 1rem !important;\n  }\n  .calendar-filter-form .filter-header h5 {\n    font-size: 1.1rem;\n  }\n  .calendar-filter-form .filter-header .filter-toggle-icon {\n    font-size: 1.2rem;\n  }\n  .calendar-filter-form .collapse .p-4 {\n    padding: 1rem !important;\n  }\n  .calendar-filter-form .row .col-md-2,\n  .calendar-filter-form .row .col-md-3,\n  .calendar-filter-form .row .col-md-4,\n  .calendar-filter-form .row .col-md-6 {\n    margin-bottom: 1rem;\n  }\n}\n\n@keyframes pulse {\n  0% {\n    transform: scale(1);\n  }\n  50% {\n    transform: scale(1.05);\n  }\n  100% {\n    transform: scale(1);\n  }\n}\n.quick-filters {\n  display: flex;\n  gap: 0.5rem;\n  flex-wrap: wrap;\n  margin-bottom: 1rem;\n}\n.quick-filters .quick-filter-btn {\n  display: inline-block;\n  padding: 0.25rem 0.75rem;\n  margin-bottom: 0;\n  font-size: 0.8rem;\n  font-weight: 400;\n  line-height: 1.5;\n  color: var(--bs-primary);\n  text-decoration: none;\n  text-align: center;\n  white-space: nowrap;\n  vertical-align: middle;\n  cursor: pointer;\n  border: 1px solid var(--bs-primary);\n  border-radius: 1rem;\n  background-color: transparent;\n  transition: all 0.15s ease-in-out;\n}\n.quick-filters .quick-filter-btn:hover {\n  color: #fff;\n  background-color: var(--bs-primary);\n  border-color: var(--bs-primary);\n}\n.quick-filters .quick-filter-btn.active {\n  color: #fff;\n  background-color: var(--bs-primary);\n  border-color: var(--bs-primary);\n}\n\n.saved-filters .dropdown-toggle {\n  display: inline-block;\n  padding: 0.375rem 0.75rem;\n  margin-bottom: 0;\n  font-size: 0.875rem;\n  font-weight: 400;\n  line-height: 1.5;\n  color: var(--bs-gray-600);\n  text-decoration: none;\n  text-align: center;\n  white-space: nowrap;\n  vertical-align: middle;\n  cursor: pointer;\n  border: 1px solid var(--bs-gray-300);\n  border-radius: var(--bs-border-radius);\n  background-color: transparent;\n  transition: all 0.15s ease-in-out;\n}\n.saved-filters .dropdown-toggle:hover {\n  color: var(--bs-gray-700);\n  background-color: var(--bs-gray-50);\n  border-color: var(--bs-gray-400);\n}\n.saved-filters .dropdown-menu {\n  min-width: 200px;\n}\n\n@media (max-width: 768px) {\n  .event-card {\n    margin-bottom: 1rem;\n  }\n  .event-card:hover {\n    transform: none;\n    box-shadow: var(--bs-box-shadow);\n  }\n  .event-card .event-card-header {\n    padding: 0.75rem;\n  }\n  .event-card .event-card-header .event-date-badge {\n    position: static;\n    display: inline-block;\n    margin-bottom: 0.5rem;\n  }\n  .event-card .event-card-body {\n    padding: 0.75rem;\n  }\n  .event-card .event-card-body .event-title {\n    font-size: 1rem;\n  }\n  .event-card .event-card-body .event-summary {\n    font-size: 0.875rem;\n    display: -webkit-box;\n    -webkit-line-clamp: 3;\n    -webkit-box-orient: vertical;\n    overflow: hidden;\n  }\n  .event-card .event-card-footer {\n    padding: 0.5rem 0.75rem;\n    flex-direction: column;\n    align-items: flex-start;\n    gap: 0.5rem;\n  }\n  .fc-toolbar {\n    flex-wrap: wrap;\n    gap: 0.5rem;\n  }\n  .fc-toolbar .fc-toolbar-chunk .fc-button-group .fc-button {\n    padding: 0.5rem 0.75rem;\n    font-size: 0.875rem;\n    min-width: 44px;\n    min-height: 44px;\n  }\n  .fc-toolbar .fc-toolbar-title {\n    font-size: 1.25rem;\n    flex: 1;\n    text-align: center;\n    min-width: 0;\n  }\n  .event-grid .event-grid-item {\n    margin-bottom: 1rem;\n  }\n  .calendar-filters {\n    position: sticky;\n    top: 0;\n    z-index: 1020;\n    margin-bottom: 1rem;\n  }\n  .calendar-filters .calendar-filters-body {\n    padding: 0.75rem;\n  }\n  .calendar-filters .filter-section {\n    margin-bottom: 1rem;\n  }\n  .calendar-filters .date-range-shortcuts {\n    justify-content: space-between;\n  }\n  .calendar-filters .date-range-shortcuts .date-shortcut {\n    flex: 1;\n    margin: 0 0.125rem;\n    font-size: 0.75rem;\n    padding: 0.25rem 0.5rem;\n  }\n  .calendar-filters .view-toggle .view-option {\n    flex: 1;\n    padding: 0.5rem;\n  }\n  .calendar-filters .view-toggle .view-option .view-label {\n    display: none;\n  }\n  .calendar-filters .view-toggle .view-option i {\n    margin-right: 0;\n    font-size: 1.1rem;\n  }\n  .quick-filters {\n    overflow-x: auto;\n    flex-wrap: nowrap;\n    padding-bottom: 0.5rem;\n  }\n  .quick-filters .quick-filter {\n    flex-shrink: 0;\n    white-space: nowrap;\n  }\n}\n.calendar-container {\n  -webkit-overflow-scrolling: touch;\n  touch-action: manipulation;\n}\n\n.swipe-indicator {\n  position: absolute;\n  top: 50%;\n  transform: translateY(-50%);\n  background: rgba(var(--bs-primary-rgb), 0.1);\n  color: var(--bs-primary);\n  border-radius: 50%;\n  width: 40px;\n  height: 40px;\n  display: flex;\n  align-items: center;\n  justify-content: center;\n  opacity: 0;\n  transition: opacity 0.3s ease;\n  pointer-events: none;\n}\n.swipe-indicator.show {\n  opacity: 1;\n}\n.swipe-indicator.swipe-left {\n  right: 1rem;\n}\n.swipe-indicator.swipe-right {\n  left: 1rem;\n}\n\n.pull-refresh {\n  position: relative;\n}\n.pull-refresh .pull-refresh-indicator {\n  position: absolute;\n  top: -60px;\n  left: 50%;\n  transform: translateX(-50%);\n  background: var(--bs-body-bg);\n  border: 1px solid var(--bs-border-color);\n  border-radius: var(--bs-border-radius);\n  padding: 0.5rem 1rem;\n  box-shadow: var(--bs-box-shadow);\n  opacity: 0;\n  transition: all 0.3s ease;\n}\n.pull-refresh .pull-refresh-indicator.active {\n  opacity: 1;\n  top: 10px;\n}\n.pull-refresh .pull-refresh-indicator .spinner-border {\n  width: 1rem;\n  height: 1rem;\n  margin-right: 0.5rem;\n}\n\n@media (max-width: 768px) {\n  .btn,\n  .form-control,\n  .form-select,\n  .event-card,\n  .list-group-item {\n    min-height: 44px;\n  }\n  .badge,\n  .category-pill {\n    min-height: 32px;\n    padding: 0.375rem 0.75rem;\n    display: inline-flex;\n    align-items: center;\n  }\n  .form-group,\n  .filter-section {\n    margin-bottom: 1.5rem;\n  }\n  .btn-group .btn {\n    margin: 0.125rem;\n  }\n}\n@media (prefers-reduced-motion: reduce) {\n  .event-card,\n  .calendar-filters,\n  .quick-filter {\n    transition: none;\n  }\n  .swipe-indicator,\n  .pull-refresh-indicator {\n    transition: none;\n  }\n}\n@media (prefers-contrast: high) {\n  .event-card {\n    border: 2px solid var(--bs-border-color);\n  }\n  .event-card:hover, .event-card:focus {\n    border-color: var(--bs-primary);\n  }\n  .calendar-filters {\n    border: 2px solid var(--bs-border-color);\n  }\n  .quick-filter {\n    border-width: 2px;\n  }\n  .quick-filter.active {\n    border-color: var(--bs-primary);\n    box-shadow: 0 0 0 2px var(--bs-primary);\n  }\n}\n@media (prefers-color-scheme: dark) {\n  .calendar-container {\n    background: var(--bs-dark, #212529);\n    border-color: var(--bs-secondary, #6c757d);\n  }\n  .event-card {\n    background: var(--bs-dark, #212529);\n    border-color: var(--bs-secondary, #6c757d);\n  }\n  .event-card .event-card-header {\n    border-color: var(--bs-secondary, #6c757d);\n  }\n  .swipe-indicator {\n    background: rgba(255, 255, 255, 0.1);\n    color: var(--bs-light, #f8f9fa);\n  }\n}\n.sr-only,\n.visually-hidden {\n  position: absolute !important;\n  width: 1px !important;\n  height: 1px !important;\n  padding: 0 !important;\n  margin: -1px !important;\n  overflow: hidden !important;\n  clip: rect(0, 0, 0, 0) !important;\n  white-space: nowrap !important;\n  border: 0 !important;\n}\n\n.skip-link {\n  position: absolute;\n  top: -40px;\n  left: 6px;\n  background: var(--bs-primary);\n  color: white;\n  padding: 8px;\n  text-decoration: none;\n  border-radius: var(--bs-border-radius);\n  z-index: 2000;\n  transition: top 0.3s;\n}\n.skip-link:focus {\n  top: 6px;\n}\n\n.calendar-container *:focus {\n  outline: 2px solid var(--bs-primary);\n  outline-offset: 2px;\n  border-radius: var(--bs-border-radius);\n}\n.calendar-container .event-card:focus {\n  outline: 2px solid var(--bs-primary);\n  outline-offset: 2px;\n  box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.25);\n}\n\n.event-grid[role=grid] .event-card[role=gridcell] {\n  cursor: pointer;\n}\n.event-grid[role=grid] .event-card[role=gridcell]:focus {\n  z-index: 1;\n}\n.event-grid[role=grid] .event-card[role=gridcell][tabindex=\"0\"] {\n  position: relative;\n}\n.event-grid[role=grid] .event-card[role=gridcell][tabindex=\"0\"]::before {\n  content: \"\";\n  position: absolute;\n  top: -2px;\n  left: -2px;\n  right: -2px;\n  bottom: -2px;\n  border: 2px solid transparent;\n  border-radius: calc(var(--bs-border-radius) + 2px);\n  transition: border-color 0.2s ease;\n}\n.event-grid[role=grid] .event-card[role=gridcell][tabindex=\"0\"]:focus::before {\n  border-color: var(--bs-primary);\n}\n\n.fc .fc-button:focus {\n  outline: 2px solid var(--bs-primary);\n  outline-offset: 2px;\n  z-index: 1;\n}\n.fc .fc-button .fc-icon::after {\n  content: attr(aria-label);\n  position: absolute;\n  left: -10000px;\n  top: auto;\n  width: 1px;\n  height: 1px;\n  overflow: hidden;\n}\n.fc .fc-daygrid-day:focus {\n  outline: 2px solid var(--bs-primary);\n  outline-offset: -2px;\n  background-color: rgba(var(--bs-primary-rgb), 0.1);\n}\n.fc .fc-daygrid-day[aria-selected=true] {\n  background-color: rgba(var(--bs-primary-rgb), 0.2);\n}\n.fc .fc-daygrid-day[aria-selected=true]::after {\n  content: \"Selected\";\n  position: absolute;\n  left: -10000px;\n  top: auto;\n  width: 1px;\n  height: 1px;\n  overflow: hidden;\n}\n.fc .fc-event:focus {\n  outline: 2px solid var(--bs-light);\n  outline-offset: 1px;\n  z-index: 10;\n}\n.fc .fc-event[style*=background-color] {\n  border: 1px solid rgba(0, 0, 0, 0.2);\n}\n\n.calendar-filters .form-label {\n  font-weight: 600;\n}\n.calendar-filters .form-label[for] {\n  cursor: pointer;\n}\n.calendar-filters .form-control.is-invalid,\n.calendar-filters .form-select.is-invalid {\n  border-color: var(--bs-danger);\n}\n.calendar-filters .form-control.is-invalid:focus,\n.calendar-filters .form-select.is-invalid:focus {\n  border-color: var(--bs-danger);\n  box-shadow: 0 0 0 0.2rem rgba(var(--bs-danger-rgb), 0.25);\n}\n.calendar-filters .form-control.is-valid,\n.calendar-filters .form-select.is-valid {\n  border-color: var(--bs-success);\n}\n.calendar-filters .form-control.is-valid:focus,\n.calendar-filters .form-select.is-valid:focus {\n  border-color: var(--bs-success);\n  box-shadow: 0 0 0 0.2rem rgba(var(--bs-success-rgb), 0.25);\n}\n.calendar-filters .form-label.required::after {\n  content: \" *\";\n  color: var(--bs-danger);\n  font-weight: bold;\n}\n.calendar-filters .form-text {\n  font-size: 0.875rem;\n  color: var(--bs-secondary);\n}\n.calendar-filters .form-text.error {\n  color: var(--bs-danger);\n}\n.calendar-filters .form-text.error::before {\n  content: \"⚠ \";\n  font-weight: bold;\n}\n\n.live-region {\n  position: absolute;\n  left: -10000px;\n  width: 1px;\n  height: 1px;\n  overflow: hidden;\n}\n@media (prefers-contrast: high) {\n  .event-card {\n    border: 2px solid;\n  }\n  .event-card:hover, .event-card:focus {\n    border-width: 3px;\n  }\n  .fc-event {\n    border: 2px solid !important;\n  }\n  .fc-event:hover, .fc-event:focus {\n    border-width: 3px !important;\n  }\n  .calendar-filters {\n    border: 2px solid;\n  }\n  .text-muted {\n    color: var(--bs-secondary) !important;\n  }\n}\n@media (prefers-reduced-motion: reduce) {\n  *,\n  ::before,\n  ::after {\n    animation-duration: 0.01ms !important;\n    animation-iteration-count: 1 !important;\n    transition-duration: 0.01ms !important;\n    scroll-behavior: auto !important;\n  }\n  .fc-event {\n    transition: none !important;\n  }\n  .event-card {\n    transition: none !important;\n  }\n  .event-card:hover {\n    transform: none !important;\n  }\n}\n.event-colorblind-friendly .fc-event.event-type-meeting {\n  background-image: repeating-linear-gradient(45deg, transparent, transparent 2px, rgba(255, 255, 255, 0.3) 2px, rgba(255, 255, 255, 0.3) 4px);\n}\n.event-colorblind-friendly .fc-event.event-type-deadline {\n  border-left: 4px solid !important;\n}\n.event-colorblind-friendly .fc-event.event-type-holiday {\n  background-image: radial-gradient(circle at 2px 2px, rgba(255, 255, 255, 0.3) 1px, transparent 1px);\n  background-size: 8px 8px;\n}\n.event-colorblind-friendly .event-card.event-type-meeting::before {\n  content: \"📅\";\n  position: absolute;\n  top: 0.5rem;\n  left: 0.5rem;\n  font-size: 0.875rem;\n}\n.event-colorblind-friendly .event-card.event-type-deadline::before {\n  content: \"⏰\";\n  position: absolute;\n  top: 0.5rem;\n  left: 0.5rem;\n  font-size: 0.875rem;\n}\n.event-colorblind-friendly .event-card.event-type-holiday::before {\n  content: \"🎉\";\n  position: absolute;\n  top: 0.5rem;\n  left: 0.5rem;\n  font-size: 0.875rem;\n}\n\n[dir=rtl] .event-card .event-date-badge {\n  right: auto;\n  left: 0.75rem;\n}\n[dir=rtl] .event-card .event-recurring-indicator::before {\n  content: \"↻ \";\n  margin-left: 0.25rem;\n  margin-right: 0;\n}\n[dir=rtl] .calendar-filters .filter-toggle {\n  margin-left: 0;\n  margin-right: auto;\n}\n[dir=rtl] .quick-filters {\n  direction: rtl;\n}", "",{"version":3,"sources":["webpack://./client/src/scss/calendar.scss","webpack://./client/src/scss/components/_filters.scss","webpack://./client/src/scss/components/_mobile.scss","webpack://./client/src/scss/components/_accessibility.scss"],"names":[],"mappings":"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","sourcesContent":["// Dynamic SilverStripe Calendar - Modern Frontend Styles\n// Drop-in module for silverstripe-essentials-theme (Bootstrap 5.3)\n\n// Import Choices.js CSS for enhanced dropdowns\n@import \"~choices.js/public/assets/styles/choices.min.css\";\n\n// FullCalendar Responsive Enhancements\n// Complement FullCalendar's built-in responsive design\n\n.fc {\n  // Desktop/Tablet: Ensure single-row header layout\n  @media (min-width: 768px) {\n    .fc-header-toolbar {\n      display: flex;\n      justify-content: space-between;\n      align-items: center;\n      flex-wrap: nowrap;\n      gap: 1rem;\n\n      .fc-toolbar-chunk {\n        display: flex;\n        align-items: center;\n        gap: 0.5rem;\n      }\n\n      // Ensure title doesn't break layout\n      .fc-toolbar-title {\n        font-size: 1.5rem;\n        white-space: nowrap;\n        overflow: hidden;\n        text-overflow: ellipsis;\n        min-width: 0;\n        flex: 1;\n        text-align: center;\n\n        @media (min-width: 992px) {\n          font-size: 1.75rem;\n        }\n      }\n    }\n  }\n\n  // Mobile optimizations\n  @media (max-width: 767.98px) {\n    .fc-header-toolbar {\n      .fc-toolbar-title {\n        font-size: 1.25rem;\n        margin: 0.5rem 0;\n      }\n\n      .fc-button-group {\n        .fc-button {\n          font-size: 0.875rem;\n          padding: 0.25rem 0.5rem;\n        }\n      }\n    }\n\n    // Mobile event display optimizations\n    .fc-daygrid-event {\n      font-size: 0.75rem;\n      padding: 1px 2px;\n    }\n\n    .fc-list-event {\n      .fc-list-event-title {\n        font-size: 0.875rem;\n      }\n    }\n\n    // Touch-friendly interactions\n    .fc-button {\n      min-height: 44px; // Apple's minimum touch target\n      min-width: 44px;\n    }\n\n    .fc-daygrid-day {\n      min-height: 44px;\n    }\n  }\n\n  // Extra small screens (portrait phones)\n  @media (max-width: 575.98px) {\n    .fc-header-toolbar {\n      .fc-toolbar-title {\n        font-size: 1.125rem;\n      }\n    }\n\n    .fc-button-group {\n      .fc-button {\n        font-size: 0.75rem;\n        padding: 0.2rem 0.4rem;\n      }\n    }\n  }\n}\n\n// Choices.js Bootstrap 5 Integration Fixes\n.choices {\n  margin-bottom: 0; // Remove default margin to match Bootstrap forms\n\n  .choices__inner {\n    background-color: var(--bs-body-bg);\n    border: var(--bs-border-width) solid var(--bs-border-color);\n    border-radius: var(--bs-border-radius);\n    color: var(--bs-body-color);\n    font-size: var(--bs-body-font-size);\n    min-height: calc(1.5em + 0.75rem + 2px); // Match Bootstrap input height\n    padding: 0.375rem 0.75rem; // Match Bootstrap input padding\n\n    &:focus-within {\n      border-color: var(--bs-primary);\n      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);\n    }\n  }\n\n  .choices__list--dropdown {\n    background-color: var(--bs-body-bg);\n    border: var(--bs-border-width) solid var(--bs-border-color);\n    border-radius: var(--bs-border-radius);\n    box-shadow: var(--bs-box-shadow);\n    z-index: 1050; // Ensure it appears above other elements\n\n    .choices__item {\n      color: var(--bs-body-color);\n\n      &:hover,\n      &.is-highlighted {\n        background-color: var(--bs-primary);\n        color: var(--bs-white);\n      }\n    }\n  }\n\n  &.is-open .choices__inner {\n    border-radius: var(--bs-border-radius) var(--bs-border-radius) 0 0;\n  }\n\n  .choices__item--choice.is-selected {\n    background-color: var(--bs-primary);\n    color: var(--bs-white);\n  }\n}\n\n// Custom calendar variables that leverage existing Bootstrap CSS custom properties\n$calendar-primary-color: var(--bs-primary, #0d6efd) !default;\n$calendar-border-radius: var(--bs-border-radius, 0.375rem) !default;\n$calendar-box-shadow: var(--bs-box-shadow, 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)) !default;\n$calendar-transition: all 0.2s ease-in-out !default;\n$calendar-spacing: var(--bs-gutter-x, 1.5rem) !default;\n\n// FullCalendar Bootstrap 5 theme overrides\n.fc {\n  // Improve typography\n  font-family: var(--bs-font-sans-serif);\n\n  // Header styling\n  .fc-toolbar {\n    margin-bottom: 1.5rem;\n    flex-wrap: wrap;\n    gap: 0.5rem;\n\n    @media (max-width: 768px) {\n      .fc-toolbar-chunk {\n        flex: 1 1 100%;\n        justify-content: center;\n\n        &:first-child {\n          order: 2;\n        }\n\n        &:last-child {\n          order: 1;\n        }\n      }\n    }\n  }\n\n  .fc-button-group {\n    .fc-button {\n      border-color: var(--bs-border-color);\n      transition: $calendar-transition;\n\n      &:hover {\n        transform: translateY(-1px);\n        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);\n      }\n    }\n  }\n\n  // Calendar grid improvements\n  .fc-daygrid-day {\n    transition: $calendar-transition;\n\n    &:hover {\n      background-color: var(--bs-light);\n    }\n  }\n\n  // Event styling\n  .fc-event {\n    border-radius: $calendar-border-radius;\n    border: none;\n    box-shadow: $calendar-box-shadow;\n    transition: $calendar-transition;\n    cursor: pointer;\n\n    &:hover {\n      transform: translateY(-1px);\n      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);\n    }\n\n    .fc-event-title {\n      font-weight: 500;\n    }\n\n    &.recurring-event {\n      border-left: 3px solid var(--bs-warning);\n\n      &::after {\n        content: \"↻\";\n        position: absolute;\n        top: 2px;\n        right: 4px;\n        font-size: 0.75rem;\n        opacity: 0.7;\n      }\n    }\n  }\n\n  // List view improvements\n  .fc-list-event {\n    &:hover {\n      background-color: var(--bs-light);\n    }\n\n    .fc-list-event-title {\n      font-weight: 500;\n    }\n  }\n\n  // Mobile optimizations\n  @media (max-width: 768px) {\n    .fc-toolbar-title {\n      font-size: 1.25rem;\n    }\n\n    .fc-button {\n      padding: 0.25rem 0.5rem;\n      font-size: 0.875rem;\n    }\n\n    .fc-daygrid-event {\n      font-size: 0.75rem;\n    }\n  }\n}\n\n// Calendar container\n.calendar-container {\n  background: var(--bs-body-bg, white);\n  border-radius: $calendar-border-radius;\n  box-shadow: $calendar-box-shadow;\n  padding: $calendar-spacing;\n  margin-bottom: 2rem;\n  border: 1px solid var(--bs-border-color, #dee2e6);\n\n  @media (max-width: 768px) {\n    padding: 1rem;\n    margin-bottom: 1rem;\n  }\n}\n\n// Import component styles\n@import \"components/event-card\";\n@import \"components/filters\";\n@import \"components/mobile\";\n@import \"components/accessibility\";\n","// Collapsible Filter Form Styles\n.calendar-filter-form {\n  transition: all 0.3s ease;\n\n  .filter-header {\n    cursor: pointer;\n    user-select: none;\n    transition: background-color 0.2s ease;\n\n    &:hover {\n      background-color: rgba(0, 0, 0, 0.05);\n    }\n\n    &:focus-visible {\n      outline: 2px solid var(--bs-primary);\n      outline-offset: -2px;\n    }\n\n    .filter-toggle-icon {\n      transition: transform 0.3s ease;\n      font-size: 1.1rem;\n    }\n\n    &[aria-expanded=\"true\"] {\n      .filter-toggle-icon {\n        transform: rotate(180deg);\n      }\n    }\n\n    h5 {\n      color: var(--bs-gray-800);\n      font-weight: 600;\n\n      .badge {\n        font-size: 0.75rem;\n        animation: pulse 2s infinite;\n      }\n    }\n  }\n\n  // Active filters indicator\n  .active-filters-summary {\n    font-size: 0.875rem;\n    color: var(--bs-gray-600);\n    margin-top: 0.5rem;\n  }\n\n  // Enhanced form styling\n  .collapse {\n    .form-control,\n    .form-select {\n      border: 1px solid var(--bs-gray-300);\n      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;\n\n      &:focus {\n        border-color: var(--bs-primary);\n        box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);\n      }\n    }\n\n    .btn {\n      transition: all 0.15s ease-in-out;\n    }\n  }\n\n  // Mobile optimizations\n  @media (max-width: 768px) {\n    .filter-header {\n      padding: 1rem !important;\n\n      h5 {\n        font-size: 1.1rem;\n      }\n\n      .filter-toggle-icon {\n        font-size: 1.2rem;\n      }\n    }\n\n    .collapse .p-4 {\n      padding: 1rem !important;\n    }\n\n    // Stack form fields on mobile\n    .row .col-md-2,\n    .row .col-md-3,\n    .row .col-md-4,\n    .row .col-md-6 {\n      margin-bottom: 1rem;\n    }\n  }\n}\n\n// Badge pulse animation\n@keyframes pulse {\n  0% {\n    transform: scale(1);\n  }\n  50% {\n    transform: scale(1.05);\n  }\n  100% {\n    transform: scale(1);\n  }\n}\n\n// Quick filter buttons (for future enhancement)\n.quick-filters {\n  display: flex;\n  gap: 0.5rem;\n  flex-wrap: wrap;\n  margin-bottom: 1rem;\n\n  .quick-filter-btn {\n    display: inline-block;\n    padding: 0.25rem 0.75rem;\n    margin-bottom: 0;\n    font-size: 0.8rem;\n    font-weight: 400;\n    line-height: 1.5;\n    color: var(--bs-primary);\n    text-decoration: none;\n    text-align: center;\n    white-space: nowrap;\n    vertical-align: middle;\n    cursor: pointer;\n    border: 1px solid var(--bs-primary);\n    border-radius: 1rem;\n    background-color: transparent;\n    transition: all 0.15s ease-in-out;\n\n    &:hover {\n      color: #fff;\n      background-color: var(--bs-primary);\n      border-color: var(--bs-primary);\n    }\n\n    &.active {\n      color: #fff;\n      background-color: var(--bs-primary);\n      border-color: var(--bs-primary);\n    }\n  }\n}\n\n// Saved filters dropdown (for future enhancement)\n.saved-filters {\n  .dropdown-toggle {\n    display: inline-block;\n    padding: 0.375rem 0.75rem;\n    margin-bottom: 0;\n    font-size: 0.875rem;\n    font-weight: 400;\n    line-height: 1.5;\n    color: var(--bs-gray-600);\n    text-decoration: none;\n    text-align: center;\n    white-space: nowrap;\n    vertical-align: middle;\n    cursor: pointer;\n    border: 1px solid var(--bs-gray-300);\n    border-radius: var(--bs-border-radius);\n    background-color: transparent;\n    transition: all 0.15s ease-in-out;\n\n    &:hover {\n      color: var(--bs-gray-700);\n      background-color: var(--bs-gray-50);\n      border-color: var(--bs-gray-400);\n    }\n  }\n\n  .dropdown-menu {\n    min-width: 200px;\n  }\n}\n","// Mobile-specific enhancements\n// Optimizations for touch devices and small screens\n\n// Touch-friendly event cards\n@media (max-width: 768px) {\n  .event-card {\n    margin-bottom: 1rem;\n\n    &:hover {\n      // Reduce hover effects on touch devices\n      transform: none;\n      box-shadow: var(--bs-box-shadow);\n    }\n\n    .event-card-header {\n      padding: 0.75rem;\n\n      .event-date-badge {\n        position: static;\n        display: inline-block;\n        margin-bottom: 0.5rem;\n      }\n    }\n\n    .event-card-body {\n      padding: 0.75rem;\n\n      .event-title {\n        font-size: 1rem;\n      }\n\n      .event-summary {\n        font-size: 0.875rem;\n        // Limit to 3 lines on mobile\n        display: -webkit-box;\n        -webkit-line-clamp: 3;\n        -webkit-box-orient: vertical;\n        overflow: hidden;\n      }\n    }\n\n    .event-card-footer {\n      padding: 0.5rem 0.75rem;\n      flex-direction: column;\n      align-items: flex-start;\n      gap: 0.5rem;\n    }\n  }\n\n  // Mobile-optimized FullCalendar header\n  .fc-toolbar {\n    flex-wrap: wrap;\n    gap: 0.5rem;\n\n    .fc-toolbar-chunk {\n      // Make view buttons touch-friendly\n      .fc-button-group {\n        .fc-button {\n          padding: 0.5rem 0.75rem;\n          font-size: 0.875rem;\n          min-width: 44px; // Touch target minimum\n          min-height: 44px;\n        }\n      }\n    }\n\n    // Ensure header doesn't overflow on small screens\n    .fc-toolbar-title {\n      font-size: 1.25rem;\n      flex: 1;\n      text-align: center;\n      min-width: 0; // Allow title to shrink\n    }\n  }\n\n  // Stacked event grid on mobile\n  .event-grid {\n    .event-grid-item {\n      margin-bottom: 1rem;\n    }\n  }\n\n  // Mobile-optimized filters\n  .calendar-filters {\n    position: sticky;\n    top: 0;\n    z-index: 1020;\n    margin-bottom: 1rem;\n\n    .calendar-filters-body {\n      padding: 0.75rem;\n    }\n\n    .filter-section {\n      margin-bottom: 1rem;\n    }\n\n    .date-range-shortcuts {\n      justify-content: space-between;\n\n      .date-shortcut {\n        flex: 1;\n        margin: 0 0.125rem;\n        font-size: 0.75rem;\n        padding: 0.25rem 0.5rem;\n      }\n    }\n\n    .view-toggle {\n      .view-option {\n        flex: 1;\n        padding: 0.5rem;\n\n        .view-label {\n          display: none;\n        }\n\n        i {\n          margin-right: 0;\n          font-size: 1.1rem;\n        }\n      }\n    }\n  }\n\n  // Quick filters on mobile\n  .quick-filters {\n    overflow-x: auto;\n    flex-wrap: nowrap;\n    padding-bottom: 0.5rem;\n\n    .quick-filter {\n      flex-shrink: 0;\n      white-space: nowrap;\n    }\n  }\n}\n\n// Touch gesture enhancements\n.calendar-container {\n  // Smooth scrolling for touch\n  -webkit-overflow-scrolling: touch;\n\n  // Prevent zoom on double-tap\n  touch-action: manipulation;\n}\n\n// Swipe indicators\n.swipe-indicator {\n  position: absolute;\n  top: 50%;\n  transform: translateY(-50%);\n  background: rgba(var(--bs-primary-rgb), 0.1);\n  color: var(--bs-primary);\n  border-radius: 50%;\n  width: 40px;\n  height: 40px;\n  display: flex;\n  align-items: center;\n  justify-content: center;\n  opacity: 0;\n  transition: opacity 0.3s ease;\n  pointer-events: none;\n\n  &.show {\n    opacity: 1;\n  }\n\n  &.swipe-left {\n    right: 1rem;\n  }\n\n  &.swipe-right {\n    left: 1rem;\n  }\n}\n\n// Pull-to-refresh indicator\n.pull-refresh {\n  position: relative;\n\n  .pull-refresh-indicator {\n    position: absolute;\n    top: -60px;\n    left: 50%;\n    transform: translateX(-50%);\n    background: var(--bs-body-bg);\n    border: 1px solid var(--bs-border-color);\n    border-radius: var(--bs-border-radius);\n    padding: 0.5rem 1rem;\n    box-shadow: var(--bs-box-shadow);\n    opacity: 0;\n    transition: all 0.3s ease;\n\n    &.active {\n      opacity: 1;\n      top: 10px;\n    }\n\n    .spinner-border {\n      width: 1rem;\n      height: 1rem;\n      margin-right: 0.5rem;\n    }\n  }\n}\n\n// Improved touch targets\n@media (max-width: 768px) {\n  // Ensure minimum 44px touch targets\n  .btn,\n  .form-control,\n  .form-select,\n  .event-card,\n  .list-group-item {\n    min-height: 44px;\n  }\n\n  // Larger tap areas for small elements\n  .badge,\n  .category-pill {\n    min-height: 32px;\n    padding: 0.375rem 0.75rem;\n    display: inline-flex;\n    align-items: center;\n  }\n\n  // Improved form spacing\n  .form-group,\n  .filter-section {\n    margin-bottom: 1.5rem;\n  }\n\n  // Better button spacing\n  .btn-group .btn {\n    margin: 0.125rem;\n  }\n}\n\n// Accessibility enhancements for mobile\n@media (prefers-reduced-motion: reduce) {\n  .event-card,\n  .calendar-filters,\n  .quick-filter {\n    transition: none;\n  }\n\n  .swipe-indicator,\n  .pull-refresh-indicator {\n    transition: none;\n  }\n}\n\n// High contrast mode support\n@media (prefers-contrast: high) {\n  .event-card {\n    border: 2px solid var(--bs-border-color);\n\n    &:hover,\n    &:focus {\n      border-color: var(--bs-primary);\n    }\n  }\n\n  .calendar-filters {\n    border: 2px solid var(--bs-border-color);\n  }\n\n  .quick-filter {\n    border-width: 2px;\n\n    &.active {\n      border-color: var(--bs-primary);\n      box-shadow: 0 0 0 2px var(--bs-primary);\n    }\n  }\n}\n\n// Dark mode support (if theme supports it)\n@media (prefers-color-scheme: dark) {\n  .calendar-container {\n    background: var(--bs-dark, #212529);\n    border-color: var(--bs-secondary, #6c757d);\n  }\n\n  .event-card {\n    background: var(--bs-dark, #212529);\n    border-color: var(--bs-secondary, #6c757d);\n\n    .event-card-header {\n      border-color: var(--bs-secondary, #6c757d);\n    }\n  }\n\n  .swipe-indicator {\n    background: rgba(255, 255, 255, 0.1);\n    color: var(--bs-light, #f8f9fa);\n  }\n}\n","// Accessibility enhancements\n// WCAG 2.1 AA compliance features\n\n// Screen reader only text\n.sr-only,\n.visually-hidden {\n  position: absolute !important;\n  width: 1px !important;\n  height: 1px !important;\n  padding: 0 !important;\n  margin: -1px !important;\n  overflow: hidden !important;\n  clip: rect(0, 0, 0, 0) !important;\n  white-space: nowrap !important;\n  border: 0 !important;\n}\n\n// Skip links for keyboard navigation\n.skip-link {\n  position: absolute;\n  top: -40px;\n  left: 6px;\n  background: var(--bs-primary);\n  color: white;\n  padding: 8px;\n  text-decoration: none;\n  border-radius: var(--bs-border-radius);\n  z-index: 2000;\n  transition: top 0.3s;\n\n  &:focus {\n    top: 6px;\n  }\n}\n\n// Focus management\n.calendar-container {\n  // Ensure focusable elements are visible\n  *:focus {\n    outline: 2px solid var(--bs-primary);\n    outline-offset: 2px;\n    border-radius: var(--bs-border-radius);\n  }\n\n  // Custom focus for cards\n  .event-card:focus {\n    outline: 2px solid var(--bs-primary);\n    outline-offset: 2px;\n    box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.25);\n  }\n}\n\n// Keyboard navigation enhancements\n.event-grid {\n  // Grid navigation support\n  &[role=\"grid\"] {\n    .event-card[role=\"gridcell\"] {\n      cursor: pointer;\n\n      &:focus {\n        z-index: 1;\n      }\n\n      // Visual indicator for keyboard users\n      &[tabindex=\"0\"] {\n        position: relative;\n\n        &::before {\n          content: \"\";\n          position: absolute;\n          top: -2px;\n          left: -2px;\n          right: -2px;\n          bottom: -2px;\n          border: 2px solid transparent;\n          border-radius: calc(var(--bs-border-radius) + 2px);\n          transition: border-color 0.2s ease;\n        }\n\n        &:focus::before {\n          border-color: var(--bs-primary);\n        }\n      }\n    }\n  }\n}\n\n// Calendar navigation accessibility\n.fc {\n  // Improve button accessibility\n  .fc-button {\n    &:focus {\n      outline: 2px solid var(--bs-primary);\n      outline-offset: 2px;\n      z-index: 1;\n    }\n\n    // Add screen reader text for icon-only buttons\n    .fc-icon::after {\n      content: attr(aria-label);\n      position: absolute;\n      left: -10000px;\n      top: auto;\n      width: 1px;\n      height: 1px;\n      overflow: hidden;\n    }\n  }\n\n  // Day cell accessibility\n  .fc-daygrid-day {\n    &:focus {\n      outline: 2px solid var(--bs-primary);\n      outline-offset: -2px;\n      background-color: rgba(var(--bs-primary-rgb), 0.1);\n    }\n\n    &[aria-selected=\"true\"] {\n      background-color: rgba(var(--bs-primary-rgb), 0.2);\n\n      &::after {\n        content: \"Selected\";\n        position: absolute;\n        left: -10000px;\n        top: auto;\n        width: 1px;\n        height: 1px;\n        overflow: hidden;\n      }\n    }\n  }\n\n  // Event accessibility\n  .fc-event {\n    &:focus {\n      outline: 2px solid var(--bs-light);\n      outline-offset: 1px;\n      z-index: 10;\n    }\n\n    // Ensure sufficient color contrast\n    &[style*=\"background-color\"] {\n      border: 1px solid rgba(0, 0, 0, 0.2);\n    }\n  }\n}\n\n// Form accessibility\n.calendar-filters {\n  // Associate labels with controls\n  .form-label {\n    font-weight: 600;\n\n    &[for] {\n      cursor: pointer;\n    }\n  }\n\n  // Error state styling\n  .form-control.is-invalid,\n  .form-select.is-invalid {\n    border-color: var(--bs-danger);\n\n    &:focus {\n      border-color: var(--bs-danger);\n      box-shadow: 0 0 0 0.2rem rgba(var(--bs-danger-rgb), 0.25);\n    }\n  }\n\n  // Success state styling\n  .form-control.is-valid,\n  .form-select.is-valid {\n    border-color: var(--bs-success);\n\n    &:focus {\n      border-color: var(--bs-success);\n      box-shadow: 0 0 0 0.2rem rgba(var(--bs-success-rgb), 0.25);\n    }\n  }\n\n  // Required field indicators\n  .form-label.required {\n    &::after {\n      content: \" *\";\n      color: var(--bs-danger);\n      font-weight: bold;\n    }\n  }\n\n  // Help text styling\n  .form-text {\n    font-size: 0.875rem;\n    color: var(--bs-secondary);\n\n    &.error {\n      color: var(--bs-danger);\n\n      &::before {\n        content: \"⚠ \";\n        font-weight: bold;\n      }\n    }\n  }\n}\n\n// Live region for dynamic content updates\n.live-region {\n  position: absolute;\n  left: -10000px;\n  width: 1px;\n  height: 1px;\n  overflow: hidden;\n\n  &[aria-live=\"polite\"] {\n    // Announcements for filter updates\n  }\n\n  &[aria-live=\"assertive\"] {\n    // Urgent announcements for errors\n  }\n}\n\n// High contrast mode support\n@media (prefers-contrast: high) {\n  .event-card {\n    border: 2px solid;\n\n    &:hover,\n    &:focus {\n      border-width: 3px;\n    }\n  }\n\n  .fc-event {\n    border: 2px solid !important;\n\n    &:hover,\n    &:focus {\n      border-width: 3px !important;\n    }\n  }\n\n  .calendar-filters {\n    border: 2px solid;\n  }\n\n  // Ensure text contrast\n  .text-muted {\n    color: var(--bs-secondary) !important;\n  }\n}\n\n// Reduced motion support\n@media (prefers-reduced-motion: reduce) {\n  *,\n  ::before,\n  ::after {\n    animation-duration: 0.01ms !important;\n    animation-iteration-count: 1 !important;\n    transition-duration: 0.01ms !important;\n    scroll-behavior: auto !important;\n  }\n\n  .fc-event {\n    transition: none !important;\n  }\n\n  .event-card {\n    transition: none !important;\n\n    &:hover {\n      transform: none !important;\n    }\n  }\n}\n\n// Color blind friendly event colors\n.event-colorblind-friendly {\n  // Use patterns/shapes instead of just colors\n  .fc-event {\n    &.event-type-meeting {\n      background-image: repeating-linear-gradient(\n        45deg,\n        transparent,\n        transparent 2px,\n        rgba(255, 255, 255, 0.3) 2px,\n        rgba(255, 255, 255, 0.3) 4px\n      );\n    }\n\n    &.event-type-deadline {\n      border-left: 4px solid !important;\n    }\n\n    &.event-type-holiday {\n      background-image: radial-gradient(\n        circle at 2px 2px,\n        rgba(255, 255, 255, 0.3) 1px,\n        transparent 1px\n      );\n      background-size: 8px 8px;\n    }\n  }\n\n  .event-card {\n    &.event-type-meeting {\n      &::before {\n        content: \"📅\";\n        position: absolute;\n        top: 0.5rem;\n        left: 0.5rem;\n        font-size: 0.875rem;\n      }\n    }\n\n    &.event-type-deadline {\n      &::before {\n        content: \"⏰\";\n        position: absolute;\n        top: 0.5rem;\n        left: 0.5rem;\n        font-size: 0.875rem;\n      }\n    }\n\n    &.event-type-holiday {\n      &::before {\n        content: \"🎉\";\n        position: absolute;\n        top: 0.5rem;\n        left: 0.5rem;\n        font-size: 0.875rem;\n      }\n    }\n  }\n}\n\n// RTL (Right-to-Left) language support\n[dir=\"rtl\"] {\n  .event-card {\n    .event-date-badge {\n      right: auto;\n      left: 0.75rem;\n    }\n\n    .event-recurring-indicator {\n      &::before {\n        content: \"↻ \";\n        margin-left: 0.25rem;\n        margin-right: 0;\n      }\n    }\n  }\n\n  .calendar-filters {\n    .filter-toggle {\n      margin-left: 0;\n      margin-right: auto;\n    }\n  }\n\n  .quick-filters {\n    direction: rtl;\n  }\n}\n"],"sourceRoot":""}]);
// Exports
/* harmony default export */ __webpack_exports__["default"] = (___CSS_LOADER_EXPORT___);


/***/ }),

/***/ "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjEiIGhlaWdodD0iMjEiIHZpZXdCb3g9IjAgMCAyMSAyMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSIjMDAwIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0yLjU5Mi4wNDRsMTguMzY0IDE4LjM2NC0yLjU0OCAyLjU0OEwuMDQ0IDIuNTkyeiIvPjxwYXRoIGQ9Ik0wIDE4LjM2NEwxOC4zNjQgMGwyLjU0OCAyLjU0OEwyLjU0OCAyMC45MTJ6Ii8+PC9nPjwvc3ZnPg==":
/*!**************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjEiIGhlaWdodD0iMjEiIHZpZXdCb3g9IjAgMCAyMSAyMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSIjMDAwIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0yLjU5Mi4wNDRsMTguMzY0IDE4LjM2NC0yLjU0OCAyLjU0OEwuMDQ0IDIuNTkyeiIvPjxwYXRoIGQ9Ik0wIDE4LjM2NEwxOC4zNjQgMGwyLjU0OCAyLjU0OEwyLjU0OCAyMC45MTJ6Ii8+PC9nPjwvc3ZnPg== ***!
  \**************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/***/ (function(module) {

module.exports = "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjEiIGhlaWdodD0iMjEiIHZpZXdCb3g9IjAgMCAyMSAyMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSIjMDAwIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0yLjU5Mi4wNDRsMTguMzY0IDE4LjM2NC0yLjU0OCAyLjU0OEwuMDQ0IDIuNTkyeiIvPjxwYXRoIGQ9Ik0wIDE4LjM2NEwxOC4zNjQgMGwyLjU0OCAyLjU0OEwyLjU0OCAyMC45MTJ6Ii8+PC9nPjwvc3ZnPg==";

/***/ }),

/***/ "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjEiIGhlaWdodD0iMjEiIHZpZXdCb3g9IjAgMCAyMSAyMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSIjRkZGIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0yLjU5Mi4wNDRsMTguMzY0IDE4LjM2NC0yLjU0OCAyLjU0OEwuMDQ0IDIuNTkyeiIvPjxwYXRoIGQ9Ik0wIDE4LjM2NEwxOC4zNjQgMGwyLjU0OCAyLjU0OEwyLjU0OCAyMC45MTJ6Ii8+PC9nPjwvc3ZnPg==":
/*!**************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjEiIGhlaWdodD0iMjEiIHZpZXdCb3g9IjAgMCAyMSAyMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSIjRkZGIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0yLjU5Mi4wNDRsMTguMzY0IDE4LjM2NC0yLjU0OCAyLjU0OEwuMDQ0IDIuNTkyeiIvPjxwYXRoIGQ9Ik0wIDE4LjM2NEwxOC4zNjQgMGwyLjU0OCAyLjU0OEwyLjU0OCAyMC45MTJ6Ii8+PC9nPjwvc3ZnPg== ***!
  \**************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/***/ (function(module) {

module.exports = "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjEiIGhlaWdodD0iMjEiIHZpZXdCb3g9IjAgMCAyMSAyMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSIjRkZGIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGQ9Ik0yLjU5Mi4wNDRsMTguMzY0IDE4LjM2NC0yLjU0OCAyLjU0OEwuMDQ0IDIuNTkyeiIvPjxwYXRoIGQ9Ik0wIDE4LjM2NEwxOC4zNjQgMGwyLjU0OCAyLjU0OEwyLjU0OCAyMC45MTJ6Ii8+PC9nPjwvc3ZnPg==";

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			id: moduleId,
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	!function() {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = function(result, chunkIds, fn, priority) {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var chunkIds = deferred[i][0];
/******/ 				var fn = deferred[i][1];
/******/ 				var priority = deferred[i][2];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every(function(key) { return __webpack_require__.O[key](chunkIds[j]); })) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	!function() {
/******/ 		__webpack_require__.b = document.baseURI || self.location.href;
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"calendar": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = function(chunkId) { return installedChunks[chunkId] === 0; };
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = function(parentChunkLoadingFunction, data) {
/******/ 			var chunkIds = data[0];
/******/ 			var moreModules = data[1];
/******/ 			var runtime = data[2];
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some(function(id) { return installedChunks[id] !== 0; })) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunksilverstripe_calendar_frontend"] = self["webpackChunksilverstripe_calendar_frontend"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/nonce */
/******/ 	!function() {
/******/ 		__webpack_require__.nc = undefined;
/******/ 	}();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["vendors"], function() { return __webpack_require__("./client/src/js/calendar.js"); })
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=calendar.bundle.js.map