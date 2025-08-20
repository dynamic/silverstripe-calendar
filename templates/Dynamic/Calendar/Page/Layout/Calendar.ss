<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <!-- Page Header -->
      <header class="page-header mb-4">
        <h1 class="h1">$Title</h1>
        <% if $Content %>
          <div class="lead">$Content</div>
        <% end_if %>
      </header>

      <!-- Calendar Controls & Filters -->
      <% if $FilterForm %>
      <div class="row mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h2 class="h5 mb-0">Filter Events</h2>
            </div>
            <div class="card-body">
              $FilterForm
            </div>
          </div>
        </div>
      </div>
      <% end_if %>

      <!-- FullCalendar View -->
      <div id="fullcalendar-view" class="calendar-view-section mb-4"
           data-calendar-id="$ID"
           data-events-url="$Link(events)"
           data-default-view="dayGridMonth"
           data-height="auto">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Calendar</h2>
            <div class="btn-group btn-group-sm" role="group" aria-label="Calendar views">
              <button type="button" class="btn btn-outline-secondary" data-calendar-view="dayGridMonth">Month</button>
              <button type="button" class="btn btn-outline-secondary" data-calendar-view="timeGridWeek">Week</button>
              <button type="button" class="btn btn-outline-secondary" data-calendar-view="timeGridDay">Day</button>
              <button type="button" class="btn btn-outline-secondary" data-calendar-view="listWeek">List</button>
            </div>
          </div>
          <div class="card-body">
            <div id="fullcalendar" style="min-height: 600px;"></div>

            <!-- Loading state -->
            <div id="calendar-loading" class="text-center p-4" style="display: none;">
              <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <p class="mt-2 text-muted">Loading events...</p>
            </div>

            <!-- Error state -->
            <div id="calendar-error" class="alert alert-danger" style="display: none;">
              <h4 class="alert-heading">Error Loading Calendar</h4>
              <p>Unable to load calendar events. Please refresh the page or contact support if the problem persists.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Elemental Area -->
      <% if $ElementalArea %>
        <div class="row">
          <div class="col-12">
            $ElementalArea
          </div>
        </div>
      <% end_if %>
    </div>
  </div>
</div>

<% require css('dynamic/silverstripe-calendar: client/dist/css/calendar.css') %>
<% require javascript('dynamic/silverstripe-calendar: client/dist/js/calendar.js') %>
