// FullCalendar View Component
// Handles FullCalendar integration and event rendering

export class FullCalendarView {
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
      const { Calendar } = await import('@fullcalendar/core');
      const dayGridPlugin = await import('@fullcalendar/daygrid');
      const timeGridPlugin = await import('@fullcalendar/timegrid');
      const listPlugin = await import('@fullcalendar/list');
      const interactionPlugin = await import('@fullcalendar/interaction');

      // Configure calendar with plugins
      this.calendar = new Calendar(this.container, {
        ...this.options,
        plugins: [
          dayGridPlugin.default,
          timeGridPlugin.default,
          listPlugin.default,
          interactionPlugin.default
        ],
        events: this.loadEvents.bind(this)
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
      const startTime = start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

      if (end) {
        const endTime = end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
      detail: { isLoading }
    }));
  }

  bindCustomEvents() {
    // Filter events
    document.addEventListener('calendar:filter', (e) => {
      this.applyFilters(e.detail.filters);
    });

    // View change events
    document.addEventListener('calendar:changeView', (e) => {
      if (this.calendar) {
        this.calendar.changeView(e.detail.view);
      }
    });

    // Navigate events
    document.addEventListener('calendar:navigate', (e) => {
      if (this.calendar) {
        const { direction, date } = e.detail;
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
export default FullCalendarView;
