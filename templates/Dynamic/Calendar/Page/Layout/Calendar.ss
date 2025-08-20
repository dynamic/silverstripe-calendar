<!-- Page Header -->
<header class="page-header">
  <h1>$Title</h1>
  <% if $Content %>
    <div class="intro">$Content</div>
  <% end_if %>
</header>

<!-- Collapsible Filter Panel -->
<% if $FilterForm %>
<div class="calendar-filter-section">
  <button type="button" class="calendar-filter-toggle" aria-expanded="false" data-target="#calendar-filters">
    <span class="filter-icon">🔍</span>
    <span class="filter-text">Filter Events</span>
    <span class="filter-arrow">▼</span>
  </button>
  <div id="calendar-filters" class="calendar-filter-panel" style="display: none;">
    <div class="calendar-filter-header">
      <h5>Filter Events</h5>
    </div>
    $FilterForm
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

<style>
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

<% require css('dynamic/silverstripe-calendar: client/dist/css/calendar.bundle.css') %>
<% require javascript('dynamic/silverstripe-calendar: client/dist/js/vendors.bundle.js') %>
<% require javascript('dynamic/silverstripe-calendar: client/dist/js/calendar.bundle.js') %>

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
