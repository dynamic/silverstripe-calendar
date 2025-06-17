<div class="container-fluid">
  <div class="row">
    <!-- Main Event Content -->
    <div class="col-lg-8">
      <article class="event-detail">
        <!-- Event Header with Hero Section -->
        <header class="event-header mb-5">
          <!-- Breadcrumb Navigation -->
          <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
              <li class="breadcrumb-item">
                <a href="$Parent.Link" class="text-decoration-none">
                  <i class="bi bi-calendar3"></i> Calendar
                </a>
              </li>
              <li class="breadcrumb-item active" aria-current="page">$Title</li>
            </ol>
          </nav>

          <!-- Hero Card -->
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="flex-grow-1">
                  <h1 class="display-5 mb-2 fw-bold">$Title</h1>
                  <% if $MenuTitle && $MenuTitle != $Title %>
                    <p class="lead text-muted mb-0">$MenuTitle</p>
                  <% end_if %>
                </div>
                <div class="flex-shrink-0 ms-3">
                  <% if $eventRecurs %>
                    <span class="badge bg-info text-dark fs-6 px-3 py-2">
                      <i class="bi bi-arrow-repeat"></i> Recurring Event
                    </span>
                  <% else %>
                    <span class="badge bg-primary fs-6 px-3 py-2">
                      <i class="bi bi-calendar-event"></i> Single Event
                    </span>
                  <% end_if %>
                </div>
              </div>

              <!-- Key Event Information -->
              <div class="row g-3">
                <!-- Date & Time Card -->
                <div class="col-md-6">
                  <div class="card h-100 border-primary border-opacity-25">
                    <div class="card-body">
                      <h6 class="card-title text-primary mb-3">
                        <i class="bi bi-calendar-event me-2"></i>Date & Time
                      </h6>
                      <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-calendar-date text-muted me-2"></i>
                        <strong class="fs-5">$StartDate.Nice</strong>
                      </div>
                      <% if $StartTime %>
                        <div class="d-flex align-items-center mb-2">
                          <i class="bi bi-clock text-muted me-2"></i>
                          <span>$StartTime.Nice<% if $EndTime %> - $EndTime.Nice<% end_if %></span>
                        </div>
                      <% end_if %>
                      <% if $AllDay %>
                        <div class="d-flex align-items-center">
                          <i class="bi bi-sun text-warning me-2"></i>
                          <span class="badge bg-warning text-dark">All Day Event</span>
                        </div>
                      <% end_if %>
                    </div>
                  </div>
                </div>

                <!-- Categories & Details -->
                <div class="col-md-6">
                  <div class="card h-100 border-success border-opacity-25">
                    <div class="card-body">
                      <h6 class="card-title text-success mb-3">
                        <i class="bi bi-info-circle me-2"></i>Event Details
                      </h6>
                      <% if $Categories %>
                        <div class="d-flex align-items-start mb-2">
                          <i class="bi bi-tags text-muted me-2 mt-1"></i>
                          <div>
                            <strong>Categories:</strong><br>
                            <% loop $Categories %>
                              <span class="badge bg-secondary me-1 mb-1">$Title</span>
                            <% end_loop %>
                          </div>
                        </div>
                      <% end_if %>
                      <% if $EventType %>
                        <div class="d-flex align-items-center mb-2">
                          <i class="bi bi-bookmark text-muted me-2"></i>
                          <span><strong>Type:</strong> $EventType</span>
                        </div>
                      <% end_if %>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Recurring Event Details -->
              <% if $eventRecurs %>
                <div class="row g-3 mt-2">
                  <div class="col-12">
                    <div class="card border-warning border-opacity-25">
                      <div class="card-body">
                        <h6 class="card-title text-warning mb-3">
                          <i class="bi bi-arrow-repeat me-2"></i>Recurrence Information
                        </h6>
                        <div class="row">
                          <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                              <i class="bi bi-arrow-clockwise text-muted me-2"></i>
                              <span><strong>Pattern:</strong>
                                <% if $Recursion == "DAILY" %>Daily<% end_if %>
                                <% if $Recursion == "WEEKLY" %>Weekly<% end_if %>
                                <% if $Recursion == "MONTHLY" %>Monthly<% end_if %>
                                <% if $Recursion == "YEARLY" %>Yearly<% end_if %>
                              </span>
                            </div>
                            <% if $Interval && $Interval > 1 %>
                              <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-skip-forward text-muted me-2"></i>
                                <span><strong>Interval:</strong> Every $Interval
                                  <% if $Recursion == "DAILY" %>day<% if $Interval != 1 %>s<% end_if %><% end_if %>
                                  <% if $Recursion == "WEEKLY" %>week<% if $Interval != 1 %>s<% end_if %><% end_if %>
                                  <% if $Recursion == "MONTHLY" %>month<% if $Interval != 1 %>s<% end_if %><% end_if %>
                                  <% if $Recursion == "YEARLY" %>year<% if $Interval != 1 %>s<% end_if %><% end_if %>
                                </span>
                              </div>
                            <% end_if %>
                          </div>
                          <div class="col-md-6">
                            <% if $RecursionEndDate %>
                              <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-calendar-x text-muted me-2"></i>
                                <span><strong>Ends:</strong> $RecursionEndDate.Nice</span>
                              </div>
                            <% else %>
                              <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-infinity text-muted me-2"></i>
                                <span class="text-muted">No end date specified</span>
                              </div>
                            <% end_if %>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              <% end_if %>
            </div>
          </div>
        </header>

        <!-- Event Description -->
        <% if $Content %>
          <section class="event-content mb-5">
            <div class="card">
              <div class="card-header">
                <h3 class="h5 mb-0">
                  <i class="bi bi-file-text me-2"></i>Event Description
                </h3>
              </div>
              <div class="card-body">
                <div class="content">$Content</div>
              </div>
            </div>
          </section>
        <% end_if %>

        <!-- Upcoming Occurrences (for recurring events) -->
        <% if $eventRecurs %>
          <section id="upcoming-occurrences" class="event-occurrences mb-5">
            <div class="card">
              <div class="card-header">
                <h3 class="h5 mb-0">
                  <i class="bi bi-calendar-range me-2"></i>Upcoming Occurrences
                </h3>
              </div>
              <div class="card-body">
                <div class="alert alert-info d-flex align-items-start">
                  <i class="bi bi-info-circle me-2 mt-1"></i>
                  <div>
                    <h6 class="alert-heading mb-1">Recurring Event</h6>
                    <p class="mb-0">
                      This event repeats
                      <% if $Recursion == "DAILY" %>daily<% end_if %>
                      <% if $Recursion == "WEEKLY" %>weekly<% end_if %>
                      <% if $Recursion == "MONTHLY" %>monthly<% end_if %>
                      <% if $Recursion == "YEARLY" %>yearly<% end_if %>
                      <% if $Interval && $Interval > 1 %>every $Interval
                        <% if $Recursion == "DAILY" %>day<% if $Interval != 1 %>s<% end_if %><% end_if %>
                        <% if $Recursion == "WEEKLY" %>week<% if $Interval != 1 %>s<% end_if %><% end_if %>
                        <% if $Recursion == "MONTHLY" %>month<% if $Interval != 1 %>s<% end_if %><% end_if %>
                        <% if $Recursion == "YEARLY" %>year<% if $Interval != 1 %>s<% end_if %><% end_if %>
                      <% end_if %>
                      starting from $StartDate.Nice<% if $RecursionEndDate %> until $RecursionEndDate.Nice<% end_if %>.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </section>
        <% end_if %>

        <!-- Related Events -->
        <% if $Parent.Children.Count > 1 %>
          <section class="related-events mb-4">
            <div class="card">
              <div class="card-header">
                <h3 class="h5 mb-0">
                  <i class="bi bi-calendar-week me-2"></i>Other Events
                </h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <% loop $Parent.Children.Limit(4).Exclude("ID", $ID) %>
                    <div class="col-md-6 mb-3">
                      <div class="card h-100 border-0 bg-light">
                        <div class="card-body p-3">
                          <h6 class="card-title">
                            <a href="$Link" class="text-decoration-none">$Title</a>
                          </h6>
                          <p class="card-text mb-1">
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
          </section>
        <% end_if %>
      </article>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
      <aside class="event-sidebar">
        <!-- Quick Actions -->
        <div class="card mb-4">
          <div class="card-body">
            <h5 class="card-title">
              <i class="bi bi-lightning me-2"></i>Quick Actions
            </h5>
            <div class="d-grid gap-2">
              <a href="$Parent.Link" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-2"></i>Back to Calendar
              </a>
              <button class="btn btn-outline-success" onclick="addToCalendar()">
                <i class="bi bi-calendar-plus me-2"></i>Add to Calendar
              </button>
              <% if $eventRecurs %>
                <div class="text-center mt-2">
                  <small class="text-muted">
                    <i class="bi bi-info-circle"></i> This event repeats automatically
                  </small>
                </div>
              <% end_if %>
            </div>
          </div>
        </div>

        <!-- Event Summary -->
        <div class="card mb-4">
          <div class="card-body">
            <h5 class="card-title">
              <i class="bi bi-clipboard-check me-2"></i>Event Summary
            </h5>
            <ul class="list-unstyled mb-0">
              <li class="mb-2">
                <strong>Date:</strong> $StartDate.Nice
              </li>
              <% if $StartTime %>
                <li class="mb-2">
                  <strong>Time:</strong> $StartTime.Nice<% if $EndTime %> - $EndTime.Nice<% end_if %>
                </li>
              <% end_if %>
              <% if $AllDay %>
                <li class="mb-2">
                  <span class="badge bg-warning text-dark">All Day Event</span>
                </li>
              <% end_if %>
              <% if $eventRecurs %>
                <li class="mb-2">
                  <strong>Repeats:</strong>
                  <% if $Recursion == "DAILY" %>Daily<% end_if %>
                  <% if $Recursion == "WEEKLY" %>Weekly<% end_if %>
                  <% if $Recursion == "MONTHLY" %>Monthly<% end_if %>
                  <% if $Recursion == "YEARLY" %>Yearly<% end_if %>
                </li>
              <% end_if %>
              <% if $Categories %>
                <li class="mb-0">
                  <strong>Categories:</strong><br>
                  <% loop $Categories %>
                    <span class="badge bg-secondary me-1 mt-1">$Title</span>
                  <% end_loop %>
                </li>
              <% end_if %>
            </ul>
          </div>
        </div>

        <!-- Include SideBar if it exists -->
        <% include SideBar %>
      </aside>
    </div>
  </div>
</div>

<script>
function addToCalendar() {
  // Basic calendar export functionality
  const title = encodeURIComponent('$Title');
  const startDate = '$StartDate.Format("Ymd")';
  const startTime = '$StartTime.Format("Hi")' || '0000';
  const endTime = '$EndTime.Format("Hi")' || '2359';

  const googleUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${startDate}T${startTime}00/${startDate}T${endTime}00`;
  window.open(googleUrl, '_blank');
}
</script>

$Form
$CommentsForm
