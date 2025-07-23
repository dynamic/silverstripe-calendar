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
    this.options = {
      plugins: [dayGridPlugin, timeGridPlugin, listPlugin, bootstrap5Plugin, interactionPlugin],
      themeSystem: 'bootstrap5',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      },
      height: 'auto',
      responsive: true,
      ...options
    };

    this.init();
  }

  init() {
    // Get calendar configuration from data attributes
    const config = this.getConfigFromElement();

    // Merge with options
    const finalOptions = {
      ...this.options,
      ...config,
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

    // Add view toggle buttons
    this.addViewToggle();

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
    const calendarId = this.options.calendarId || this.element.dataset.calendarId;
    const eventsUrl = this.options.eventsUrl || this.element.dataset.eventsUrl || '/calendar/events';

    const params = new URLSearchParams({
      start: info.startStr,
      end: info.endStr,
      calendar: calendarId || ''
    });

    // Add any active filters
    const activeFilters = this.getActiveFilters();
    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value) params.append(key, value);
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
      successCallback(this.transformEvents(events));
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
    const filterForm = document.querySelector('.calendar-filter-form');
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
      // Open event detail page
      window.open(event.url, '_blank');
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

  addViewToggle() {
    const toolbar = this.element.querySelector('.fc-toolbar');
    if (!toolbar) return;

    const viewToggle = document.createElement('div');
    viewToggle.className = 'btn-group calendar-view-toggle ms-3';
    viewToggle.innerHTML = `
      <button type="button" class="btn btn-outline-primary btn-sm" data-view="dayGridMonth">
        <i class="bi bi-calendar-month"></i> Month
      </button>
      <button type="button" class="btn btn-outline-primary btn-sm" data-view="timeGridWeek">
        <i class="bi bi-calendar-week"></i> Week
      </button>
      <button type="button" class="btn btn-outline-primary btn-sm" data-view="listWeek">
        <i class="bi bi-list-ul"></i> List
      </button>
    `;

    // Insert after the right toolbar
    const rightToolbar = toolbar.querySelector('.fc-toolbar-chunk:last-child');
    if (rightToolbar) {
      rightToolbar.appendChild(viewToggle);
    }

    // Add click handlers
    viewToggle.addEventListener('click', (e) => {
      if (e.target.closest('[data-view]')) {
        const view = e.target.closest('[data-view]').dataset.view;
        this.calendar.changeView(view);

        // Update active button
        viewToggle.querySelectorAll('.btn').forEach(btn => btn.classList.remove('active'));
        e.target.closest('[data-view]').classList.add('active');
      }
    });
  }

  initializeMobileOptimizations() {
    // Mobile-specific optimizations
    if (window.innerWidth < 768) {
      this.calendar.setOption('height', 'auto');
      this.calendar.setOption('initialView', 'listWeek');

      // Simplify header for mobile
      this.calendar.setOption('headerToolbar', {
        left: 'prev,next',
        center: 'title',
        right: 'today'
      });
    }

    // Handle resize
    window.addEventListener('resize', () => {
      this.calendar.updateSize();
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
