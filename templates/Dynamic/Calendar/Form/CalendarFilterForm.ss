<%--
    Calendar Filter Form - Simplified traditional form template for essentials theme.
    Designed to be used within an external collapse container from main Calendar.ss.
    Requires Bootstrap 5+ for form styling classes.
--%>
<form $AttributesHTML class="calendar-filter-form bg-light rounded shadow-sm p-4 mb-4" aria-label="Filter calendar events">
                    <div class="row g-3">
        <!-- Search Field -->
        <% with $Fields.find('Name', 'search') %>
        <div class="col-md-6">
            <label for="{$ID}" class="form-label">{$Title}</label>
            {$Field}
        </div>
        <% end_with %>

        <!-- Categories Field -->
        <% with $Fields.find('Name', 'categories') %>
        <div class="col-md-6">
            <label for="{$ID}" class="form-label">{$Title}</label>
            {$Field}
        </div>
        <% end_with %>
    </div>

    <div class="row g-3 mt-2">
        <!-- Date From Field -->
        <% with $Fields.find('Name', 'from') %>
        <div class="col-md-6">
            <label for="{$ID}" class="form-label">{$Title}</label>
            {$Field}
        </div>
        <% end_with %>

        <!-- Date To Field -->
        <% with $Fields.find('Name', 'to') %>
        <div class="col-md-6">
            <label for="{$ID}" class="form-label">{$Title}</label>
            {$Field}
        </div>
        <% end_with %>
    </div>

    <% if $Fields.find('Name', 'eventType') || $Fields.find('Name', 'allDay') %>
    <div class="row g-3 mt-2">
        <!-- Event Type Field -->
        <% with $Fields.find('Name', 'eventType') %>
        <% if $Up %>
        <div class="col-md-6">
            <label for="{$ID}" class="form-label">{$Title}</label>
            {$Field}
        </div>
        <% end_if %>
        <% end_with %>

        <!-- All Day Field -->
        <% with $Fields.find('Name', 'allDay') %>
        <% if $Up %>
        <div class="col-md-6">
            <label for="{$ID}" class="form-label">{$Title}</label>
            {$Field}
        </div>
        <% end_if %>
        <% end_with %>
    </div>
    <% end_if %>

    <!-- Form Actions -->
    <div class="d-flex gap-2 mt-3 justify-content-between align-items-center">
        <div class="form-actions-primary d-flex gap-2">
            <% loop $Actions %>
                $Field
            <% end_loop %>
        </div>
        <% if $HasActiveFilters && $ClearFiltersLink %>
        <div class="form-actions-secondary">
            <a href="$ClearFiltersLink.ATT" class="btn btn-outline-secondary btn-sm" role="button" aria-label="Remove all filters and show all events">
                <i class="bi bi-x-circle me-1"></i>Clear All Filters
            </a>
        </div>
        <% end_if %>
    </div>

    <!-- Hidden Fields -->
    <% loop $Fields %>
        <% if $Name == 'SecurityID' || $Name == 'advanced' %>
            $Field
        <% end_if %>
    <% end_loop %>
</form>
