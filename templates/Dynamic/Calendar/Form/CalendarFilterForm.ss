<%-- Calendar Filter Form - Simplified traditional form template for essentials theme.<%-- Calendar Filter Form - Simplified traditional form template.

     Designed to be used within an external collapse container from main Calendar.ss.     Designed to be used within an external collapse container.

     Requires Bootstrap 5+ for form styling classes. --%>     Requires Bootstrap 5+ for form styling classes. --%>

<form $AttributesHTML class="calendar-filter-form bg-light rounded shadow-sm p-4 mb-4" aria-label="Filter calendar events"><form $AttributesHTML class="calendar-filter-form" aria-label="Filter calendar events">

                    <div class="row g-3">    <div class="row g-3">

        <!-- Search Field -->        <!-- Search Field -->

        <% with $Fields.find('Name', 'search') %>        <% with $Fields.find('Name', 'search') %>

        <div class="col-md-6">        <div class="col-md-6">

            <label for="{$ID}" class="form-label">{$Title}</label>            <label for="{$ID}" class="form-label">{$Title}</label>

            {$Field}            {$Field}

        </div>        </div>

        <% end_with %>        <% end_with %>



        <!-- Categories Field -->        <!-- Categories Field -->

        <% with $Fields.find('Name', 'categories') %>        <% with $Fields.find('Name', 'categories') %>

        <div class="col-md-6">        <div class="col-md-6">

            <label for="{$ID}" class="form-label">{$Title}</label>            <label for="{$ID}" class="form-label">{$Title}</label>

            {$Field}            {$Field}

        </div>        </div>

        <% end_with %>        <% end_with %>

    </div>    </div>



    <div class="row g-3 mt-2">    <div class="row g-3 mt-2">

        <!-- Date From Field -->        <!-- Date From Field -->

        <% with $Fields.find('Name', 'from') %>        <% with $Fields.find('Name', 'from') %>

        <div class="col-md-6">        <div class="col-md-6">

            <label for="{$ID}" class="form-label">{$Title}</label>            <label for="{$ID}" class="form-label">{$Title}</label>

            {$Field}            {$Field}

        </div>        </div>

        <% end_with %>        <% end_with %>



        <!-- Date To Field -->        <!-- Date To Field -->

        <% with $Fields.find('Name', 'to') %>        <% with $Fields.find('Name', 'to') %>

        <div class="col-md-6">        <div class="col-md-6">

            <label for="{$ID}" class="form-label">{$Title}</label>            <label for="{$ID}" class="form-label">{$Title}</label>

            {$Field}            {$Field}

        </div>        </div>

        <% end_with %>        <% end_with %>

    </div>    </div>



    <% if $Fields.find('Name', 'eventType') || $Fields.find('Name', 'allDay') %>    <% if $Fields.find('Name', 'eventType') || $Fields.find('Name', 'allDay') %>

    <div class="row g-3 mt-2">    <div class="row g-3 mt-2">

        <!-- Event Type Field -->        <!-- Event Type Field -->

        <% with $Fields.find('Name', 'eventType') %>        <% with $Fields.find('Name', 'eventType') %>

        <% if $Up %>        <% if $Up %>

        <div class="col-md-6">        <div class="col-md-6">

            <label for="{$ID}" class="form-label">{$Title}</label>            <label for="{$ID}" class="form-label">{$Title}</label>

            {$Field}            {$Field}

        </div>        </div>

        <% end_if %>        <% end_if %>

        <% end_with %>        <% end_with %>



        <!-- All Day Field -->        <!-- All Day Field -->

        <% with $Fields.find('Name', 'allDay') %>        <% with $Fields.find('Name', 'allDay') %>

        <% if $Up %>        <% if $Up %>

        <div class="col-md-6">        <div class="col-md-6">

            <label for="{$ID}" class="form-label">{$Title}</label>            <label for="{$ID}" class="form-label">{$Title}</label>

            {$Field}            {$Field}

        </div>        </div>

        <% end_if %>        <% end_if %>

        <% end_with %>        <% end_with %>

    </div>    </div>

    <% end_if %>    <% end_if %>



    <!-- Form Actions -->    <!-- Form Actions -->

    <div class="d-flex gap-2 mt-3 justify-content-between align-items-center">    <div class="d-flex gap-2 mt-3">

        <div class="form-actions-primary d-flex gap-2">        <% loop $Actions %>

            <% loop $Actions %>            $Field

                $Field        <% end_loop %>

            <% end_loop %>        <% if $HasActiveFilters && $ClearFiltersLink %>

        </div>            <a href="$ClearFiltersLink.ATT" class="btn btn-outline-danger" role="button" aria-label="Remove all filters and show all events">

        <% if $HasActiveFilters && $ClearFiltersLink %>                <i class="bi bi-x-circle me-1"></i>Clear All

        <div class="form-actions-secondary">            </a>

            <a href="$ClearFiltersLink.ATT" class="btn btn-outline-secondary btn-sm" role="button" aria-label="Remove all filters and show all events">        <% end_if %>

                <i class="bi bi-x-circle me-1"></i>Clear All Filters    </div>

            </a>

        </div>    <!-- Hidden Fields -->

        <% end_if %>    <% loop $Fields %>

    </div>        <% if $Name == 'SecurityID' || $Name == 'advanced' %>

            $Field

    <!-- Hidden Fields -->        <% end_if %>

    <% loop $Fields %>    <% end_loop %>

        <% if $Name == 'SecurityID' || $Name == 'advanced' %></form>

            $Field
        <% end_if %>
    <% end_loop %>
</form>