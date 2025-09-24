<% if $FeaturedImage %>
    <div class="event-featured-image-wrapper">
        <div class="event-featured-image mb-4">
            <picture>
                <source media="(max-width: 575px)" srcset="$FeaturedImage.FocusFill(575,431).URL">
                <source media="(max-width: 767px)" srcset="$FeaturedImage.FocusFill(767,511).URL">
                <source media="(max-width: 991px)" srcset="$FeaturedImage.FocusFill(991,496).URL">
                <img src="$FeaturedImage.FocusFill(1200,514).URL" class="img-fluid w-100" alt="$FeaturedImage.Title" loading="lazy">
            </picture>
        </div>
    </div>
<% end_if %>

<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="mt-3 mt-md-4">
                <% if $BreadCrumbs %>
                    <nav aria-label="breadcrumb" class="mb-3">
                        $BreadCrumbs
                    </nav>
                <% end_if %>

                <article role="article" class="event-article">
                    <header class="event-header mb-3 mb-md-4">
                        <h1 class="event-title">$Title</h1>
                        <% if $MenuTitle && $MenuTitle != $Title %>
                            <p class="lead text-muted">$MenuTitle</p>
                        <% end_if %>

                        <div class="event-meta mt-3">
                            <div class="row g-3">
                                <!-- Date & Time -->
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 40px; height: 40px;">
                                            <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-semibold">Date & Time</h6>
                                            <p class="mb-1">$StartDate.Nice</p>
                                            <% if not $AllDay %><% if $StartTime %>
                                                <p class="mb-0 text-muted small">
                                                    <i class="bi bi-clock me-1" aria-hidden="true"></i>
                                                    $StartTime.Format('h:mm a')<% if $EndTime %> - $EndTime.Format('h:mm a')<% end_if %>
                                                </p>
                                            <% end_if %><% end_if %>
                                            <% if $AllDay %>
                                                <span class="badge bg-warning text-dark mt-1">
                                                    <i class="bi bi-sun me-1" aria-hidden="true"></i>All Day
                                                </span>
                                            <% end_if %>
                                        </div>
                                    </div>
                                </div>

                                <!-- Categories -->
                                <% if $Categories %>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-start">
                                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 40px; height: 40px;">
                                                <i class="bi bi-tags" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-semibold">Categories</h6>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <% loop $Categories %>
                                                        <span class="badge bg-secondary"
                                                            <% if $ValidatedColor %>
                                                                style="background-color: $ValidatedColor !important;"
                                                            <% end_if %>
                                                        >$Title</span>
                                                    <% end_loop %>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <% end_if %>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-4 d-flex gap-2 flex-wrap">
                                <a href="$Parent.Link" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Calendar
                                </a>
                                <button class="btn btn-success js-add-to-calendar"
                                        data-event-title="$Title"
                                        data-event-start-date="$StartDate.Format('yyyyMMdd')"
                                        <% if not $AllDay && $StartTime %>data-event-start-time="$StartTime.Format('HHmmss')"<% end_if %>
                                        data-event-end-date="<% if $EndDate %>$EndDate.Format('yyyyMMdd')<% else %>$StartDate.Format('yyyyMMdd')<% end_if %>"
                                        <% if not $AllDay && ($EndTime || $StartTime) %>data-event-end-time="<% if $EndTime %>$EndTime.Format('HHmmss')<% else %>$StartTime.Format('HHmmss')<% end_if %>"<% end_if %>
                                        data-event-description="<% if $Content %>$Content.Summary(50)<% else %>$Title<% end_if %>"
                                        data-event-location="<% if $LocationName %>$LocationName<% end_if %>"
                                        data-event-all-day="<% if $AllDay %>true<% else %>false<% end_if %>"
                                        type="button">
                                    <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Add to Calendar
                                </button>
                            </div>
                        </div>
                    </header>

                    <% if $Content %>
                        <div class="event-content typography mb-4">
                            $Content
                        </div>
                    <% end_if %>

                    <div class="event-share mt-4">
                        <% include PageShareLinks %>
                    </div>
                </article>

                $Form
                $CommentsForm
            </div>
        </div>
    </div>
</div>

<div class="element-area main-element-area">
    $ElementalArea
</div>
