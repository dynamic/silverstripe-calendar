<!-- EventPage Bootstrap 5 Template -->
<div class="container py-4">
  <!-- Breadcrumb Navigation -->
  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="$Parent.Link" class="text-decoration-none">
          <i class="bi bi-calendar3 me-1"></i>Calendar
        </a>
      </li>
      <li class="breadcrumb-item active" aria-current="page">$Title</li>
    </ol>
  </nav>

  <!-- Event Hero Section -->
  <div class="row mb-5">
    <div class="col-12">
      <div class="card border-0 shadow-lg bg-gradient">
        <div class="card-body p-4 p-md-5">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-4">
            <div class="flex-grow-1 mb-3 mb-md-0">
              <h1 class="display-4 fw-bold text-primary mb-2">$Title</h1>
              <% if $MenuTitle && $MenuTitle != $Title %>
                <p class="lead text-muted mb-0">$MenuTitle</p>
              <% end_if %>
            </div>
            <div class="flex-shrink-0">
              <% if $eventRecurs %>
                <span class="badge bg-info text-dark fs-5 px-4 py-2 rounded-pill">
                  <i class="bi bi-arrow-repeat me-2"></i>Recurring Event
                </span>
              <% else %>
                <span class="badge bg-primary fs-5 px-4 py-2 rounded-pill">
                  <i class="bi bi-calendar-event me-2"></i>Single Event
                </span>
              <% end_if %>
            </div>
          </div>

          <!-- Quick Event Info Grid -->
          <div class="row g-4">
            <!-- Date & Time -->
            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-center p-3 bg-white rounded-3 shadow-sm h-100">
                <div class="flex-shrink-0 me-3">
                  <div class="bg-primary rounded-circle p-3 text-white">
                    <i class="bi bi-calendar-date fs-4"></i>
                  </div>
                </div>
                <div class="flex-grow-1">
                  <h6 class="fw-bold mb-1 text-primary">Date</h6>
                  <p class="mb-1 fw-semibold">$StartDate.Nice</p>
                  <% if $StartTime %>
                    <small class="text-muted">
                      $StartTime.Nice<% if $EndTime %> - $EndTime.Nice<% end_if %>
                    </small>
                  <% end_if %>
                  <% if $AllDay %>
                    <br><span class="badge bg-warning text-dark mt-1">All Day</span>
                  <% end_if %>
                </div>
              </div>
            </div>

            <!-- Event Type -->
            <% if $EventType %>
            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-center p-3 bg-white rounded-3 shadow-sm h-100">
                <div class="flex-shrink-0 me-3">
                  <div class="bg-success rounded-circle p-3 text-white">
                    <i class="bi bi-bookmark fs-4"></i>
                  </div>
                </div>
                <div class="flex-grow-1">
                  <h6 class="fw-bold mb-1 text-success">Type</h6>
                  <p class="mb-0 fw-semibold">$EventType</p>
                </div>
              </div>
            </div>
            <% end_if %>

            <% if $Categories %>
            <div class="col-md-6 col-lg-4">
              <div class="d-flex align-items-center p-3 bg-white rounded-3 shadow-sm h-100">
                <div class="flex-shrink-0 me-3">
                  <div class="bg-info rounded-circle p-3 text-white">
                    <i class="bi bi-tags fs-4"></i>
                  </div>
                </div>
                <div class="flex-grow-1">
                  <h6 class="fw-bold mb-2 text-info">Categories</h6>
                  <% loop $Categories %>
                    <span class="badge bg-secondary me-1 mb-1">$Title</span>
                  <% end_loop %>
                </div>
              </div>
            </div>
            <% end_if %>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recurring Event Information -->
  <% if $eventRecurs %>
  <div class="row mb-5">
    <div class="col-12">
      <div class="card border-warning">
        <div class="card-header bg-warning bg-opacity-10">
          <h3 class="h5 mb-0 text-warning-emphasis">
            <i class="bi bi-arrow-repeat me-2"></i>Recurrence Details
          </h3>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <div class="d-flex align-items-center mb-3">
                <i class="bi bi-arrow-clockwise text-warning me-3 fs-5"></i>
                <div>
                  <span class="fw-semibold">Repeats:</span>
                  <% if $Recursion == "DAILY" %>Daily<% end_if %>
                  <% if $Recursion == "WEEKLY" %>Weekly<% end_if %>
                  <% if $Recursion == "MONTHLY" %>Monthly<% end_if %>
                  <% if $Recursion == "YEARLY" %>Yearly<% end_if %>
                </div>
              </div>
              <% if $Interval && $Interval > 1 %>
              <div class="d-flex align-items-center">
                <i class="bi bi-skip-forward text-warning me-3 fs-5"></i>
                <div>
                  <span class="fw-semibold">Interval:</span> Every $Interval
                  <% if $Recursion == "DAILY" %>day<% if $Interval != 1 %>s<% end_if %><% end_if %>
                  <% if $Recursion == "WEEKLY" %>week<% if $Interval != 1 %>s<% end_if %><% end_if %>
                  <% if $Recursion == "MONTHLY" %>month<% if $Interval != 1 %>s<% end_if %><% end_if %>
                  <% if $Recursion == "YEARLY" %>year<% if $Interval != 1 %>s<% end_if %><% end_if %>
                </div>
              </div>
              <% end_if %>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-center">
                <% if $RecursionEndDate %>
                  <i class="bi bi-calendar-x text-warning me-3 fs-5"></i>
                  <div>
                    <span class="fw-semibold">Ends:</span> $RecursionEndDate.Nice
                  </div>
                <% else %>
                  <i class="bi bi-infinity text-warning me-3 fs-5"></i>
                  <div class="text-muted">
                    <span class="fw-semibold">No end date</span> - repeats indefinitely
                  </div>
                <% end_if %>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <% end_if %>

  <!-- Main Content Area -->
  <div class="row">
    <!-- Event Description -->
    <div class="col-lg-8 mb-4">
      <% if $Content %>
      <div class="card h-100">
        <div class="card-header">
          <h3 class="h5 mb-0">
            <i class="bi bi-file-text me-2"></i>Event Description
          </h3>
        </div>
        <div class="card-body">
          <div class="content">$Content</div>
        </div>
      </div>
      <% else %>
      <div class="card h-100 bg-light">
        <div class="card-body text-center py-5">
          <i class="bi bi-file-text display-1 text-muted"></i>
          <p class="text-muted mt-3 mb-0">No additional description provided for this event.</p>
        </div>
      </div>
      <% end_if %>
    </div>

    <!-- Quick Actions & Event Summary -->
    <div class="col-lg-4">
      <!-- Quick Actions -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="bi bi-lightning me-2"></i>Quick Actions
          </h5>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="$Parent.Link" class="btn btn-outline-primary">
              <i class="bi bi-arrow-left me-2"></i>Back to Calendar
            </a>
            <button class="btn btn-success js-add-to-calendar"
                    data-event-title="$Title"
                    data-event-start-date="$StartDate.Format('yyyyMMdd')"
                    data-event-start-time="<% if $StartTime %>$StartTime.Format('HHmmss')<% else %>000000<% end_if %>"
                    data-event-end-date="<% if $EndDate %>$EndDate.Format('yyyyMMdd')<% else %>$StartDate.Format('yyyyMMdd')<% end_if %>"
                    data-event-end-time="<% if $EndTime %>$EndTime.Format('HHmmss')<% else_if $StartTime %>$StartTime.Format('HHmmss')<% else %>235959<% end_if %>"
                    data-event-description="$Content.Summary(50)"
                    data-event-location="<% if $LocationName %>$LocationName<% end_if %>"
                    data-event-all-day="<% if $AllDay %>true<% else %>false<% end_if %>">
              <i class="bi bi-calendar-plus me-2"></i>Add to Calendar
            </button>
            <% if $eventRecurs %>
            <div class="alert alert-info mb-0 mt-2">
              <small>
                <i class="bi bi-info-circle me-1"></i>
                This recurring event will automatically appear on your calendar for all future dates.
              </small>
            </div>
            <% end_if %>
          </div>
        </div>
      </div>

      <!-- Event Summary -->
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="bi bi-clipboard-check me-2"></i>Event Summary
          </h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="d-flex align-items-center mb-2">
              <i class="bi bi-calendar-date text-primary me-2"></i>
              <span><strong>Date:</strong> $StartDate.Nice</span>
            </li>
            <% if $StartTime %>
            <li class="d-flex align-items-center mb-2">
              <i class="bi bi-clock text-primary me-2"></i>
              <span><strong>Time:</strong> $StartTime.Nice<% if $EndTime %> - $EndTime.Nice<% end_if %></span>
            </li>
            <% end_if %>
            <% if $AllDay %>
            <li class="d-flex align-items-center mb-2">
              <i class="bi bi-sun text-warning me-2"></i>
              <span class="badge bg-warning text-dark">All Day Event</span>
            </li>
            <% end_if %>
            <% if $eventRecurs %>
            <li class="d-flex align-items-center mb-2">
              <i class="bi bi-arrow-repeat text-info me-2"></i>
              <span><strong>Repeats:</strong>
                <% if $Recursion == "DAILY" %>Daily<% end_if %>
                <% if $Recursion == "WEEKLY" %>Weekly<% end_if %>
                <% if $Recursion == "MONTHLY" %>Monthly<% end_if %>
                <% if $Recursion == "YEARLY" %>Yearly<% end_if %>
              </span>
            </li>
            <% end_if %>
            <% if $EventType %>
            <li class="d-flex align-items-center mb-2">
              <i class="bi bi-bookmark text-success me-2"></i>
              <span><strong>Type:</strong> $EventType</span>
            </li>
            <% end_if %>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Related Events -->
  <% if $Parent.Children.Count > 1 %>
  <div class="row mt-5">
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="h5 mb-0">
            <i class="bi bi-calendar-week me-2"></i>Other Calendar Events
          </h3>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <% loop $Parent.Children.Limit(6).Exclude("ID", $ID) %>
            <div class="col-md-6 col-lg-4">
              <div class="card h-100 border-0 bg-light">
                <div class="card-body p-3">
                  <h6 class="card-title">
                    <a href="$Link" class="text-decoration-none stretched-link">$Title</a>
                  </h6>
                  <p class="card-text mb-2">
                    <small class="text-muted">
                      <i class="bi bi-calendar-date me-1"></i>$StartDate.Nice
                    </small>
                  </p>
                  <% if $eventRecurs %>
                    <span class="badge bg-info text-dark small">
                      <i class="bi bi-arrow-repeat"></i> Recurring
                    </span>
                  <% end_if %>
                </div>
              </div>
            </div>
            <% end_loop %>
          </div>
        </div>
      </div>
    </div>
  </div>
  <% end_if %>

  <!-- Element Area for CMS content -->
  <% if $ElementalArea %>
  <div class="row mt-5">
    <div class="col-12">
      <div class="element-area">
        $ElementalArea
      </div>
    </div>
  </div>
  <% end_if %>
</div>

$Form
$CommentsForm
