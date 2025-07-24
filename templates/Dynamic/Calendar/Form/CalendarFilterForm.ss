<%-- Bootstrap-styled collapsible calendar event filter form template.
     Requires Bootstrap 5+ for styling classes (row, col-*, g-3, bg-light, p-4, etc.).
     Falls back gracefully without Bootstrap but styling will be minimal. --%>
<div class="calendar-filter-form bg-light rounded shadow-sm mb-4">
    <!-- Collapsible Filter Header -->
    <div class="filter-header p-3 border-bottom d-flex justify-content-between align-items-center"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#calendar-filters-content"
         aria-expanded="false"
         aria-controls="calendar-filters-content">
        <h5 class="mb-0 d-flex align-items-center">
            <i class="bi bi-funnel me-2"></i>
            Filter Events
            <% if $HasActiveFilters %>
                <span class="badge bg-primary ms-2" aria-label="Active filters">$ActiveFiltersCount</span>
            <% end_if %>
        </h5>
        <i class="bi bi-chevron-down filter-toggle-icon" aria-hidden="true"></i>
    </div>

    <!-- Collapsible Filter Content -->
    <div class="collapse" id="calendar-filters-content">
        <div class="p-4">
            <form $AttributesHTML aria-labelledby="filter-events-heading">
                <div class="row g-3">
                    <% if $Fields.find('Name', 'search') %>
                    <div class="col-md-4">
                        <label for="{$Fields.find('Name', 'search').ID}" class="form-label">$Fields.find('Name', 'search').Title</label>
                        $Fields.find('Name', 'search')
                    </div>
                    <% end_if %>

                    <% if $Fields.find('Name', 'categories') %>
                    <div class="col-md-3">
                        <label for="{$Fields.find('Name', 'categories').ID}" class="form-label">$Fields.find('Name', 'categories').Title</label>
                        $Fields.find('Name', 'categories')
                    </div>
                    <% end_if %>

                    <% if $Fields.find('Name', 'from') %>
                    <div class="col-md-2">
                        <label for="{$Fields.find('Name', 'from').ID}" class="form-label">$Fields.find('Name', 'from').Title</label>
                        $Fields.find('Name', 'from')
                    </div>
                    <% end_if %>

                    <% if $Fields.find('Name', 'to') %>
                    <div class="col-md-2">
                        <label for="{$Fields.find('Name', 'to').ID}" class="form-label">$Fields.find('Name', 'to').Title</label>
                        $Fields.find('Name', 'to')
                    </div>
                    <% end_if %>

                </div>

                <% if $Fields.find('Name', 'eventType') || $Fields.find('Name', 'allDay') %>
                <div class="row g-3 mt-3">
                    <% if $Fields.find('Name', 'eventType') %>
                    <div class="col-md-6">
                        <label for="{$Fields.find('Name', 'eventType').ID}" class="form-label">$Fields.find('Name', 'eventType').Title</label>
                        $Fields.find('Name', 'eventType')
                    </div>
                    <% end_if %>

                    <% if $Fields.find('Name', 'allDay') %>
                    <div class="col-md-6">
                        <label for="{$Fields.find('Name', 'allDay').ID}" class="form-label">$Fields.find('Name', 'allDay').Title</label>
                        $Fields.find('Name', 'allDay')
                    </div>
                    <% end_if %>
                </div>
                <% end_if %>

                <div class="row mt-3">
                    <div class="col-12">
                        <% loop $Actions %>
                            $Field
                        <% end_loop %>
                        <% if $HasActiveFilters && $ClearFiltersLink %>
                            <a href="$ClearFiltersLink.ATT" class="btn btn-outline-secondary" role="button" aria-label="Remove all filters and show all events">Clear All</a>
                        <% end_if %>
                    </div>
                </div>

                <% loop $Fields %>
                    <% if $Name == 'SecurityID' || $Name == 'advanced' %>
                        $Field
                    <% end_if %>
                <% end_loop %>
            </form>
        </div>
    </div>
</div>
