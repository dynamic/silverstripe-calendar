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

// Global function for Choices.js initialization (called by CalendarFilterForm)
window.initializeChoicesJS = function() {
  const multiSelectElements = document.querySelectorAll('.js-choice');
  const config = window.CalendarChoicesConfig || {};

  multiSelectElements.forEach(function(element) {
    if (element.tagName === 'SELECT' && !element.hasAttribute('data-choices-initialized')) {
      new Choices(element, config);
      element.setAttribute('data-choices-initialized', 'true');
    }
  });
};

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
      this.smartFiltering = new SmartFiltering(filterForm);
    }

    // Initialize accessibility features
    this.keyboardNavigation = new KeyboardNavigation();

    // Initialize mobile/touch features
    if (this.isTouchDevice()) {
      this.touchInteractions = new TouchInteractions();
    }

    console.log('Calendar module initialized successfully');
  }

  initializeFullCalendar() {
    const calendarElement = document.querySelector('#fullcalendar');
    const fullCalendarSection = document.querySelector('#fullcalendar-view');

    if (!calendarElement || !fullCalendarSection) {
      console.warn('FullCalendar element not found');
      return;
    }

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

  isTouchDevice() {
    return 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  }
}

// Initialize when module loads
new CalendarModule();
