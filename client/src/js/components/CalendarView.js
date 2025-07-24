// FullCalendar Integration for Dynamic SilverStripe Calendar
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import bootstrap5Plugin from '@fullcalendar/bootstrap5';
import interactionPlugin from '@fullcalendar/interaction';

export class CalendarView {
  constructor(element, options = {}) {
    this.element = element;

    // Store custom configuration from options and data attributes
    this.config = {
      ...this.getConfigFromElement(),
      ...options
    };

    this.options = {
      plugins: [dayGridPlugin, timeGridPlugin, listPlugin, bootstrap5Plugin, interactionPlugin],
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
      eventClick: (info) => this.handleEventClick(info),
      dateClick: (info) => this.handleDateClick(info),
      eventDidMount: (info) => this.handleEventDidMount(info)
    };

    // Initialize FullCalendar
    this.calendar = new Calendar(this.element, finalOptions);
    this.calendar.render();

    // Store reference globally for debugging
    window.fullCalendarInstance = this.calendar;

    // Initialize mobile optimizations
    this.initializeMobileOptimizations();

    console.log('FullCalendar initialized');
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

    console.log('Fetching events from:', `${eventsUrl}?${params.toString()}`);

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
      allDay: event.AllDay || (!event.StartTime && !event.EndTime),
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
      'worship': '#0d6efd',     // Bootstrap primary
      'education': '#198754',    // Bootstrap success
      'fellowship': '#fd7e14',   // Bootstrap warning
      'service': '#dc3545',      // Bootstrap danger
      'music': '#6f42c1',        // Bootstrap purple
      'youth': '#20c997'         // Bootstrap teal
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
