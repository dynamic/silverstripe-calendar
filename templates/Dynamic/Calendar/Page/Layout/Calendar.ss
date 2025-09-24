<!-- Page Header -->
<header class="page-header">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
      <h1>$Title</h1>
      <% if $Content %>
        <div class="intro">$Content</div>
      <% end_if %>
    </div>
  </div>
</header>

<!-- Calendar Action Toolbar -->
<div class="calendar-toolbar">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

    <!-- Filter and Subscribe Actions -->
    <div class="toolbar-actions d-flex gap-2">
      <% if $FilterForm %>
      <!-- Filter Toggle Button -->
      <button type="button" class="btn btn-outline-secondary js-toggle-filters"
              aria-expanded="false"
              data-bs-toggle="collapse"
              data-bs-target="#calendar-filters"
              aria-controls="calendar-filters">
        <i class="bi bi-funnel me-2"></i>Filter Events
        <i class="bi bi-chevron-down ms-2 filter-chevron"></i>
      </button>
      <% end_if %>

      <!-- Subscribe Button -->
      <button type="button" class="btn btn-outline-primary js-subscribe-calendar"
              data-calendar-url="$Link"
              data-bs-toggle="modal"
              data-bs-target="#subscribeModal">
        <i class="bi bi-calendar-plus me-2"></i>Subscribe
      </button>
    </div>

    <!-- Additional toolbar items could go here in the future -->
    <div class="toolbar-secondary">
      <!-- Space for future enhancements -->
    </div>
  </div>
</div>

<!-- Collapsible Filter Panel -->
<% if $FilterForm %>
<div class="collapse calendar-filter-collapse" id="calendar-filters">
  <div class="card mb-3">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Events</h5>
        <button type="button" class="btn btn-outline-danger btn-sm js-clear-filters">
          <i class="bi bi-x-circle me-1"></i>Clear All
        </button>
      </div>
    </div>
    <div class="card-body">
      $FilterForm
    </div>
  </div>
</div>
<% end_if %>

<!-- Calendar Section -->
<section class="calendar-section">
  <!-- FullCalendar Container -->
  <div class="calendar-container">
    <div id="fullcalendar-view"
         data-calendar-id="$ID"
         data-events-url="$Link/events"
         data-default-view="dayGridMonth"
         data-height="auto">

      <div id="fullcalendar" style="min-height: 600px;"></div>

      <!-- Loading State -->
      <div id="calendar-loading" class="calendar-loading" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading events...</p>
      </div>

      <!-- Error State -->
      <div id="calendar-error" class="calendar-error" style="display: none;">
        <h3>Unable to Load Calendar</h3>
        <p>Please refresh the page or contact support if the problem persists.</p>
      </div>
    </div>
  </div>
</section>

<!-- Elemental Area -->
<% if $ElementalArea %>
<div class="elemental-area">
  $ElementalArea
</div>
<% end_if %>

<!-- ICS Subscription Modal -->
<div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="subscribeModalLabel">
          <i class="bi bi-calendar-plus me-2"></i>Subscribe to Calendar
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Subscribe to this calendar to receive automatic updates in your calendar application. This subscription will respect your current filter settings.</p>

        <div class="alert alert-info" role="alert">
          <i class="bi bi-info-circle me-2"></i>
          <strong>Current Filters:</strong> <span id="current-filters-display">All events</span>
        </div>

        <div class="mb-3">
          <label for="subscription-url" class="form-label">Subscription URL:</label>
          <div class="input-group">
            <input type="text" class="form-control" id="subscription-url" readonly>
            <button type="button" class="btn btn-outline-secondary js-copy-url" title="Copy URL">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>

        <div class="subscription-instructions">
          <h6>How to subscribe:</h6>
          <div class="accordion accordion-flush" id="subscriptionInstructions">
            <div class="accordion-item">
              <h2 class="accordion-header" id="google-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#google-instructions" aria-expanded="false" aria-controls="google-instructions">
                  Google Calendar
                </button>
              </h2>
              <div id="google-instructions" class="accordion-collapse collapse" aria-labelledby="google-heading">
                <div class="accordion-body">
                  <ol>
                    <li>Copy the subscription URL above</li>
                    <li>Open Google Calendar</li>
                    <li>On the left side, click the "+" next to "Other calendars"</li>
                    <li>Select "From URL"</li>
                    <li>Paste the URL and click "Add calendar"</li>
                  </ol>
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="outlook-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#outlook-instructions" aria-expanded="false" aria-controls="outlook-instructions">
                  Microsoft Outlook
                </button>
              </h2>
              <div id="outlook-instructions" class="accordion-collapse collapse" aria-labelledby="outlook-heading">
                <div class="accordion-body">
                  <ol>
                    <li>Copy the subscription URL above</li>
                    <li>Open Outlook Calendar</li>
                    <li>Click "Add calendar" → "From internet"</li>
                    <li>Paste the URL and click "OK"</li>
                  </ol>
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header" id="apple-heading">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#apple-instructions" aria-expanded="false" aria-controls="apple-instructions">
                  Apple Calendar
                </button>
              </h2>
              <div id="apple-instructions" class="accordion-collapse collapse" aria-labelledby="apple-heading">
                <div class="accordion-body">
                  <ol>
                    <li>Copy the subscription URL above</li>
                    <li>Open Calendar app</li>
                    <li>File → New Calendar Subscription</li>
                    <li>Paste the URL and click "Subscribe"</li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary js-copy-url">
          <i class="bi bi-clipboard me-2"></i>Copy URL
        </button>
      </div>
    </div>
  </div>
</div>

<style>
/* Subscribe Button Styles */
.page-header {
  margin-bottom: 2rem;
}

.subscribe-section {
  flex-shrink: 0;
}

.js-subscribe-calendar {
  white-space: nowrap;
}

/* Modal Enhancements */
#subscribeModal .modal-body {
  padding: 1.5rem;
}

#subscribeModal .alert-info {
  border-left: 4px solid #0dcaf0;
}

#subscribeModal .input-group {
  margin-bottom: 1rem;
}

#subscribeModal .subscription-instructions {
  margin-top: 1rem;
}

#subscribeModal .accordion-button {
  padding: 0.75rem 1rem;
  font-weight: 500;
}

#subscribeModal .accordion-body ol {
  margin-bottom: 0;
  padding-left: 1.2rem;
}

#subscribeModal .accordion-body li {
  margin-bottom: 0.5rem;
}

/* Copy button feedback */
.js-copy-url.copied {
  background-color: #198754;
  border-color: #198754;
  color: white;
}

/* Responsive header layout */
@media (max-width: 576px) {
  .page-header .d-flex {
    flex-direction: column;
    align-items: stretch !important;
  }

  .subscribe-section {
    margin-top: 1rem;
  }

  .js-subscribe-calendar {
    width: 100%;
    justify-content: center;
  }
}

/* Clean Calendar Layout - Ensure filter is always above calendar */
.calendar-filter-section {
  margin: 20px 0;
  width: 100%;
  display: block; /* Force block layout */
  clear: both; /* Clear any floats */
}

.calendar-filter-toggle {
  background: #f8f9fa;
  border: 1px solid #ddd;
  padding: 12px 16px;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  max-width: 300px;
  font-size: 14px;
  color: #333;
  margin-bottom: 0; /* Remove any margin that might cause layout issues */
}

.calendar-filter-toggle:hover {
  background: #e9ecef;
}

.calendar-filter-toggle[aria-expanded="true"] .filter-arrow {
  transform: rotate(180deg);
}

.filter-arrow {
  margin-left: auto;
  transition: transform 0.2s ease;
}

.calendar-filter-panel {
  margin-top: 15px;
  padding: 20px;
  background: #f8f9fa;
  border: 1px solid #ddd;
  border-radius: 4px;
  max-width: 500px;
  width: 100%;
  box-sizing: border-box;
}

.calendar-filter-header h5 {
  margin: 0 0 15px 0;
  color: #333;
  font-size: 16px;
}

/* Calendar Section - Ensure it's below filter */
.calendar-section {
  margin: 30px 0;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 4px;
  overflow: hidden;
  width: 100%;
  display: block; /* Force block layout */
  clear: both; /* Clear any floats */
}

/* Calendar Container */
.calendar-container {
  padding: 20px;
  background: #fff;
  min-height: 600px;
}

#fullcalendar {
  background: #fff;
}

/* Loading States */
.calendar-loading,
.calendar-error {
  text-align: center;
  padding: 40px 20px;
  color: #666;
}

.loading-spinner {
  width: 30px;
  height: 30px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #007bff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 15px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.calendar-error {
  color: #dc3545;
}

/* Responsive Design */
@media (max-width: 768px) {
  .calendar-container {
    padding: 15px;
  }

  .calendar-filter-toggle {
    max-width: none;
  }

  .calendar-filter-panel {
    max-width: none;
  }
}

/* Fix for FullCalendar navigation button icons */
.fc-prev-button .fc-icon,
.fc-next-button .fc-icon {
  font-family: Arial, sans-serif !important;
  font-size: 14px !important;
  font-weight: bold !important;
}

.fc-prev-button .fc-icon::before {
  content: "‹" !important;
}

.fc-next-button .fc-icon::before {
  content: "›" !important;
}

/* Fix FullCalendar navigation buttons */
.fc-direction-ltr .fc-button-group > .fc-button:not(:last-child),
.fc-direction-rtl .fc-button-group > .fc-button:not(:first-child) {
  border-radius: 0.25em 0 0 0.25em;
}

.fc-direction-ltr .fc-button-group > .fc-button:not(:first-child),
.fc-direction-rtl .fc-button-group > .fc-button:not(:last-child) {
  border-radius: 0 0.25em 0.25em 0;
}

/* Replace broken navigation icons with text */
.fc-prev-button {
  position: relative;
}

.fc-next-button {
  position: relative;
}

.fc-prev-button::after {
  content: "‹";
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 18px;
  font-weight: bold;
  color: white;
  z-index: 1;
}

.fc-next-button::after {
  content: "›";
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 18px;
  font-weight: bold;
  color: white;
  z-index: 1;
}

/* Hide the broken img elements */
.fc-prev-button img,
.fc-next-button img {
  opacity: 0;
}
</style>

<% require css('dynamic/silverstripe-calendar:client/dist/css/calendar.bundle.css') %>
<% require javascript('dynamic/silverstripe-calendar:client/dist/js/vendors.bundle.js') %>
<% require javascript('dynamic/silverstripe-calendar:client/dist/js/calendar.bundle.js') %>

<script>
// Simple, clean calendar initialization
document.addEventListener('DOMContentLoaded', function() {
  // Filter toggle functionality
  const filterToggle = document.querySelector('.calendar-filter-toggle');
  const filterPanel = document.querySelector('.calendar-filter-panel');

  if (filterToggle && filterPanel) {
    filterToggle.addEventListener('click', function() {
      const isExpanded = this.getAttribute('aria-expanded') === 'true';
      const newState = !isExpanded;

      this.setAttribute('aria-expanded', newState);
      filterPanel.style.display = newState ? 'block' : 'none';
    });
  }
});
</script>
