<div class="page-calendar"><div class="page-calendar">

  <div class="container">  <div class="container">

    <div class="row">    <div class="row">

      <div class="col-12">      <div class="col-12">

      <!-- Page Header -->      <!-- Page Header -->

      <header class="page-header mb-4">      <header class="page-header mb-4">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">

          <div>          <div>

            <h1 class="display-4">$Title</h1>            <h1 class="display-4">$Title</h1>

            <% if $Content %>            <% if $Content %>

              <div class="lead">$Content</div>              <div class="lead">$Content</div>

            <% end_if %>            <% end_if %>

          </div>          </div>

        </div>        </div>

      </header>      </header>



      <!-- Calendar Action Toolbar --><!-- Calendar Action Toolbar -->

      <div class="calendar-toolbar mb-4"><div class="calendar-toolbar">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">



          <!-- Filter and Subscribe Actions -->    <!-- Filter and Subscribe Actions -->

          <div class="toolbar-actions d-flex gap-2">    <div class="toolbar-actions d-flex gap-2">

            <% if $FilterForm %>      <% if $FilterForm %>

            <!-- Filter Toggle Button -->      <!-- Filter Toggle Button -->

            <button type="button" class="btn btn-outline-secondary js-toggle-filters"      <button type="button" class="btn btn-outline-secondary js-toggle-filters"

                    aria-expanded="false"              aria-expanded="false"

                    data-bs-toggle="collapse"              data-bs-toggle="collapse"

                    data-bs-target="#calendar-filters"              data-bs-target="#calendar-filters"

                    aria-controls="calendar-filters">              aria-controls="calendar-filters">

              <i class="bi bi-funnel me-2"></i>Filter Events        <i class="bi bi-funnel me-2"></i>Filter Events

              <i class="bi bi-chevron-down ms-2 filter-chevron"></i>        <i class="bi bi-chevron-down ms-2 filter-chevron"></i>

            </button>      </button>

            <% end_if %>      <% end_if %>



            <!-- Subscribe Button -->      <!-- Subscribe Button -->

            <button type="button" class="btn btn-outline-primary js-subscribe-calendar"      <button type="button" class="btn btn-outline-primary js-subscribe-calendar"

                    data-calendar-url="$Link"              data-calendar-url="$Link"

                    data-bs-toggle="modal"              data-bs-toggle="modal"

                    data-bs-target="#subscribeModal">              data-bs-target="#subscribeModal">

              <i class="bi bi-calendar-plus me-2"></i>Subscribe        <i class="bi bi-calendar-plus me-2"></i>Subscribe

            </button>      </button>

          </div>    </div>



          <!-- Additional toolbar items could go here in the future -->    <!-- Additional toolbar items could go here in the future -->

          <div class="toolbar-secondary">    <div class="toolbar-secondary">

            <!-- Space for future enhancements -->      <!-- Space for future enhancements -->

          </div>    </div>

        </div>  </div>

      </div></div>



      <!-- Collapsible Filter Panel --><!-- Collapsible Filter Panel -->

      <% if $FilterForm %><% if $FilterForm %>

      <div class="collapse calendar-filter-collapse" id="calendar-filters"><div class="collapse calendar-filter-collapse" id="calendar-filters">

        $FilterForm  <div class="card mb-3">

      </div>    <div class="card-header">

      <% end_if %>      <div class="d-flex justify-content-between align-items-center">

        <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Events</h5>

      <!-- FullCalendar View -->        <button type="button" class="btn btn-outline-danger btn-sm js-clear-filters">

      <div id="fullcalendar-view" class="calendar-view-section"          <i class="bi bi-x-circle me-1"></i>Clear All

           data-calendar-id="$ID"        </button>

           data-events-url="$Link/events"      </div>

           data-default-view="dayGridMonth">    </div>

        <div class="card">    <div class="card-body">

          <div class="card-body">      $FilterForm

            <div id="fullcalendar" style="min-height: 600px;"></div>    </div>

          </div>  </div>

        </div></div>

      </div><% end_if %>



    </div><!-- Calendar Section -->

  </div><section class="calendar-section">

</div>  <!-- FullCalendar Container -->

</div>  <div class="calendar-container">

    <div id="fullcalendar-view"

<!-- Elemental Area -->         data-calendar-id="$ID"

<% if $ElementalArea %>         data-events-url="$Link/events"

<div class="element-area main-element-area">         data-default-view="dayGridMonth"

  $ElementalArea         data-height="auto">

</div>

<% end_if %>      <div id="fullcalendar" style="min-height: 600px;"></div>



<!-- ICS Subscription Modal -->      <!-- Loading State -->

<div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">      <div id="calendar-loading" class="calendar-loading" style="display: none;">

  <div class="modal-dialog">        <div class="loading-spinner"></div>

    <div class="modal-content">        <p>Loading events...</p>

      <div class="modal-header">      </div>

        <h5 class="modal-title" id="subscribeModalLabel">

          <i class="bi bi-calendar-plus me-2"></i>Subscribe to Calendar      <!-- Error State -->

        </h5>      <div id="calendar-error" class="calendar-error" style="display: none;">

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>        <h3>Unable to Load Calendar</h3>

      </div>        <p>Please refresh the page or contact support if the problem persists.</p>

      <div class="modal-body">      </div>

        <p class="mb-3">Subscribe to receive automatic calendar updates in your calendar app:</p>    </div>

  </div>

        <div class="alert alert-info" role="alert"></section>

          <i class="bi bi-info-circle me-2"></i>

          <strong>Current Filters:</strong> <span id="current-filters-display">All events</span><!-- Elemental Area -->

        </div><% if $ElementalArea %>

<div class="elemental-area">

        <!-- Subscription URL Display -->  $ElementalArea

        <div class="mb-3"></div>

          <label for="subscription-url" class="form-label">Subscription URL:</label><% end_if %>

          <div class="input-group">

            <input type="text" class="form-control" id="subscription-url" readonly><!-- ICS Subscription Modal -->

            <button type="button" class="btn btn-outline-secondary js-copy-url" title="Copy URL"><div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">

              <i class="bi bi-clipboard"></i>  <div class="modal-dialog">

            </button>    <div class="modal-content">

          </div>      <div class="modal-header">

        </div>        <h5 class="modal-title" id="subscribeModalLabel">

          <i class="bi bi-calendar-plus me-2"></i>Subscribe to Calendar

        <div class="subscription-instructions">        </h5>

          <h6>How to subscribe:</h6>        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

          <div class="accordion accordion-flush" id="subscriptionInstructions">      </div>

            <div class="accordion-item">      <div class="modal-body">

              <h2 class="accordion-header" id="google-heading">        <p class="mb-3">Subscribe to this calendar to receive automatic updates in your calendar application. This subscription will respect your current filter settings.</p>

                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"

                        data-bs-target="#google-instructions" aria-expanded="false" aria-controls="google-instructions">        <div class="alert alert-info" role="alert">

                  Google Calendar          <i class="bi bi-info-circle me-2"></i>

                </button>          <strong>Current Filters:</strong> <span id="current-filters-display">All events</span>

              </h2>        </div>

              <div id="google-instructions" class="accordion-collapse collapse" aria-labelledby="google-heading">

                <div class="accordion-body">        <div class="mb-3">

                  <ol>          <label for="subscription-url" class="form-label">Subscription URL:</label>

                    <li>Copy the subscription URL above</li>          <div class="input-group">

                    <li>Open Google Calendar</li>            <input type="text" class="form-control" id="subscription-url" readonly>

                    <li>On the left side, click the "+" next to "Other calendars"</li>            <button type="button" class="btn btn-outline-secondary js-copy-url" title="Copy URL">

                    <li>Select "From URL"</li>              <i class="bi bi-clipboard"></i>

                    <li>Paste the URL and click "Add calendar"</li>            </button>

                  </ol>          </div>

                </div>        </div>

              </div>

            </div>        <div class="subscription-instructions">

          <h6>How to subscribe:</h6>

            <div class="accordion-item">          <div class="accordion accordion-flush" id="subscriptionInstructions">

              <h2 class="accordion-header" id="outlook-heading">            <div class="accordion-item">

                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"              <h2 class="accordion-header" id="google-heading">

                        data-bs-target="#outlook-instructions" aria-expanded="false" aria-controls="outlook-instructions">                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"

                  Microsoft Outlook                        data-bs-target="#google-instructions" aria-expanded="false" aria-controls="google-instructions">

                </button>                  Google Calendar

              </h2>                </button>

              <div id="outlook-instructions" class="accordion-collapse collapse" aria-labelledby="outlook-heading">              </h2>

                <div class="accordion-body">              <div id="google-instructions" class="accordion-collapse collapse" aria-labelledby="google-heading">

                  <ol>                <div class="accordion-body">

                    <li>Copy the subscription URL above</li>                  <ol>

                    <li>Open Outlook Calendar</li>                    <li>Copy the subscription URL above</li>

                    <li>Click "Add calendar" → "From internet"</li>                    <li>Open Google Calendar</li>

                    <li>Paste the URL and click "OK"</li>                    <li>On the left side, click the "+" next to "Other calendars"</li>

                  </ol>                    <li>Select "From URL"</li>

                </div>                    <li>Paste the URL and click "Add calendar"</li>

              </div>                  </ol>

            </div>                </div>

              </div>

            <div class="accordion-item">            </div>

              <h2 class="accordion-header" id="apple-heading">

                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"            <div class="accordion-item">

                        data-bs-target="#apple-instructions" aria-expanded="false" aria-controls="apple-instructions">              <h2 class="accordion-header" id="outlook-heading">

                  Apple Calendar                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"

                </button>                        data-bs-target="#outlook-instructions" aria-expanded="false" aria-controls="outlook-instructions">

              </h2>                  Microsoft Outlook

              <div id="apple-instructions" class="accordion-collapse collapse" aria-labelledby="apple-heading">                </button>

                <div class="accordion-body">              </h2>

                  <ol>              <div id="outlook-instructions" class="accordion-collapse collapse" aria-labelledby="outlook-heading">

                    <li>Copy the subscription URL above</li>                <div class="accordion-body">

                    <li>Open Calendar app</li>                  <ol>

                    <li>File → New Calendar Subscription</li>                    <li>Copy the subscription URL above</li>

                    <li>Paste the URL and click "Subscribe"</li>                    <li>Open Outlook Calendar</li>

                  </ol>                    <li>Click "Add calendar" → "From internet"</li>

                </div>                    <li>Paste the URL and click "OK"</li>

              </div>                  </ol>

            </div>                </div>

          </div>              </div>

        </div>            </div>

      </div>

      <div class="modal-footer">            <div class="accordion-item">

        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>              <h2 class="accordion-header" id="apple-heading">

        <a href="#" class="btn btn-success js-subscribe-app me-2">                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"

          <i class="bi bi-calendar-plus me-2"></i>Subscribe in App                        data-bs-target="#apple-instructions" aria-expanded="false" aria-controls="apple-instructions">

        </a>                  Apple Calendar

        <button type="button" class="btn btn-primary js-copy-url">                </button>

          <i class="bi bi-clipboard me-2"></i>Copy URL              </h2>

        </button>              <div id="apple-instructions" class="accordion-collapse collapse" aria-labelledby="apple-heading">

      </div>                <div class="accordion-body">

    </div>                  <ol>

  </div>                    <li>Copy the subscription URL above</li>

</div>                    <li>Open Calendar app</li>

                    <li>File → New Calendar Subscription</li>

<% require css('dynamic/silverstripe-calendar:client/dist/css/calendar.bundle.css') %>                    <li>Paste the URL and click "Subscribe"</li>

<% require javascript('dynamic/silverstripe-calendar:client/dist/js/vendors.bundle.js') %>                  </ol>

<% require javascript('dynamic/silverstripe-calendar:client/dist/js/calendar.bundle.js') %>                </div>
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
