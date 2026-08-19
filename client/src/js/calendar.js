// Calendar Frontend Module
// Bootstrap 5.3 + FullCalendar integration for Dynamic SilverStripe Calendar

import '../scss/calendar.scss';
import Choices from 'choices.js';

// Import components
import { CalendarView } from './components/CalendarView';
import { FullCalendarView } from './components/FullCalendarView';
import { SmartFiltering } from './components/SmartFiltering';
import { TouchInteractions } from './components/TouchInteractions';
import { KeyboardNavigation } from './components/KeyboardNavigation';
import { FilterEnhancements } from './components/FilterEnhancements';
import { EventPage } from './components/EventPage';
import { CalendarSubscription } from './components/CalendarSubscription';

// Namespace for calendar utilities
const CalendarUtils = {};

// Function for Choices.js initialization (called by CalendarFilterForm)
CalendarUtils.initializeChoicesJS = function () {
    const multiSelectElements = document.querySelectorAll('.js-choice');
    const config = window.CalendarChoicesConfig || {};

    multiSelectElements.forEach(function (element) {
        if (element.tagName === 'SELECT' && !element.hasAttribute('data-choices-initialized')) {
            new Choices(element, config);
            element.setAttribute('data-choices-initialized', 'true');
        }
    });
};

// Expose CalendarUtils globally for backwards compatibility
window.CalendarUtils = CalendarUtils;
// Legacy support
window.initializeChoicesJS = CalendarUtils.initializeChoicesJS;

class CalendarModule {
    constructor()
    {
        this.init();
    }

    init()
    {
      // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeComponents());
        } else {
            this.initializeComponents();
        }
    }

    initializeComponents()
    {
        console.log('Initializing Dynamic Calendar Module...');

      // Initialize FullCalendar directly
        this.initializeFullCalendar();

      // Initialize filtering system
        const filterForm = document.querySelector('.calendar-filter-form');
        if (filterForm) {
            this.smartFiltering = new SmartFiltering(filterForm);
        }

      // Initialize EventPage components
        EventPage.init();

      // Initialize subscription functionality
        this.calendarSubscription = new CalendarSubscription();

      // Initialize accessibility features
        this.keyboardNavigation = new KeyboardNavigation();

      // Initialize mobile/touch features
        if (this.isTouchDevice()) {
            this.touchInteractions = new TouchInteractions();
        }

        console.log('Calendar module initialized successfully');
    }

    initializeFullCalendar()
    {
      // Initialize main calendar page
        const calendarElement = document.querySelector('#fullcalendar');
        const fullCalendarSection = document.querySelector('#fullcalendar-view');

        if (calendarElement && fullCalendarSection) {
          // Get configuration from the parent container
            const eventsUrl = fullCalendarSection.dataset.eventsUrl;
            const calendarId = fullCalendarSection.dataset.calendarId;

            console.log('Initializing FullCalendar with events URL:', eventsUrl);

            try {
                this.calendarView = new CalendarView(calendarElement, {
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

    initializeElementCalendars()
    {
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
                new FullCalendarView(targetCalendar, {
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

    fetchCalendarEvents(start, end, successCallback, failureCallback)
    {
      // Get current filter values
        const filterForm = document.querySelector('form[name="CalendarFilterForm"]');
        const params = new URLSearchParams();

        params.append('start', start.toISOString().split('T')[0]);
        params.append('end', end.toISOString().split('T')[0]);
        params.append('format', 'json');

        if (filterForm) {
            const formData = new FormData(filterForm);
            for (const [key, value] of formData.entries()) {
                // Same contract as CalendarView.getActiveFilters(): '0' is a
                // real filter value (allDay=Timed Events), and SecurityID has
                // no business on a feed URL.
                if (value !== '' && key !== 'action_doFilter' && key !== 'SecurityID') {
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
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(events => {
            console.log('Fetched calendar events:', events.length);
            successCallback(events);
        })
        .catch(error => {
            console.error('Failed to fetch calendar events:', error);
            failureCallback(error);
        });
    }

    fetchElementCalendarEvents(eventsUrl, info, successCallback, failureCallback)
    {
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
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(events => {
            console.log('Fetched element calendar events:', events.length);

            // Events are already in FullCalendar format from the Calendar module
            successCallback(events);
        })
        .catch(error => {
            console.error('Failed to fetch element calendar events:', error);
            failureCallback(error);
        });
    }

    isTouchDevice()
    {
        return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    }
}

// Initialize when module loads
new CalendarModule();
