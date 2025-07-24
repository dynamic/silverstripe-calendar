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
                    <% with $Fields.find('Name', 'search') as $SearchField %>
                    <% if $SearchField %>
                    <div class="col-md-4">
                        <label for="{$SearchField.ID}" class="form-label">{$SearchField.Title}</label>
                        {$SearchField}
                    </div>
                    <% end_if %>
                    <% end_with %>

                    <% with $Fields.find('Name', 'categories') as $CategoriesField %>
                    <% if $CategoriesField %>
                    <div class="col-md-3">
                        <label for="{$CategoriesField.ID}" class="form-label">{$CategoriesField.Title}</label>
                        {$CategoriesField}
                    </div>
                    <% end_if %>
                    <% end_with %>

                    <% with $Fields.find('Name', 'from') as $FromField %>
                    <% if $FromField %>
                    <div class="col-md-2">
                        <label for="{$FromField.ID}" class="form-label">{$FromField.Title}</label>
                        {$FromField}
                    </div>
                    <% end_if %>
                    <% end_with %>

                    <% with $Fields.find('Name', 'to') as $ToField %>
                    <% if $ToField %>
                    <div class="col-md-2">
                        <label for="{$ToField.ID}" class="form-label">{$ToField.Title}</label>
                        {$ToField}
                    </div>
                    <% end_if %>
                    <% end_with %>

                </div>

                <% with $Fields.find('Name', 'eventType') as $EventTypeField %>
                <% with $Fields.find('Name', 'allDay') as $AllDayField %>
                <% if $EventTypeField || $AllDayField %>
                <div class="row g-3 mt-3">
                    <% if $EventTypeField %>
                    <div class="col-md-6">
                        <label for="{$EventTypeField.ID}" class="form-label">{$EventTypeField.Title}</label>
                        {$EventTypeField}
                    </div>
                    <% end_if %>

                    <% if $AllDayField %>
                    <div class="col-md-6">
                        <label for="{$AllDayField.ID}" class="form-label">{$AllDayField.Title}</label>
                        {$AllDayField}
                    </div>
                    <% end_if %>
                </div>
                <% end_if %>
                <% end_with %>
                <% end_with %>

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
