// Dynamic SilverStripe Calendar - Admin Interface Enhancements
// CMS-specific functionality for improved user experience

import './admin/EventFormEnhancements.js';

// Initialize admin functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  initAdminEnhancements();
});

function initAdminEnhancements() {
  // Only run in CMS context
  if (!document.body.classList.contains('cms')) {
    return;
  }

  console.log('Initializing Calendar Admin enhancements...');

  // Initialize event form enhancements
  if (window.EventFormEnhancements) {
    new EventFormEnhancements();
  }

  // Initialize bulk actions
  initBulkActions();

  // Initialize preview functionality
  initEventPreview();

  // Initialize recurring event helpers
  initRecurrenceHelpers();
}

function initBulkActions() {
  // Enhanced bulk operations for event management
  const bulkSelect = document.querySelector('.bulk-select-all');
  if (bulkSelect) {
    bulkSelect.addEventListener('change', function() {
      const checkboxes = document.querySelectorAll('.bulk-select-item');
      checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
      });
      updateBulkActionButtons();
    });
  }

  // Individual checkbox handlers
  const itemCheckboxes = document.querySelectorAll('.bulk-select-item');
  itemCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkActionButtons);
  });
}

function updateBulkActionButtons() {
  const selectedItems = document.querySelectorAll('.bulk-select-item:checked');
  const bulkActions = document.querySelector('.bulk-actions');

  if (bulkActions) {
    bulkActions.style.display = selectedItems.length > 0 ? 'block' : 'none';

    // Update action button text with count
    const actionButtons = bulkActions.querySelectorAll('.bulk-action-btn');
    actionButtons.forEach(btn => {
      const action = btn.dataset.action;
      const originalText = btn.dataset.originalText || btn.textContent;
      btn.dataset.originalText = originalText;
      btn.textContent = `${originalText} (${selectedItems.length})`;
    });
  }
}

function initEventPreview() {
  // Add preview functionality for recurring events
  const recursionFields = document.querySelectorAll('[name*="Recursion"], [name*="Interval"]');

  recursionFields.forEach(field => {
    field.addEventListener('change', updateRecurrencePreview);
  });

  // Initial preview
  updateRecurrencePreview();
}

function updateRecurrencePreview() {
  const previewContainer = document.querySelector('.recursion-preview');
  if (!previewContainer) return;

  const recursion = document.querySelector('[name*="Recursion"]')?.value;
  const interval = document.querySelector('[name*="Interval"]')?.value || 1;
  const startDate = document.querySelector('[name*="StartDate"]')?.value;

  if (!recursion || recursion === 'NONE' || !startDate) {
    previewContainer.style.display = 'none';
    return;
  }

  // Generate preview dates
  const previewDates = generatePreviewDates(startDate, recursion, interval);

  const previewHTML = `
    <div class="card-body">
      <h6><i class="bi bi-calendar-week"></i> Preview Upcoming Dates</h6>
      <div class="preview-dates">
        ${previewDates.map(date => `
          <span class="badge bg-light text-dark me-1 mb-1">${formatPreviewDate(date)}</span>
        `).join('')}
      </div>
      <small class="text-muted">Showing next ${previewDates.length} occurrences</small>
    </div>
  `;

  previewContainer.innerHTML = previewHTML;
  previewContainer.style.display = 'block';
}

function generatePreviewDates(startDate, recursion, interval) {
  const dates = [];
  const start = new Date(startDate);
  const maxDates = 10;

  for (let i = 0; i < maxDates; i++) {
    const date = new Date(start);

    switch (recursion) {
      case 'DAILY':
        date.setDate(start.getDate() + (i * parseInt(interval)));
        break;
      case 'WEEKLY':
        date.setDate(start.getDate() + (i * 7 * parseInt(interval)));
        break;
      case 'MONTHLY':
        date.setMonth(start.getMonth() + (i * parseInt(interval)));
        break;
      case 'YEARLY':
        date.setFullYear(start.getFullYear() + (i * parseInt(interval)));
        break;
    }

    dates.push(date);
  }

  return dates;
}

function formatPreviewDate(date) {
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined
  });
}

function initRecurrenceHelpers() {
  // Add helpful UI for complex recursion patterns
  const recursionSelect = document.querySelector('[name*="Recursion"]');
  if (!recursionSelect) return;

  recursionSelect.addEventListener('change', function() {
    showRecurrenceHelp(this.value);
  });

  // Initial help text
  showRecurrenceHelp(recursionSelect.value);
}

function showRecurrenceHelp(recursionType) {
  const helpContainer = document.querySelector('.recursion-help');
  if (!helpContainer) return;

  const helpTexts = {
    'NONE': '',
    'DAILY': 'Event will repeat every day(s) as specified in the interval.',
    'WEEKLY': 'Event will repeat every week(s) on the same day of the week.',
    'MONTHLY': 'Event will repeat every month(s) on the same date.',
    'YEARLY': 'Event will repeat every year(s) on the same date.'
  };

  const helpText = helpTexts[recursionType] || '';
  helpContainer.innerHTML = helpText ? `
    <div class="alert alert-info">
      <i class="bi bi-info-circle"></i> ${helpText}
    </div>
  ` : '';
}

// Export for potential external use
window.CalendarAdminEnhancements = {
  updateRecurrencePreview,
  generatePreviewDates,
  formatPreviewDate
};
