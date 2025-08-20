<div class="container py-4">
    <!-- Event Header -->
    <header class="event-header mb-5">
        <!-- Breadcrumb Navigation -->
        <% if $BreadCrumbs %>
            <nav aria-label="breadcrumb" class="mb-3">
                $BreadCrumbs
            </nav>
        <% end_if %>

        <!-- Hero Section -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h1 class="h1 mb-2 fw-bold">$Title</h1>
                                <% if $MenuTitle && $MenuTitle != $Title %>
                                    <p class="lead text-muted mb-0">$MenuTitle</p>
                                <% end_if %>
                            </div>
                        </div>

                        <!-- Event Details Grid -->
                        <div class="row g-3 mt-3">
                            <!-- Date & Time -->
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        📅
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Date & Time</h6>
                                        <p class="mb-1 fw-bold">$StartDate.Nice</p>
                                        <% if $StartTime %>
                                            <p class="mb-0 text-muted">
                                                🕐 $StartTime.Nice<% if $EndTime %> - $EndTime.Nice<% end_if %>
                                            </p>
                                        <% end_if %>
                                        <% if $AllDay %>
                                            <span class="badge bg-warning text-dark mt-1">
                                                ☀️ All Day
                                            </span>
                                        <% end_if %>
                                    </div>
                                </div>
                            </div>

                            <!-- Categories & Type -->
                            <% if $Categories %>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            🏷️
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Categories</h6>
                                            <div class="mb-2">
                                                <% loop $Categories %>
                                                    <span class="badge bg-secondary me-1 mb-1" <% if $Color %>style="background-color: $Color !important;"<% end_if %>>$Title</span>
                                                <% end_loop %>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <% end_if %>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-grid gap-2">
                            <a href="$Parent.Link" class="btn btn-outline-primary">
                                ← Back to Calendar
                            </a>
                            <button class="btn btn-success" onclick="addToCalendar()">
                                📅 Add to Calendar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Event Description -->
    <% if $Content %>
        <section class="mb-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="h5 mb-0">
                        📄 Event Description
                    </h3>
                </div>
                <div class="card-body">
                    <div class="typography">$Content</div>
                </div>
            </div>
        </section>
    <% end_if %>

    <!-- Related Events -->
    <% if $Parent.Children.Count > 1 %>
        <section class="mb-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="h5 mb-0">
                        📅 More Events
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <% loop $Parent.Children.Limit(6).Exclude("ID", $ID) %>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body p-3">
                                        <h6 class="card-title">
                                            <a href="$Link" class="text-decoration-none stretched-link">$Title</a>
                                        </h6>
                                        <p class="card-text mb-2">
                                            <small class="text-muted">
                                                📅 $StartDate.Nice
                                            </small>
                                        </p>
                                        <% if $eventRecurs %>
                                            <span class="badge bg-info small">
                                                🔄 Recurring
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

    <!-- Elemental Area -->
    <div class="element-area">
        $ElementalArea
    </div>
</div>

<script>
function addToCalendar() {
    // Google Calendar export
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
