<div class="calendar-filter-form bg-light p-4 rounded shadow-sm mb-4">
    <h5>Filter Events</h5>
    <form $AttributesHTML>
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

            <% if $Fields.find('Name', 'eventType') %>
            <div class="col-md-6 mt-2">
                $Fields.find('Name', 'eventType')
            </div>
            <% end_if %>

            <% if $Fields.find('Name', 'allDay') %>
            <div class="col-md-6 mt-2">
                $Fields.find('Name', 'allDay')
            </div>
            <% end_if %>
        </div>

        <div class="row mt-3">
            <div class="col-12">
                <% loop $Actions %>
                    $Field
                <% end_loop %>
                <% if $HasActiveFilters %>
                    <a href="$ClearFiltersLink" class="btn btn-outline-secondary" title="Remove all filters and show all events">Clear All</a>
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
