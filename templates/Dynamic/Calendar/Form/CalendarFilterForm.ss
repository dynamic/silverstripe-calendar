<%-- Bootstrap-styled calendar event filter form template.
     Requires Bootstrap 5+ for styling classes (row, col-*, g-3, bg-light, p-4, etc.).
     Falls back gracefully without Bootstrap but styling will be minimal. --%>
<div class="calendar-filter-form bg-light p-4 rounded shadow-sm mb-4">
    <h5 id="filter-events-heading">Filter Events</h5>
    <form $AttributesHTML aria-labelledby="filter-events-heading">
        <div class="row g-3">
            <% if $Fields.find('Name', 'search') %>
            <div class="col-md-4">
                $Fields.find('Name', 'search')
            </div>
            <% end_if %>

            <% if $Fields.find('Name', 'categories') %>
            <div class="col-md-3">
                $Fields.find('Name', 'categories')
            </div>
            <% end_if %>

            <% if $Fields.find('Name', 'from') %>
            <div class="col-md-2">
                $Fields.find('Name', 'from')
            </div>
            <% end_if %>

            <% if $Fields.find('Name', 'to') %>
            <div class="col-md-2">
                $Fields.find('Name', 'to')
            </div>
            <% end_if %>

        </div>

        <% if $Fields.find('Name', 'eventType') || $Fields.find('Name', 'allDay') %>
        <div class="row g-3 mt-3">
            <% if $Fields.find('Name', 'eventType') %>
            <div class="col-md-6">
                $Fields.find('Name', 'eventType')
            </div>
            <% end_if %>

            <% if $Fields.find('Name', 'allDay') %>
            <div class="col-md-6">
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
