<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <header class="page-header mb-4">
        <h1 class="display-4">$Title</h1>
        <% if $Content %>
          <div class="lead">$Content</div>
        <% end_if %>
      </header>

      <!-- Calendar Filters -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card calendar-filters">
            <div class="card-body">
              <h5 class="card-title">Filter Events</h5>
              <form method="get" class="row g-3" data-auto-submit="false">
                <div class="col-md-4">
                  <label for="from" class="form-label">From Date</label>
                  <input type="date" class="form-control" id="from" name="from" value="$CurrentFromDate">
                </div>
                <div class="col-md-4">
                  <label for="to" class="form-label">To Date</label>
                  <input type="date" class="form-control" id="to" name="to" value="$CurrentToDate">
                </div>
                
                <% if $ShowCategoryFilter && $AvailableCategories.Count %>
                <div class="col-md-4">
                  <label class="form-label">Categories</label>
                  <div class="category-filter">
                    <% loop $AvailableCategories %>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" 
                             id="category_{$ID}" name="categories[]" value="$ID"
                             <% if $IsSelected %>checked<% end_if %>>
                      <label class="form-check-label" for="category_{$ID}">
                        $Title
                      </label>
                    </div>
                    <% end_loop %>
                  </div>
                </div>
                <% end_if %>
                
                <div class="col-12">
                  <button type="submit" class="btn btn-primary me-2">Filter Events</button>
                  <a href="$Link" class="btn btn-outline-secondary">Clear Filters</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Elemental Area -->
      <% if $ElementalArea %>
        <div class="row mb-4">
          <div class="col-12">
            $ElementalArea
          </div>
        </div>
      <% end_if %>

      <% if $Events %>
        <div class="row">
          <% loop $Events %>
            <div class="col-lg-6 col-xl-4 mb-4">
              <% include Dynamic/Calendar/Includes/EventPreview %>
            </div>
          <% end_loop %>
        </div>
      <% else %>
        <div class="alert alert-info text-center" role="alert">
          <i class="bi bi-calendar-x display-1 text-muted"></i>
          <h4 class="alert-heading mt-3">No Events Found</h4>
          <p>Sorry, no events are scheduled for the selected time period.</p>
          <hr>
          <p class="mb-0">Try adjusting your date filters or check back later for new events.</p>
        </div>
      <% end_if %>

      <% if $Events.MoreThanOnePage %>
        <nav aria-label="Events pagination" class="mt-4">
          <ul class="pagination justify-content-center">
            <% if $Events.NotFirstPage %>
              <li class="page-item">
                <a class="page-link" href="$Events.PrevLink" aria-label="Previous">
                  <span aria-hidden="true">&laquo;</span>
                </a>
              </li>
            <% end_if %>
            <% loop $Events.Pages %>
              <li class="page-item <% if $CurrentBool %>active<% end_if %>">
                <% if $CurrentBool %>
                  <span class="page-link">$PageNum</span>
                <% else %>
                  <a class="page-link" href="$Link">$PageNum</a>
                <% end_if %>
              </li>
            <% end_loop %>
            <% if $Events.NotLastPage %>
              <li class="page-item">
                <a class="page-link" href="$Events.NextLink" aria-label="Next">
                  <span aria-hidden="true">&raquo;</span>
                </a>
              </li>
            <% end_if %>
          </ul>
          <p class="text-center text-muted">Page $Events.CurrentPage of $Events.TotalPages</p>
        </nav>
      <% end_if %>
    </div>
  </div>
</div>
</div>
