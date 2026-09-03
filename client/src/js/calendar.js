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

            try {
                this.calendarView = new CalendarView(calendarElement, {
                    eventsUrl: eventsUrl,
                    calendarId: calendarId,
                    defaultView: fullCalendarSection.dataset.defaultView
                });
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
            } catch (error) {
                console.error(`Failed to initialize Element Calendar ${index + 1}:`, error);
            }
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
