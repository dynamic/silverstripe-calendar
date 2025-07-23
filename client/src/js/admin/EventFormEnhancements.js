// Event Form Enhancements for CMS
// Improves the event creation/editing experience

export class EventFormEnhancements {
  constructor() {
    this.initConditionalFields();
    this.initRecurrencePreview();
    this.initQuickFillButtons();
    this.initDateTimeHelpers();
  }

  initConditionalFields() {
    // Show/hide fields based on selections
    const allDayCheckbox = document.querySelector('[name*="AllDay"]');
    const recursionSelect = document.querySelector('[name*="Recursion"]');

    if (allDayCheckbox) {
      allDayCheckbox.addEventListener('change', this.toggleTimeFields.bind(this));
      this.toggleTimeFields(); // Initial state
    }

    if (recursionSelect) {
      recursionSelect.addEventListener('change', this.toggleRecurrenceFields.bind(this));
      this.toggleRecurrenceFields(); // Initial state
    }
  }

  toggleTimeFields() {
    const allDayCheckbox = document.querySelector('[name*="AllDay"]');
    const timeFields = document.querySelectorAll('[name*="StartTime"], [name*="EndTime"]');
    const timeFieldContainers = document.querySelectorAll('.field[id*="Time"]');

    const isAllDay = allDayCheckbox?.checked;

    timeFields.forEach(field => {
      field.disabled = isAllDay;
      if (isAllDay) {
        field.value = '';
      }
    });

    timeFieldContainers.forEach(container => {
      container.style.opacity = isAllDay ? '0.5' : '1';
    });
  }

  toggleRecurrenceFields() {
    const recursionSelect = document.querySelector('[name*="Recursion"]');
    const recursionFields = document.querySelectorAll(
      '[name*="Interval"], [name*="RecursionEndDate"]'
    );
    const recursionContainers = document.querySelectorAll(
      '.field[id*="Interval"], .field[id*="RecursionEndDate"]'
    );

    const hasRecurrence = recursionSelect?.value && recursionSelect.value !== 'NONE';

    recursionFields.forEach(field => {
      field.disabled = !hasRecurrence;
      if (!hasRecurrence && field.name.includes('Interval')) {
        field.value = '1'; // Reset to default
      }
    });

    recursionContainers.forEach(container => {
      container.style.display = hasRecurrence ? 'block' : 'none';
    });
  }

  initRecurrencePreview() {
    // Add live preview of recurring dates
    const form = document.querySelector('.cms-edit-form');
    if (!form) return;

    // Create preview container
    const previewContainer = document.createElement('div');
    previewContainer.className = 'recursion-preview card mt-3';
    previewContainer.style.display = 'none';

    // Insert after recursion fields
    const recursionTab = form.querySelector('#Root_Recurrence, [id*="Recurrence"]');
    if (recursionTab) {
      recursionTab.appendChild(previewContainer);
    }

    // Watch for changes
    const watchFields = document.querySelectorAll(
      '[name*="Recursion"], [name*="Interval"], [name*="StartDate"], [name*="RecursionEndDate"]'
    );

    watchFields.forEach(field => {
      field.addEventListener('change', this.updatePreview.bind(this));
    });
  }

  updatePreview() {
    // Trigger preview update (implementation in admin.js)
    if (window.CalendarAdminEnhancements) {
      window.CalendarAdminEnhancements.updateRecurrencePreview();
    }
  }

  initQuickFillButtons() {
    // Add quick-fill buttons for common event patterns
    const titleField = document.querySelector('[name*="Title"]');
    if (!titleField) return;

    const quickFillContainer = document.createElement('div');
    quickFillContainer.className = 'quick-fill-buttons mt-2';
    quickFillContainer.innerHTML = `
      <small class="text-muted">Quick Fill:</small>
      <div class="btn-group btn-group-sm ms-2" role="group">
        <button type="button" class="btn btn-outline-secondary" data-pattern="service">Service</button>
        <button type="button" class="btn btn-outline-secondary" data-pattern="meeting">Meeting</button>
        <button type="button" class="btn btn-outline-secondary" data-pattern="study">Bible Study</button>
        <button type="button" class="btn btn-outline-secondary" data-pattern="event">Special Event</button>
      </div>
    `;

    titleField.parentNode.appendChild(quickFillContainer);

    // Handle quick-fill clicks
    quickFillContainer.addEventListener('click', (e) => {
      if (e.target.dataset.pattern) {
        this.applyQuickFill(e.target.dataset.pattern);
      }
    });
  }

  applyQuickFill(pattern) {
    const patterns = {
      service: {
        title: 'Worship Service',
        startTime: '10:00:00',
        endTime: '11:00:00'
      },
      meeting: {
        title: 'Meeting',
        startTime: '19:00:00',
        endTime: '20:00:00'
      },
      study: {
        title: 'Bible Study',
        startTime: '19:30:00',
        endTime: '21:00:00'
      },
      event: {
        title: 'Special Event',
        allDay: true
      }
    };

    const pattern_data = patterns[pattern];
    if (!pattern_data) return;

    // Apply pattern data
    Object.keys(pattern_data).forEach(key => {
      const field = document.querySelector(`[name*="${key}"], [name*="${key.charAt(0).toUpperCase() + key.slice(1)}"]`);
      if (field) {
        if (field.type === 'checkbox') {
          field.checked = pattern_data[key];
        } else {
          field.value = pattern_data[key];
        }

        // Trigger change event
        field.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  initDateTimeHelpers() {
    // Add helpful datetime shortcuts
    const dateFields = document.querySelectorAll('[name*="Date"]');

    dateFields.forEach(field => {
      if (field.type === 'date') {
        this.addDateShortcuts(field);
      }
    });

    const timeFields = document.querySelectorAll('[name*="Time"]');
    timeFields.forEach(field => {
      if (field.type === 'time') {
        this.addTimeShortcuts(field);
      }
    });
  }

  addDateShortcuts(dateField) {
    const shortcuts = document.createElement('div');
    shortcuts.className = 'date-shortcuts mt-1';
    shortcuts.innerHTML = `
      <small class="text-muted">Quick dates:</small>
      <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-action="today">Today</button>
      <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-action="tomorrow">Tomorrow</button>
      <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-action="next-sunday">Next Sunday</button>
    `;

    dateField.parentNode.appendChild(shortcuts);

    shortcuts.addEventListener('click', (e) => {
      if (e.target.dataset.action) {
        const date = this.getDateForAction(e.target.dataset.action);
        if (date) {
          dateField.value = date.toISOString().split('T')[0];
          dateField.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
  }

  addTimeShortcuts(timeField) {
    const shortcuts = document.createElement('div');
    shortcuts.className = 'time-shortcuts mt-1';
    shortcuts.innerHTML = `
      <small class="text-muted">Common times:</small>
      <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-time="09:00">9 AM</button>
      <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-time="10:00">10 AM</button>
      <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-time="19:00">7 PM</button>
      <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-time="19:30">7:30 PM</button>
    `;

    timeField.parentNode.appendChild(shortcuts);

    shortcuts.addEventListener('click', (e) => {
      if (e.target.dataset.time) {
        timeField.value = e.target.dataset.time;
        timeField.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  getDateForAction(action) {
    const today = new Date();

    switch (action) {
      case 'today':
        return today;
      case 'tomorrow':
        return new Date(today.getTime() + 24 * 60 * 60 * 1000);
      case 'next-sunday':
        const daysUntilSunday = (7 - today.getDay()) % 7;
        const nextSunday = new Date(today.getTime() + (daysUntilSunday || 7) * 24 * 60 * 60 * 1000);
        return nextSunday;
      default:
        return null;
    }
  }
}

// Make available globally for admin.js
window.EventFormEnhancements = EventFormEnhancements;
