<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <!-- Page Header -->
      <header class="page-header mb-4">
        <h1 class="display-4">$Title</h1>
        <% if $Content %>
          <div class="lead">$Content</div>
        <% end_if %>
      </header>

      <!-- Calendar Controls & Filters -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <!-- Filter Form -->
              $FilterForm
            </div>
          </div>
        </div>
      </div>

      <!-- FullCalendar View -->
      <div id="fullcalendar-view" class="calendar-view-section"
           data-calendar-id="$ID"
           data-events-url="$Link/events"
           data-default-view="dayGridMonth">
        <div class="card">
          <div class="card-body">
            <div id="fullcalendar" style="min-height: 600px;"></div>
          </div>
        </div>
      </div>

      <!-- Elemental Area -->
      <% if $ElementalArea %>
        <div class="row mt-4">
          <div class="col-12">
            $ElementalArea
          </div>
        </div>
      <% end_if %>
    </div>
  </div>
</div>
